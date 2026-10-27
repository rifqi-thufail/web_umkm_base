<?php

namespace App\Http\Middleware;

use App\Models\Order;
use App\Services\BaseChainService;
use App\Services\OrderPaymentService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hostinger shared hosting has no cron binary or queue worker, so ordinary page traffic does the
 * background work instead: at most once every 10 minutes, after the response has been sent,
 * a few paid orders that are not on chain yet get another anchoring attempt, and a few abandoned
 * checkouts past their payment window are settled with the gateway (usually: expired).
 * (`base:anchor --pending`, `payments:sync` and the scheduler do the same where cron exists.)
 */
class SweepPendingWork
{
    public function __construct(private BaseChainService $base, private OrderPaymentService $payments)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (Cache::add('sweep:pending-work', 1, 600)) {
            \Illuminate\Support\defer(function () {
                if ($this->base->enabled()) {
                    Order::awaitingAnchor()->orderBy('paid_at')->orderBy('id')->take(3)->get()
                        ->each(fn (Order $order) => $order->anchorOnBase($this->base));
                }

                Order::staleCheckouts()->take(20)->get()
                    ->unique(fn (Order $order) => $order->gatewayReference())->take(5)
                    ->each(fn (Order $order) => $this->payments->sync($order));
            }, 'sweep-pending-work');
        }

        return $response;
    }
}
