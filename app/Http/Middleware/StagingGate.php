<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staging is public on the internet but must not be usable by strangers or search engines.
 * Open it once with ?access=<STAGING_ACCESS_KEY>; a cookie keeps the browser in for 30 days.
 * Payment webhooks (routes/api.php) and /up are outside the web group, so they still reach staging.
 */
class StagingGate
{
    private const COOKIE = 'staging_access';

    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) config('app.staging_access_key');

        if (! app()->environment('staging')) {
            return $next($request);
        }

        // Fail closed: a staging site without a key is not left open to everyone.
        if ($key === '') {
            return response('Staging AMPUH belum dikonfigurasi: isi STAGING_ACCESS_KEY di .env.', 503)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $given = $request->query('access');
        if (is_string($given) && hash_equals($key, $given)) {
            $query = $request->except('access');

            return redirect($request->url() . ($query ? '?' . http_build_query($query) : ''))
                ->withCookie(cookie(self::COOKIE, hash('sha256', $key), 60 * 24 * 30));
        }

        $cookie = $request->cookie(self::COOKIE);
        if (is_string($cookie) && hash_equals(hash('sha256', $key), $cookie)) {
            return $next($request);
        }

        return response('Staging AMPUH. Buka dengan tautan akses dari tim.', 403)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
