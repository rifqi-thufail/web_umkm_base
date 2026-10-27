<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\Present;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $wishlistIds = $user->wishlists()->pluck('product_id')->map(fn ($id) => (int) $id)->all();

        return Inertia::render('buyer/dashboard', [
            'profile' => [
                'name' => $user->nama,
                'email' => $user->email,
                'phone' => $user->no_hp !== '-' ? $user->no_hp : null,
                'address' => $user->alamat !== '-' ? $user->alamat : null,
                'joined' => $user->created_at?->toISOString(),
                'verified' => (bool) $user->email_verified_at,
            ],
            'stats' => [
                'orders' => $user->orders()->count(),
                'active' => $user->orders()->whereIn('status', ['pending', 'processing', 'shipped'])->count(),
                'spent' => (int) $user->orders()->where('payment_status', 'paid')->sum('total_price'),
                'anchored' => $user->orders()->where('blockchain_status', 'confirmed')->whereNotNull('blockchain_hash')->count(),
            ],
            'recentOrders' => $user->orders()
                ->with(['seller', 'orderItems.product.primaryImage'])
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($o) => Present::order($o)),
            'wishlist' => $user->wishlistProducts()
                ->with(['primaryImage', 'seller', 'category'])
                ->latest('wishlists.created_at')
                ->take(4)
                ->get()
                ->map(fn ($p) => Present::productCard($p, $wishlistIds)),
            'recommended' => Product::with(['primaryImage', 'seller', 'category'])
                ->where('stok', '>', 0)
                ->whereNotIn('id', $wishlistIds)
                ->inRandomOrder()
                ->take(4)
                ->get()
                ->map(fn ($p) => Present::productCard($p, $wishlistIds)),
        ]);
    }
}
