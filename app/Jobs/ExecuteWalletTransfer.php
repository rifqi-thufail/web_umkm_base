<?php

namespace App\Jobs;

use App\Models\WalletTransfer;
use App\Services\Mpc\MpcException;
use App\Services\MpcWalletService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * Signs and broadcasts a transfer the buyer has already confirmed. Runs once per sender at a time,
 * so two transfers never race for the same nonce.
 */
class ExecuteWalletTransfer implements ShouldQueue
{
    use Queueable;

    // Attempts are only spent waiting for the sender's previous transfer (WithoutOverlapping).
    // Signing itself never runs twice: see the nonce guard in handle().
    public int $tries = 20;

    public int $timeout = 180;

    public bool $failOnTimeout = true;

    public function __construct(public WalletTransfer $transfer)
    {
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('wallet-sender:' . $this->transfer->sender_id))->releaseAfter(15)->expireAfter(240)];
    }

    public function handle(MpcWalletService $mpc): void
    {
        $transfer = $this->transfer->fresh();
        // A recorded nonce means an earlier run got as far as authorizing a signature. Running again
        // could sign a second transaction for the same transfer, so leave it to wallet:reconcile.
        if (! $transfer || $transfer->status !== 'pending' || $transfer->tx_hash || $transfer->nonce !== null) {
            return;
        }

        try {
            $mpc->execute($transfer);
            ConfirmWalletTransfer::dispatch($transfer)->delay(now()->addSeconds(5));
        } catch (\Throwable $e) {
            $definite = $e instanceof MpcException ? $e->notBroadcast : true;
            Log::error('Wallet transfer failed', [
                'transfer_id' => $transfer->id,
                'sender_id' => $transfer->sender_id,
                'error' => $e->getMessage(),
                'broadcast' => $definite ? 'no' : 'unknown',
            ]);

            $transfer->update($definite
                ? ['status' => 'failed', 'error_message' => $e instanceof MpcException ? $e->userMessage : 'Transfer gagal diproses.']
                : ['tx_hash' => $e instanceof MpcException ? $e->txHash : null, 'error_message' => 'Menunggu konfirmasi jaringan.']);
        }
    }
}
