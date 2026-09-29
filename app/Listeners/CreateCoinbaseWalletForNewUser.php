<?php

namespace App\Listeners;

use App\Jobs\ProvisionCoinbaseWallet;
use App\Models\User;
use App\Services\CoinbaseWalletService;
use Illuminate\Auth\Events\Registered;

class CreateCoinbaseWalletForNewUser
{
    public function __construct(protected CoinbaseWalletService $cdp)
    {
    }

    public function handle(Registered $event): void
    {
        // Sellers fire Registered too; only buyers get a wallet.
        if (! $event->user instanceof User || ! $this->cdp->enabled()) {
            return;
        }

        ProvisionCoinbaseWallet::dispatch($event->user);
    }
}
