<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WalletTransfer;
use App\Services\MpcWalletService;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            ],
            'transfers' => Present::paginate($transfers, fn ($t) => $this->presentTransfer($t, $user->id)),
        ]);
    }

    /**
     * Process a wallet-to-wallet ETH transfer.
     */
    public function send(Request $request)
    {
        $request->validate([
            'recipient' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:100'],
        ]);

        $user = Auth::user();

        if (! $user->hasWallet()) {
            return back()->with('error', 'Wallet Anda belum aktif. Silakan tunggu beberapa saat setelah registrasi.');
        }

        if (! $this->mpc->enabled()) {
            return back()->with('error', 'Fitur wallet sedang tidak tersedia.');
        }

        // Recipient may be given as an email (resolved to their wallet) or a raw 0x address.
        $input = trim($request->recipient);
        if (preg_match('/^0x[0-9a-fA-F]{40}$/', $input)) {
            $recipientAddress = strtolower($input);
        } elseif (filter_var($input, FILTER_VALIDATE_EMAIL)) {
            $target = User::where('email', $input)->first();
            if (! $target) {
                return back()->withErrors(['recipient' => 'Pengguna dengan email tersebut tidak ditemukan.']);
            }
            if (! $target->hasWallet()) {
                return back()->withErrors(['recipient' => 'Pengguna tersebut belum memiliki wallet.']);
            }
            $recipientAddress = strtolower($target->wallet_address);
        } else {
            return back()->withErrors(['recipient' => 'Masukkan email pengguna atau alamat wallet 0x.']);
        }

        if (strtolower($user->wallet_address) === $recipientAddress) {
            return back()->with('error', 'Tidak bisa mengirim ke alamat sendiri.');
        }

        // Convert ETH amount to wei
        $amountEth = $request->amount;
        $amountWei = bcmul((string) $amountEth, '1000000000000000000', 0);

        // Check balance
        try {
            $balance = $this->mpc->getBalance($user->wallet_address);
            if (bccomp($balance['wei'], $amountWei) < 0) {
                return back()->with('error', 'Saldo tidak mencukupi. Saldo: ' . $balance['display']);
            }
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal memeriksa saldo: ' . $e->getMessage());
        }

        // Find recipient user (if they are in our system)
        $recipient = User::where(DB::raw('LOWER(wallet_address)'), $recipientAddress)->first();

        // Create transfer record
        $transfer = WalletTransfer::create([
            'sender_id' => $user->id,
            'recipient_id' => $recipient?->id,
            'sender_address' => $user->wallet_address,
            'recipient_address' => $recipientAddress,
            'amount_wei' => $amountWei,
            'amount_display' => rtrim(rtrim(number_format((float) $amountEth, 6), '0'), '.') . ' ETH',
            'status' => 'pending',
        ]);

        try {
            $result = $this->mpc->sendEth($user, $recipientAddress, $amountWei);

            $txHash = $result['tx_hash'] ?? null;

            $transfer->update([
                'tx_hash' => $txHash,
                'status' => 'confirmed',
            ]);

            return back()->with('success', 'Transfer berhasil! ' . ($txHash ? 'TX: ' . substr($txHash, 0, 10) . '…' : ''));
        } catch (\Throwable $e) {
            Log::error('Wallet transfer failed', [
                'transfer_id' => $transfer->id,
                'sender_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            $transfer->update([
                'status' => 'failed',
                'error_message' => substr($e->getMessage(), 0, 255),
            ]);

            return back()->with('error', 'Transfer gagal: ' . $e->getMessage());
        }
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
