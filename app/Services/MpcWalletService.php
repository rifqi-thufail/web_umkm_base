<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTransfer;
use App\Services\Mpc\MpcAuthorizer;
use App\Services\Mpc\MpcException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Self-hosted MPC wallets backed by a Mpcium cluster (see mpc/).
 *
 * Each buyer's key is generated as 2-of-3 threshold shares across the Mpcium nodes; no full
 * private key exists anywhere. Laravel talks to the cluster through the HTTP signer in mpc/signer,
 * and co-signs every request with its authorizer key after checking it against policy. The nodes
 * require that co-signature, so the signer (or anyone who compromises it) cannot move funds alone.
 */
class MpcWalletService
{
    private const WEI_PER_ETH = '1000000000000000000';

    private ?MpcAuthorizer $authorizer = null;

    public function enabled(): bool
    {
        return (bool) config('blockchain.mpc.enabled')
            && config('blockchain.mpc.signer_url')
            && config('blockchain.mpc.authorizer_key');
    }

    public function walletIdFor(User $user): string
    {
        return config('blockchain.mpc.wallet_prefix') . '-' . $user->id;
    }

    /**
     * Run distributed key generation for the user and store the resulting address.
     * Users who already have a wallet address are left untouched; retries are safe
     * because Mpcium returns the existing key for a known wallet id.
     */
    public function provisionFor(User $user): User
    {
        if ($user->wallet_address) {
            return $user;
        }

        $walletId = $this->walletIdFor($user);
        $prepared = $this->call('POST', '/v1/keygen/prepare', ['wallet_id' => $walletId]);
        $wallet = $this->call('POST', '/v1/keygen', [
            'wallet_id' => $walletId,
            'authorizer_signature' => $this->authorizer()->authorizeKeygen($walletId, (string) ($prepared['initiator_signature'] ?? '')),
        ], timeout: 150);

        $address = $wallet['address'] ?? '';
        if (! preg_match('/^0x[0-9a-fA-F]{40}$/', $address) || ($wallet['wallet_id'] ?? null) !== $walletId) {
            throw new MpcException('keygen returned an invalid wallet: ' . json_encode($wallet));
        }
        if (User::whereRaw('LOWER(wallet_address) = ?', [strtolower($address)])->whereKeyNot($user->id)->exists()) {
            throw new MpcException("keygen returned address {$address} that already belongs to another user");
        }

        $user->forceFill([
            'wallet_address' => $address,
            'wallet_provider' => 'mpcium',
            'mpc_wallet_id' => $walletId,
            'wallet_connected_at' => now(),
        ])->save();

        return $user;
    }

    /**
     * ETH balance of an address via the Base RPC endpoint.
     *
     * @return array{wei:string,eth:string,display:string}
     */
    public function getBalance(string $address): array
    {
        if (! config('blockchain.base.rpc_url')) {
            return ['wei' => '0', 'eth' => '0', 'display' => '0 ETH'];
        }

        $wei = gmp_strval(gmp_init($this->rpc('eth_getBalance', [$address, 'latest']) ?? '0x0', 16));

        return ['wei' => $wei, 'eth' => bcdiv($wei, self::WEI_PER_ETH, 18), 'display' => self::formatEth($wei)];
    }

    public static function toWei(string $eth): string
    {
        return bcmul($eth, self::WEI_PER_ETH, 0);
    }

    public static function formatEth(string $wei): string
    {
        $eth = bcdiv($wei, self::WEI_PER_ETH, 6);

        return (str_contains($eth, '.') ? rtrim(rtrim($eth, '0'), '.') : $eth) . ' ETH';
    }

    /**
     * Why a new transfer of $amountWei from $user would break the configured limits, or null.
     */
    public function limitViolation(User $user, string $amountWei): ?string
    {
        $perTransfer = self::toWei((string) config('blockchain.mpc.limits.per_transfer_eth'));
        if (bccomp($amountWei, $perTransfer) > 0) {
            return 'Jumlah melebihi batas per transfer (' . self::formatEth($perTransfer) . ').';
        }

        $daily = self::toWei((string) config('blockchain.mpc.limits.daily_eth'));
        $sent = WalletTransfer::where('sender_id', $user->id)
            ->where('status', '!=', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->pluck('amount_wei')
            ->reduce(fn (string $sum, $wei) => bcadd($sum, (string) $wei), '0');

        if (bccomp(bcadd($sent, $amountWei), $daily) > 0) {
            $left = bccomp($daily, $sent) > 0 ? bcsub($daily, $sent) : '0';

            return 'Jumlah melebihi batas harian. Sisa batas 24 jam: ' . self::formatEth($left) . '.';
        }

        return null;
    }

    /**
     * Have the cluster sign the transfer and broadcast it. On return the transfer has its tx hash
     * and is awaiting confirmation. On MpcException, ->notBroadcast says whether it is safe to mark
     * the transfer failed.
     */
    public function execute(WalletTransfer $transfer): void
    {
        $sender = $transfer->sender;
        if (! $sender || ! $transfer->uuid || strcasecmp((string) $sender->wallet_address, $transfer->sender_address) !== 0) {
            throw new MpcException("transfer {$transfer->id} does not match its sender's wallet");
        }
        $walletId = $sender->mpc_wallet_id ?: $this->walletIdFor($sender);

        $prepared = $this->call('POST', '/v1/tx/prepare', [
            'wallet_id' => $walletId,
            'tx_id' => $transfer->uuid,
            'from' => $transfer->sender_address,
            'to' => $transfer->recipient_address,
            'value_wei' => $transfer->amount_wei,
        ]);

        $tx = $prepared['tx'] ?? null;
        if (! is_array($tx)) {
            throw new MpcException('signer prepare returned no transaction');
        }
        $this->assertWithinPolicy($tx, $transfer);
        $transfer->update(['nonce' => $tx['nonce']]);

        $authorizerSignature = $this->authorizer()->authorizeTransfer($walletId, $transfer->uuid, $tx, (string) ($prepared['initiator_signature'] ?? ''));

        $result = $this->call('POST', '/v1/tx/submit', [
            'wallet_id' => $walletId,
            'tx_id' => $transfer->uuid,
            'from' => $transfer->sender_address,
            'tx' => $tx,
            'authorizer_signature' => $authorizerSignature,
        ], timeout: 150, canBroadcast: true);

        $hash = $result['tx_hash'] ?? '';
        if (! preg_match('/^0x[0-9a-f]{64}$/', $hash)) {
            throw new MpcException('signer submit returned no tx hash', notBroadcast: false);
        }

        $transfer->update(['tx_hash' => $hash, 'submitted_at' => now(), 'error_message' => null]);
    }

    /**
     * Move a pending transfer towards confirmed/failed using the chain and the signer's records.
     * Returns true once the transfer is final.
     */
    public function refresh(WalletTransfer $transfer): bool
    {
        if ($transfer->status !== 'pending') {
            return true;
        }

        if (! $transfer->tx_hash) {
            return $this->recoverUnsubmitted($transfer);
        }

        $receipt = $this->rpc('eth_getTransactionReceipt', [$transfer->tx_hash]);
        if (is_array($receipt) && isset($receipt['blockNumber'])) {
            $block = hexdec($receipt['blockNumber']);
            $head = hexdec((string) $this->rpc('eth_blockNumber', []));
            if ($head - $block + 1 < max(1, (int) config('blockchain.mpc.confirmations'))) {
                return false;
            }

            $ok = ($receipt['status'] ?? null) === '0x1';
            $transfer->update([
                'status' => $ok ? 'confirmed' : 'failed',
                'block_number' => $block,
                'confirmed_at' => $ok ? now() : null,
                'error_message' => $ok ? null : 'Transaksi gagal di jaringan.',
            ]);

            return true;
        }

        // Not mined. If the node no longer knows the transaction after an hour, it was dropped.
        if ($transfer->submitted_at?->lt(now()->subHour()) && $this->rpc('eth_getTransactionByHash', [$transfer->tx_hash]) === null) {
            $transfer->update(['status' => 'failed', 'error_message' => 'Transaksi tidak masuk ke jaringan.']);

            return true;
        }

        return false;
    }

    /** Pending transfer with no tx hash: the submit outcome was lost (e.g. timeout). Ask the signer. */
    private function recoverUnsubmitted(WalletTransfer $transfer): bool
    {
        if ($transfer->created_at?->gt(now()->subMinutes(5))) {
            return false;
        }

        try {
            $status = $this->client(10)->get('/v1/tx/' . $transfer->uuid);
        } catch (ConnectionException) {
            return false;
        }

        if ($status->successful() && ($status['status'] ?? null) === 'broadcast' && preg_match('/^0x[0-9a-f]{64}$/', (string) $status['tx_hash'])) {
            $transfer->update(['tx_hash' => $status['tx_hash'], 'submitted_at' => now(), 'error_message' => null]);

            return false;
        }
        if ($status->successful() && ($status['status'] ?? null) === 'failed') {
            $transfer->update(['status' => 'failed', 'error_message' => 'Transfer gagal diproses.']);

            return true;
        }

        // The signer has no record (restarted) or the outcome is unknown. Never guess with money:
        // leave it pending and alert an operator to check the sender's nonce on-chain.
        if ($transfer->created_at?->lt(now()->subMinutes(30))) {
            Log::critical('Wallet transfer outcome unknown; needs manual review', [
                'transfer_id' => $transfer->id,
                'uuid' => $transfer->uuid,
                'sender' => $transfer->sender_address,
                'nonce' => $transfer->nonce,
            ]);
        }

        return false;
    }

    /**
     * The signer chooses nonce and fees; everything else must be exactly what the buyer asked for.
     */
    private function assertWithinPolicy(array $tx, WalletTransfer $transfer): void
    {
        $maxFee = bcmul((string) config('blockchain.mpc.limits.max_fee_gwei'), '1000000000', 0);
        $perTransfer = self::toWei((string) config('blockchain.mpc.limits.per_transfer_eth'));
        $digits = fn ($v) => is_string($v) && preg_match('/^(0|[1-9]\d{0,77})$/', $v);

        $violations = array_filter([
            'chain' => ($tx['chain_id'] ?? null) !== (string) config('blockchain.base.chain_id'),
            'recipient' => strcasecmp((string) ($tx['to'] ?? ''), $transfer->recipient_address) !== 0,
            'value' => ($tx['value'] ?? null) !== (string) $transfer->amount_wei,
            'limit' => bccomp((string) $transfer->amount_wei, $perTransfer) > 0,
            'gas' => ($tx['gas_limit'] ?? null) !== (string) MpcAuthorizer::GAS_LIMIT,
            'nonce' => ! $digits($tx['nonce'] ?? null),
            'fee' => ! $digits($tx['max_fee_per_gas'] ?? null) || bccomp($tx['max_fee_per_gas'], $maxFee) > 0,
            'tip' => ! $digits($tx['max_priority_fee_per_gas'] ?? null)
                || bccomp($tx['max_priority_fee_per_gas'], (string) ($tx['max_fee_per_gas'] ?? '0')) > 0,
        ]);

        if ($violations) {
            Log::warning('Refusing to authorize MPC transfer', ['transfer_id' => $transfer->id, 'violations' => array_keys($violations), 'tx' => $tx]);
            throw new MpcException(
                'transaction outside policy: ' . implode(', ', array_keys($violations)),
                isset($violations['fee']) ? 'Biaya jaringan sedang terlalu tinggi. Silakan coba lagi nanti.' : 'Transfer gagal diproses. Silakan coba lagi nanti.',
            );
        }
    }

    protected function authorizer(): MpcAuthorizer
    {
        return $this->authorizer ??= new MpcAuthorizer(
            (string) config('blockchain.mpc.authorizer_key'),
            (string) config('blockchain.mpc.authorizer_id'),
        );
    }

    /**
     * @param  bool  $canBroadcast  whether this call may have sent a transaction even if it errored
     */
    protected function call(string $method, string $path, array $body, int $timeout = 30, bool $canBroadcast = false): array
    {
        if (! $this->enabled()) {
            throw new MpcException('MPC wallet is disabled or not configured', 'Fitur wallet sedang tidak tersedia.');
        }

        try {
            $response = $this->client($timeout)->send($method, $path, ['json' => $body]);
        } catch (ConnectionException $e) {
            throw new MpcException("signer {$path}: {$e->getMessage()}", notBroadcast: ! $canBroadcast, previous: $e);
        }

        if ($response->successful() && is_array($response->json())) {
            return $response->json();
        }

        throw $this->signerError($path, $response, $canBroadcast);
    }

    private function signerError(string $path, Response $response, bool $canBroadcast): MpcException
    {
        $error = (string) ($response->json('error') ?? $response->body());
        // 4xx are validation/policy rejections before anything is published. 5xx after a submit may
        // have broadcast, unless the signer says otherwise.
        $notBroadcast = ! $canBroadcast || $response->clientError() || $response->json('broadcast') === false;
        $userMessage = $response->json('code') === 'insufficient_funds'
            ? 'Saldo tidak mencukupi untuk jumlah ini ditambah biaya jaringan.'
            : 'Transfer gagal diproses. Silakan coba lagi nanti.';

        return new MpcException(
            "signer {$path} -> {$response->status()}: {$error}",
            $userMessage,
            $notBroadcast,
            $response->json('tx_hash'),
        );
    }

    protected function client(int $timeout): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('blockchain.mpc.signer_url'), '/'))
            ->withToken((string) config('blockchain.mpc.signer_token'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout($timeout);
    }

    private function rpc(string $method, array $params): mixed
    {
        $body = Http::timeout(10)->post((string) config('blockchain.base.rpc_url'), [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params,
        ])->throw()->json();

        if (isset($body['error'])) {
            throw new RuntimeException("{$method}: " . ($body['error']['message'] ?? json_encode($body['error'])));
        }

        return $body['result'] ?? null;
    }
}
