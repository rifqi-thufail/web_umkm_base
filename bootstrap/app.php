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
            \App\Http\Middleware\StagingGate::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\AnchorPendingOrders::class,
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
        // Branded error pages for browser visits; JSON callers (webhooks, API) keep plain responses.
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, \Illuminate\Http\Request $request) {
            $status = $response->getStatusCode();

            if ($request->expectsJson() || $request->is('api/*', 'up')) {
                return $response;
            }

            // Expired CSRF token (a tab left open too long): go back with a hint instead of a 419 page.
            if ($status === 419) {
                return back()->with('error', 'Sesi Anda berakhir. Silakan coba lagi.');
            }

            if (in_array($status, [403, 404, 429, 503], true) || ($status === 500 && ! config('app.debug'))) {
                return \Inertia\Inertia::render('error', ['status' => $status])->toResponse($request)->setStatusCode($status);
            }

            return $response;
        });
    })->create();
