<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * CoinPayments API v2: hosted invoices priced in IDR, paid in crypto.
 * https://docs.coinpayments.net/api/invoices
 *
 * Requests and webhooks are signed with HMAC-SHA256 over
 * BOM + method + full URL + client id + timestamp + raw body, keyed with the client secret.
 */
class CoinPaymentsService
{
    private const BOM = "\xEF\xBB\xBF";

    public function configured(): bool
    {
        return (bool) config('services.coinpayments.client_id') && (bool) config('services.coinpayments.client_secret');
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array{id: string, checkout_url: string}
     */
    public function createInvoice(string $reference, Collection $orders, User $user): array
    {
        $total = (string) MidtransService::total($orders);
        $orders->loadMissing('orderItems.product');

        $items = $orders->flatMap(fn (Order $o) => $o->orderItems->map(fn ($item) => [
            'name' => mb_substr($item->product?->nama_produk ?? 'Produk', 0, 100),
            'quantity' => ['value' => (int) $item->quantity, 'type' => 2],
            'amount' => (string) ((int) round((float) $item->price) * (int) $item->quantity),
        ]))->values()->all();

        $body = [
            'currency' => 'IDR',
            'invoiceId' => $reference,
            'items' => $items,
            'amount' => ['breakdown' => ['subtotal' => $total], 'total' => $total],
            'description' => 'AMPUH ' . $reference,
            'buyer' => ['emailAddress' => $user->email],
            'successUrl' => route('orders.show', $orders->first()),
            'cancelUrl' => route('orders.show', $orders->first()),
        ];

        // CoinPayments only calls public HTTPS URLs; elsewhere the order page's status check settles it.
        if (str_starts_with($this->webhookUrl(), 'https://')) {
            $body['webhooks'] = [[
                'notificationsUrl' => $this->webhookUrl(),
                'notifications' => ['invoiceCompleted', 'invoicePaid', 'invoiceCancelled', 'invoiceTimedOut'],
            ]];
        }

        $invoice = $this->request('POST', '/merchant/invoices', $body)['invoices'][0] ?? null;
        if (! isset($invoice['id'])) {
            throw new RuntimeException('CoinPayments did not return an invoice id');
        }

        return ['id' => $invoice['id'], 'checkout_url' => $invoice['checkoutLink'] ?? self::checkoutUrl($invoice['id'])];
    }

    public function invoice(string $invoiceId): array
    {
        return $this->request('GET', '/merchant/invoices/' . rawurlencode($invoiceId));
    }

    public static function checkoutUrl(string $invoiceId): string
    {
        return 'https://a-checkout.coinpayments.net/checkout/?invoice-id=' . rawurlencode($invoiceId);
    }

    /** The exact URL registered with each invoice; signatures are computed over it. */
    public function webhookUrl(): string
    {
        return rtrim((string) config('app.url'), '/') . '/api/coinpayments/ipn';
    }

    public function validWebhook(Request $request): bool
    {
        $clientId = (string) config('services.coinpayments.client_id');
        $secret = (string) config('services.coinpayments.client_secret');
        if ($clientId === '' || $secret === '') {
            return false;
        }

        $timestamp = (string) $request->header('X-CoinPayments-Timestamp');
        $sentAt = strtotime($timestamp . 'Z');
        if (! $sentAt || abs(time() - $sentAt) > 300) {
            return false;
        }

        if (! hash_equals($clientId, (string) $request->header('X-CoinPayments-Client'))) {
            return false;
        }

        $expected = $this->sign('POST', $this->webhookUrl(), $clientId, $timestamp, $request->getContent());

        return hash_equals($expected, (string) $request->header('X-CoinPayments-Signature'));
    }

    /** paid | failed | expired | null. Only "completed" means the funds reached the merchant. */
    public static function outcome(array $invoice): ?string
    {
        return match (strtolower((string) ($invoice['status'] ?? ''))) {
            'completed' => 'paid',
            'cancelled', 'deleted' => 'failed',
            'timedout' => 'expired',
            default => null,
        };
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        $url = rtrim((string) (config('services.coinpayments.api_url') ?: 'https://a-api.coinpayments.net'), '/') . '/api/v2' . $path;
        $clientId = (string) config('services.coinpayments.client_id');
        $timestamp = gmdate('Y-m-d\TH:i:s');
        $payload = $body === null ? '' : json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $response = Http::timeout(20)
            ->withHeaders([
                'X-CoinPayments-Client' => $clientId,
                'X-CoinPayments-Timestamp' => $timestamp,
                'X-CoinPayments-Signature' => $this->sign($method, $url, $clientId, $timestamp, $payload),
                'Accept' => 'application/json',
            ])
            ->withBody($payload, 'application/json')
            ->send($method, $url);

        if (! $response->successful()) {
            Log::error('CoinPayments API error', ['method' => $method, 'path' => $path, 'status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);
            throw new RuntimeException('CoinPayments API error ' . $response->status());
        }

        return $response->json() ?? [];
    }

    private function sign(string $method, string $url, string $clientId, string $timestamp, string $payload): string
    {
        return base64_encode(hash_hmac('sha256', self::BOM . $method . $url . $clientId . $timestamp . $payload, (string) config('services.coinpayments.client_secret'), true));
    }
}
