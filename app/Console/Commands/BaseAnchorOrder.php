<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\BaseChainService;
use Illuminate\Console\Command;

class BaseAnchorOrder extends Command
{
    protected $signature = 'base:anchor {order? : Order id}
        {--pending : Anchor every paid order that is not on chain yet}
        {--force : Anchor even if the order is not paid}';

    protected $description = 'Anchor (or retry anchoring) a paid order on Base';

    public function handle(BaseChainService $base): int
    {
        if ($this->option('pending')) {
            return $this->anchorPending($base);
        }

        if (! $this->argument('order')) {
            $this->error('Pass an order id, or --pending.');

            return self::FAILURE;
        }

        $order = Order::findOrFail($this->argument('order'));

        if ($order->payment_status !== 'paid' && ! $this->option('force')) {
            $this->error("Order {$order->id} is not paid. Use --force to anchor anyway.");

            return self::FAILURE;
        }

        $result = $order->anchorOnBase($base);

        if ($result['already_anchored'] ?? false) {
            $this->info("Order {$order->id} is already anchored: {$order->blockchain_hash}");

            return self::SUCCESS;
        }

        if ($result['pending'] ?? false) {
            $this->warn("Sent {$result['tx_hash']}, not mined yet. Run this again in a minute.");

            return self::SUCCESS;
        }

        if (! $result['success']) {
            $this->error('Failed: ' . $result['error']);

            return self::FAILURE;
        }

        $this->info("Anchored order {$order->id} in block {$result['block_number']}");
        $this->line("tx:   {$result['tx_hash']}");
        $this->line("hash: {$result['data_hash']}");

        return self::SUCCESS;
    }

    private function anchorPending(BaseChainService $base): int
    {
        if (! $base->enabled()) {
            $this->error('Base anchoring is disabled or not configured.');

            return self::FAILURE;
        }

        $orders = Order::awaitingAnchor()->orderBy('id')->get();

        $failed = 0;
        foreach ($orders as $order) {
            $result = $order->anchorOnBase($base);
            if ($result['success']) {
                $this->line("order {$order->id}: {$result['tx_hash']}");
            } elseif ($result['pending'] ?? false) {
                $this->line("order {$order->id}: {$result['tx_hash']} (not mined yet)");
            } else {
                $failed++;
                $this->warn("order {$order->id}: {$result['error']}");
            }
        }

        $this->info(sprintf('%d anchored, %d failed.', $orders->count() - $failed, $failed));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
