<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Services\CoinPaymentsService;
use App\Services\MidtransService;
use App\Services\OrderPaymentService;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function __construct(
        protected MidtransService $midtrans,
        protected CoinPaymentsService $coinPayments,
        protected OrderPaymentService $payments,
    ) {
    }

    private function gateways(): array
    {
        return [
            'midtrans' => $this->midtrans->configured(),
            'coinpayments' => $this->coinPayments->configured(),
        ];
    }

    public function checkout()
    {
        $cart = Cart::with('items.product.primaryImage', 'items.product.seller')->where('user_id', Auth::id())->first();
        $items = CartController::presentItems($cart);

        if (! $items) {
            return redirect()->route('cart.index')->with('error', 'Keranjang Anda kosong.');
        }

        $user = Auth::user();

        return Inertia::render('buyer/checkout', [
            'items' => $items,
            'buyer' => [
                'name' => $user->nama,
                'email' => $user->email,
                'phone' => $user->no_hp !== '-' ? $user->no_hp : null,
                'address' => $user->alamat !== '-' ? $user->alamat : null,
            ],
            'gateways' => $this->gateways(),
        ]);
    }

    /**
     * Turn the cart into one order per seller, all charged together under one payment reference.
     */
    public function process(Request $request)
    {
        $gateways = array_keys(array_filter($this->gateways()));
        $request->validate(['payment_gateway' => ['required', Rule::in($gateways)]], [
            'payment_gateway.in' => 'Metode pembayaran ini belum tersedia.',
            'payment_gateway.required' => 'Pilih metode pembayaran.',
        ]);
        $gateway = $request->input('payment_gateway');

        $user = Auth::user();
        if (! $user->alamat || $user->alamat === '-') {
            return redirect()->route('profile.edit')->with('error', 'Isi alamat pengiriman dulu supaya penjual bisa mengirim pesanan.');
        }

        $reference = 'PAY-' . Str::uuid();

        try {
            $orders = DB::transaction(function () use ($user, $gateway, $reference) {
                $cart = Cart::where('user_id', $user->id)->first();
                $items = $cart?->items()->get() ?? collect();
                if ($items->isEmpty()) {
                    throw ValidationException::withMessages(['cart' => 'Keranjang Anda kosong.']);
                }

                // Lock the products so two buyers cannot both pass the stock check for the last unit.
                $products = Product::whereKey($items->pluck('product_id'))->lockForUpdate()->get()->keyBy('id');

                $orders = collect();
                foreach ($items->groupBy(fn ($item) => $products->get($item->product_id)?->seller_id) as $sellerId => $sellerItems) {
                    $lines = [];
                    foreach ($sellerItems as $item) {
                        $product = $products->get($item->product_id);
                        if (! $product) {
                            throw ValidationException::withMessages(['cart' => 'Ada produk di keranjang yang sudah tidak dijual. Hapus dulu dari keranjang.']);
                        }
                        if ($product->stok < $item->quantity) {
                            throw ValidationException::withMessages(['cart' => "Stok {$product->nama_produk} tinggal {$product->stok}."]);
                        }
                        $lines[] = ['product_id' => $product->id, 'quantity' => (int) $item->quantity, 'price' => (int) round((float) $product->harga)];
                    }

                    $order = Order::create([
                        'user_id' => $user->id,
                        'seller_id' => $sellerId,
                        'order_number' => 'ORD-' . Str::uuid(),
                        'payment_reference' => $reference,
                        'total_price' => array_sum(array_map(fn ($l) => $l['quantity'] * $l['price'], $lines)),
                        'status' => 'pending',
                        'payment_status' => 'unpaid',
                        'payment_gateway' => $gateway,
                        'alamat_pengiriman' => $user->alamat,
                    ]);
                    $order->items()->createMany($lines);
                    $orders->push($order);
                }

                return new \Illuminate\Database\Eloquent\Collection($orders->all());
            });
        } catch (ValidationException $e) {
            return redirect()->route('cart.index')->with('error', collect($e->errors())->flatten()->first());
        }

        try {
            if ($gateway === 'coinpayments') {
                $invoice = $this->coinPayments->createInvoice($reference, $orders, $user);
                $token = $invoice['id'];
            } else {
                $token = $this->midtrans->snapToken($reference, $orders, $user);
            }
        } catch (\Throwable $e) {
            Log::error('Payment gateway refused the checkout', ['reference' => $reference, 'gateway' => $gateway, 'error' => $e->getMessage()]);
            Order::whereKey($orders->modelKeys())->delete();

            return redirect()->route('orders.checkout')->with('error', 'Gateway pembayaran sedang tidak bisa dihubungi. Keranjang Anda masih utuh, silakan coba lagi.');
        }

        Order::whereKey($orders->modelKeys())->update(['snap_token' => $token]);

        Cart::where('user_id', $user->id)->first()?->items()
            ->whereIn('product_id', $orders->flatMap(fn ($o) => $o->items->pluck('product_id')))
            ->delete();

        return redirect()->route('orders.pay', $reference);
    }

    /**
     * Payment page for a checkout. A GET route, so a refresh or "pay later" link keeps working.
     */
    public function pay(string $reference)
    {
        $orders = Order::where('payment_reference', $reference)->where('user_id', Auth::id())
            ->with(['seller', 'orderItems.product.primaryImage'])->orderBy('id')->get();
        abort_if($orders->isEmpty(), 404);

        $first = $orders->first();
        $this->payments->sync($first);
        $first->refresh();

        if ($first->payment_status !== 'unpaid' || ! $first->paymentWindowOpen() || ! $first->snap_token) {
            return redirect()->route('orders.show', $first);
        }

        if ($first->payment_gateway === 'coinpayments') {
            return Inertia::location(CoinPaymentsService::checkoutUrl($first->snap_token));
        }

        return Inertia::render('buyer/payment', [
            'snapToken' => $first->snap_token,
            'clientKey' => config('midtrans.client_key'),
            'isProduction' => (bool) config('midtrans.is_production'),
            'orders' => $orders->map(fn ($o) => Present::order($o))->all(),
            'total' => MidtransService::total($orders),
        ]);
    }

    public function midtransCallback(Request $request)
    {
        $payload = $request->all();

        if (! config('midtrans.server_key')) {
            Log::critical('Midtrans notification rejected: MIDTRANS_SERVER_KEY is not configured');

            return response()->json(['message' => 'Not configured'], 503);
        }

        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key', 'transaction_status'] as $field) {
            if (! is_string($payload[$field] ?? null) || $payload[$field] === '') {
                return response()->json(['message' => "Missing {$field}"], 400);
            }
        }

        if (! $this->midtrans->validSignature($payload)) {
            Log::warning('Invalid Midtrans signature', ['order_id' => $payload['order_id']]);

            return response()->json(['message' => 'Invalid signature'], 403);
        }

        Log::info('Midtrans notification', ['order_id' => $payload['order_id'], 'status' => $payload['transaction_status']]);

        if ($this->payments->ordersFor($payload['order_id'])->isEmpty()) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $this->payments->applyMidtrans($payload);

        return response()->json(['message' => 'OK']);
    }

    /**
     * CoinPayments webhook. After the signature check the payload only tells us which invoice to
     * re-read; the invoice itself is fetched from the API, so a replayed body cannot settle anything.
     */
    public function coinpaymentsCallback(Request $request)
    {
        if (! $this->coinPayments->configured()) {
            Log::critical('CoinPayments webhook rejected: COINPAYMENTS_CLIENT_ID/SECRET are not configured');

            return response()->json(['message' => 'Not configured'], 503);
        }

        if (! $this->coinPayments->validWebhook($request)) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $invoiceId = $request->input('invoice.id') ?? $request->input('invoiceId') ?? $request->input('id');
        $order = is_string($invoiceId)
            ? Order::where('payment_gateway', 'coinpayments')->where('snap_token', $invoiceId)->first()
            : null;

        if (! $order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        try {
            $this->payments->applyCoinPayments($this->coinPayments->invoice($invoiceId));
        } catch (\Throwable $e) {
            Log::error('CoinPayments webhook: invoice lookup failed', ['invoice' => $invoiceId, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Lookup failed'], 502);
        }

        return response()->json(['message' => 'OK']);
    }

    public function index()
    {
        $orders = Order::where('user_id', Auth::id())
            ->with(['seller', 'orderItems.product.primaryImage'])
            ->latest()
            ->paginate(10);

        // Settle the few newest open checkouts with the gateway (throttled per checkout).
        $open = collect($orders->items())->where('payment_status', 'unpaid');
        $open->unique(fn ($o) => $o->gatewayReference())->take(3)->each(fn ($o) => $this->payments->sync($o));
        $open->each->refresh();

        return Inertia::render('buyer/orders/index', [
            'orders' => Present::paginate($orders, fn ($o) => Present::order($o)),
        ]);
    }

    public function show(Order $order)
    {
        if ($order->user_id !== Auth::id()) {
            abort(404);
        }

        $this->payments->sync($order);
        $order->refresh()->load(['seller', 'orderItems.product.primaryImage']);

        $siblings = $order->paymentGroup()->reject(fn ($o) => $o->id === $order->id)->values();

        return Inertia::render('buyer/orders/show', [
            'order' => Present::order($order),
            'payUrl' => $order->paymentWindowOpen() && $order->snap_token && $order->payment_reference
                ? route('orders.pay', $order->payment_reference) : null,
            'siblings' => $siblings->map(fn ($o) => ['id' => $o->id, 'number' => $o->order_number])->all(),
        ]);
    }

    /** Put a closed order's products back in the cart so the buyer can check out again. */
    public function reorder(Order $order)
    {
        if ($order->user_id !== Auth::id()) {
            abort(404);
        }

        $cart = Auth::user()->cart()->firstOrCreate([]);
        $added = 0;
        foreach ($order->orderItems()->with('product')->get() as $item) {
            $product = $item->product;
            if (! $product || $product->trashed() || $product->stok < 1) {
                continue;
            }
            $line = $cart->items()->firstOrNew(['product_id' => $product->id]);
            $line->quantity = min($product->stok, max((int) $line->quantity, (int) $item->quantity));
            $line->save();
            $added++;
        }

        return $added
            ? redirect()->route('cart.index')->with('success', 'Produk dari pesanan ini dimasukkan lagi ke keranjang.')
            : back()->with('error', 'Produk dari pesanan ini sudah tidak tersedia.');
    }

    /** The buyer confirms the parcel arrived. */
    public function complete(Order $order)
    {
        if ($order->user_id !== Auth::id()) {
            abort(404);
        }
        if ($order->payment_status !== 'paid' || $order->status !== 'shipped') {
            return back()->with('error', 'Pesanan ini belum dikirim.');
        }

        $order->update(['status' => 'completed']);

        return back()->with('success', 'Terima kasih, pesanan ditandai selesai.');
    }
}
