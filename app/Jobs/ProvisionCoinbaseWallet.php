<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\CoinbaseWalletService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Creates the buyer's Coinbase CDP (MPC) wallet outside the registration request,
 * so signup never blocks on or fails because of the Coinbase API.
 */
class ProvisionCoinbaseWallet implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public User $user)
    {
    }

    public function backoff(): array
    {
        return [10, 30, 60, 300];
    }

    public function uniqueId(): string
    {
        return (string) $this->user->id;
    }

    public function handle(CoinbaseWalletService $cdp): void
    {
        if (! $cdp->enabled()) {
            return;
        }

        $cdp->provisionFor($this->user);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Coinbase wallet provisioning failed', ['user_id' => $this->user->id, 'error' => $e->getMessage()]);
    }
}
