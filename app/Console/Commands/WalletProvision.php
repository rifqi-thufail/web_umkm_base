<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\MpcWalletService;
use Illuminate\Console\Command;

class WalletProvision extends Command
{
    protected $signature = 'wallet:provision {--user= : Only this user id}';

    protected $description = 'Create MPC wallets for users that do not have one yet';

    public function handle(MpcWalletService $mpc): int
    {
        if (! $mpc->enabled()) {
            $this->error('Set MPC_WALLET_ENABLED=true and start the cluster with: npm run dev:mpc');

            return self::FAILURE;
        }

        $users = User::query()
            ->when($this->option('user'), fn ($q, $id) => $q->whereKey($id))
            ->whereNull('wallet_address')
            ->lazyById();

        $failed = 0;
        foreach ($users as $user) {
            try {
                $mpc->provisionFor($user);
                $this->line("#{$user->id} {$user->email}: {$user->wallet_address}");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("#{$user->id} {$user->email}: {$e->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
