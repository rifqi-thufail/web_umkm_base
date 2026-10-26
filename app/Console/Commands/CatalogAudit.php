<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Seller;
use Illuminate\Console\Command;

/**
 * Read-only report of catalog data that should not be live (UAT D3):
 * test sellers and products without photos.
 */
class CatalogAudit extends Command
{
    protected $signature = 'catalog:audit';

    protected $description = 'List test sellers and products without photos';

    private const TEST_PATTERN = '/\b(test|testing|uji ?coba|dummy|contoh|sample)|mars/i';

    public function handle(): int
    {
        $testSellers = Seller::withCount('products')->get()->filter(fn (Seller $s) => collect([
            $s->nama_koperasi, $s->email, $s->kecamatan, $s->desa_kelurahan, $s->alamat_toko,
        ])->contains(fn ($v) => $v && preg_match(self::TEST_PATTERN, $v)));

        $this->info("Test-looking sellers: {$testSellers->count()}");
        $this->table(['ID', 'Name', 'Email', 'Area', 'Products'], $testSellers->map(fn (Seller $s) => [
            $s->id, $s->nama_koperasi, $s->email, trim("{$s->desa_kelurahan}, {$s->kecamatan}", ', '), $s->products_count,
        ]));

        $noPhoto = Product::with('seller')->doesntHave('images')->get();
        $this->info("Products without a photo: {$noPhoto->count()} of ".Product::count());
        $this->table(['ID', 'Product', 'Seller', 'Seller email'], $noPhoto->map(fn (Product $p) => [
            $p->id, $p->nama_produk, $p->seller?->nama_koperasi, $p->seller?->email,
        ]));

        return self::SUCCESS;
    }
}
