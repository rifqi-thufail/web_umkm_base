<?php

namespace App\Services;

use App\Models\Seller;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends the seller password-reset OTP through the gateway named by SMS_DRIVER:
 * zenziva (SMS), fonnte (WhatsApp), twilio (SMS), generic (JSON POST) or log.
 */
class SellerOtpService
{
    public function sendPasswordResetOtp(Seller $seller, string $otp): void
    {
        $message = sprintf('Kode OTP reset password seller AMPUH: %s. Berlaku 10 menit. Jangan berikan kode ini ke siapa pun.', $otp);
        $to = $this->normalizePhone((string) $seller->no_hp);

        match ($this->driver()) {
            'zenziva' => $this->sendZenziva($to, $message),
            'fonnte' => $this->sendFonnte($to, $message),
            'twilio' => $this->sendTwilio($to, $message),
            'generic' => $this->sendGeneric($to, $message),
            default => Log::warning('SMS gateway belum dikonfigurasi. OTP reset password seller dicatat di log.', [
                'seller_id' => $seller->id,
                'no_hp' => $to,
                'otp' => $otp,
            ]),
        };
    }

    public function driver(): string
    {
        $driver = config('services.sms.driver');

        // Older .env files only set SMS_GATEWAY_URL.
        if (! $driver) {
            return config('services.sms.gateway_url') ? 'generic' : 'log';
        }

        return strtolower($driver);
    }

    /**
     * True when OTPs only go to the log, i.e. no seller can receive them.
     */
    public function logsOnly(): bool
    {
        return $this->driver() === 'log';
    }

    /**
     * 0812-3456-789 / +62 812 3456 789 / 812... -> 628123456789
     */
    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62' . $digits;
        }

        return $digits;
    }

    protected function sendZenziva(string $to, string $message): void
    {
        $response = $this->http()->asForm()->post(config('services.sms.zenziva.url'), [
            'userkey' => config('services.sms.zenziva.userkey'),
            'passkey' => config('services.sms.zenziva.passkey'),
            'to' => $to,
            'message' => $message,
        ])->throw();

        if ((string) $response->json('status') !== '1') {
            throw new RuntimeException('Zenziva: ' . ($response->json('text') ?? $response->body()));
        }
    }

    protected function sendFonnte(string $to, string $message): void
    {
        $response = $this->http()
            ->withHeaders(['Authorization' => (string) config('services.sms.token')])
            ->asForm()
            ->post(config('services.sms.fonnte.url'), [
                'target' => $to,
                'message' => $message,
                'countryCode' => '62',
            ])->throw();

        if ($response->json('status') !== true) {
            throw new RuntimeException('Fonnte: ' . ($response->json('reason') ?? $response->body()));
        }
    }

    protected function sendTwilio(string $to, string $message): void
    {
        $sid = config('services.sms.twilio.sid');

        $this->http()
            ->withBasicAuth($sid, (string) config('services.sms.twilio.token'))
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To' => '+' . $to,
                'From' => config('services.sms.twilio.from'),
                'Body' => $message,
            ])->throw();
    }

    protected function sendGeneric(string $to, string $message): void
    {
        $request = $this->http()->asJson();

        if ($token = config('services.sms.token')) {
            $request = $request->withToken($token);
        }

        $request->post(config('services.sms.gateway_url'), [
            'to' => $to,
            'message' => $message,
            'sender' => config('services.sms.sender_id'),
            'type' => 'otp',
        ])->throw();
    }

    protected function http(): PendingRequest
    {
        return Http::timeout(15)->acceptJson();
    }
}
