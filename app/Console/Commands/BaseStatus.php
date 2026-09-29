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
            $this->warn('No contract at BASE_REGISTRY_ADDRESS. Deploy it with blockchain/deploy-local.sh.');
        }

        return self::SUCCESS;
    }
}
