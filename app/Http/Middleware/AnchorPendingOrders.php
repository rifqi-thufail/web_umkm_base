<?php

namespace App\Http\Middleware;

use App\Models\Order;
use App\Services\BaseChainService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hostinger shared hosting has no cron binary or queue worker, so ordinary page traffic retries
 * Base anchoring instead: at most once every 10 minutes, after the response has been sent,
 * a few paid orders that are not on chain yet get another attempt.
 * (`php artisan base:anchor --pending` and the scheduler do the same where cron exists.)
 */
class AnchorPendingOrders
{
    public function __construct(private BaseChainService $base)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->base->enabled() && Cache::add('base:anchor-sweep', 1, 600)) {
            \Illuminate\Support\defer(function () {
                Order::awaitingAnchor()->orderBy('paid_at')->orderBy('id')->take(3)->get()
                    ->each(fn (Order $order) => $order->anchorOnBase($this->base));
            }, 'base-anchor-sweep');
        }

        return $response;
    }
}
