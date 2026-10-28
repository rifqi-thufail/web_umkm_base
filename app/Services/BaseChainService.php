<?php

namespace App\Services;

use App\Models\Order;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use kornrunner\Ethereum\EIP1559Transaction;
use kornrunner\Keccak;
use RuntimeException;

/**
 * Anchors a fingerprint of each paid order in the OrderRegistry contract on Base.
 *
 * Works against any Base-compatible JSON-RPC endpoint: a local anvil fork of
 * Base Sepolia for development (see blockchain/README.md), Base Sepolia, or Base mainnet.
 */
class BaseChainService
{
    protected Client $http;

    public function __construct()
    {
        $this->http = new Client(['timeout' => 10, 'connect_timeout' => 5]);
    }

    public function enabled(): bool
    {
        return (bool) config('blockchain.base.enabled')
            && config('blockchain.base.rpc_url')
            && config('blockchain.base.contract_address')
            && config('blockchain.base.private_key');
    }

    public function network(): array
    {
        return [
            'name' => config('blockchain.base.network_name'),
            'chain_id' => (int) config('blockchain.base.chain_id'),
            'contract' => config('blockchain.base.contract_address'),
            'explorer' => config('blockchain.base.explorer_url'),
        ];
    }

    /**
     * Connection check used by `php artisan base:status`.
     */
    public function status(): array
    {
        $chainId = hexdec($this->rpc('eth_chainId'));
        $block = hexdec($this->rpc('eth_blockNumber'));
        $contract = config('blockchain.base.contract_address');
        $code = $contract ? $this->rpc('eth_getCode', [$contract, 'latest']) : '0x';
        $recorder = $this->recorderAddress();
        $deployed = strlen((string) $code) > 2;

        return [
            'rpc_url' => config('blockchain.base.rpc_url'),
            'chain_id' => $chainId,
            'expected_chain_id' => (int) config('blockchain.base.chain_id'),
            'block_number' => $block,
            'contract' => $contract,
            'contract_deployed' => $deployed,
            'recorder' => $recorder,
            'recorder_balance_eth' => $recorder ? $this->weiToEth(hexdec($this->rpc('eth_getBalance', [$recorder, 'latest']))) : null,
            'recorder_allowed' => $recorder && $deployed ? $this->isRecorder($recorder) : false,
        ];
    }

    public function isRecorder(string $address): bool
    {
        $result = $this->rpc('eth_call', [[
            'to' => config('blockchain.base.contract_address'),
            'data' => $this->selector('isRecorder(address)') . $this->word(substr(strtolower($address), 2)),
        ], 'latest']);

        return hexdec(substr((string) $result, -1)) === 1;
    }

    private function weiToEth(float|int $wei): string
    {
        return rtrim(rtrim(number_format($wei / 1e18, 8, '.', ''), '0'), '.');
    }

    /**
     * Deterministic fingerprint of the order. Only fields that must never change
     * after payment are included, so the hash can be recomputed at any time.
     */
    public function fingerprint(Order $order): array
    {
        $order->loadMissing('orderItems');

        $payload = [
            'order_number' => $order->order_number,
            'user_id' => (int) $order->user_id,
            'seller_id' => (int) $order->seller_id,
            'total_price' => (string) (int) round((float) $order->total_price),
            'created_at' => $order->created_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'items' => $order->orderItems
                ->sortBy('product_id')
                ->map(fn ($item) => [
                    'product_id' => (int) $item->product_id,
                    'quantity' => (int) $item->quantity,
                    'price' => (string) (int) round((float) $item->price),
                ])
                ->values()
                ->all(),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return ['payload' => $payload, 'hash' => '0x' . Keccak::hash($json, 256)];
    }

    /**
     * Sign and broadcast anchor(dataHash, orderId). Returns the tx hash without waiting for it to be mined.
     *
     * Sends are serialized with a cache lock so two webhooks never sign with the same nonce, and refused
     * when the network fee is above BASE_MAX_FEE_GWEI or the daily anchor budget is spent, so a fee spike
     * or a flood of (sandbox) payments cannot drain the recorder wallet.
     */
    public function submitAnchor(Order $order, string $dataHash): string
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Base anchoring is disabled or not configured');
        }

        $budget = (int) config('blockchain.base.max_anchors_per_day');
        $spentKey = 'base:anchors:' . now()->utc()->format('Y-m-d');
        if ($budget > 0 && (int) Cache::get($spentKey, 0) >= $budget) {
            throw new RuntimeException("Daily anchor budget of {$budget} reached; it will be retried tomorrow");
        }

        $data = $this->selector('anchor(bytes32,uint64)')
            . $this->word(substr($dataHash, 2))
            . $this->word(dechex($order->id));

        $txHash = Cache::lock('base:recorder-nonce', 30)->block(25, fn () => $this->sendTransaction(config('blockchain.base.contract_address'), $data));

        Cache::add($spentKey, 0, now()->addDays(2));
        Cache::increment($spentKey);

        return $txHash;
    }

    /** The mined receipt, or null while the tx is still in the mempool (or unknown to the node). */
    public function receipt(string $txHash): ?array
    {
        return $this->rpc('eth_getTransactionReceipt', [$txHash]) ?: null;
    }

    /** Whether the node still knows a tx we broadcast (mined or pending). */
    public function transactionKnown(string $txHash): bool
    {
        return (bool) $this->rpc('eth_getTransactionByHash', [$txHash]);
    }

    /** Poll for the receipt up to $timeoutSeconds; null if it has not been mined yet. */
    public function waitForReceipt(string $txHash, int $timeoutSeconds = 20): ?array
    {
        $deadline = microtime(true) + $timeoutSeconds;
        do {
            if ($receipt = $this->receipt($txHash)) {
                return $receipt;
            }
            usleep(1_000_000);
        } while (microtime(true) < $deadline);

        return null;
    }

    /**
     * Check an order against the chain: recompute its fingerprint and read the registry.
     */
    public function verifyOrder(Order $order): array
    {
        $fingerprint = $this->fingerprint($order);

        try {
            [$orderId, $anchoredAt] = $this->recordOf($fingerprint['hash']);
        } catch (\Throwable $e) {
            return ['reachable' => false, 'verified' => false, 'error' => $e->getMessage(), 'data_hash' => $fingerprint['hash']];
        }

        return [
            'reachable' => true,
            'verified' => $anchoredAt > 0 && $orderId === (int) $order->id,
            'data_hash' => $fingerprint['hash'],
            'matches_stored_hash' => $order->transaction_data_hash === $fingerprint['hash'],
            'anchored_at' => $anchoredAt ?: null,
        ];
    }

    /** @return array{0:int,1:int} [orderId, anchoredAt] */
    public function recordOf(string $dataHash): array
    {
        $result = $this->rpc('eth_call', [[
            'to' => config('blockchain.base.contract_address'),
            'data' => $this->selector('recordOf(bytes32)') . $this->word(substr($dataHash, 2)),
        ], 'latest']);

        $hex = substr($result, 2);
        if (strlen($hex) < 128) {
            return [0, 0];
        }

        return [hexdec(substr($hex, 0, 64)), hexdec(substr($hex, 64, 64))];
    }

    public function recorderAddress(): ?string
    {
        $key = config('blockchain.base.private_key');
        if (! $key) {
            return null;
        }

        $generator = \Mdanter\Ecc\EccFactory::getSecgCurves()->generator256k1();
        $point = $generator->getPrivateKeyFrom(gmp_init($this->strip0x($key), 16))->getPublicKey()->getPoint();
        $pub = '04' . str_pad(gmp_strval($point->getX(), 16), 64, '0', STR_PAD_LEFT)
            . str_pad(gmp_strval($point->getY(), 16), 64, '0', STR_PAD_LEFT);

        return '0x' . substr(Keccak::hash(hex2bin(substr($pub, 2)), 256), 24);
    }

    protected function sendTransaction(string $to, string $data): string
    {
        $from = $this->recorderAddress();
        $nonce = $this->rpc('eth_getTransactionCount', [$from, 'pending']);
        $gas = hexdec($this->rpc('eth_estimateGas', [['from' => $from, 'to' => $to, 'data' => $data]]));
        $tip = hexdec($this->rpc('eth_maxPriorityFeePerGas'));
        $baseFee = hexdec($this->rpc('eth_getBlockByNumber', ['latest', false])['baseFeePerGas'] ?? '0x0');

        $maxFee = $baseFee * 2 + max($tip, 1);
        $capWei = (float) config('blockchain.base.max_fee_gwei') * 1e9;
        if ($capWei > 0 && $maxFee > $capWei) {
            throw new RuntimeException(sprintf('Base fee %.4f gwei is above BASE_MAX_FEE_GWEI; will retry later', $maxFee / 1e9));
        }

        $tx = new EIP1559Transaction(
            $nonce,
            '0x' . dechex(max($tip, 1)),
            '0x' . dechex($maxFee),
            '0x' . dechex((int) ceil($gas * 1.2)),
            $to,
            '0x0',
            $data,
        );

        $raw = $tx->getRaw($this->strip0x(config('blockchain.base.private_key')), (int) config('blockchain.base.chain_id'));

        return $this->rpc('eth_sendRawTransaction', ['0x' . $raw]);
    }

    protected function rpc(string $method, array $params = []): mixed
    {
        $response = $this->http->post(config('blockchain.base.rpc_url'), [
            'json' => ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params],
        ]);
        $body = json_decode((string) $response->getBody(), true);

        if (isset($body['error'])) {
            throw new RuntimeException("{$method}: " . ($body['error']['message'] ?? json_encode($body['error'])));
        }

        return $body['result'] ?? null;
    }

    private function selector(string $signature): string
    {
        return '0x' . substr(Keccak::hash($signature, 256), 0, 8);
    }

    private function strip0x(string $hex): string
    {
        return str_starts_with($hex, '0x') ? substr($hex, 2) : $hex;
    }

    private function word(string $hex): string
    {
        return str_pad(strtolower($hex), 64, '0', STR_PAD_LEFT);
    }
}
