<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Local development only: tops up a wallet on the anvil chain with anvil_setBalance.
 */
class WalletFund extends Command
{
    protected $signature = 'wallet:fund {who : User email or 0x address} {amount=1 : ETH to add}';

    protected $description = 'Give a wallet test ETH on the local anvil chain';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('wallet:fund is for the local anvil chain only.');

            return self::FAILURE;
        }

        $who = $this->argument('who');
        $address = str_starts_with($who, '0x') ? $who : User::where('email', $who)->value('wallet_address');
        if (! $address) {
            $this->error("No wallet found for {$who}.");

            return self::FAILURE;
        }

        $rpc = config('blockchain.base.rpc_url');
        $rpcCall = fn (string $method, array $params) => Http::post($rpc, ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params])->json();

        $current = gmp_init($rpcCall('eth_getBalance', [$address, 'latest'])['result'] ?? '0x0', 16);
        $add = gmp_init(bcmul($this->argument('amount'), '1000000000000000000', 0), 10);
        $result = $rpcCall('anvil_setBalance', [$address, '0x' . gmp_strval(gmp_add($current, $add), 16)]);

        if (isset($result['error'])) {
            $this->error('anvil_setBalance failed (is BASE_RPC_URL the local anvil chain?): ' . $result['error']['message']);

            return self::FAILURE;
        }

        $this->info("Added {$this->argument('amount')} ETH to {$address}.");

        return self::SUCCESS;
    }
}
