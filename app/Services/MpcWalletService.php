<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Self-hosted MPC wallets backed by a local Mpcium cluster (see mpc/).
 *
 * Each buyer's key is generated as 2-of-3 threshold shares across the Mpcium nodes;
 * no full private key exists anywhere. Laravel talks to the cluster through the small
 * HTTP signer in mpc/signer, and reads balances straight from the Base RPC.
 */
class MpcWalletService
{
    public function enabled(): bool
    {
        return (bool) config('blockchain.mpc.enabled') && config('blockchain.mpc.signer_url');
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
        $wallet = $this->call('POST', '/wallets', ['wallet_id' => $walletId]);

        $user->forceFill([
            'wallet_address' => $wallet['address'],
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
        $rpcUrl = config('blockchain.base.rpc_url');
        if (! $rpcUrl) {
            return ['wei' => '0', 'eth' => '0', 'display' => '0 ETH'];
        }

        $body = Http::timeout(10)->post($rpcUrl, [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'eth_getBalance', 'params' => [$address, 'latest'],
        ])->throw()->json();

        if (isset($body['error'])) {
            throw new RuntimeException('eth_getBalance: ' . ($body['error']['message'] ?? json_encode($body['error'])));
        }

        $wei = gmp_strval(gmp_init($body['result'] ?? '0x0', 16));
        $eth = bcdiv($wei, '1000000000000000000', 18);
        $display = rtrim(rtrim(bcadd($eth, '0', 6), '0'), '.') . ' ETH';

        return ['wei' => $wei, 'eth' => $eth, 'display' => $display];
    }

    /**
     * Send native ETH from the user's MPC wallet. The signer builds the transaction,
     * has the nodes threshold-sign it, and broadcasts it.
     *
     * @return array{tx_hash:string}
     */
    public function sendEth(User $from, string $toAddress, string $amountWei): array
    {
        return $this->call('POST', '/send', [
            'wallet_id' => $from->mpc_wallet_id ?: $this->walletIdFor($from),
            'from' => $from->wallet_address,
            'to' => $toAddress,
            'value_wei' => $amountWei,
        ]);
    }

    protected function call(string $method, string $path, array $body = []): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('MPC wallet is disabled or not configured');
        }

        try {
            return $this->client()->send($method, $path, ['json' => $body])->throw()->json();
        } catch (RequestException $e) {
            throw new RuntimeException($e->response->json('error') ?? $e->getMessage(), 0, $e);
        }
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('blockchain.mpc.signer_url'), '/'))
            ->withToken((string) config('blockchain.mpc.signer_token'))
            ->acceptJson()
            ->timeout(120);
    }
}
