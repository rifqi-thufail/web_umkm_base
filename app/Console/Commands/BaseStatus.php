<?php

namespace App\Console\Commands;

use App\Services\BaseChainService;
use Illuminate\Console\Command;

class BaseStatus extends Command
{
    protected $signature = 'base:status';

    protected $description = 'Check the Base RPC connection and the OrderRegistry contract';

    public function handle(BaseChainService $base): int
    {
        try {
            $status = $base->status();
        } catch (\Throwable $e) {
            $this->error('Cannot reach ' . config('blockchain.base.rpc_url') . ': ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Key', 'Value'], collect($status)->map(fn ($v, $k) => [$k, is_bool($v) ? ($v ? 'yes' : 'no') : (string) $v])->values());

        if ($status['chain_id'] !== $status['expected_chain_id']) {
            $this->warn('Chain id does not match BASE_CHAIN_ID.');
        }
        if (! $status['contract_deployed']) {
            $this->warn('No contract at BASE_REGISTRY_ADDRESS. Deploy it with blockchain/deploy-base.sh mainnet|sepolia (or deploy-local.sh).');
        } elseif ($status['recorder'] && ! $status['recorder_allowed']) {
            $this->warn("Recorder {$status['recorder']} may not anchor. Run setRecorder from the deployer wallet.");
        }
        if ($status['recorder'] && (float) $status['recorder_balance_eth'] < 0.00005) {
            $this->warn("Recorder balance is low. Send a little ETH on Base to {$status['recorder']}.");
        }
        if (strtolower((string) $status['recorder']) === '0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266' && $status['chain_id'] === 8453) {
            $this->error('BASE_RECORDER_PRIVATE_KEY is anvil\'s public dev key. Never use it on mainnet.');
        }
        if (! config('blockchain.base.enabled')) {
            $this->warn('BASE_ENABLED=false: paid orders are not anchored.');
        }

        return self::SUCCESS;
    }
}
