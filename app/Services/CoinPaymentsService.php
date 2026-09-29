<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CoinPaymentsService
{
    protected $apiUrl;
    protected $clientId;
    protected $clientSecret;

    public function __construct()
    {
        $this->apiUrl = env('COINPAYMENTS_API_URL');
        $this->clientId = env('COINPAYMENTS_CLIENT_ID');
        $this->clientSecret = env('COINPAYMENTS_CLIENT_SECRET');
    }

    /**
     * Generate API Signature sesuai dokumentasi terbaru
     */
    private function generateSignature($method, $fullUrl, $payload, $timestamp)
    {
        // 1. Tambahkan \xEF\xBB\xBF (ini adalah versi PHP untuk \ufeff atau Byte Order Mark)
        $bom = "\xEF\xBB\xBF";
        
        // 2. Gabungkan string sesuai urutan: BOM + Method + URL Lengkap + Client ID + Timestamp + Payload JSON
        $stringToSign = $bom . $method . $fullUrl . $this->clientId . $timestamp . $payload;
        
        // 3. Buat HMAC SHA256 menggunakan Client Secret, lalu konversi ke Base64
        $signature = base64_encode(hash_hmac('sha256', $stringToSign, $this->clientSecret, true));
        
        return $signature;
    }

    /**
     * Membuat Invoice / Tagihan Pembayaran
     */
    public function createInvoice($order)
    {
        $endpoint = '/api/v1/invoices'; // Pastikan ini endpoint invoice yang benar di docs
        $url = $this->apiUrl . $endpoint;
        
        // Buat format waktu ISO-8601 tanpa milidetik dan tanpa zona waktu (Z)
        // Setara dengan: new Date().toISOString().split('.')[0] di Node.js
        $timestamp = gmdate('Y-m-d\TH:i:s'); 

        // Data pesanan yang akan dikirim
        $params = [
            'invoiceId' => $order->order_number,
            'currencyId' => 2, // ID mata uang di CoinPayments (misal BTC=1, USDT=2, cek di dashboard Anda)
            'amount' => $order->total_price,
            'displayValue' => $order->total_price,
            'buyerEmail' => $order->user->email,
        ];

        // Ubah array ke JSON string
        $payload = json_encode($params);
        
        // Generate Signature (Perhatikan: kita mengirimkan $url lengkap, bukan cuma $endpoint)
        $signature = $this->generateSignature('POST', $url, $payload, $timestamp);

        // Kirim HTTP Request ke CoinPayments
        $response = Http::withHeaders([
            'X-CoinPayments-Client' => $this->clientId,
            'X-CoinPayments-Timestamp' => $timestamp,
            'X-CoinPayments-Signature' => $signature,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($url, $params);

        if ($response->successful()) {
            return $response->json();
        }

        Log::error('CoinPayments Error', [
            'status' => $response->status(),
            'response' => $response->json(),
            'sent_payload' => $payload,
            'string_to_sign_debug' => "BOM+POST+{$url}+{$this->clientId}+{$timestamp}+{$payload}"
        ]);
        
        return null;
    }
}