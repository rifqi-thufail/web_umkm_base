<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Without this the container would autowire a bare Guzzle client (no timeout).
        $this->app->bind(\App\Services\CoinbaseWalletService::class, fn () => new \App\Services\CoinbaseWalletService());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
