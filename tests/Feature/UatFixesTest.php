<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UatFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_seller_is_sent_to_seller_login(): void
    {
        $this->get('/seller/dashboard')->assertRedirect(route('seller.login'));
    }

    public function test_guest_buyer_is_sent_to_login_chooser(): void
    {
        $this->get('/cart')->assertRedirect(route('login'));
    }

    public function test_buyer_returns_to_product_after_login(): void
    {
        $user = User::factory()->create();

        $this->get('/user/login?redirect=/products/30')->assertOk();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(url('/products/30'));
    }

    public function test_login_redirect_ignores_external_urls(): void
    {
        $user = User::factory()->create();

        foreach (['//evil.test/x', 'https://evil.test', '/\\evil.test'] as $target) {
            $this->get('/user/login?redirect='.urlencode($target));
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_security_headers_are_set(): void
    {
        $this->get('/')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    private function seller(string $name = 'Koperasi Maju'): Seller
    {
        return Seller::create([
            'nama_koperasi' => $name, 'password' => 'password', 'kecamatan' => 'Tamalanrea',
            'desa_kelurahan' => 'Tamalanrea', 'jenis_usaha' => 'Pangan', 'no_hp' => '0811'.random_int(1000, 9999),
        ]);
    }

    public function test_catalog_audit_lists_test_sellers_and_products_without_photos(): void
    {
        $tester = $this->seller('Testing_Produk');
        Product::create(['seller_id' => $tester->id, 'nama_produk' => 'Kopi', 'harga' => 30, 'stok' => 1]);

        $this->artisan('catalog:audit')
            ->expectsOutputToContain('Test-looking sellers: 1')
            ->expectsOutputToContain('Products without a photo: 1 of 1')
            ->assertSuccessful();
    }
}
