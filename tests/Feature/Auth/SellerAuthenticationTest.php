<?php

namespace Tests\Feature\Auth;

use App\Models\Seller;
use App\Models\SellerPasswordResetOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SellerAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/seller/login');

        $response->assertStatus(200);
    }

    public function test_seller_can_authenticate_using_phone_number(): void
    {
        $seller = Seller::create([
            'nama_koperasi' => 'Koperasi Test',
            'email' => 'seller@example.com',
            'password' => 'password',
            'kecamatan' => 'Kecamatan A',
            'desa_kelurahan' => 'Desa A',
            'jenis_usaha' => 'Kuliner',
            'no_hp' => '081234567890',
        ]);

        $response = $this->post('/seller/login', [
            'no_hp' => $seller->no_hp,
            'password' => 'password',
        ]);

        $this->assertAuthenticated('seller');
        $response->assertRedirect(route('seller.dashboard', absolute: false));
    }

    public function test_seller_password_can_be_reset_with_valid_otp(): void
    {
        $seller = Seller::create([
            'nama_koperasi' => 'Koperasi Test',
            'email' => 'seller-reset@example.com',
            'password' => 'old-password',
            'kecamatan' => 'Kecamatan A',
            'desa_kelurahan' => 'Desa A',
            'jenis_usaha' => 'Kuliner',
            'no_hp' => '081298765432',
        ]);

        SellerPasswordResetOtp::create([
            'seller_id' => $seller->id,
            'no_hp' => $seller->no_hp,
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        $response = $this->post('/seller/reset-password', [
            'no_hp' => $seller->no_hp,
            'otp' => '123456',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('seller.login'));

        $this->assertTrue(Hash::check('new-password', $seller->fresh()->password));
    }
}