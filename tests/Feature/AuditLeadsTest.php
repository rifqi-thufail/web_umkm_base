<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuditLeadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_ignores_request_host(): void
    {
        config(['app.url' => 'https://ampuh.test']);
        Notification::fake();
        $user = User::factory()->create();

        $this->withHeader('Host', 'attacker.test')->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($n) use ($user) {
            $url = $n->toMail($user)->actionUrl;

            return str_starts_with($url, 'https://ampuh.test/reset-password/');
        });
    }

    public function test_help_form_is_rate_limited(): void
    {
        Mail::fake();
        foreach (range(1, 5) as $i) {
            $this->post(route('help.store'), ['gmail' => 'a@example.test', 'pesan' => 'Butuh bantuan pesanan'])->assertRedirect();
        }
        $this->post(route('help.store'), ['gmail' => 'a@example.test', 'pesan' => 'Butuh bantuan pesanan'])->assertStatus(429);
    }
}
