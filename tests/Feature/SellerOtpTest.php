<?php

namespace Tests\Feature;

use App\Models\Seller;
use App\Models\SellerPasswordResetOtp as OtpRecord;
use App\Notifications\SellerPasswordResetOtp;
use App\Services\SellerOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SellerOtpTest extends TestCase
{
    use RefreshDatabase;

    private function seller(): Seller
    {
        return Seller::create([
            'nama_koperasi' => 'Koperasi Uji', 'password' => 'password', 'kecamatan' => 'Tamalanrea',
            'desa_kelurahan' => 'Tamalanrea', 'jenis_usaha' => 'Pangan', 'no_hp' => '0812-3456-7890', 'email' => 'toko@example.com',
        ]);
    }

    private function sentOtp(Seller $seller): ?string
    {
        $otp = null;
        Notification::assertSentTo($seller, SellerPasswordResetOtp::class, function (SellerPasswordResetOtp $n) use (&$otp) {
            $otp = $n->otp;

            return true;
        });

        return $otp;
    }

    public function test_otp_is_emailed_and_seller_can_reset_password(): void
    {
        Notification::fake();
        $seller = $this->seller();

        $this->post(route('seller.password.email'), ['no_hp' => $seller->no_hp])
            ->assertRedirect(route('seller.password.reset'))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 't***@example.com'))
            ->assertSessionMissing('dev_otp');

        $otp = $this->sentOtp($seller);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);

        $mail = (new SellerPasswordResetOtp($otp))->toMail($seller);
        $this->assertStringContainsString($otp, implode(' ', $mail->introLines));

        $this->post(route('seller.password.store'), [
            'no_hp' => $seller->no_hp, 'otp' => $otp, 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('seller.login'));

        $this->assertTrue(Hash::check('new-password-123', $seller->fresh()->password));
    }

    public function test_seller_without_email_gets_an_error_and_no_otp(): void
    {
        Notification::fake();
        $seller = $this->seller();
        $seller->forceFill(['email' => null])->save();

        $this->post(route('seller.password.email'), ['no_hp' => $seller->no_hp])->assertSessionHasErrors('no_hp');

        Notification::assertNothingSent();
        $this->assertSame(0, OtpRecord::count());
    }

    public function test_mail_failure_shows_error_and_leaves_no_usable_otp(): void
    {
        $seller = $this->seller();
        $this->mock(SellerOtpService::class, function ($mock) {
            $mock->shouldReceive('normalizeEmail')->andReturnUsing(fn ($e) => strtolower($e));
            $mock->shouldReceive('sendPasswordResetOtp')->andThrow(new \RuntimeException('SMTP down'));
        });

        $this->post(route('seller.password.email'), ['no_hp' => $seller->no_hp])->assertSessionHasErrors('no_hp');

        $this->assertSame(0, OtpRecord::count());
    }

    public function test_local_log_driver_shows_code_on_reset_page(): void
    {
        config(['mail.default' => 'log']);
        $this->app['env'] = 'local';
        // CSRF is only skipped automatically in the testing environment.
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $seller = $this->seller();

        $response = $this->post(route('seller.password.email'), ['no_hp' => $seller->no_hp]);

        $otp = session('dev_otp');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);
        $this->assertTrue(Hash::check($otp, OtpRecord::first()->otp_hash));
        $response->assertRedirect(route('seller.password.reset'));
    }

    public function test_otp_issue_limit_is_per_account_not_per_ip(): void
    {
        Notification::fake();
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
        Notification::fake();
        $seller = $this->seller();
        $guess = fn () => $this->post(route('seller.password.store'), [
            'no_hp' => $seller->no_hp, 'otp' => '000000', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ]);

        // Two OTPs, five wrong guesses each: the account budget (10) is spent.
        foreach ([1, 2] as $round) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.1.0.$round"])
                ->post(route('seller.password.email'), ['no_hp' => $seller->no_hp]);
            OtpRecord::query()->update(['otp_hash' => Hash::make('123456')]);
            foreach (range(1, 5) as $i) {
                $guess()->assertSessionHasErrors('otp');
            }
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.1.0.3'])
            ->post(route('seller.password.email'), ['no_hp' => $seller->no_hp]);
        OtpRecord::query()->update(['otp_hash' => Hash::make('123456')]);

        // Even the correct code is refused until the window passes.
        $this->post(route('seller.password.store'), [
            'no_hp' => $seller->no_hp, 'otp' => '123456', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors('otp');
        $this->assertFalse(Hash::check('new-password-123', $seller->fresh()->password));
    }
}
