<?php

namespace App\Jobs;

use App\Models\WalletTransfer;
use App\Services\MpcWalletService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Polls the chain until a broadcast transfer is mined (or dropped). wallet:reconcile is the
 * safety net for anything this misses.
 */
class ConfirmWalletTransfer implements ShouldQueue
{
    use Queueable;

    public int $tries = 60;

    public function __construct(public WalletTransfer $transfer)
    {
    }

    public function backoff(): int
    {
        return 10;
    }

    public function handle(MpcWalletService $mpc): void
    {
        if (! $mpc->refresh($this->transfer->fresh())) {
            $this->release(min(10 * $this->attempts(), 60));
        }
    }
}
