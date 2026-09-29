<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use App\Services\BaseChainService;
use App\Services\CoinPaymentsService; // Pastikan import service CoinPayments

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Snap;

class OrderController extends Controller
{
    protected $blockchainService;
    protected $coinPaymentsService;

    // Inject kedua service ke dalam constructor
    public function __construct(BaseChainService $blockchainService, CoinPaymentsService $coinPaymentsService) {
        $this->blockchainService = $blockchainService;
        $this->coinPaymentsService = $coinPaymentsService;
    }

    public function checkout() {
        $cart = Cart::with('items.product.primaryImage', 'items.product.seller')->where('user_id', Auth::id())->first();

        if (!$cart || $cart->items->isEmpty()) {
            return redirect()->route('home')->with('error', 'Keranjang Anda kosong.');
        }

        $user = Auth::user();

        return \Inertia\Inertia::render('buyer/checkout', [
            'items' => CartController::presentItems($cart),
            'buyer' => [
                'name' => $user->nama,
                'email' => $user->email,
                'phone' => $user->no_hp !== '-' ? $user->no_hp : null,
                'address' => $user->alamat !== '-' ? $user->alamat : null,
            ],
            'gateways' => [
                'midtrans' => (bool) config('midtrans.server_key'),
                'coinpayments' => (bool) env('COINPAYMENTS_CLIENT_ID'),
            ],
        ]);
    }

    public function process(Request $request)
    {
        $user = Auth::user();
        $cart = Cart::with('items.product.seller')->where('user_id', $user->id)->firstOrFail();

        if ($cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Tidak ada item di keranjang untuk di-checkout.');
        }

        // Ambil pilihan gateway dari form checkout (default ke midtrans jika kosong)
        // Pastikan di form checkout HTML Anda ada input name="payment_gateway" value="midtrans" atau "coinpayments"
        $selectedGateway = $request->input('payment_gateway', 'midtrans'); 
        
        $itemsBySeller = $cart->items->groupBy('product.seller_id');

        try {
            $orders = DB::transaction(function () use ($user, $itemsBySeller, $selectedGateway) {
                $createdOrders = [];
                
                foreach ($itemsBySeller as $sellerId => $items) {
                    $totalPerSeller = $items->sum(function ($item) {
                        return $item->quantity * $item->product->harga;
                    });

                    // Buat Data Pesanan
                    $order = Order::create([
                        'user_id' => $user->id,
                        'seller_id' => $sellerId,
                        'order_number' => 'ORD-' . Str::uuid(),
                        'total_price' => $totalPerSeller,
                        'status' => 'pending',
                        'payment_status' => 'unpaid',
                        'payment_gateway' => $selectedGateway, // Simpan gateway yang dipilih
                    ]);

                    foreach ($items as $item) {
                        if ($item->product->stok < $item->quantity) {
                            throw new \Exception('Stok untuk produk ' . $item->product->nama_produk . ' tidak mencukupi.');
                        }

                        $order->items()->create([
                            'product_id' => $item->product_id,
                            'quantity' => $item->quantity,
                            'price' => $item->product->harga,
                        ]);
                    }
                    $createdOrders[] = $order;
                }

                return $createdOrders;
            });
            
            $mainOrder = $orders[0];

            // ======================================================
            // LOGIKA CABANG BERDASARKAN PAYMENT GATEWAY YANG DIPILIH
            // ======================================================
            
            if ($selectedGateway === 'coinpayments') {
                // -----------------------------
                // PROSES VIA COINPAYMENTS (CRYPTO)
                // -----------------------------
                $invoice = $this->coinPaymentsService->createInvoice($mainOrder);

                if (!$invoice || !isset($invoice['id'])) {
                    throw new \Exception('Gagal membuat tagihan di CoinPayments.');
                }

                $mainOrder->snap_token = $invoice['id']; // Menyimpan ID Invoice CoinPayments
                $mainOrder->save();

                // Kosongkan cart karena sudah jadi order
                $this->clearUserCart($user->id, $mainOrder);

                // Redirect pembeli ke halaman pembayaran CoinPayments (keluar dari Inertia)
                return \Inertia\Inertia::location($invoice['invoiceUrl']);

            } else {
                // -----------------------------
                // PROSES VIA MIDTRANS (IDR)
                // -----------------------------
                Config::$serverKey = config('midtrans.server_key');
                Config::$clientKey = config('midtrans.client_key');
                Config::$isProduction = (bool) config('midtrans.is_production');
                Config::$isSanitized = (bool) config('midtrans.is_sanitized');
                Config::$is3ds = (bool) config('midtrans.is_3ds');

                $midtrans_params = [
                    'transaction_details' => [
                        'order_id' => $mainOrder->order_number,
                        'gross_amount' => (int) $mainOrder->total_price,
                    ],
                    'customer_details' => [
                        'first_name' => $user->nama,
                        'email' => $user->email,
                        'phone' => $user->no_hp,
                    ],
                    'callbacks' => [
                        'finish' => route('orders.show', $mainOrder) . '?status=success',
                        'error' => route('orders.show', $mainOrder) . '?status=error',
                        'unfinish' => route('orders.show', $mainOrder) . '?status=pending',
                    ],
                ];

                $snapToken = Snap::getSnapToken($midtrans_params);
                $mainOrder->snap_token = $snapToken;
                $mainOrder->save();

                // Kosongkan cart karena sudah jadi order
                $this->clearUserCart($user->id, $mainOrder);

                // Arahkan ke halaman pembayaran dengan membawa token Snap Midtrans
                return \Inertia\Inertia::render('buyer/payment', [
                    'snapToken' => $snapToken,
                    'clientKey' => config('midtrans.client_key'),
                    'isProduction' => (bool) config('midtrans.is_production'),
                    'order' => \App\Support\Present::order($mainOrder->load('orderItems.product.primaryImage', 'seller')),
                ]);
            }

        } catch (\Exception $e) {
            return redirect()->route('orders.checkout')->with('error', 'Gagal memproses pesanan: ' . $e->getMessage());
        }
    }

    // Fungsi helper agar tidak mengulang kode penghapusan keranjang
    private function clearUserCart($userId, $order) {
        $cart = Cart::where('user_id', $userId)->first();
        if($cart) {
            $orderedProductIds = $order->items->pluck('product_id');
            $cart->items()->whereIn('product_id', $orderedProductIds)->delete();
        }
    }

    // ======================================================
    // WEBHOOK / CALLBACK UNTUK MIDTRANS
    // ======================================================
    public function midtransCallback(Request $request)
    {
        $notification_payload = $request->all();
        Log::info('Midtrans notification received', $notification_payload);

        $orderId = $notification_payload['order_id'];
        $statusCode = $notification_payload['status_code'];
        $grossAmount = $notification_payload['gross_amount'];
        $signatureKey = $notification_payload['signature_key'];

        $order = Order::where('order_number', $orderId)->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $serverKey = config('midtrans.server_key');
        $signature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        if ($signature !== $signatureKey) {
            Log::error('Invalid Midtrans signature', ['order_id' => $orderId]);
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $transactionStatus = $notification_payload['transaction_status'];
        $fraudStatus = $notification_payload['fraud_status'];

        if ($transactionStatus == 'capture' || $transactionStatus == 'settlement') {
            if ($fraudStatus == 'accept') {
                $this->processSuccessfulPayment($order);
            }
        } else if (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
            $order->update(['payment_status' => 'failed']);
        }

        return response()->json(['message' => 'Midtrans Notification processed successfully']);
    }

    // ======================================================
    // WEBHOOK / IPN UNTUK COINPAYMENTS
    // ======================================================
    public function coinpaymentsCallback(Request $request)
    {
        $payload = $request->getContent();
        Log::info('CoinPayments IPN received', $request->all());

        $hmacHeader = $request->header('HMAC');
        $ipnSecret = env('COINPAYMENTS_IPN_SECRET'); // Buat secret ini di dashboard CoinPayments
        
        $calculatedHmac = hash_hmac('sha512', $payload, $ipnSecret);

        if ($hmacHeader !== $calculatedHmac) {
            Log::error('Invalid CoinPayments IPN Signature');
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $status = intval($request->input('status'));
        $orderNumber = $request->input('invoiceId'); 

        $order = Order::where('order_number', $orderNumber)->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($status >= 100 || $status == 2) {
            // Status >= 100 artinya pembayaran komplit atau selesai
            $this->processSuccessfulPayment($order);
        } else if ($status < 0) {
            // Status minus artinya error/timeout
            $order->update(['payment_status' => 'failed']);
        }

        return response()->json(['message' => 'CoinPayments IPN processed successfully']);
    }

    // ======================================================
    // FUNGSI UTAMA PENYELESAIAN ORDER & BLOCKCHAIN
    // ======================================================
    protected function processSuccessfulPayment(Order $order)
    {
        DB::transaction(function () use ($order) {
            if ($order->payment_status == 'unpaid') {
                $order->update(['payment_status' => 'paid', 'status' => 'processing']);

                foreach ($order->items as $item) {
                    Product::find($item->product_id)->decrement('stok', $item->quantity);
                }

                // Catat sidik jari transaksi ke Base setelah pembayaran berhasil
                $this->hashOrderToBlockchain($order);
            }
        });
    }

    private function hashOrderToBlockchain(Order $order): void
    {
        // Anchor after the payment transaction commits so a chain error never rolls back the payment.
        DB::afterCommit(fn () => $order->anchorOnBase($this->blockchainService));
    }

    public function index()
    {
        $orders = Order::where('user_id', Auth::id())
                        ->with(['seller', 'orderItems.product.primaryImage'])
                        ->latest()
                        ->paginate(10);

        return \Inertia\Inertia::render('buyer/orders/index', [
            'orders' => \App\Support\Present::paginate($orders, fn ($o) => \App\Support\Present::order($o)),
        ]);
    }

    public function show(Order $order)
    {
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }
        $order->load(['seller', 'orderItems.product.primaryImage']);

        return \Inertia\Inertia::render('buyer/orders/show', [
            'order' => \App\Support\Present::order($order),
            'returnStatus' => request('status'),
        ]);
    }
}