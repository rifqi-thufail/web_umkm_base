<?php

namespace Tests\Feature;

use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SiteHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_pages_render_the_branded_error_page_but_webhooks_stay_json(): void
    {
        $this->get('/does-not-exist')->assertNotFound()->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 404));
        $this->get('/products/999999')->assertNotFound()->assertInertia(fn (Assert $page) => $page->component('error'));

        $this->postJson('/api/midtrans/callback', [])->assertStatus(503)->assertJson(['message' => 'Not configured']);
    }

    public function test_messages_are_in_indonesian(): void
    {
        app()->setLocale('id');

        $this->post('/seller/login', ['no_hp' => '0800', 'password' => 'salah'])
            ->assertSessionHasErrors(['no_hp' => 'Data masuk tidak cocok. Periksa lagi lalu coba ulang.']);
        $this->post('/register', ['nama' => 'A', 'email' => 'bukan-email', 'password' => 'x', 'password_confirmation' => 'y'])
            ->assertSessionHasErrors(['email' => 'Email harus alamat email yang valid.']);
    }

    public function test_seller_server_keys_are_encrypted_at_rest(): void
    {
        $seller = Seller::create([
            'nama_koperasi' => 'Koperasi Uji', 'email' => 'k@example.com', 'password' => 'password', 'kecamatan' => 'Tamalanrea',
            'desa_kelurahan' => 'Tamalanrea', 'jenis_usaha' => 'Pangan', 'no_hp' => '0811', 'server_key' => 'SB-Mid-server-secret',
        ]);

        $this->assertStringNotContainsString('secret', DB::table('sellers')->where('id', $seller->id)->value('server_key'));
        $this->assertSame('SB-Mid-server-secret', $seller->fresh()->server_key);
        $this->assertArrayNotHasKey('server_key', $seller->fresh()->toArray());
    }
}
