<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Transaction;

/**
 * Midtrans Snap: one transaction per checkout (order_id = the checkout's payment reference),
 * charged for every seller's order in it.
 */
class MidtransService
{
    public function configured(): bool
    {
        return (bool) config('midtrans.server_key') && (bool) config('midtrans.client_key');
    }

    private function configure(): void
    {
        Config::$serverKey = (string) config('midtrans.server_key');
        Config::$clientKey = (string) config('midtrans.client_key');
        Config::$isProduction = (bool) config('midtrans.is_production');
        Config::$isSanitized = (bool) config('midtrans.is_sanitized');
        Config::$is3ds = (bool) config('midtrans.is_3ds');
    }

    /** @param Collection<int, Order> $orders */
    public function snapToken(string $reference, Collection $orders, User $user): string
    {
        $this->configure();

        $customer = ['first_name' => mb_substr((string) $user->nama, 0, 50), 'email' => $user->email];
        if ($user->no_hp && $user->no_hp !== '-') {
            $customer['phone'] = $user->no_hp;
        }

        $finish = route('orders.show', $orders->first());

        return Snap::getSnapToken([
            'transaction_details' => [
                'order_id' => $reference,
                'gross_amount' => self::total($orders),
            ],
            'customer_details' => $customer,
            'expiry' => ['unit' => 'hour', 'duration' => Order::PAYMENT_WINDOW_HOURS],
            'callbacks' => ['finish' => $finish],
        ]);
    }

    /**
     * The transaction as Midtrans sees it, or null if no payment was ever started for this reference.
     */
    public function status(string $reference): ?array
    {
        $this->configure();

        try {
            return (array) Transaction::status($reference);
        } catch (\Exception $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /** Signature Midtrans puts on notifications and status responses. */
    public function validSignature(array $payload): bool
    {
        $serverKey = (string) config('midtrans.server_key');
        if ($serverKey === '') {
            return false;
        }

        $expected = hash('sha512', ($payload['order_id'] ?? '') . ($payload['status_code'] ?? '') . ($payload['gross_amount'] ?? '') . $serverKey);

        return hash_equals($expected, (string) ($payload['signature_key'] ?? ''));
    }

    /** @param Collection<int, Order> $orders */
    public static function total(Collection $orders): int
    {
        return (int) $orders->sum(fn (Order $o) => (int) round((float) $o->total_price));
    }

    /**
     * paid | failed | expired | null (still open, or nothing we act on).
     * A denied or failed attempt is not final: Snap lets the buyer try another card or method under
     * the same order id until it expires. https://docs.midtrans.com/docs/https-notification-webhooks#status-definition
     */
    public static function outcome(array $payload): ?string
    {
        $status = $payload['transaction_status'] ?? null;
        $fraud = $payload['fraud_status'] ?? 'accept';

        return match ($status) {
            'settlement' => 'paid',
            'capture' => $fraud === 'accept' ? 'paid' : null,
            'cancel' => 'failed',
            'expire' => 'expired',
            default => null,
        };
    }
}
