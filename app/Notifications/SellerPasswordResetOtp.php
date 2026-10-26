<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent synchronously: shared hosting has no queue worker, and the controller
 * needs to know right away whether the code went out.
 */
class SellerPasswordResetOtp extends Notification
{
    public function __construct(public readonly string $otp)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kode OTP reset password seller AMPUH')
            ->greeting('Halo, '.$notifiable->nama_koperasi)
            ->line('Gunakan kode berikut untuk mengatur ulang password toko Anda:')
            ->line('**'.$this->otp.'**')
            ->line('Kode berlaku 10 menit. Jangan berikan kode ini ke siapa pun.')
            ->line('Abaikan email ini jika Anda tidak meminta reset password.');
    }
}
