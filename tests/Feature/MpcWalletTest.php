<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WalletTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MpcWalletTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'blockchain.mpc.enabled' => true,
            'blockchain.mpc.signer_url' => 'http://signer.test',
            'blockchain.mpc.signer_token' => 'secret',
            'blockchain.mpc.wallet_prefix' => 'ampuh-user',
            'blockchain.base.rpc_url' => 'http://rpc.test',
        ]);
    }

    public function test_registration_creates_an_mpc_wallet(): void
    {
        $address = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';
        Http::fake(['signer.test/wallets' => Http::response(['wallet_id' => 'x', 'address' => $address])]);

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'test@example.com')->first();
        $this->assertSame($address, $user->wallet_address);
        $this->assertSame('mpcium', $user->wallet_provider);
        $this->assertSame("ampuh-user-{$user->id}", $user->mpc_wallet_id);

        Http::assertSent(fn (Request $r) => $r->url() === 'http://signer.test/wallets'
            && $r->hasHeader('Authorization', 'Bearer secret')
            && $r['wallet_id'] === "ampuh-user-{$user->id}");
    }

    public function test_send_by_email_resolves_recipient_and_records_transfer(): void
    {
        $sender = User::factory()->create(['wallet_address' => '0x' . str_repeat('a', 40), 'mpc_wallet_id' => 'ampuh-user-1']);
        $recipient = User::factory()->create(['wallet_address' => '0x' . str_repeat('B', 40)]);
        $txHash = '0x' . str_repeat('1', 64);

        Http::fake([
            'rpc.test' => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => '0xde0b6b3a7640000']), // 1 ETH
            'signer.test/send' => Http::response(['tx_hash' => $txHash]),
        ]);

        $this->actingAs($sender)
            ->post(route('wallet.send'), ['recipient' => $recipient->email, 'amount' => '0.25'])
            ->assertSessionHas('success');

        Http::assertSent(fn (Request $r) => $r->url() === 'http://signer.test/send'
            && $r['wallet_id'] === 'ampuh-user-1'
            && $r['to'] === strtolower($recipient->wallet_address)
            && $r['value_wei'] === '250000000000000000');

        $transfer = WalletTransfer::sole();
        $this->assertSame($recipient->id, $transfer->recipient_id);
        $this->assertSame($txHash, $transfer->tx_hash);
        $this->assertSame('confirmed', $transfer->status);
    }

    public function test_send_rejects_unknown_email(): void
    {
        $sender = User::factory()->create(['wallet_address' => '0x' . str_repeat('a', 40)]);
        Http::fake();

        $this->actingAs($sender)
            ->post(route('wallet.send'), ['recipient' => 'nobody@example.com', 'amount' => '0.1'])
            ->assertSessionHasErrors('recipient');

        Http::assertNothingSent();
    }
}
