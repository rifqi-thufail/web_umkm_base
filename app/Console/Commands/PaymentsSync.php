<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderPaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PaymentsSync extends Command
{
    protected $signature = 'payments:sync {--all : Also re-check unpaid checkouts still inside their payment window}';

    protected $description = 'Ask Midtrans / CoinPayments about unpaid checkouts: mark the paid ones paid and close the abandoned ones';

    public function handle(OrderPaymentService $payments): int
    {
        $orders = ($this->option('all') ? Order::where('payment_status', 'unpaid')->orderBy('created_at') : Order::staleCheckouts())
            ->get()->unique(fn (Order $order) => $order->gatewayReference());

        foreach ($orders as $order) {
            Cache::forget('payment-sync:' . $order->gatewayReference());
            $payments->sync($order);
            $this->line(sprintf('%s %s: %s', $order->gatewayReference(), $order->payment_gateway, $order->fresh()->payment_status));
        }

        $this->info($orders->count() . ' checkout(s) checked.');

        return self::SUCCESS;
    }
}
