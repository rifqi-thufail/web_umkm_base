<?php

namespace App\Http\Controllers;

use App\Jobs\ExecuteWalletTransfer;
use App\Models\User;
use App\Models\WalletTransfer;
use App\Services\MpcWalletService;
use App\Support\Present;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class WalletController extends Controller
{
    public function __construct(protected MpcWalletService $mpc)
    {
    }

    /**
     * Show the wallet page with balance, address, and transfer history.
     */
    public function index()
    {
        $user = Auth::user();
        $balance = null;
        $walletReady = $user->hasWallet() && $this->mpc->enabled();

        if ($walletReady) {
            try {
                $balance = $this->mpc->getBalance($user->wallet_address);
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch wallet balance', ['user_id' => $user->id, 'error' => $e->getMessage()]);
                $balance = ['wei' => '0', 'eth' => '0', 'display' => '0 ETH'];
            }
        }

        // Transfer history: sent and received, merged and sorted by date
        $transfers = WalletTransfer::where('sender_id', $user->id)
            ->orWhere('recipient_id', $user->id)
            ->with(['sender:id,nama,email,wallet_address', 'recipient:id,nama,email,wallet_address'])
            ->latest()
            ->paginate(15);

        return Inertia::render('buyer/wallet', [
            'wallet' => [
                'enabled' => $this->mpc->enabled(),
                'address' => $user->wallet_address,
                'provider' => $user->wallet_provider,
                'connected_at' => $user->wallet_connected_at?->toISOString(),
                'balance' => $balance,
                'limits' => [
                    'per_transfer' => MpcWalletService::formatEth(MpcWalletService::toWei((string) config('blockchain.mpc.limits.per_transfer_eth'))),
                    'daily' => MpcWalletService::formatEth(MpcWalletService::toWei((string) config('blockchain.mpc.limits.daily_eth'))),
                ],
            ],
            'transfers' => Present::paginate($transfers, fn ($t) => $this->presentTransfer($t, $user->id)),
        ]);
    }

    /**
     * Queue a wallet-to-wallet ETH transfer. The MPC signing happens in ExecuteWalletTransfer;
     * the transfer shows as pending until it is mined.
     */
    public function send(Request $request)
    {
        $request->validate([
            'recipient' => ['required', 'string', 'max:255'],
            // Plain decimal only: "numeric" would let "1e-30" or "0x10" through to bcmath.
            'amount' => ['required', 'string', 'regex:/^\d{1,9}(\.\d{1,18})?$/'],
            'password' => ['required', 'current_password:web'],
        ], [
            'amount.regex' => 'Masukkan jumlah ETH yang valid, misalnya 0.01.',
            'password.current_password' => 'Kata sandi salah.',
        ]);

        $user = Auth::user();

        if (! $user->hasWallet()) {
            return back()->with('error', 'Wallet Anda belum aktif. Silakan tunggu beberapa saat setelah registrasi.');
        }

        if (! $this->mpc->enabled()) {
            return back()->with('error', 'Fitur wallet sedang tidak tersedia.');
        }

        $amountWei = MpcWalletService::toWei($request->amount);
        if (bccomp($amountWei, '0') <= 0) {
            return back()->withErrors(['amount' => 'Jumlah harus lebih dari 0.']);
        }

        // Recipient may be given as an email (resolved to their wallet) or a raw 0x address.
        $input = trim($request->recipient);
        if (preg_match('/^0x[0-9a-fA-F]{40}$/', $input)) {
            $recipientAddress = strtolower($input);
        } elseif (filter_var($input, FILTER_VALIDATE_EMAIL)) {
            $target = User::where('email', $input)->first();
            if (! $target || ! $target->hasWallet()) {
                return back()->withErrors(['recipient' => 'Penerima dengan email tersebut tidak ditemukan atau belum memiliki wallet.']);
            }
            $recipientAddress = strtolower($target->wallet_address);
        } else {
            return back()->withErrors(['recipient' => 'Masukkan email pengguna atau alamat wallet 0x.']);
        }

        if ($recipientAddress === '0x' . str_repeat('0', 40)) {
            return back()->withErrors(['recipient' => 'Alamat tujuan tidak valid.']);
        }

        if (strtolower($user->wallet_address) === $recipientAddress) {
            return back()->with('error', 'Tidak bisa mengirim ke alamat sendiri.');
        }

        try {
            $balance = $this->mpc->getBalance($user->wallet_address);
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch wallet balance', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Gagal memeriksa saldo. Silakan coba lagi nanti.');
        }
        if (bccomp($balance['wei'], $amountWei) < 0) {
            return back()->with('error', 'Saldo tidak mencukupi. Saldo: ' . $balance['display']);
        }

        // Serialize the limit check and insert per sender, so parallel requests cannot both pass.
        try {
            $transfer = Cache::lock('wallet-send:' . $user->id, 10)->block(5, function () use ($user, $amountWei, $recipientAddress) {
                if ($violation = $this->mpc->limitViolation($user, $amountWei)) {
                    return $violation;
                }

                return WalletTransfer::create([
                    'uuid' => (string) Str::uuid(),
                    'sender_id' => $user->id,
                    'recipient_id' => User::whereRaw('LOWER(wallet_address) = ?', [$recipientAddress])->value('id'),
                    'sender_address' => $user->wallet_address,
                    'recipient_address' => $recipientAddress,
                    'amount_wei' => $amountWei,
                    'amount_display' => MpcWalletService::formatEth($amountWei),
                    'status' => 'pending',
                ]);
            });
        } catch (LockTimeoutException) {
            return back()->with('error', 'Transfer lain sedang diproses. Silakan coba lagi.');
        }

        if (is_string($transfer)) {
            return back()->withErrors(['amount' => $transfer]);
        }

        ExecuteWalletTransfer::dispatch($transfer);

        return back()->with('success', 'Transfer sedang diproses. Status akan diperbarui setelah dikonfirmasi jaringan.');
    }

    private function presentTransfer(WalletTransfer $t, int $currentUserId): array
    {
        $isSender = $t->sender_id === $currentUserId;

        return [
            'id' => $t->id,
            'direction' => $isSender ? 'sent' : 'received',
            'amount' => $t->amount_display,
            'tx_hash' => $t->tx_hash,
            'status' => $t->status,
            'status_label' => $t->status_label,
            'counterparty' => $isSender
                ? ['name' => $t->recipient?->nama ?? 'Unknown', 'email' => $t->recipient?->email, 'address' => $t->recipient_address]
                : ['name' => $t->sender?->nama ?? 'Unknown', 'email' => $t->sender?->email, 'address' => $t->sender_address],
            'created_at' => $t->created_at?->toISOString(),
        ];
    }
}
