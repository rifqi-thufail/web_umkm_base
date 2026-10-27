<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Services\BaseChainService;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER_KEY = 'SB-Mid-server-test';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'midtrans.server_key' => self::SERVER_KEY,
            'midtrans.client_key' => 'SB-Mid-client-test',
            'services.coinpayments.client_id' => 'cp-client',
            'services.coinpayments.client_secret' => 'cp-secret',
            'services.coinpayments.api_url' => 'https://a-api.coinpayments.net',
            'app.url' => 'https://ampuh.test',
        ]);
    }

    private function seller(string $phone): Seller
    {
        return Seller::create([
            'nama_koperasi' => 'Koperasi ' . $phone, 'email' => $phone . '@example.com', 'password' => 'password',
            'kecamatan' => 'Tamalanrea', 'desa_kelurahan' => 'Tamalanrea', 'jenis_usaha' => 'Pangan', 'no_hp' => $phone,
        ]);
    }

    private function product(Seller $seller, int $price, int $stock = 5): Product
    {
        return Product::create(['seller_id' => $seller->id, 'nama_produk' => 'Produk ' . $price, 'harga' => $price, 'stok' => $stock]);
    }

    /** A buyer with two sellers' products in the cart. */
    private function cartWithTwoSellers(): array
    {
        $buyer = User::factory()->create(['alamat' => 'Jl. Perintis Kemerdekaan 10']);
        $a = $this->product($this->seller('0811'), 50000);
        $b = $this->product($this->seller('0812'), 30000);
        $cart = $buyer->cart()->create();
        $cart->items()->create(['product_id' => $a->id, 'quantity' => 2]);
        $cart->items()->create(['product_id' => $b->id, 'quantity' => 1]);

        return [$buyer, $a, $b];
    }

    private function fakeSnap(): void
    {
        $this->partialMock(MidtransService::class, fn ($mock) => $mock->shouldReceive('snapToken')->once()->andReturn('snap-token-1'));
    }

    private function notify(array $overrides = [], ?string $key = self::SERVER_KEY)
    {
        $payload = array_merge(['status_code' => '200', 'transaction_status' => 'settlement', 'fraud_status' => 'accept'], $overrides);
        $payload['signature_key'] ??= hash('sha512', $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . $key);

        return $this->postJson('/api/midtrans/callback', $payload);
    }

    public function test_checkout_charges_every_seller_once_under_one_reference(): void
    {
        [$buyer, $a, $b] = $this->cartWithTwoSellers();
        $this->fakeSnap();

        $response = $this->actingAs($buyer)->post('/orders/process', ['payment_gateway' => 'midtrans']);

        $orders = Order::orderBy('id')->get();
        $this->assertCount(2, $orders, 'one order per seller');
        $this->assertSame(1, $orders->pluck('payment_reference')->unique()->count());
        $this->assertSame(['100000.00', '30000.00'], $orders->pluck('total_price')->all());
        $this->assertSame(['snap-token-1', 'snap-token-1'], $orders->pluck('snap_token')->all());
        $this->assertSame('Jl. Perintis Kemerdekaan 10', $orders[0]->alamat_pengiriman);
        $this->assertSame(0, $buyer->cart->items()->count(), 'the whole cart became orders');
        $this->assertSame(5, $a->fresh()->stok, 'stock is taken on payment, not checkout');
        $response->assertRedirect(route('orders.pay', $orders[0]->payment_reference));

        $this->actingAs($buyer)->get(route('orders.pay', $orders[0]->payment_reference))
            ->assertInertia(fn (Assert $page) => $page->component('buyer/payment')->where('total', 130000)->has('orders', 2)->where('snapToken', 'snap-token-1'));
    }

    public function test_checkout_needs_an_address_a_known_gateway_and_stock(): void
    {
        [$buyer, $a] = $this->cartWithTwoSellers();

        $this->actingAs($buyer)->post('/orders/process', ['payment_gateway' => 'doku'])->assertSessionHasErrors('payment_gateway');

        $buyer->update(['alamat' => '-']);
        $this->actingAs($buyer)->post('/orders/process', ['payment_gateway' => 'midtrans'])->assertRedirect(route('profile.edit'));

        $buyer->update(['alamat' => 'Jl. Sahabat 1']);
        $a->update(['stok' => 1]);
        $this->actingAs($buyer)->post('/orders/process', ['payment_gateway' => 'midtrans'])
            ->assertRedirect(route('cart.index'))->assertSessionHas('error');

        $this->assertSame(0, Order::count());
        $this->assertSame(2, $buyer->cart->items()->count(), 'a refused checkout keeps the cart');
    }

    public function test_gateway_failure_rolls_back_the_orders_and_keeps_the_cart(): void
    {
        [$buyer] = $this->cartWithTwoSellers();
        $this->partialMock(MidtransService::class, fn ($mock) => $mock->shouldReceive('snapToken')->andThrow(new \Exception('Access denied')));

        $this->actingAs($buyer)->post('/orders/process', ['payment_gateway' => 'midtrans'])
            ->assertRedirect(route('orders.checkout'))->assertSessionHas('error');

        $this->assertSame(0, Order::count());
        $this->assertSame(2, $buyer->cart->items()->count());
    }

    public function test_settlement_marks_the_whole_checkout_paid_exactly_once(): void
    {
        [$buyer, $a, $b] = $this->cartWithTwoSellers();
        $this->fakeSnap();
        $this->actingAs($buyer)->post('/orders/process', ['payment_gateway' => 'midtrans']);
        $reference = Order::first()->payment_reference;

        $this->notify(['order_id' => $reference, 'gross_amount' => '130000.00'])->assertOk();
        $this->notify(['order_id' => $reference, 'gross_amount' => '130000.00'])->assertOk();

        foreach (Order::all() as $order) {
            $this->assertSame('paid', $order->payment_status);
            $this->assertSame('processing', $order->status);
            $this->assertNotNull($order->paid_at);
        }
        $this->assertSame(3, $a->fresh()->stok, 'a duplicate notification does not take stock twice');
        $this->assertSame(4, $b->fresh()->stok);
    }

    public function test_webhook_rejects_bad_input_wrong_signature_wrong_amount_and_missing_key(): void
    {
        [$buyer] = $this->cartWithTwoSellers();
        $this->fakeSnap();
        $this->actingAs($buyer)->post('/orders/process', ['payment_gateway' => 'midtrans']);
        $reference = Order::first()->payment_reference;

        $this->postJson('/api/midtrans/callback', ['transaction_status' => 'settlement'])->assertStatus(400);
        $this->notify(['order_id' => $reference, 'gross_amount' => '130000.00'], 'wrong-key')->assertStatus(403);
        $this->notify(['order_id' => $reference, 'gross_amount' => '1000.00'])->assertOk();
        $this->assertSame(['unpaid'], Order::pluck('payment_status')->unique()->values()->all(), 'an amount mismatch never marks paid');

        config(['midtrans.server_key' => '']);
        $payload = ['order_id' => $reference, 'status_code' => '200', 'gross_amount' => '130000.00', 'transaction_status' => 'settlement'];
        $payload['signature_key'] = hash('sha512', $reference . '200130000.00');
        $this->postJson('/api/midtrans/callback', $payload)->assertStatus(503);
        $this->assertSame(['unpaid'], Order::pluck('payment_status')->unique()->values()->all());
    }

    public function test_expire_closes_the_checkout_and_a_late_settlement_still_counts(): void
    {
        [$buyer, $a] = $this->cartWithTwoSellers();
        $this->fakeSnap();
        $this->actingAs($buyer)->post('/orders/process', ['payment_gateway' => 'midtrans']);
        $reference = Order::first()->payment_reference;

        $this->notify(['order_id' => $reference, 'gross_amount' => '130000.00', 'transaction_status' => 'expire', 'status_code' => '407'])->assertOk();
        $this->assertSame(['expired'], Order::pluck('payment_status')->unique()->values()->all());
        $this->assertSame(['cancelled'], Order::pluck('status')->unique()->values()->all());

        $this->notify(['order_id' => $reference, 'gross_amount' => '130000.00'])->assertOk();
        $this->assertSame(['paid'], Order::pluck('payment_status')->unique()->values()->all());
        $this->assertSame(3, $a->fresh()->stok);
    }

    public function test_a_denied_card_leaves_the_checkout_open_for_another_try(): void
    {
        [$buyer] = $this->cartWithTwoSellers();
        $this->fakeSnap();
        $this->actingAs($buyer)->post('/orders/process', ['payment_gateway' => 'midtrans']);
        $reference = Order::first()->payment_reference;

        $this->notify(['order_id' => $reference, 'gross_amount' => '130000.00', 'transaction_status' => 'deny', 'status_code' => '202'])->assertOk();
        $this->assertSame(['unpaid'], Order::pluck('payment_status')->unique()->values()->all());

        $this->notify(['order_id' => $reference, 'gross_amount' => '130000.00'])->assertOk();
        $this->assertSame(['paid'], Order::pluck('payment_status')->unique()->values()->all());
    }

    public function test_reorder_puts_a_closed_orders_products_back_in_the_cart(): void
    {
        [$buyer, $a] = $this->cartWithTwoSellers();
        $this->fakeSnap();
        $this->actingAs($buyer)->post('/orders/process', ['payment_gateway' => 'midtrans']);
        $order = Order::first();
        $order->update(['payment_status' => 'expired', 'status' => 'cancelled']);

        $this->actingAs($buyer)->post(route('orders.reorder', $order))->assertRedirect(route('cart.index'));

        $this->assertSame([[$a->id, 2]], $buyer->cart->items()->get()->map(fn ($i) => [$i->product_id, $i->quantity])->all());
    }

    public function test_returning_buyer_gets_the_payment_confirmed_without_a_webhook(): void
    {
        [$buyer] = $this->cartWithTwoSellers();
        $this->fakeSnap();
        $this->actingAs($buyer)->post('/orders/process', ['payment_gateway' => 'midtrans']);
        $order = Order::first();
        $status = ['order_id' => $order->payment_reference, 'status_code' => '200', 'gross_amount' => '130000.00', 'transaction_status' => 'settlement', 'fraud_status' => 'accept'];
        $status['signature_key'] = hash('sha512', $status['order_id'] . '200' . '130000.00' . self::SERVER_KEY);
        $this->partialMock(MidtransService::class, fn ($mock) => $mock->shouldReceive('status')->once()->andReturn($status));

        $this->actingAs($buyer)->get(route('orders.show', $order))
            ->assertInertia(fn (Assert $page) => $page->component('buyer/orders/show')->where('order.payment_status', 'paid')->has('siblings', 1)->where('payUrl', null));

        $this->assertSame(['paid'], Order::pluck('payment_status')->unique()->values()->all());
    }

    public function test_an_abandoned_checkout_expires_after_the_payment_window(): void
    {
        [$buyer] = $this->cartWithTwoSellers();
        $this->fakeSnap();
        $this->actingAs($buyer)->post('/orders/process', ['payment_gateway' => 'midtrans']);
        $order = Order::first();
        $this->partialMock(MidtransService::class, fn ($mock) => $mock->shouldReceive('status')->andReturn(null));

        $this->actingAs($buyer)->get(route('orders.show', $order))->assertInertia(fn (Assert $page) => $page->where('order.payment_status', 'unpaid')->whereNot('payUrl', null));

        $this->travel(25)->hours();
        \Illuminate\Support\Facades\Cache::flush();
        $this->actingAs($buyer)->get(route('orders.show', $order))->assertInertia(fn (Assert $page) => $page->where('order.payment_status', 'expired')->where('payUrl', null));
    }

    public function test_buyers_cannot_see_or_pay_someone_elses_orders(): void
    {
        [$buyer] = $this->cartWithTwoSellers();
        $this->fakeSnap();
        $this->actingAs($buyer)->post('/orders/process', ['payment_gateway' => 'midtrans']);
        $order = Order::first();
        $other = User::factory()->create();

        $this->actingAs($other)->get(route('orders.show', $order))->assertNotFound();
        $this->actingAs($other)->get(route('orders.pay', $order->payment_reference))->assertNotFound();
        $this->actingAs($other)->post(route('orders.complete', $order))->assertNotFound();
    }

    private function coinpaymentsWebhook(array $body, ?string $timestamp = null, string $secret = 'cp-secret')
    {
        $raw = json_encode($body);
        $timestamp ??= gmdate('Y-m-d\TH:i:s');
        $signature = base64_encode(hash_hmac('sha256', "\xEF\xBB\xBF" . 'POST' . 'https://ampuh.test/api/coinpayments/ipn' . 'cp-client' . $timestamp . $raw, $secret, true));

        return $this->call('POST', '/api/coinpayments/ipn', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_COINPAYMENTS_CLIENT' => 'cp-client',
            'HTTP_X_COINPAYMENTS_TIMESTAMP' => $timestamp,
            'HTTP_X_COINPAYMENTS_SIGNATURE' => $signature,
        ], $raw);
    }

    public function test_coinpayments_checkout_and_verified_webhook(): void
    {
        [$buyer, $a] = $this->cartWithTwoSellers();
        Http::fake([
            'a-api.coinpayments.net/api/v2/merchant/invoices' => Http::response(['invoices' => [['id' => 'inv-1', 'checkoutLink' => 'https://a-checkout.coinpayments.net/checkout/?invoice-id=inv-1']]], 201),
        ]);

        $this->actingAs($buyer)->post('/orders/process', ['payment_gateway' => 'coinpayments']);
        $reference = Order::first()->payment_reference;
        Http::assertSent(fn ($request) => $request['currency'] === 'IDR' && $request['amount']['total'] === '130000' && $request['invoiceId'] === $reference
            && $request['webhooks'][0]['notificationsUrl'] === 'https://ampuh.test/api/coinpayments/ipn');
        $this->actingAs($buyer)->get(route('orders.pay', $reference))->assertRedirect('https://a-checkout.coinpayments.net/checkout/?invoice-id=inv-1');

        $current = ['status' => 'Completed', 'currency' => ['symbol' => 'USD'], 'amount' => ['total' => '130000']];
        Http::fake(['a-api.coinpayments.net/api/v2/merchant/invoices/inv-1' => function () use (&$current, $reference) {
            return Http::response(['id' => 'inv-1', 'invoiceId' => $reference, ...$current]);
        }]);
        $this->coinpaymentsWebhook(['type' => 'invoiceCompleted', 'invoice' => ['id' => 'inv-1']])->assertOk();
        $this->assertSame(['unpaid'], Order::pluck('payment_status')->unique()->values()->all(), 'wrong currency never marks paid');

        $this->coinpaymentsWebhook(['invoice' => ['id' => 'inv-1']], secret: 'wrong')->assertStatus(403);
        $this->coinpaymentsWebhook(['invoice' => ['id' => 'inv-1']], gmdate('Y-m-d\TH:i:s', time() - 3600))->assertStatus(403);
        $this->coinpaymentsWebhook(['invoice' => ['id' => 'nope']])->assertStatus(404);

        $current['currency'] = ['symbol' => 'IDR'];
        $this->coinpaymentsWebhook(['type' => 'invoiceCompleted', 'invoice' => ['id' => 'inv-1']])->assertOk();
        $this->assertSame(['paid'], Order::pluck('payment_status')->unique()->values()->all());
        $this->assertSame(3, $a->fresh()->stok);
    }

    public function test_coinpayments_webhook_fails_closed_without_credentials(): void
    {
        config(['services.coinpayments.client_secret' => '']);

        $this->coinpaymentsWebhook(['invoice' => ['id' => 'inv-1']], secret: '')->assertStatus(503);
    }

    public function test_seller_ships_and_buyer_confirms_receipt(): void
    {
        [$buyer] = $this->cartWithTwoSellers();
        $this->fakeSnap();
        $this->actingAs($buyer)->post('/orders/process', ['payment_gateway' => 'midtrans']);
        $order = Order::first();
        $this->notify(['order_id' => $order->payment_reference, 'gross_amount' => '130000.00']);
        $otherSeller = Seller::where('id', '!=', $order->seller_id)->first();

        $this->actingAs($buyer)->post(route('orders.complete', $order))->assertSessionHas('error');
        $this->actingAs($otherSeller, 'seller')->post(route('seller.orders.ship', $order))->assertNotFound();
        $this->actingAs($order->seller, 'seller')->get(route('seller.orders.index'))
            ->assertInertia(fn (Assert $page) => $page->component('seller/orders')->has('orders.data', 1)->where('orders.data.0.buyer.address', 'Jl. Perintis Kemerdekaan 10'));
        $this->actingAs($order->seller, 'seller')->post(route('seller.orders.ship', $order))->assertSessionHas('success');
        $this->assertSame('shipped', $order->fresh()->status);

        $this->actingAs($buyer, 'web')->post(route('orders.complete', $order))->assertSessionHas('success');
        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_paid_orders_are_anchored_after_the_response(): void
    {
        [$buyer] = $this->cartWithTwoSellers();
        $this->fakeSnap();
        $this->actingAs($buyer)->post('/orders/process', ['payment_gateway' => 'midtrans']);
        $reference = Order::first()->payment_reference;

        $this->mock(BaseChainService::class, function ($mock) {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('fingerprint')->andReturnUsing(fn ($o) => ['hash' => '0x' . str_pad(dechex($o->id), 64, '0', STR_PAD_LEFT), 'payload' => []]);
            $mock->shouldReceive('submitAnchor')->twice()->andReturn('0x' . str_repeat('1', 64), '0x' . str_repeat('2', 64));
            $mock->shouldReceive('waitForReceipt')->twice()->andReturn(['status' => '0x1', 'blockNumber' => '0x10']);
        });

        $this->notify(['order_id' => $reference, 'gross_amount' => '130000.00'])->assertOk();

        $this->assertSame(['confirmed'], Order::pluck('blockchain_status')->unique()->values()->all());
        $this->assertSame(16, Order::first()->block_number);
    }

    public function test_an_unmined_anchor_is_resumed_not_sent_twice(): void
    {
        $order = $this->paidOrder();
        $tx = '0x' . str_repeat('3', 64);

        $this->mock(BaseChainService::class, function ($mock) use ($tx) {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('fingerprint')->andReturn(['hash' => '0x' . str_repeat('a', 64), 'payload' => []]);
            $mock->shouldReceive('submitAnchor')->once()->andReturn($tx);
            $mock->shouldReceive('waitForReceipt')->andReturn(null, ['status' => '0x1', 'blockNumber' => '0x20']);
            $mock->shouldReceive('receipt')->andReturn(null);
            $mock->shouldReceive('transactionKnown')->andReturn(true);
        });
        $base = app(BaseChainService::class);

        $first = $order->anchorOnBase($base);
        $this->assertTrue($first['pending']);
        $this->assertSame('pending', $order->fresh()->blockchain_status);
        $this->assertSame($tx, $order->fresh()->blockchain_metadata['pending_tx']);

        $second = $order->fresh()->anchorOnBase($base);
        $this->assertTrue($second['success']);
        $this->assertSame($tx, $order->fresh()->blockchain_hash);
        $this->assertSame(32, $order->fresh()->block_number);
    }

    public function test_page_traffic_retries_unanchored_orders_without_cron(): void
    {
        $order = $this->paidOrder();
        $order->update(['blockchain_status' => 'failed']);

        $this->mock(BaseChainService::class, function ($mock) {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('fingerprint')->andReturn(['hash' => '0x' . str_repeat('b', 64), 'payload' => []]);
            $mock->shouldReceive('submitAnchor')->once()->andReturn('0x' . str_repeat('4', 64));
            $mock->shouldReceive('waitForReceipt')->andReturn(['status' => '0x1', 'blockNumber' => '0x5']);
            $mock->shouldReceive('network')->andReturn(['name' => 'Base', 'chain_id' => 8453, 'contract' => null, 'explorer' => null]);
        });

        $this->get('/bantuan')->assertOk();
        $this->get('/bantuan')->assertOk(); // the sweep runs at most every 10 minutes

        $this->assertSame('confirmed', $order->fresh()->blockchain_status);
    }

    private function paidOrder(): Order
    {
        $buyer = User::factory()->create();
        $seller = $this->seller('0899');
        $product = $this->product($seller, 10000);
        $order = Order::create([
            'user_id' => $buyer->id, 'seller_id' => $seller->id, 'order_number' => 'ORD-paid-1', 'total_price' => 10000,
            'status' => 'processing', 'payment_status' => 'paid', 'paid_at' => now(), 'payment_gateway' => 'midtrans',
        ]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 10000]);

        return $order;
    }

    public function test_deleting_a_product_keeps_paid_orders_intact(): void
    {
        $order = $this->paidOrder();
        $product = $order->orderItems->first()->product;
        $buyer = User::factory()->create();
        $buyer->cart()->create()->items()->create(['product_id' => $product->id, 'quantity' => 1]);
        $hash = app(BaseChainService::class)->fingerprint($order)['hash'];

        $this->actingAs($order->seller, 'seller')->delete(route('seller.products.destroy', $product))->assertRedirect(route('seller.products.index'));

        $order = Order::with('orderItems.product')->find($order->id);
        $this->assertCount(1, $order->orderItems);
        $this->assertSame('Produk 10000', $order->orderItems->first()->product->nama_produk);
        $this->assertSame($hash, app(BaseChainService::class)->fingerprint($order)['hash'], 'the anchored fingerprint still matches');
        $this->assertSame(0, $buyer->cart->items()->count());
        $this->get(route('products.show', $product->id))->assertNotFound();
        $this->actingAs($buyer, 'web')->post('/cart', ['product_id' => $product->id, 'quantity' => 1])->assertSessionHasErrors('product_id');
    }

    public function test_deleting_an_account_with_orders_anonymizes_it(): void
    {
        $order = $this->paidOrder();
        $buyer = $order->user;

        $this->actingAs($buyer)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

        $buyer = $buyer->fresh();
        $this->assertNotNull($buyer, 'the row stays because the order and its fingerprint reference it');
        $this->assertSame('Akun dihapus', $buyer->nama);
        $this->assertStringEndsWith('@deleted.invalid', $buyer->email);
        $this->assertSame('-', $buyer->alamat);
        $this->assertNotNull($order->fresh());
        $this->assertGuest();
    }

    public function test_seller_login_locks_out_after_five_wrong_passwords(): void
    {
        $this->seller('0855');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/seller/login', ['no_hp' => '0855', 'password' => 'wrong']);
        }
        $this->post('/seller/login', ['no_hp' => '0855', 'password' => 'password'])->assertSessionHasErrors('no_hp');
        $this->assertGuest('seller');
    }
}
