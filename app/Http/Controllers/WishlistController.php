<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Wishlist;
use App\Models\Product;

class WishlistController extends Controller
{
    /**
     * Display wishlist page
     */
    public function index()
    {
        $user = Auth::user();
        
        $products = $user->wishlistProducts()
            ->with(['category', 'primaryImage', 'seller'])
            ->latest('wishlists.created_at')
            ->paginate(12);
        $ids = collect($products->items())->pluck('id')->map(fn ($id) => (int) $id)->all();

        return \Inertia\Inertia::render('buyer/wishlist', [
            'products' => \App\Support\Present::paginate($products, fn ($p) => \App\Support\Present::productCard($p, $ids)),
        ]);
    }

    /**
     * Remove product from wishlist
     */
    public function destroy($productId)
    {
        Wishlist::where('user_id', Auth::id())->where('product_id', $productId)->delete();

        return back()->with('success', 'Dihapus dari wishlist.');
    }

    /**
     * Toggle wishlist (add if not exists, remove if exists)
     */
    public function toggle(Request $request)
    {
        $request->validate(['product_id' => 'required|exists:products,id']);

        $existing = Wishlist::where('user_id', Auth::id())->where('product_id', $request->product_id)->first();

        if ($existing) {
            $existing->delete();

            return back()->with('success', 'Dihapus dari wishlist.');
        }

        Wishlist::create(['user_id' => Auth::id(), 'product_id' => $request->product_id]);

        return back()->with('success', 'Disimpan ke wishlist.');
    }
}
