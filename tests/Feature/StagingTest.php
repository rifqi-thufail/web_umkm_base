<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StagingTest extends TestCase
{
    use RefreshDatabase;

    private function asStaging(string $key = 'secret-key'): void
    {
        $this->app['env'] = 'staging';
        config(['app.staging_access_key' => $key]);
    }

    public function test_staging_refuses_visitors_without_the_key(): void
    {
        $this->asStaging();

        $this->get('/')->assertForbidden();
        $this->get('/?access=wrong')->assertForbidden();
    }

    public function test_access_link_sets_cookie_and_strips_the_key(): void
    {
        $this->asStaging();

        $response = $this->get('/?access=secret-key&category=makanan');
        $response->assertRedirect(url('/').'?category=makanan');
        $response->assertCookie('staging_access');

        $this->withCookie('staging_access', hash('sha256', 'secret-key'))
            ->get('/')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_staging_without_a_key_fails_closed(): void
    {
        $this->asStaging('');

        $this->get('/')->assertStatus(503);
    }

    public function test_webhooks_bypass_the_gate(): void
    {
        $this->asStaging();

        $this->assertNotEquals(403, $this->postJson('/api/midtrans/callback', [])->status());
        $this->get('/up')->assertOk();
    }

    public function test_production_has_no_gate_and_is_indexable(): void
    {
        $this->app['env'] = 'production';
        config(['app.staging_access_key' => 'secret-key']);

        $this->get('/')->assertOk()->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_staging_forces_payment_sandbox_and_refuses_base_mainnet(): void
    {
        $this->app['env'] = 'staging';
        config([
            'midtrans.is_production' => true,
            'doku.is_production' => true,
            'blockchain.base.enabled' => true,
            'blockchain.base.chain_id' => 8453,
        ]);

        (new AppServiceProvider($this->app))->boot();

        $this->assertFalse(config('midtrans.is_production'));
        $this->assertFalse(config('doku.is_production'));
        $this->assertFalse(config('blockchain.base.enabled'));
    }
}
