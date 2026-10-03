<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WalletTransfer;
use App\Services\Mpc\MpcAuthorizer;
use App\Services\MpcWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MpcWalletTest extends TestCase
{
    use RefreshDatabase;

    private const AUTHORIZER_SEED = '2222222222222222222222222222222222222222222222222222222222222222';

    private string $initiatorSig;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'blockchain.mpc.enabled' => true,
            'blockchain.mpc.signer_url' => 'http://signer.test',
            'blockchain.mpc.signer_token' => 'secret',
            'blockchain.mpc.authorizer_key' => self::AUTHORIZER_SEED,
            'blockchain.mpc.authorizer_id' => 'ampuh-laravel',
            'blockchain.mpc.wallet_prefix' => 'ampuh-user',
            'blockchain.mpc.limits' => ['per_transfer_eth' => '1', 'daily_eth' => '2', 'max_fee_gwei' => '50'],
            'blockchain.mpc.confirmations' => 1,
            'blockchain.base.rpc_url' => 'http://rpc.test',
            'blockchain.base.chain_id' => 84532,
        ]);

        $this->initiatorSig = base64_encode(str_repeat("\x07", 64));
    }

    public function test_registration_creates_an_mpc_wallet_with_an_authorized_keygen(): void
    {
        $address = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';
        Http::fake([
            'signer.test/v1/keygen/prepare' => Http::response(['wallet_id' => 'x', 'initiator_signature' => $this->initiatorSig]),
            'signer.test/v1/keygen' => fn (Request $r) => Http::response(['wallet_id' => $r['wallet_id'], 'address' => $address]),
        ]);

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

        $walletId = "ampuh-user-{$user->id}";
        Http::assertSent(fn (Request $r) => $r->url() === 'http://signer.test/v1/keygen'
            && $r->hasHeader('Authorization', 'Bearer secret')
            && $r['wallet_id'] === $walletId
            && $this->verifiesAuthorizer($r['authorizer_signature'], MpcAuthorizer::authorizerRaw($walletId, $walletId, base64_decode($this->initiatorSig))));
    }

    public function test_send_by_email_cosigns_exactly_the_requested_transfer(): void
    {
        [$sender, $recipient] = $this->users();
        $txHash = '0x' . str_repeat('1', 64);
        $this->fakeCluster(submit: Http::response(['tx_hash' => $txHash]));

        $this->actingAs($sender)
            ->post(route('wallet.send'), ['recipient' => $recipient->email, 'amount' => '0.25', 'password' => 'password'])
            ->assertSessionHas('success');

        $transfer = WalletTransfer::sole();
        $this->assertSame($recipient->id, $transfer->recipient_id);
        $this->assertSame($txHash, $transfer->tx_hash);
        $this->assertSame('confirmed', $transfer->status);
        $this->assertSame(123, $transfer->block_number);
        $this->assertSame(7, $transfer->nonce);

        Http::assertSent(function (Request $r) use ($transfer, $recipient) {
            if ($r->url() !== 'http://signer.test/v1/tx/submit') {
                return false;
            }
            $tx = $this->preparedTx(strtolower($recipient->wallet_address), '250000000000000000');
            $raw = MpcAuthorizer::signTxRaw('ampuh-user-1', 'evm:84532', $transfer->uuid, hex2bin(MpcAuthorizer::unsignedHash($tx)));

            return $r['tx_id'] === $transfer->uuid
                && $r['tx'] === $tx
                && $this->verifiesAuthorizer($r['authorizer_signature'], MpcAuthorizer::authorizerRaw($transfer->uuid, $raw, base64_decode($this->initiatorSig)));
        });
    }

    public function test_refuses_to_cosign_a_transaction_the_signer_altered(): void
    {
        [$sender, $recipient] = $this->users();
        $this->fakeCluster(prepareTx: $this->preparedTx('0x' . str_repeat('e', 40), '250000000000000000'));

        $this->actingAs($sender)
            ->post(route('wallet.send'), ['recipient' => $recipient->email, 'amount' => '0.25', 'password' => 'password']);

        $this->assertSame('failed', WalletTransfer::sole()->status);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/v1/tx/submit'));
    }

    public function test_refuses_to_cosign_above_the_fee_cap(): void
    {
        [$sender, $recipient] = $this->users();
        $tx = $this->preparedTx(strtolower($recipient->wallet_address), '250000000000000000');
        $tx['max_fee_per_gas'] = '51000000000';
        $this->fakeCluster(prepareTx: $tx);

        $this->actingAs($sender)
            ->post(route('wallet.send'), ['recipient' => $recipient->email, 'amount' => '0.25', 'password' => 'password']);

        $transfer = WalletTransfer::sole();
        $this->assertSame('failed', $transfer->status);
        $this->assertStringContainsString('Biaya jaringan', $transfer->error_message);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/v1/tx/submit'));
    }

    public function test_ambiguous_submit_failure_stays_pending(): void
    {
        [$sender, $recipient] = $this->users();
        $this->fakeCluster(submit: Http::response(['error' => 'broadcast failed: timeout'], 502));

        $this->actingAs($sender)
            ->post(route('wallet.send'), ['recipient' => $recipient->email, 'amount' => '0.25', 'password' => 'password']);

        $this->assertSame('pending', WalletTransfer::sole()->status);
    }

    public function test_signing_failure_before_broadcast_marks_failed(): void
    {
        [$sender, $recipient] = $this->users();
        $this->fakeCluster(submit: Http::response(['error' => 'signing failed', 'broadcast' => false], 502));

        $this->actingAs($sender)
            ->post(route('wallet.send'), ['recipient' => $recipient->email, 'amount' => '0.25', 'password' => 'password']);

        $this->assertSame('failed', WalletTransfer::sole()->status);
    }

    public function test_send_requires_the_account_password(): void
    {
        [$sender, $recipient] = $this->users();
        Http::fake();

        $this->actingAs($sender)
            ->post(route('wallet.send'), ['recipient' => $recipient->email, 'amount' => '0.1', 'password' => 'wrong'])
            ->assertSessionHasErrors('password');

        Http::assertNothingSent();
        $this->assertSame(0, WalletTransfer::count());
    }

    public function test_send_enforces_limits(): void
    {
        [$sender, $recipient] = $this->users();
        $this->fakeCluster(balanceWei: '0x8ac7230489e80000'); // 10 ETH

        $this->actingAs($sender)
            ->post(route('wallet.send'), ['recipient' => $recipient->email, 'amount' => '1.5', 'password' => 'password'])
            ->assertSessionHasErrors('amount');

        WalletTransfer::create([
            'uuid' => fake()->uuid(), 'sender_id' => $sender->id, 'sender_address' => $sender->wallet_address,
            'recipient_address' => $recipient->wallet_address, 'amount_wei' => '1800000000000000000',
            'amount_display' => '1.8 ETH', 'status' => 'confirmed',
        ]);

        $this->actingAs($sender)
            ->post(route('wallet.send'), ['recipient' => $recipient->email, 'amount' => '0.5', 'password' => 'password'])
            ->assertSessionHasErrors('amount');

        $this->assertSame(1, WalletTransfer::count());
    }

    public function test_send_rejects_malformed_amounts(): void
    {
        [$sender, $recipient] = $this->users();
        Http::fake();

        foreach (['1e-30', '0x10', '-1', '0.1234567890123456789', '0'] as $amount) {
            $this->actingAs($sender)
                ->post(route('wallet.send'), ['recipient' => $recipient->email, 'amount' => $amount, 'password' => 'password'])
                ->assertSessionHasErrors('amount');
        }

        Http::assertNothingSent();
    }

    public function test_send_rejects_unknown_email(): void
    {
        [$sender] = $this->users();
        Http::fake();

        $this->actingAs($sender)
            ->post(route('wallet.send'), ['recipient' => 'nobody@example.com', 'amount' => '0.1', 'password' => 'password'])
            ->assertSessionHasErrors('recipient');

        Http::assertNothingSent();
    }

    public function test_wallet_fields_are_not_mass_assignable(): void
    {
        [$sender] = $this->users();

        $sender->fill(['wallet_address' => '0x' . str_repeat('f', 40), 'mpc_wallet_id' => 'ampuh-user-999']);

        $this->assertFalse($sender->isDirty(['wallet_address', 'mpc_wallet_id']));
    }

    public function test_amount_formatting_is_exact(): void
    {
        $this->assertSame('0.25 ETH', MpcWalletService::formatEth('250000000000000000'));
        $this->assertSame('1 ETH', MpcWalletService::formatEth('1000000000000000000'));
        $this->assertSame('123456789000000000000', MpcWalletService::toWei('123.456789'));
    }

    /** @return array{0: User, 1: User} */
    private function users(): array
    {
        $sender = User::factory()->create(['wallet_address' => '0x' . str_repeat('a', 40), 'mpc_wallet_id' => 'ampuh-user-1']);
        $recipient = User::factory()->create(['wallet_address' => '0x' . str_repeat('B', 40)]);

        return [$sender, $recipient];
    }

    private function preparedTx(string $to, string $value): array
    {
        return [
            'chain_id' => '84532',
            'nonce' => '7',
            'max_fee_per_gas' => '1500000000',
            'max_priority_fee_per_gas' => '1000000',
            'gas_limit' => '21000',
            'to' => $to,
            'value' => $value,
        ];
    }

    private function fakeCluster(?array $prepareTx = null, $submit = null, string $balanceWei = '0xde0b6b3a7640000'): void
    {
        Http::fake([
            'rpc.test' => fn (Request $r) => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => match ($r['method']) {
                'eth_getBalance' => $balanceWei,
                'eth_getTransactionReceipt' => ['blockNumber' => '0x7b', 'status' => '0x1'],
                'eth_blockNumber' => '0x7c',
                default => null,
            }]),
            'signer.test/v1/tx/prepare' => fn (Request $r) => Http::response([
                'tx' => $prepareTx ?? $this->preparedTx($r['to'], $r['value_wei']),
                'unsigned_hash' => '0x00',
                'initiator_signature' => $this->initiatorSig,
            ]),
            'signer.test/v1/tx/submit' => $submit ?? Http::response(['tx_hash' => '0x' . str_repeat('1', 64)]),
        ]);
    }

    private function verifiesAuthorizer(?string $signatureB64, string $message): bool
    {
        $public = sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair(hex2bin(self::AUTHORIZER_SEED)));

        return is_string($signatureB64) && sodium_crypto_sign_verify_detached(base64_decode($signatureB64), $message, $public);
    }
}
