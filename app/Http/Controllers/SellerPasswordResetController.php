<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use App\Models\SellerPasswordResetOtp;
use App\Services\SellerOtpService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SellerPasswordResetController extends Controller
{
    public function create(Request $request): \Inertia\Response
    {
        return \Inertia\Inertia::render('seller-auth/forgot-password', [
            'no_hp' => old('no_hp', $request->session()->get('seller_password_reset_no_hp')),
        ]);
    }

    public function store(Request $request, SellerOtpService $otpService): RedirectResponse
    {
        $request->validate([
            'no_hp' => ['required', 'string', 'exists:sellers,no_hp'],
        ]);

        $this->ensureIsNotRateLimited($request);

        $seller = Seller::query()->where('no_hp', '=', (string) $request->input('no_hp'))->firstOrFail();
        $otp = (string) random_int(100000, 999999);

        SellerPasswordResetOtp::query()->where('seller_id', '=', $seller->id)->delete();

        SellerPasswordResetOtp::create([
            'seller_id' => $seller->id,
            'no_hp' => $seller->no_hp,
            'otp_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        $otpService->sendPasswordResetOtp($seller, $otp);

        RateLimiter::hit($this->throttleKey($request), 300);

        $request->session()->put('seller_password_reset_no_hp', $seller->no_hp);

        return redirect()->route('seller.password.reset')->with('status', 'Kode OTP reset password sudah dikirim ke nomor HP seller.');
    }

    public function edit(Request $request): \Inertia\Response
    {
        return \Inertia\Inertia::render('seller-auth/reset-password', [
            'status' => session('status'),
            'no_hp' => old('no_hp', $request->session()->get('seller_password_reset_no_hp')),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'no_hp' => ['required', 'string', 'exists:sellers,no_hp'],
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $seller = Seller::query()->where('no_hp', '=', (string) $request->input('no_hp'))->firstOrFail();
        $otpRecord = SellerPasswordResetOtp::query()->where('seller_id', '=', $seller->id)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (! $otpRecord) {
            throw ValidationException::withMessages([
                'otp' => 'OTP reset password tidak ditemukan atau sudah dipakai.',
            ]);
        }

        if ($otpRecord->expires_at->isPast()) {
            $otpRecord->delete();

            throw ValidationException::withMessages([
                'otp' => 'OTP reset password sudah kedaluwarsa. Silakan minta OTP baru.',
            ]);
        }

        if ($otpRecord->attempts >= 5) {
            $otpRecord->delete();

            throw ValidationException::withMessages([
                'otp' => 'Terlalu banyak percobaan OTP. Silakan minta OTP baru.',
            ]);
        }

        if (! Hash::check($request->string('otp'), $otpRecord->otp_hash)) {
            $otpRecord->increment('attempts');

            throw ValidationException::withMessages([
                'otp' => 'OTP yang dimasukkan tidak valid.',
            ]);
        }

        $seller->forceFill([
            'password' => Hash::make($request->string('password')),
        ])->save();

        $otpRecord->forceFill([
            'verified_at' => now(),
            'consumed_at' => now(),
        ])->save();

        $request->session()->forget('seller_password_reset_no_hp');
        RateLimiter::clear($this->throttleKey($request));

        return redirect()->route('seller.login')->with('status', 'Password seller berhasil direset. Silakan login kembali dengan nomor HP.');
    }

    protected function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'no_hp' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->string('no_hp')).'|'.$request->ip());
    }
}