<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Services\BaseChainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BaseChainTest extends TestCase
{
    use RefreshDatabase;

    private function paidOrder(): Order
    {
        $user = User::factory()->create();
        $seller = Seller::create([
            'nama_koperasi' => 'Koperasi Uji', 'password' => 'password', 'kecamatan' => 'Tamalanrea',
            'desa_kelurahan' => 'Tamalanrea', 'jenis_usaha' => 'Pangan', 'no_hp' => '0811',
        ]);
        $product = Product::create(['seller_id' => $seller->id, 'nama_produk' => 'Madu', 'harga' => 50000, 'stok' => 5]);
        $order = Order::create([
            'user_id' => $user->id, 'seller_id' => $seller->id, 'order_number' => 'ORD-test-1',
            'total_price' => 100000, 'status' => 'processing', 'payment_status' => 'paid',
        ]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 2, 'price' => 50000]);

        return $order->fresh();
    }

    public function test_fingerprint_is_deterministic_and_detects_changes(): void
    {
        $base = new BaseChainService();
        $order = $this->paidOrder();

        $first = $base->fingerprint($order);
        $this->assertSame($first['hash'], $base->fingerprint($order->fresh())['hash']);
        $this->assertMatchesRegularExpression('/^0x[0-9a-f]{64}$/', $first['hash']);
        $this->assertArrayNotHasKey('email', $first['payload'], 'no personal data is hashed');

        $order->orderItems()->first()->update(['price' => 49000]);
        $this->assertNotSame($first['hash'], $base->fingerprint($order->fresh())['hash']);
    }

    public function test_anchoring_is_skipped_cleanly_when_base_is_disabled(): void
    {
        config(['blockchain.base.enabled' => false]);
        $order = $this->paidOrder();

        $result = $order->anchorOnBase(new BaseChainService());

        $this->assertFalse($result['success']);
        $this->assertSame('failed', $order->fresh()->blockchain_status);
        $this->assertSame('paid', $order->fresh()->payment_status, 'payment is never rolled back');
    }

    public function test_confirmed_orders_are_not_downgraded_by_a_retry(): void
    {
        $order = $this->paidOrder();
        $order->update(['blockchain_status' => 'confirmed', 'blockchain_hash' => '0x' . str_repeat('a', 64)]);

        $result = $order->anchorOnBase(new BaseChainService());

        $this->assertTrue($result['already_anchored']);
        $this->assertSame('confirmed', $order->fresh()->blockchain_status);
    }

    public function test_public_pages_render_their_react_components(): void
    {
        $order = $this->paidOrder();
        $order->update(['blockchain_status' => 'confirmed', 'blockchain_hash' => '0x' . str_repeat('b', 64), 'block_number' => 7]);
        config(['blockchain.base.rpc_url' => 'http://127.0.0.1:1']); // unreachable on purpose

        $this->get('/')->assertInertia(fn (Assert $page) => $page->component('home')->has('products.data', 1)->has('recentProofs', 1));
        $this->get('/transparansi')->assertInertia(fn (Assert $page) => $page->component('transparency/index')->where('stats.total_transactions', 1));
        $this->get('/transparansi/' . $order->blockchain_hash)
            ->assertInertia(fn (Assert $page) => $page->component('transparency/show')->where('check.reachable', false));
        $this->get('/verifikasi?q=ORD-test-1')->assertInertia(fn (Assert $page) => $page->component('verify')->where('result.found', true));
        $this->get('/verifikasi?q=nope')->assertInertia(fn (Assert $page) => $page->where('result.found', false));
    }
}
