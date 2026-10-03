<?php

namespace App\Console\Commands;

use App\Models\WalletTransfer;
use App\Services\MpcWalletService;
use Illuminate\Console\Command;

class WalletReconcile extends Command
{
    protected $signature = 'wallet:reconcile';

    protected $description = 'Settle pending wallet transfers against the chain and the MPC signer';

    public function handle(MpcWalletService $mpc): int
    {
        if (! $mpc->enabled()) {
            return self::SUCCESS;
        }

        $failed = 0;
        WalletTransfer::where('status', 'pending')
            ->where('created_at', '<', now()->subMinute())
            ->lazyById()
            ->each(function (WalletTransfer $transfer) use ($mpc, &$failed) {
                try {
                    $mpc->refresh($transfer);
                } catch (\Throwable $e) {
                    $failed++;
                    $this->warn("#{$transfer->id}: {$e->getMessage()}");
                }
            });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
