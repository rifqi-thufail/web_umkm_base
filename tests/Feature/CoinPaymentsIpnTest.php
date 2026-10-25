<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoinPaymentsIpnTest extends TestCase
{
    use RefreshDatabase;

    private function unpaidOrder(string $gateway = 'coinpayments'): Order
    {
        $user = User::factory()->create();
        $seller = Seller::create([
            'nama_koperasi' => 'Koperasi Uji', 'password' => 'password', 'kecamatan' => 'Tamalanrea',
            'desa_kelurahan' => 'Tamalanrea', 'jenis_usaha' => 'Pangan', 'no_hp' => '0811',
        ]);
        $product = Product::create(['seller_id' => $seller->id, 'nama_produk' => 'Madu', 'harga' => 50000, 'stok' => 5]);
        $order = Order::create([
            'user_id' => $user->id, 'seller_id' => $seller->id, 'order_number' => 'ORD-ipn-1',
            'total_price' => 50000, 'payment_status' => 'unpaid', 'payment_gateway' => $gateway,
        ]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 50000]);

        return $order;
    }

    private function ipn(string $key, array $body = ['invoiceId' => 'ORD-ipn-1', 'status' => 100])
    {
        $payload = json_encode($body);

        return $this->call('POST', '/api/coinpayments/ipn', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_HMAC' => hash_hmac('sha512', $payload, $key),
        ], $payload);
    }

    public function test_empty_secret_rejects_empty_key_hmac(): void
    {
        config(['services.coinpayments.ipn_secret' => null]);
        $order = $this->unpaidOrder();

        $this->ipn('')->assertStatus(503);

        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_wrong_key_is_rejected_and_valid_ipn_marks_paid(): void
    {
        config(['services.coinpayments.ipn_secret' => 'dummy-secret']);
        $order = $this->unpaidOrder();

        $this->ipn('')->assertStatus(403);
        $this->assertSame('unpaid', $order->fresh()->payment_status);

        $this->ipn('dummy-secret')->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_ipn_cannot_settle_an_order_from_another_gateway(): void
    {
        config(['services.coinpayments.ipn_secret' => 'dummy-secret']);
        $order = $this->unpaidOrder('midtrans');

        $this->ipn('dummy-secret')->assertStatus(409);

        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }
}
