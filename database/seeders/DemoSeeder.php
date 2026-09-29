<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Seller;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\BaseChainService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Local demo data: three cooperatives, twelve products with CC0 photos, a buyer with
 * paid orders (anchored on Base when BASE_ENABLED=true and the local chain is running),
 * a cart and a wishlist.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Logins: buyer test@example.com / password, seller 081234560001 / password
 */
class DemoSeeder extends Seeder
{
    public function run(BaseChainService $base): void
    {
        $this->call(CategorySeeder::class);
        $cat = Category::pluck('id', 'slug');

        $sellers = collect([
            ['nama_koperasi' => 'Koperasi Tani Kopi Sesean', 'no_hp' => '081234560001', 'kecamatan' => 'Sesean', 'desa_kelurahan' => 'Pangli', 'jenis_usaha' => 'Kopi & hasil kebun', 'alamat_toko' => 'Jl. Poros Rantepao–Batutumonga, Toraja Utara', 'deskripsi_toko' => 'Koperasi 64 petani kopi arabika di lereng Sesean. Kopi dipetik merah, dijemur di para-para, dan disangrai kecil-kecilan.', 'client_key' => 'demo-client', 'server_key' => 'demo-server', 'merchant_id' => 'G000000001', 'is_active' => true],
            ['nama_koperasi' => 'KUD Mandiri Enrekang', 'no_hp' => '081234560002', 'kecamatan' => 'Baraka', 'desa_kelurahan' => 'Kadingeh', 'jenis_usaha' => 'Pangan olahan', 'alamat_toko' => 'Jl. Pendidikan No. 12, Baraka, Enrekang', 'deskripsi_toko' => 'Madu hutan, gula aren, dan camilan dari anggota koperasi di Enrekang.'],
            ['nama_koperasi' => 'Koperasi Tenun Wajo', 'no_hp' => '081234560003', 'kecamatan' => 'Tempe', 'desa_kelurahan' => 'Sengkang', 'jenis_usaha' => 'Kerajinan & tenun', 'alamat_toko' => 'Jl. Sutera No. 5, Sengkang, Wajo', 'deskripsi_toko' => 'Penenun sutra dan anyaman lontar dari Sengkang.'],
        ])->map(fn ($s) => Seller::updateOrCreate(['no_hp' => $s['no_hp']], [...$s, 'password' => Hash::make('password')]));

        [$kopi, $kud, $tenun] = $sellers->all();

        $products = [
            [$kopi, 'minuman', 'Kopi Arabika Toraja Sesean 250 g', 98000, 42, 'kopi-toraja', 'Biji kopi arabika sangrai sedang dari ketinggian 1.600 mdpl. Catatan rasa gula aren, rempah, dan sedikit asam jeruk. Tersedia biji utuh atau giling.'],
            [$kopi, 'minuman', 'Teh Serai Kering 50 g', 25000, 60, 'teh-serai', 'Serai dan daun jeruk kering untuk diseduh panas. Tanpa pemanis dan pewarna.'],
            [$kopi, 'pertanian', 'Kakao Fermentasi Luwu 500 g', 65000, 8, 'cokelat-kakao', 'Biji kakao terfermentasi 5 hari, siap disangrai untuk cokelat rumahan.'],
            [$kopi, 'pertanian', 'Beras Pecah Kulit Organik 2 kg', 54000, 25, 'beras-pecah-kulit', 'Beras pecah kulit dari sawah tadah hujan tanpa pupuk kimia.'],
            [$kud, 'makanan', 'Madu Hutan Enrekang 500 ml', 135000, 18, 'madu-hutan', 'Madu lebah hutan Apis dorsata, dipanen dengan cara tiris tanpa pemanasan.'],
            [$kud, 'makanan', 'Gula Aren Cair 350 ml', 38000, 30, 'gula-aren', 'Nira aren dimasak perlahan sampai kental. Cocok untuk kopi susu dan kue.'],
            [$kud, 'makanan', 'Keripik Pisang Kepok 200 g', 22000, 75, 'keripik-pisang', 'Pisang kepok diiris tipis dan digoreng dengan minyak kelapa. Rasa original.'],
            [$kud, 'makanan', 'Kacang Mete Sangrai 250 g', 72000, 4, 'kacang-mete', 'Mete utuh dari Pangkep, disangrai tanpa minyak dan diberi sedikit garam.'],
            [$kud, 'makanan', 'Minyak Kelapa Murni 250 ml', 45000, 20, 'minyak-kelapa', 'VCO dari kelapa segar, diproses dingin.'],
            [$kud, 'makanan', 'Kerupuk Rumput Laut 150 g', 18000, 0, 'kerupuk-rumput-laut', 'Kerupuk renyah dengan rumput laut dari pesisir Takalar.'],
            [$tenun, 'pakaian', 'Sarung Sutra Sengkang Motif Balo Renni', 450000, 6, 'sarung-sutra', 'Sutra tenun gedogan, dikerjakan sekitar tiga minggu per lembar. Panjang 180 cm.'],
            [$tenun, 'kerajinan-tangan', 'Tas Anyaman Lontar Bundar', 120000, 12, 'tas-anyaman', 'Anyaman daun lontar dengan tutup, diameter 24 cm. Setiap tas sedikit berbeda.'],
        ];

        Storage::disk('public')->makeDirectory('products');
        $created = [];
        foreach ($products as $i => [$seller, $slug, $name, $price, $stock, $image, $desc]) {
            $product = Product::updateOrCreate(
                ['seller_id' => $seller->id, 'nama_produk' => $name],
                ['category_id' => $cat[$slug] ?? null, 'harga' => $price, 'stok' => $stock, 'deskripsi' => $desc],
            );
            $path = "products/demo-{$image}.jpg";
            Storage::disk('public')->put($path, file_get_contents(__DIR__ . "/demo-images/{$image}.jpg"));
            ProductImage::updateOrCreate(['product_id' => $product->id, 'order' => 0], ['image_path' => $path, 'is_primary' => true]);
            // Stagger created_at so the catalog order is stable.
            $product->forceFill(['created_at' => now()->subHours(count($products) - $i)])->save();
            $created[$image] = $product;
        }

        $buyer = User::updateOrCreate(['email' => 'test@example.com'], [
            'nama' => 'Andi Tenri Ajeng',
            'password' => Hash::make('password'),
            'alamat' => 'Jl. Perintis Kemerdekaan Km. 10, Tamalanrea, Makassar',
            'no_hp' => '085299001122',
            'email_verified_at' => now(),
        ]);

        // Paid orders (anchored on Base) + one waiting for payment.
        if (! Order::where('user_id', $buyer->id)->exists()) {
            $orders = [
                [$kopi, [['kopi-toraja', 2], ['teh-serai', 1]], 'completed', 'paid', 9],
                [$kud, [['madu-hutan', 1], ['gula-aren', 2]], 'shipped', 'paid', 4],
                [$tenun, [['tas-anyaman', 1]], 'processing', 'paid', 1],
                [$kud, [['keripik-pisang', 3]], 'pending', 'unpaid', 0],
            ];
            foreach ($orders as [$seller, $items, $status, $payment, $daysAgo]) {
                $total = collect($items)->sum(fn ($it) => $created[$it[0]]->harga * $it[1]);
                $order = Order::create([
                    'user_id' => $buyer->id,
                    'seller_id' => $seller->id,
                    'order_number' => 'ORD-' . Str::uuid(),
                    'total_price' => $total,
                    'status' => $status,
                    'payment_status' => $payment,
                    'payment_gateway' => 'midtrans',
                    'blockchain_status' => 'pending',
                ]);
                $order->forceFill(['created_at' => now()->subDays($daysAgo)->subHours(3)])->save();
                foreach ($items as [$key, $qty]) {
                    $order->items()->create(['product_id' => $created[$key]->id, 'quantity' => $qty, 'price' => $created[$key]->harga]);
                }

                if ($payment === 'paid' && $base->enabled()) {
                    $result = $order->fresh()->anchorOnBase($base);
                    $this->command?->info($result['success']
                        ? "Order {$order->id} anchored on Base: {$result['tx_hash']}"
                        : "Order {$order->id} not anchored: {$result['error']}");
                }
            }
        }

        $cart = Cart::firstOrCreate(['user_id' => $buyer->id]);
        $cart->items()->delete();
        $cart->items()->create(['product_id' => $created['kacang-mete']->id, 'quantity' => 1]);
        $cart->items()->create(['product_id' => $created['sarung-sutra']->id, 'quantity' => 1]);
        $cart->items()->create(['product_id' => $created['minyak-kelapa']->id, 'quantity' => 2]);

        foreach (['cokelat-kakao', 'madu-hutan', 'beras-pecah-kulit'] as $key) {
            Wishlist::firstOrCreate(['user_id' => $buyer->id, 'product_id' => $created[$key]->id]);
        }
    }
}
