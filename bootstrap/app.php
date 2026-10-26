<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Penjual yang belum masuk diarahkan ke login penjual, bukan halaman pilihan.
        $middleware->redirectGuestsTo(fn (\Illuminate\Http\Request $request) => $request->is('seller', 'seller/*')
            ? route('seller.login')
            : route('login'));

        $middleware->alias([
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'seller.active' => \App\Http\Middleware\EnsureSellerIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
