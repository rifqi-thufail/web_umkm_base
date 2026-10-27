<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies a gateway's verdict to every order of one checkout, and asks the gateway for that verdict
 * when the buyer comes back, so payments are confirmed even if a webhook never arrives
 * (shared hosting has no queue worker or cron to fall back on).
 */
class OrderPaymentService
{
    public function __construct(
        private MidtransService $midtrans,
        private CoinPaymentsService $coinPayments,
        private BaseChainService $base,
    ) {
    }

    /** Orders charged under a gateway reference: a checkout reference, or an old order's own number. */
    public function ordersFor(string $reference): Collection
    {
        $orders = Order::where('payment_reference', $reference)->orderBy('id')->get();

        return $orders->isNotEmpty() ? $orders : Order::where('order_number', $reference)->whereNull('payment_reference')->get();
    }

    /**
     * Midtrans notification or status response (already signature-checked).
     *
     * @return string|null the outcome applied
     */
    public function applyMidtrans(array $payload): ?string
    {
        $orders = $this->ordersFor((string) ($payload['order_id'] ?? ''))->where('payment_gateway', 'midtrans');
        if ($orders->isEmpty()) {
            return null;
        }

        $outcome = MidtransService::outcome($payload);
        if ($outcome === 'paid' && (int) round((float) ($payload['gross_amount'] ?? 0)) !== MidtransService::total($orders)) {
            Log::critical('Midtrans amount does not match the orders', ['reference' => $payload['order_id'], 'gross_amount' => $payload['gross_amount'] ?? null]);

            return null;
        }

        return $this->apply($orders, $outcome);
    }

    /** A CoinPayments invoice fetched from their API. */
    public function applyCoinPayments(array $invoice): ?string
    {
        $orders = $this->ordersFor((string) ($invoice['invoiceId'] ?? ''))
            ->where('payment_gateway', 'coinpayments')
            ->where('snap_token', $invoice['id'] ?? null);
        if ($orders->isEmpty()) {
            return null;
        }

        $outcome = CoinPaymentsService::outcome($invoice);
        if ($outcome === 'paid' && (
            strtoupper((string) ($invoice['currency']['symbol'] ?? '')) !== 'IDR'
            || (int) round((float) ($invoice['amount']['total'] ?? 0)) !== MidtransService::total($orders)
        )) {
            Log::critical('CoinPayments invoice does not match the orders', ['invoice' => $invoice['id'] ?? null]);

            return null;
        }

        return $this->apply($orders, $outcome);
    }

    /**
     * Ask the gateway about an unpaid checkout. Throttled per reference so page reloads stay cheap.
     */
    public function sync(Order $order): void
    {
        // Closed orders are re-checked for a few days in case the money arrived after all.
        if (! in_array($order->payment_status, ['unpaid', 'expired', 'failed'], true)
            || ($order->payment_status !== 'unpaid' && $order->created_at?->lt(now()->subDays(3)))) {
            return;
        }

        $reference = $order->gatewayReference();
        if (! Cache::add('payment-sync:' . $reference, 1, 15)) {
            return;
        }

        try {
            $outcome = null;

            if ($order->payment_gateway === 'midtrans' && $this->midtrans->configured()) {
                $status = $this->midtrans->status($reference);
                if ($status && $this->midtrans->validSignature($status)) {
                    $outcome = $this->applyMidtrans($status);
                }
            } elseif ($order->payment_gateway === 'coinpayments' && $order->snap_token && $this->coinPayments->configured()) {
                $outcome = $this->applyCoinPayments($this->coinPayments->invoice($order->snap_token));
            }

            // Nothing settled inside the window: the Snap token / checkout link is dead, close the order.
            if ($outcome === null && $order->payment_status === 'unpaid' && ! $order->paymentWindowOpen()) {
                $this->apply($this->ordersFor($reference), 'expired');
            }
        } catch (\Throwable $e) {
            Log::warning('Payment status check failed', ['reference' => $reference, 'error' => $e->getMessage()]);
        }
    }

    private function apply(Collection $orders, ?string $outcome): ?string
    {
        match ($outcome) {
            'paid' => $this->markPaid($orders),
            'failed', 'expired' => $this->close($orders, $outcome),
            default => null,
        };

        return $outcome;
    }

    /**
     * Mark the checkout's orders paid exactly once, take the stock, and anchor them on Base after the
     * response is sent. A late payment for an order we already closed still counts: the money arrived.
     */
    private function markPaid(Collection $orders): void
    {
        $newlyPaid = DB::transaction(function () use ($orders) {
            $paid = [];
            foreach ($orders as $order) {
                $locked = Order::whereKey($order->id)->lockForUpdate()->first();
                if (! $locked || ! in_array($locked->payment_status, ['unpaid', 'expired', 'failed'], true)) {
                    continue;
                }

                $locked->update(['payment_status' => 'paid', 'status' => 'processing', 'paid_at' => now()]);

                foreach ($locked->orderItems as $item) {
                    $product = Product::withTrashed()->whereKey($item->product_id)->lockForUpdate()->first();
                    if ($product) {
                        $product->update(['stok' => max(0, (int) $product->stok - (int) $item->quantity)]);
                    }
                }

                $paid[] = $locked;
            }

            return $paid;
        });

        foreach ($newlyPaid as $order) {
            \Illuminate\Support\defer(fn () => $order->anchorOnBase($this->base), 'anchor-order-' . $order->id);
        }
    }

    private function close(Collection $orders, string $outcome): void
    {
        Order::whereKey($orders->modelKeys())
            ->where('payment_status', 'unpaid')
            ->update(['payment_status' => $outcome, 'status' => 'cancelled', 'updated_at' => now()]);
    }
}
