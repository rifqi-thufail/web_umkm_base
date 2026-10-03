<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\MpcWalletService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Runs MPC key generation for a new buyer outside the registration request,
 * so signup never blocks on or fails because of the MPC cluster.
 */
class ProvisionMpcWallet implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 150;

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

    public function handle(MpcWalletService $mpc): void
    {
        if (! $mpc->enabled()) {
            return;
        }

        $mpc->provisionFor($this->user);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('MPC wallet provisioning failed', ['user_id' => $this->user->id, 'error' => $e->getMessage()]);
    }
}
