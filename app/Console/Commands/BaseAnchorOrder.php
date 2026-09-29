<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\BaseChainService;
use Illuminate\Console\Command;

class BaseAnchorOrder extends Command
{
    protected $signature = 'base:anchor {order : Order id} {--force : Anchor even if the order is not paid}';

    protected $description = 'Anchor (or retry anchoring) a paid order on Base';

    public function handle(BaseChainService $base): int
    {
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

        if (! $result['success']) {
            $this->error('Failed: ' . $result['error']);

            return self::FAILURE;
        }

        $this->info("Anchored order {$order->id} in block {$result['block_number']}");
        $this->line("tx:   {$result['tx_hash']}");
        $this->line("hash: {$result['data_hash']}");

        return self::SUCCESS;
    }
}
