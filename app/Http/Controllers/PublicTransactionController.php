<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\BaseChainService;
use App\Support\Present;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicTransactionController extends Controller
{
    private function anchored()
    {
        return Order::where('payment_status', 'paid')->whereNotNull('blockchain_hash');
    }

    public function index(Request $request, BaseChainService $base): Response
    {
        $query = $this->anchored()->with(['seller:id,nama_koperasi', 'orderItems'])->latest('blockchain_created_at');

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $transactions = $query->paginate(20)->withQueryString();

        return Inertia::render('transparency/index', [
            'transactions' => Present::paginate($transactions, fn (Order $o) => [
                'number' => $o->order_number,
                'total' => (int) round((float) $o->total_price),
                'items' => $o->orderItems->sum('quantity'),
                'seller' => $o->seller?->nama_koperasi,
                'chain' => Present::chain($o),
            ]),
            'stats' => [
                'total_transactions' => $this->anchored()->count(),
                'total_amount' => (int) $this->anchored()->sum('total_price'),
                'today_transactions' => $this->anchored()->whereDate('blockchain_created_at', today())->count(),
            ],
            'network' => $base->network(),
            'filters' => $request->only(['date_from', 'date_to']),
        ]);
    }

    public function show(string $hash, BaseChainService $base): Response
    {
        $order = $this->anchored()
            ->where('blockchain_hash', $hash)
            ->with(['seller', 'orderItems.product.primaryImage'])
            ->firstOrFail();

        return Inertia::render('transparency/show', [
            'transaction' => Present::order($order),
            'fingerprint' => $base->fingerprint($order)['payload'],
            'check' => $base->verifyOrder($order),
            'network' => $base->network(),
        ]);
    }

    public function api()
    {
        $transactions = $this->anchored()
            ->select(['id', 'order_number', 'blockchain_hash', 'transaction_data_hash', 'block_number', 'total_price', 'created_at', 'blockchain_status'])
            ->latest()
            ->paginate(50);

        return response()->json([
            'success' => true,
            'network' => config('blockchain.base.network_name'),
            'chain_id' => (int) config('blockchain.base.chain_id'),
            'contract' => config('blockchain.base.contract_address'),
            'data' => $transactions,
        ]);
    }
}
