<?php

namespace App\Services;

use App\Models\Order;
use GuzzleHttp\Client;
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
        $this->http = new Client(['timeout' => 20]);
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

        return [
            'rpc_url' => config('blockchain.base.rpc_url'),
            'chain_id' => $chainId,
            'expected_chain_id' => (int) config('blockchain.base.chain_id'),
            'block_number' => $block,
            'contract' => $contract,
            'contract_deployed' => strlen($code) > 2,
            'recorder' => $this->recorderAddress(),
        ];
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
     * Send anchor(dataHash, orderId) to the registry and wait for the receipt.
     */
    public function anchorOrder(Order $order): array
    {
        if (! $this->enabled()) {
            return ['success' => false, 'error' => 'Base anchoring is disabled or not configured'];
        }

        $fingerprint = $this->fingerprint($order);

        try {
            $data = $this->selector('anchor(bytes32,uint64)')
                . $this->word(substr($fingerprint['hash'], 2))
                . $this->word(dechex($order->id));

            $txHash = $this->sendTransaction(config('blockchain.base.contract_address'), $data);
            $receipt = $this->waitForReceipt($txHash);

            if (hexdec($receipt['status']) !== 1) {
                throw new RuntimeException('Transaction reverted');
            }

            return [
                'success' => true,
                'tx_hash' => $txHash,
                'block_number' => hexdec($receipt['blockNumber']),
                'data_hash' => $fingerprint['hash'],
                'chain_id' => (int) config('blockchain.base.chain_id'),
            ];
        } catch (\Throwable $e) {
            Log::error('Base anchor failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage(), 'data_hash' => $fingerprint['hash']];
        }
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

        $tx = new EIP1559Transaction(
            $nonce,
            '0x' . dechex(max($tip, 1)),
            '0x' . dechex($baseFee * 2 + max($tip, 1)),
            '0x' . dechex((int) ceil($gas * 1.2)),
            $to,
            '0x0',
            $data,
        );

        $raw = $tx->getRaw($this->strip0x(config('blockchain.base.private_key')), (int) config('blockchain.base.chain_id'));

        return $this->rpc('eth_sendRawTransaction', ['0x' . $raw]);
    }

    protected function waitForReceipt(string $txHash, int $timeoutSeconds = 30): array
    {
        $deadline = microtime(true) + $timeoutSeconds;
        do {
            $receipt = $this->rpc('eth_getTransactionReceipt', [$txHash]);
            if ($receipt) {
                return $receipt;
            }
            usleep(500_000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException("No receipt for {$txHash} after {$timeoutSeconds}s");
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
