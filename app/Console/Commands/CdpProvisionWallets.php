<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CoinbaseWalletService;
use Illuminate\Console\Command;

class CdpProvisionWallets extends Command
{
    protected $signature = 'cdp:provision-wallets {--user= : Only this user id}';

    protected $description = 'Create Coinbase CDP wallets for users that do not have one yet';

    public function handle(CoinbaseWalletService $cdp): int
    {
        if (! $cdp->enabled()) {
            $this->error('Set CDP_WALLET_ENABLED=true and CDP_API_KEY_ID, CDP_API_KEY_SECRET, CDP_WALLET_SECRET.');

            return self::FAILURE;
        }

        $users = User::query()
            ->when($this->option('user'), fn ($q, $id) => $q->whereKey($id))
            ->whereNull('wallet_address')
            ->lazyById();

        $failed = 0;
        foreach ($users as $user) {
            try {
                $cdp->provisionFor($user);
                $this->line("#{$user->id} {$user->email}: {$user->wallet_address}");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("#{$user->id} {$user->email}: {$e->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
