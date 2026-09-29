<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Support\Present;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Product::with(['category', 'seller', 'primaryImage']);

        if ($request->filled('search')) {
            $query->where('nama_produk', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->category));
        }

        $wishlistIds = $this->wishlistIds($request);
        $products = $query->latest()->paginate(12)->withQueryString();

        return Inertia::render('home', [
            'products' => Present::paginate($products, fn ($p) => Present::productCard($p, $wishlistIds)),
            'categories' => Category::orderBy('name')->get()->map(fn ($c) => Present::category($c)),
            'filters' => $request->only(['search', 'category']),
            'recentProofs' => Order::where('payment_status', 'paid')
                ->where('blockchain_status', 'confirmed')
                ->latest('blockchain_created_at')
                ->take(4)
                ->get()
                ->map(fn ($o) => [
                    'number' => $o->order_number,
                    'total' => (int) round((float) $o->total_price),
                    'tx_hash' => $o->blockchain_hash,
                    'block' => $o->block_number,
                    'anchored_at' => $o->blockchain_created_at?->toISOString(),
                ]),
            'stats' => [
                'products' => Product::count(),
                'sellers' => \App\Models\Seller::count(),
                'anchored' => Order::where('blockchain_status', 'confirmed')->whereNotNull('blockchain_hash')->count(),
            ],
        ]);
    }

    public function show(Request $request, Product $product): Response
    {
        $wishlistIds = $this->wishlistIds($request);

        $related = Product::with(['primaryImage', 'seller', 'category'])
            ->where('seller_id', $product->seller_id)
            ->whereKeyNot($product->id)
            ->latest()
            ->take(4)
            ->get();

        return Inertia::render('products/show', [
            'product' => Present::productDetail($product, $wishlistIds),
            'related' => $related->map(fn ($p) => Present::productCard($p, $wishlistIds)),
        ]);
    }

    private function wishlistIds(Request $request): array
    {
        return $request->user('web')?->wishlists()->pluck('product_id')->map(fn ($id) => (int) $id)->all() ?? [];
    }
}
