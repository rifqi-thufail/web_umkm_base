<?php

namespace App\Listeners;

use App\Jobs\ProvisionMpcWallet;
use App\Models\User;
use App\Services\MpcWalletService;
use Illuminate\Auth\Events\Registered;

class CreateMpcWalletForNewUser
{
    public function __construct(protected MpcWalletService $mpc)
    {
    }

    public function handle(Registered $event): void
    {
        // Sellers fire Registered too; only buyers get a wallet.
        if (! $event->user instanceof User || ! $this->mpc->enabled()) {
            return;
        }

        ProvisionMpcWallet::dispatch($event->user)->afterCommit();
    }
}
