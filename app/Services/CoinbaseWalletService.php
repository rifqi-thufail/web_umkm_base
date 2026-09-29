<?php

namespace App\Services;

use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates Coinbase Developer Platform (CDP) Server Wallet accounts.
 *
 * Keys are generated and held by Coinbase's MPC/TEE infrastructure; the app only
 * stores the account name and address. Auth follows the CDP v2 REST API: a
 * Bearer JWT signed with the API key, plus an X-Wallet-Auth JWT signed with the
 * Wallet Secret for write operations.
 */
class CoinbaseWalletService
{
    protected Client $http;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?? new Client(['timeout' => 20]);
    }

    public function enabled(): bool
    {
        return (bool) config('blockchain.cdp.enabled')
            && config('blockchain.cdp.api_key_id')
            && config('blockchain.cdp.api_key_secret')
            && config('blockchain.cdp.wallet_secret');
    }

    public function accountNameFor(User $user): string
    {
        return config('blockchain.cdp.account_prefix') . '-' . $user->id;
    }

    /**
     * Create (or recover, if it already exists) the user's EVM account and store its address.
     * Users who already have a wallet address are left untouched.
     */
    public function provisionFor(User $user): User
    {
        if ($user->wallet_address) {
            return $user;
        }

        $name = $this->accountNameFor($user);
        $account = $this->createEvmAccount($name, "user-{$user->id}-evm-account");

        $user->forceFill([
            'wallet_address' => $account['address'],
            'wallet_provider' => 'coinbase_cdp',
            'cdp_account_name' => $name,
            'wallet_connected_at' => now(),
        ])->save();

        return $user;
    }

    /**
     * POST /v2/evm/accounts. A 409 means the name already exists (e.g. a retried
     * job), so the existing account is fetched instead.
     *
     * @return array{address:string,name:?string}
     */
    public function createEvmAccount(string $name, ?string $idempotencyKey = null): array
    {
        try {
            return $this->request('POST', '/v2/evm/accounts', ['name' => $name], walletAuth: true, idempotencyKey: $idempotencyKey);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 409) {
                return $this->getEvmAccountByName($name);
            }

            throw new RuntimeException('CDP create account failed: ' . $e->getResponse()->getBody(), 0, $e);
        }
    }

    /** @return array{address:string,name:?string} */
    public function getEvmAccountByName(string $name): array
    {
        return $this->request('GET', '/v2/evm/accounts/by-name/' . rawurlencode($name));
    }

    protected function request(string $method, string $path, ?array $body = null, bool $walletAuth = false, ?string $idempotencyKey = null): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Coinbase CDP wallet is disabled or not configured');
        }

        $url = rtrim(config('blockchain.cdp.api_url'), '/') . $path;
        $host = parse_url($url, PHP_URL_HOST);
        $fullPath = parse_url($url, PHP_URL_PATH);

        $headers = [
            'Authorization' => 'Bearer ' . $this->bearerJwt($method, $host, $fullPath),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        if ($walletAuth) {
            $headers['X-Wallet-Auth'] = $this->walletJwt($method, $host, $fullPath, $body ?? []);
        }
        if ($idempotencyKey) {
            $headers['X-Idempotency-Key'] = $idempotencyKey;
        }

        $options = ['headers' => $headers];
        if ($body !== null) {
            $options['body'] = $this->json($body);
        }

        $response = $this->http->request($method, $url, $options);
        $data = json_decode((string) $response->getBody(), true);

        if (! is_array($data) || empty($data['address'])) {
            throw new RuntimeException("CDP {$method} {$path}: unexpected response");
        }

        return $data;
    }

    /**
     * API key JWT. Ed25519 keys (base64, 64 bytes) sign with EdDSA; PEM EC keys with ES256.
     */
    public function bearerJwt(string $method, string $host, string $path): string
    {
        $keyId = config('blockchain.cdp.api_key_id');
        $secret = str_replace('\n', "\n", (string) config('blockchain.cdp.api_key_secret'));
        $now = time();

        $claims = [
            'sub' => $keyId,
            'iss' => 'cdp',
            'uris' => ["{$method} {$host}{$path}"],
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 120,
        ];

        $decoded = base64_decode($secret, true);
        if ($decoded !== false && strlen($decoded) === SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            $header = ['alg' => 'EdDSA', 'kid' => $keyId, 'typ' => 'JWT', 'nonce' => bin2hex(random_bytes(16))];

            return $this->sign($header, $claims, fn ($input) => sodium_crypto_sign_detached($input, $decoded));
        }

        $header = ['alg' => 'ES256', 'kid' => $keyId, 'typ' => 'JWT', 'nonce' => bin2hex(random_bytes(16))];

        return $this->sign($header, $claims, fn ($input) => $this->es256($input, $secret));
    }

    /**
     * Wallet Secret JWT, bound to the request by a SHA-256 hash of the key-sorted body.
     */
    public function walletJwt(string $method, string $host, string $path, array $body): string
    {
        $pem = "-----BEGIN PRIVATE KEY-----\n"
            . chunk_split((string) config('blockchain.cdp.wallet_secret'), 64, "\n")
            . "-----END PRIVATE KEY-----\n";
        $now = time();

        $claims = [
            'uris' => ["{$method} {$host}{$path}"],
            'iat' => $now,
            'nbf' => $now,
            'jti' => bin2hex(random_bytes(16)),
        ];
        if (array_filter($body, fn ($v) => $v !== null) !== []) {
            $claims['reqHash'] = hash('sha256', $this->json($this->sortKeys($body)));
        }

        return $this->sign(['alg' => 'ES256', 'typ' => 'JWT'], $claims, fn ($input) => $this->es256($input, $pem));
    }

    private function sign(array $header, array $claims, callable $signer): string
    {
        $input = $this->base64url($this->json($header)) . '.' . $this->base64url($this->json($claims));

        return $input . '.' . $this->base64url($signer($input));
    }

    /**
     * ES256 JWS signatures are raw r||s (64 bytes); openssl returns DER, so convert.
     */
    private function es256(string $input, string $pem): string
    {
        $key = openssl_pkey_get_private($pem);
        if (! $key || ! openssl_sign($input, $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Invalid CDP EC private key');
        }

        $offset = 2 + (ord($der[1]) & 0x80 ? ord($der[1]) & 0x7F : 0);
        $rLen = ord($der[$offset + 1]);
        $r = substr($der, $offset + 2, $rLen);
        $offset += 2 + $rLen;
        $s = substr($der, $offset + 2, ord($der[$offset + 1]));

        return str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT) . str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
    }

    private function sortKeys(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data, SORT_STRING);
        }

        return array_map(fn ($v) => is_array($v) ? $this->sortKeys($v) : $v, $data);
    }

    private function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
