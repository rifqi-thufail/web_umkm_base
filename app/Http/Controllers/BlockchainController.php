<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\BaseChainService;
use App\Support\Present;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BlockchainController extends Controller
{
    public function __construct(protected BaseChainService $base)
    {
    }

    /**
     * Look up an order by Base tx hash, data hash, or order number and re-check it on-chain.
     * GET so a verification result can be shared as a link: /verifikasi?q=0x...
     */
    public function verify(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $result = null;

        if ($q !== '') {
            $request->validate(['q' => 'string|max:120']);

            $order = Order::with(['orderItems', 'seller'])
                ->where(fn ($query) => $query
                    ->where('blockchain_hash', $q)
                    ->orWhere('transaction_data_hash', $q)
                    ->orWhere('order_number', $q))
                ->first();

            $result = $order ? [
                'found' => true,
                'order' => [
                    'number' => $order->order_number,
                    'total' => (int) round((float) $order->total_price),
                    'created_at' => $order->created_at?->toISOString(),
                    'seller' => $order->seller?->nama_koperasi,
                    'items' => (int) $order->orderItems->sum('quantity'),
                    'chain' => Present::chain($order),
                ],
                'check' => $order->blockchain_status === 'confirmed' ? $this->base->verifyOrder($order) : null,
            ] : ['found' => false];
        }

        return Inertia::render('verify', [
            'query' => $q ?: null,
            'network' => $this->base->network(),
            'result' => $result,
        ]);
    }
}
