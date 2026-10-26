<?php

namespace App\Services;

use App\Models\Seller;
use App\Notifications\SellerPasswordResetOtp;
use RuntimeException;

/**
 * Sends the seller password-reset OTP to the seller's email through the default mailer.
 */
class SellerOtpService
{
    public function sendPasswordResetOtp(Seller $seller, string $otp): void
    {
        if (! $seller->email) {
            throw new RuntimeException('Seller has no email address.');
        }

        $seller->notify(new SellerPasswordResetOtp($otp));
    }

    /**
     * True when mail only goes to the log, i.e. no seller can receive the OTP.
     */
    public function logsOnly(): bool
    {
        return config('mail.default') === 'log';
    }

    public function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
