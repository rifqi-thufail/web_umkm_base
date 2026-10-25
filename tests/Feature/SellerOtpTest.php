<?php

namespace Tests\Feature;

use App\Models\Seller;
use App\Models\SellerPasswordResetOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SellerOtpTest extends TestCase
{
    use RefreshDatabase;

    private function seller(): Seller
    {
        return Seller::create([
            'nama_koperasi' => 'Koperasi Uji', 'password' => 'password', 'kecamatan' => 'Tamalanrea',
            'desa_kelurahan' => 'Tamalanrea', 'jenis_usaha' => 'Pangan', 'no_hp' => '0812-3456-7890',
        ]);
    }

    private function sentOtp(Request $request): ?string
    {
        preg_match('/\b(\d{6})\b/', $request['message'] ?? $request['Body'] ?? '', $m);

        return $m[1] ?? null;
    }

    public function test_zenziva_sends_otp_and_seller_can_reset_password(): void
    {
        config(['services.sms.driver' => 'zenziva', 'services.sms.zenziva.userkey' => 'u', 'services.sms.zenziva.passkey' => 'p']);
        Http::fake(['console.zenziva.net/*' => Http::response(['messageId' => 1, 'to' => '6281234567890', 'status' => '1', 'text' => 'Success'])]);
        $seller = $this->seller();

        $this->post(route('seller.password.email'), ['no_hp' => $seller->no_hp])
            ->assertRedirect(route('seller.password.reset'))
            ->assertSessionMissing('dev_otp');

        $otp = null;
        Http::assertSent(function (Request $request) use (&$otp) {
            $otp = $this->sentOtp($request);

            return $request['to'] === '6281234567890' && $request['userkey'] === 'u';
        });

        $this->post(route('seller.password.store'), [
            'no_hp' => $seller->no_hp, 'otp' => $otp, 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('seller.login'));

        $this->assertTrue(Hash::check('new-password-123', $seller->fresh()->password));
    }

    public function test_fonnte_and_twilio_payloads(): void
    {
        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => true]),
            'api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201),
        ]);
        $seller = $this->seller();

        config(['services.sms.driver' => 'fonnte', 'services.sms.token' => 'device-token']);
        $this->post(route('seller.password.email'), ['no_hp' => $seller->no_hp])->assertSessionHasNoErrors();
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'fonnte') && $r->hasHeader('Authorization', 'device-token') && $r['target'] === '6281234567890');

        config(['services.sms.driver' => 'twilio', 'services.sms.twilio.sid' => 'AC1', 'services.sms.twilio.token' => 't', 'services.sms.twilio.from' => '+15550001']);
        $this->post(route('seller.password.email'), ['no_hp' => $seller->no_hp])->assertSessionHasNoErrors();
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'Accounts/AC1/Messages.json') && $r['To'] === '+6281234567890');
    }

    public function test_gateway_failure_shows_error_and_leaves_no_usable_otp(): void
    {
        config(['services.sms.driver' => 'fonnte', 'services.sms.token' => 'x']);
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => false, 'reason' => 'device disconnected'])]);
        $seller = $this->seller();

        $this->post(route('seller.password.email'), ['no_hp' => $seller->no_hp])->assertSessionHasErrors('no_hp');

        $this->assertSame(0, SellerPasswordResetOtp::count());
    }

    public function test_local_log_driver_shows_code_on_reset_page(): void
    {
        config(['services.sms.driver' => 'log']);
        $this->app['env'] = 'local';
        // CSRF is only skipped automatically in the testing environment.
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $seller = $this->seller();

        $response = $this->post(route('seller.password.email'), ['no_hp' => $seller->no_hp]);

        $otp = session('dev_otp');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);
        $this->assertTrue(Hash::check($otp, SellerPasswordResetOtp::first()->otp_hash));
        $response->assertRedirect(route('seller.password.reset'));
    }

    public function test_otp_issue_limit_is_per_account_not_per_ip(): void
    {
        config(['services.sms.driver' => 'log']);
        $seller = $this->seller();

        foreach (range(1, 5) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.$i"])
                ->post(route('seller.password.email'), ['no_hp' => $seller->no_hp])
                ->assertSessionHasNoErrors();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->post(route('seller.password.email'), ['no_hp' => $seller->no_hp])
            ->assertSessionHasErrors('no_hp');
    }

    public function test_wrong_guess_budget_survives_new_otps(): void
    {
        config(['services.sms.driver' => 'log']);
        $seller = $this->seller();
        $guess = fn () => $this->post(route('seller.password.store'), [
            'no_hp' => $seller->no_hp, 'otp' => '000000', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ]);

        // Two OTPs, five wrong guesses each: the account budget (10) is spent.
        foreach ([1, 2] as $round) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.1.0.$round"])
                ->post(route('seller.password.email'), ['no_hp' => $seller->no_hp]);
            SellerPasswordResetOtp::query()->update(['otp_hash' => Hash::make('123456')]);
            foreach (range(1, 5) as $i) {
                $guess()->assertSessionHasErrors('otp');
            }
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.1.0.3'])
            ->post(route('seller.password.email'), ['no_hp' => $seller->no_hp]);
        SellerPasswordResetOtp::query()->update(['otp_hash' => Hash::make('123456')]);

        // Even the correct code is refused until the window passes.
        $this->post(route('seller.password.store'), [
            'no_hp' => $seller->no_hp, 'otp' => '123456', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors('otp');
        $this->assertFalse(Hash::check('new-password-123', $seller->fresh()->password));
    }
}
