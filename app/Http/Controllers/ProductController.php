<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    public function index()
    {
        $seller = Auth::guard('seller')->user();
        $products = Product::where('seller_id', $seller->id)
            ->with(['category', 'primaryImage'])
            ->latest()
            ->paginate(12);

        return \Inertia\Inertia::render('seller/products/index', [
            'products' => \App\Support\Present::paginate($products, fn ($p) => \App\Support\Present::productCard($p)),
        ]);
    }

    public function create()
    {
        $seller = Auth::guard('seller')->user();
        
        // Seller bisa create produk tanpa aktivasi penuh
        // Hanya tampilkan notifikasi jika belum aktivasi
        if (!$seller->canReceivePayments()) {
            session()->flash('info', 'Anda dapat mengelola produk, namun perlu aktivasi akun untuk menerima pembayaran.');
        }
        
        return \Inertia\Inertia::render('seller/products/form', [
            'product' => null,
            'categories' => Category::orderBy('name')->get()->map(fn ($c) => \App\Support\Present::category($c)),
        ]);
    }

    public function store(Request $request)
    {
        $seller = Auth::guard('seller')->user();
        
        // Seller bisa store produk tanpa aktivasi penuh
        // Tidak ada pembatasan, hanya validasi data biasa

        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'nama_produk' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'harga' => 'required|numeric|min:0',
            'stok' => 'required|integer|min:0',
            'images' => 'required|array|min:1|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ], [
            'images.required' => 'Minimal harus upload 1 gambar',
            'images.min' => 'Minimal harus upload 1 gambar',
            'images.max' => 'Maksimal upload 5 gambar',
            'images.*.image' => 'File harus berupa gambar',
            'images.*.mimes' => 'Format gambar harus: jpeg, png, jpg, gif, atau webp',
            'images.*.max' => 'Ukuran gambar maksimal 2MB',
        ]);        DB::beginTransaction();
        try {
            // Buat produk
            $product = Product::create([
                'seller_id' => $seller->id,
                'category_id' => $validated['category_id'],
                'nama_produk' => $validated['nama_produk'],
                'deskripsi' => $validated['deskripsi'],
                'harga' => $validated['harga'],
                'stok' => $validated['stok'],
            ]);

            // Upload dan simpan gambar
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $path = $image->store('products', 'public');
                    
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'order' => $index,
                        'is_primary' => $index === 0,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('seller.products.index')
                ->with('success', 'Produk berhasil ditambahkan dengan ' . count($request->file('images')) . ' gambar');

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Hapus gambar yang sudah terupload jika terjadi error
            if (isset($product)) {
                foreach ($product->images as $image) {
                    Storage::disk('public')->delete($image->image_path);
                }
            }
            
            return back()->withInput()->with('error', 'Gagal menambahkan produk: ' . $e->getMessage());
        }
    }

    public function edit(Product $product)
    {
        $seller = Auth::guard('seller')->user();
        
        // Pastikan seller hanya bisa edit produknya sendiri
        if ($product->seller_id !== $seller->id) {
            abort(403, 'Unauthorized action.');
        }
        
        return \Inertia\Inertia::render('seller/products/form', [
            'product' => \App\Support\Present::productDetail($product),
            'categories' => Category::orderBy('name')->get()->map(fn ($c) => \App\Support\Present::category($c)),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        
        $seller = Auth::guard('seller')->user();
        
        if ($product->seller_id !== $seller->id) {
            abort(403, 'Unauthorized action.');
        }
        
        $validated = $request->validate([
            'nama_produk' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'deskripsi' => 'nullable|string',
            'harga' => 'required|numeric|min:0',
            'stok' => 'required|integer|min:0',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ], [
            'images.max' => 'Maksimal upload 5 gambar',
            'images.*.image' => 'File harus berupa gambar',
            'images.*.mimes' => 'Format gambar harus: jpeg, png, jpg, gif, atau webp',
            'images.*.max' => 'Ukuran gambar maksimal 2MB',
        ]);

        DB::beginTransaction();
        try {
            // Update produk
            $product->update([
                'category_id' => $validated['category_id'],
                'nama_produk' => $validated['nama_produk'],
                'deskripsi' => $validated['deskripsi'],
                'harga' => $validated['harga'],
                'stok' => $validated['stok'],
            ]);

            // Upload gambar baru
            if ($request->hasFile('images')) {
                $existingCount = $product->images()->count();
                $totalImages = $existingCount + count($request->file('images'));
                
                // Validasi total gambar tidak lebih dari 5
                if ($totalImages > 5) {
                    DB::rollBack();
                    return back()->withInput()
                        ->with('error', 'Total gambar tidak boleh lebih dari 5. Saat ini: ' . $existingCount . ' gambar');
                }
                
                $hasPrimary = $product->images()->where('is_primary', true)->exists();

                foreach ($request->file('images') as $index => $image) {
                    $path = $image->store('products', 'public');
                    
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'order' => $existingCount + $index,
                        'is_primary' => !$hasPrimary && $index === 0,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('seller.products.edit', $product)
                ->with('success', 'Produk berhasil diupdate');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Gagal mengupdate produk: ' . $e->getMessage());
        }
    }

    public function destroy(Product $product)
    {
        
        $seller = Auth::guard('seller')->user();
        
        // Pastikan seller hanya bisa delete produknya sendiri
        if ($product->seller_id !== $seller->id) {
            abort(403, 'Unauthorized action.');
        }
        
        DB::beginTransaction();
        try {
            // Hapus semua gambar dari storage
            foreach ($product->images as $image) {
                Storage::disk('public')->delete($image->image_path);
            }

            // Hapus produk (images akan terhapus otomatis karena onDelete cascade)
            $product->delete();

            DB::commit();

            return redirect()->route('seller.products.index')
                ->with('success', 'Produk dan semua gambar berhasil dihapus');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menghapus produk: ' . $e->getMessage());
        }
    }

    /**
     * Set gambar sebagai primary image
     */
    public function setPrimaryImage(Product $product, ProductImage $image)
    {
        $seller = Auth::guard('seller')->user();
        
        if ($product->seller_id !== $seller->id || $image->product_id !== $product->id) {
            abort(403, 'Unauthorized action.');
        }

        DB::beginTransaction();
        try {
            // Unset semua primary
            $product->images()->update(['is_primary' => false]);
            
            // Set yang dipilih jadi primary
            $image->update(['is_primary' => true]);

            DB::commit();

            return back()->with('success', 'Gambar utama berhasil diubah');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal mengubah gambar utama');
        }
    }

    /**
     * Delete single image
     */
    public function deleteImage(Product $product, ProductImage $image)
    {
        $seller = Auth::guard('seller')->user();
        
        if ($product->seller_id !== $seller->id || $image->product_id !== $product->id) {
            abort(403, 'Unauthorized action.');
        }

        // Cek apakah masih ada gambar lain
        if ($product->images()->count() <= 1) {
            return back()->with('error', 'Tidak bisa menghapus gambar terakhir. Produk harus memiliki minimal 1 gambar.');
        }

        DB::beginTransaction();
        try {
            $wasPrimary = $image->is_primary;
            
            // Hapus file dari storage
            Storage::disk('public')->delete($image->image_path);
            
            // Hapus dari database
            $image->delete();

            // Jika yang dihapus adalah primary, set gambar pertama sebagai primary
            if ($wasPrimary) {
                $firstImage = $product->images()->orderBy('order')->first();
                if ($firstImage) {
                    $firstImage->update(['is_primary' => true]);
                }
            }

            DB::commit();

            return back()->with('success', 'Gambar berhasil dihapus');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menghapus gambar: ' . $e->getMessage());
        }
    }
}