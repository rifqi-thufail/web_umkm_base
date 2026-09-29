<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Seller;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SellerProfileController extends Controller
{
    /**
     * Halaman toko publik.
     */
    public function show(Request $request, Seller $seller): Response
    {
        $products = Product::where('seller_id', $seller->id)
            ->with(['primaryImage', 'category'])
            ->latest()
            ->paginate(12);

        $wishlistIds = $request->user('web')?->wishlists()->pluck('product_id')->map(fn ($id) => (int) $id)->all() ?? [];

        return Inertia::render('sellers/show', [
            'seller' => Present::seller($seller),
            'products' => Present::paginate($products, fn ($p) => Present::productCard($p, $wishlistIds)),
            'stats' => [
                'products' => $products->total(),
                'orders' => $seller->orders()->where('payment_status', 'paid')->count(),
            ],
        ]);
    }

    public function edit(Request $request): Response
    {
        $seller = $request->user('seller');

        return Inertia::render('seller/profile', [
            'seller' => [
                ...Present::seller($seller),
                'email' => $seller->email,
                'phone' => $seller->no_hp,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $seller = Auth::guard('seller')->user();

        $data = $request->validate([
            'nama_koperasi' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:sellers,email,' . $seller->id,
            'no_hp' => 'required|string|max:20|unique:sellers,no_hp,' . $seller->id,
            'kecamatan' => 'required|string|max:255',
            'desa_kelurahan' => 'required|string|max:255',
            'jenis_usaha' => 'required|string|max:255',
            'alamat_toko' => 'nullable|string',
            'deskripsi_toko' => 'nullable|string',
            'foto_profil' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        unset($data['foto_profil']);
        if ($request->hasFile('foto_profil')) {
            if ($seller->foto_profil) {
                Storage::disk('public')->delete($seller->foto_profil);
            }
            $data['foto_profil'] = $request->file('foto_profil')->store('profiles', 'public');
        }

        $seller->update($data);

        return redirect()->route('seller.profile.edit')->with('success', 'Profil toko disimpan.');
    }
}
