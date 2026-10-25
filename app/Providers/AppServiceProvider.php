<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Wallet transfers also require the account password, so this mainly slows password guessing.
        RateLimiter::for('wallet-send', fn (Request $request) => [
            Limit::perMinute(5)->by('wallet-send:' . $request->user()?->id),
            Limit::perHour(30)->by('wallet-send-hour:' . $request->user()?->id),
        ]);

        // Build reset links from APP_URL, never from the request Host header.
        ResetPassword::createUrlUsing(fn ($user, string $token) => rtrim(config('app.url'), '/')
            . '/reset-password/' . $token . '?email=' . urlencode($user->getEmailForPasswordReset()));
    }
}
