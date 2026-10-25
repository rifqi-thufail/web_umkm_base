<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('wallet:reconcile')->everyMinute()->withoutOverlapping();

// Shared hosting (Hostinger) has no supervisor: the one cron entry `php artisan schedule:run`
// drains the queue each minute instead of a long-running `queue:work`.
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')->everyMinute()->withoutOverlapping();

// Retry orders whose on-chain anchoring failed at payment time (RPC hiccup, empty recorder wallet).
Schedule::command('base:anchor --pending')->hourly()->withoutOverlapping()
    ->when(fn () => app(\App\Services\BaseChainService::class)->enabled());
