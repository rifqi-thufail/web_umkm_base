<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Shapes Eloquent models into the plain arrays the React pages expect.
 */
class Present
{
    public static function storage(?string $path): ?string
    {
        return $path ? asset('storage/' . $path) : null;
    }

    public static function productCard(Product $p, array $wishlistIds = []): array
    {
        $image = $p->relationLoaded('primaryImage') ? $p->primaryImage : null;
        if (! $image && $p->relationLoaded('images')) {
            $image = $p->images->firstWhere('is_primary', true) ?? $p->images->first();
        }

        return [
            'id' => $p->id,
            'name' => $p->nama_produk,
            'price' => (int) round((float) $p->harga),
            'stock' => (int) $p->stok,
            'image' => self::storage($image?->image_path),
            'category' => $p->relationLoaded('category') ? $p->category?->name : null,
            'seller' => $p->relationLoaded('seller') && $p->seller ? [
                'id' => $p->seller->id,
                'name' => $p->seller->nama_koperasi,
                'area' => $p->seller->kecamatan,
            ] : null,
            'wishlisted' => in_array($p->id, $wishlistIds, true),
        ];
    }

    public static function productDetail(Product $p, array $wishlistIds = []): array
    {
        $p->loadMissing(['images', 'category', 'seller']);

        return [
            ...self::productCard($p, $wishlistIds),
            'description' => $p->deskripsi,
            'category_id' => $p->category_id,
            'images' => $p->images->sortBy('order')->values()->map(fn ($img) => [
                'id' => $img->id,
                'url' => self::storage($img->image_path),
                'is_primary' => (bool) $img->is_primary,
            ])->all(),
            'seller' => $p->seller ? self::seller($p->seller) : null,
        ];
    }

    public static function seller(Seller $s): array
    {
        return [
            'id' => $s->id,
            'name' => $s->nama_koperasi,
            'type' => $s->jenis_usaha,
            'area' => $s->kecamatan,
            'village' => $s->desa_kelurahan,
            'address' => $s->alamat_toko,
            'description' => $s->deskripsi_toko,
            'avatar' => self::storage($s->foto_profil),
            'joined' => $s->created_at?->toISOString(),
        ];
    }

    public static function category(Category $c): array
    {
        return ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug];
    }

    public static function order(Order $o, bool $withItems = true): array
    {
        $data = [
            'id' => $o->id,
            'number' => $o->order_number,
            'total' => (int) round((float) $o->total_price),
            'status' => $o->status,
            'status_label' => $o->status_label,
            'payment_status' => $o->payment_status,
            'payment_status_label' => $o->payment_status_label,
            'gateway' => $o->payment_gateway,
            'created_at' => $o->created_at?->toISOString(),
            'seller' => $o->relationLoaded('seller') && $o->seller
                ? ['id' => $o->seller->id, 'name' => $o->seller->nama_koperasi] : null,
            'buyer' => $o->relationLoaded('user') && $o->user
                ? ['name' => $o->user->nama, 'phone' => $o->user->no_hp, 'address' => $o->alamat_pengiriman ?: $o->user->alamat] : null,
            'chain' => self::chain($o),
        ];

        if ($withItems && $o->relationLoaded('orderItems')) {
            $data['items'] = $o->orderItems->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'name' => $item->product?->nama_produk ?? 'Produk dihapus',
                'image' => self::storage($item->product?->primaryImage?->image_path),
                'quantity' => (int) $item->quantity,
                'price' => (int) round((float) $item->price),
            ])->all();
        }

        return $data;
    }

    public static function chain(Order $o): array
    {
        return [
            'status' => $o->blockchain_status,
            'tx_hash' => $o->blockchain_hash,
            'data_hash' => $o->transaction_data_hash,
            'block' => $o->block_number,
            'chain_id' => $o->blockchain_transaction_id ? (int) $o->blockchain_transaction_id : null,
            'anchored_at' => $o->blockchain_created_at?->toISOString(),
            'explorer_url' => $o->blockchain_url,
        ];
    }

    /**
     * Laravel paginator -> { data, meta, links } with the items mapped.
     */
    public static function paginate(LengthAwarePaginator $paginator, callable $map): array
    {
        return [
            'data' => collect($paginator->items())->map($map)->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
                'pages' => collect($paginator->getUrlRange(1, $paginator->lastPage()))
                    ->map(fn ($url, $page) => ['page' => $page, 'url' => $url])
                    ->values()->all(),
            ],
        ];
    }
}
