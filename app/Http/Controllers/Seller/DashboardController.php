<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Support\Present;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $seller = $request->user('seller');
        $paid = $seller->orders()->where('payment_status', 'paid');

        return Inertia::render('seller/dashboard', [
            'seller' => Present::seller($seller),
            'activation' => $seller->getActivationStatus(),
            'stats' => [
                'products' => $seller->products()->count(),
                'orders' => (clone $paid)->count(),
                'to_process' => (clone $paid)->where('status', 'processing')->count(),
                'revenue_month' => (int) (clone $paid)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('total_price'),
                'anchored' => (clone $paid)->where('blockchain_status', 'confirmed')->count(),
            ],
            'recentOrders' => $seller->orders()
                ->with(['user', 'orderItems.product.primaryImage'])
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($o) => Present::order($o)),
            'lowStock' => $seller->products()
                ->with('primaryImage')
                ->where('stok', '<', 10)
                ->orderBy('stok')
                ->take(5)
                ->get()
                ->map(fn ($p) => Present::productCard($p)),
        ]);
    }
}
