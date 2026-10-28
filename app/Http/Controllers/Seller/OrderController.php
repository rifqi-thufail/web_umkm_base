<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Present;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class OrderController extends Controller
{
    /**
     * Paid orders for this seller, newest first. Unpaid checkouts are not the seller's business yet.
     */
    public function index()
    {
        $orders = Order::where('seller_id', Auth::guard('seller')->id())
            ->where('payment_status', 'paid')
            ->with(['user', 'orderItems.product.primaryImage'])
            ->latest('paid_at')
            ->latest()
            ->paginate(10);

        return Inertia::render('seller/orders', [
            'orders' => Present::paginate($orders, fn ($o) => Present::order($o)),
        ]);
    }

    /** processing -> shipped. The buyer then confirms receipt (completed). */
    public function ship(Order $order)
    {
        if ($order->seller_id !== Auth::guard('seller')->id()) {
            abort(404);
        }
        if ($order->payment_status !== 'paid' || $order->status !== 'processing') {
            return back()->with('error', 'Pesanan ini tidak bisa ditandai dikirim.');
        }

        $order->update(['status' => 'shipped']);

        return back()->with('success', 'Pesanan ditandai dikirim.');
    }
}
