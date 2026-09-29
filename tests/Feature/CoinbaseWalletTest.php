<?php

namespace Tests\Feature;

use App\Jobs\ProvisionCoinbaseWallet;
use App\Models\User;
use App\Services\CoinbaseWalletService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CoinbaseWalletTest extends TestCase
{
    use RefreshDatabase;

    private array $history = [];

    private string $walletPublicPem;

    protected function setUp(): void
    {
        parent::setUp();

        $walletKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($walletKey, $walletPem);
        $this->walletPublicPem = openssl_pkey_get_details($walletKey)['key'];
        $walletDer = preg_replace('/-----[^-]+-----|\s/', '', $walletPem);

        config([
            'blockchain.cdp.enabled' => true,
            'blockchain.cdp.api_url' => 'https://api.cdp.coinbase.com/platform',
            'blockchain.cdp.api_key_id' => 'test-key-id',
            'blockchain.cdp.api_key_secret' => base64_encode(sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair())),
            'blockchain.cdp.wallet_secret' => $walletDer,
            'blockchain.cdp.account_prefix' => 'ampuh-user',
        ]);
    }

    private function fakeCdp(array $responses): void
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        $this->app->instance(CoinbaseWalletService::class, new CoinbaseWalletService(new Client(['handler' => $stack])));
    }

    private function claims(string $jwt): array
    {
        return json_decode(base64_decode(strtr(explode('.', $jwt)[1], '-_', '+/')), true);
    }

    public function test_registration_creates_a_coinbase_wallet(): void
    {
        $address = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';
        $this->fakeCdp([new Response(201, [], json_encode(['address' => $address, 'name' => 'ampuh-user-1']))]);

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'test@example.com')->first();
        $this->assertSame($address, $user->wallet_address);
        $this->assertSame('coinbase_cdp', $user->wallet_provider);
        $this->assertSame("ampuh-user-{$user->id}", $user->cdp_account_name);

        /** @var Request $request */
        $request = $this->history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://api.cdp.coinbase.com/platform/v2/evm/accounts', (string) $request->getUri());
        $this->assertSame(['name' => "ampuh-user-{$user->id}"], json_decode((string) $request->getBody(), true));
        $this->assertSame("user-{$user->id}-evm-account", $request->getHeaderLine('X-Idempotency-Key'));

        $bearer = $this->claims(substr($request->getHeaderLine('Authorization'), 7));
        $this->assertSame(['POST api.cdp.coinbase.com/platform/v2/evm/accounts'], $bearer['uris']);
        $this->assertSame('cdp', $bearer['iss']);

        $wallet = $this->claims($request->getHeaderLine('X-Wallet-Auth'));
        $this->assertSame(hash('sha256', (string) $request->getBody()), $wallet['reqHash']);
    }

    public function test_jwts_verify_against_their_keys(): void
    {
        $cdp = new CoinbaseWalletService();

        [$h, $p, $s] = explode('.', $cdp->walletJwt('POST', 'api.cdp.coinbase.com', '/platform/v2/evm/accounts', ['name' => 'x']));
        $sig = base64_decode(strtr($s, '-_', '+/'));
        $this->assertSame(64, strlen($sig));
        $this->assertSame(1, openssl_verify("{$h}.{$p}", $this->rawToDer($sig), $this->walletPublicPem, OPENSSL_ALGO_SHA256));

        [$h, $p, $s] = explode('.', $cdp->bearerJwt('GET', 'api.cdp.coinbase.com', '/platform/v2/evm/accounts'));
        $secret = base64_decode(config('blockchain.cdp.api_key_secret'));
        $this->assertSame('EdDSA', json_decode(base64_decode(strtr($h, '-_', '+/')), true)['alg']);
        $this->assertTrue(sodium_crypto_sign_verify_detached(
            base64_decode(strtr($s, '-_', '+/')), "{$h}.{$p}", sodium_crypto_sign_publickey_from_secretkey($secret)
        ));
    }

    public function test_existing_account_is_recovered_on_conflict(): void
    {
        $user = User::factory()->create();
        $this->fakeCdp([
            new Response(409, [], json_encode(['errorType' => 'already_exists'])),
            new Response(200, [], json_encode(['address' => '0x1111111111111111111111111111111111111111'])),
        ]);

        app(CoinbaseWalletService::class)->provisionFor($user);

        $this->assertSame('0x1111111111111111111111111111111111111111', $user->fresh()->wallet_address);
        $this->assertSame("/platform/v2/evm/accounts/by-name/ampuh-user-{$user->id}", $this->history[1]['request']->getUri()->getPath());
    }

    public function test_nothing_is_dispatched_when_disabled(): void
    {
        config(['blockchain.cdp.enabled' => false]);
        Queue::fake();

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        Queue::assertNotPushed(ProvisionCoinbaseWallet::class);
        $this->assertNull(User::first()->wallet_address);
    }

    private function rawToDer(string $sig): string
    {
        $int = function (string $x) {
            $x = ltrim($x, "\0");
            if (ord($x[0]) & 0x80) {
                $x = "\0" . $x;
            }

            return "\x02" . chr(strlen($x)) . $x;
        };
        $seq = $int(substr($sig, 0, 32)) . $int(substr($sig, 32));

        return "\x30" . chr(strlen($seq)) . $seq;
    }
}
