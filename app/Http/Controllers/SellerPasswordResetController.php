<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use App\Models\SellerPasswordResetOtp;
use App\Services\SellerOtpService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SellerPasswordResetController extends Controller
{
    /** OTPs one seller account (or one destination email) can be sent per window. */
    private const ACCOUNT_ISSUE_LIMIT = 5;

    /** Wrong OTP guesses allowed per seller account per window, across all OTPs. */
    private const ACCOUNT_GUESS_LIMIT = 10;

    private const ACCOUNT_WINDOW = 3600;

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

        if (! $seller->email) {
            throw ValidationException::withMessages([
                'no_hp' => 'Akun ini belum punya email untuk menerima kode OTP. Hubungi admin AMPUH untuk reset password.',
            ]);
        }

        // Per-account and per-destination budgets: neither rotating IPs nor re-spelling the
        // address buys more OTPs for the same seller or the same inbox.
        $accountKeys = [
            $this->sellerIssueKey($seller),
            'seller-otp-issue-dest:'.$otpService->normalizeEmail((string) $seller->email),
        ];
        foreach ($accountKeys as $key) {
            $this->ensureKeyNotLimited($request, $key, self::ACCOUNT_ISSUE_LIMIT, 'no_hp');
        }

        $otp = (string) random_int(100000, 999999);

        SellerPasswordResetOtp::query()->where('seller_id', '=', $seller->id)->delete();

        SellerPasswordResetOtp::create([
            'seller_id' => $seller->id,
            'no_hp' => $seller->no_hp,
            'otp_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        RateLimiter::hit($this->throttleKey($request), 300);
        foreach ($accountKeys as $key) {
            RateLimiter::hit($key, self::ACCOUNT_WINDOW);
        }

        try {
            $otpService->sendPasswordResetOtp($seller, $otp);
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim OTP reset password seller', ['seller_id' => $seller->id, 'error' => $e->getMessage()]);
            SellerPasswordResetOtp::query()->where('seller_id', '=', $seller->id)->delete();

            throw ValidationException::withMessages([
                'no_hp' => 'Kode OTP gagal dikirim. Coba lagi beberapa saat lagi.',
            ]);
        }

        $request->session()->put('seller_password_reset_no_hp', $seller->no_hp);

        $redirect = redirect()->route('seller.password.reset')->with('status', 'Kode OTP reset password sudah dikirim ke '.$this->maskEmail((string) $seller->email).'.');

        // No mail server in local development: show the code instead of making people dig through the log.
        // Also require a loopback client, so a misconfigured APP_ENV=local server never shows it.
        if ($otpService->logsOnly() && app()->isLocal() && in_array($request->ip(), ['127.0.0.1', '::1'], true)) {
            $redirect->with('dev_otp', $otp);
        }

        return $redirect;
    }

    public function edit(Request $request): \Inertia\Response
    {
        return \Inertia\Inertia::render('seller-auth/reset-password', [
            'status' => session('status'),
            'dev_otp' => session('dev_otp'),
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

        // Wrong guesses are counted per seller across every OTP issued, so requesting a
        // fresh code does not reset the budget.
        $this->ensureKeyNotLimited($request, $this->sellerVerifyKey($seller), self::ACCOUNT_GUESS_LIMIT, 'otp');

        // Claim an attempt atomically before the (slow) hash check, so concurrent
        // submissions cannot all slip past the per-OTP limit.
        $claimed = SellerPasswordResetOtp::query()
            ->whereKey($otpRecord->id)
            ->where('attempts', '<', 5)
            ->increment('attempts');

        if ($claimed === 0) {
            $otpRecord->delete();

            throw ValidationException::withMessages([
                'otp' => 'Terlalu banyak percobaan OTP. Silakan minta OTP baru.',
            ]);
        }

        if (! Hash::check($request->string('otp'), $otpRecord->otp_hash)) {
            RateLimiter::hit($this->sellerVerifyKey($seller), self::ACCOUNT_WINDOW);

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
        RateLimiter::clear($this->sellerVerifyKey($seller));

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

    protected function ensureKeyNotLimited(Request $request, string $key, int $max, string $field): void
    {
        if (! RateLimiter::tooManyAttempts($key, $max)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            $field => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * toko@example.com -> t***@example.com, so the reset page does not disclose the address.
     */
    protected function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).'***@'.$domain;
    }

    protected function sellerIssueKey(Seller $seller): string
    {
        return 'seller-otp-issue:'.$seller->id;
    }

    protected function sellerVerifyKey(Seller $seller): string
    {
        return 'seller-otp-verify:'.$seller->id;
    }
}