<?php

namespace App\Services;

use App\Models\Seller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SellerOtpService
{
    public function sendPasswordResetOtp(Seller $seller, string $otp): void
    {
        $message = sprintf('Kode OTP reset password seller Anda: %s. Berlaku 10 menit.', $otp);
        $gatewayUrl = config('services.sms.gateway_url');

        if ($gatewayUrl) {
            $request = Http::asJson()->timeout(15);

            if ($token = config('services.sms.token')) {
                $request = $request->withToken($token);
            }

            $response = $request->post($gatewayUrl, [
                'to' => $seller->no_hp,
                'message' => $message,
                'sender' => config('services.sms.sender_id'),
                'type' => 'otp',
            ]);

            $response->throw();

            return;
        }

        Log::warning('SMS gateway belum dikonfigurasi. OTP reset password seller dicatat di log.', [
            'seller_id' => $seller->id,
            'no_hp' => $seller->no_hp,
            'otp' => $otp,
        ]);
    }
}