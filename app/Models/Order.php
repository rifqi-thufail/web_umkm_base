<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    /** Midtrans Snap tokens and our checkout links stay valid this long. */
    public const PAYMENT_WINDOW_HOURS = 24;

    protected $fillable = [
        'user_id',
        'seller_id',
        'mid',
        'order_number',
        'payment_reference',
        'paid_at',
        'alamat_pengiriman',
        'total_price',
        'status',
        'payment_status',
        'payment_gateway',
        'snap_token',
        'client_key',
        'blockchain_hash',
        'blockchain_transaction_id',
        'block_number',
        'transaction_data_hash',
        'blockchain_metadata',
        'blockchain_created_at',
        'blockchain_status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'total_price' => 'decimal:2',
        'blockchain_created_at' => 'datetime',
        'paid_at' => 'datetime',
        'blockchain_metadata' => 'array',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Alias for orderItems relationship
     */
    public function items()
    {
        return $this->orderItems();
    }

    // Helper Methods
    public function getStatusBadgeColorAttribute()
    {
        return match($this->status) {
            'pending' => 'yellow',
            'processing' => 'blue',
            'shipped' => 'indigo',
            'completed' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'pending' => 'Menunggu Pembayaran',
            'processing' => 'Diproses',
            'shipped' => 'Dikirim',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            default => ucfirst($this->status),
        };
    }

    public function getPaymentStatusLabelAttribute()
    {
        return match($this->payment_status) {
            'unpaid' => 'Belum Dibayar',
            'paid' => 'Sudah Dibayar',
            'failed' => 'Pembayaran Gagal',
            'expired' => 'Kedaluwarsa',
            'refund' => 'Refund',
            default => ucfirst($this->payment_status),
        };
    }

    /** What the gateway was asked to charge: the shared checkout reference, or the order number for old orders. */
    public function gatewayReference(): string
    {
        return $this->payment_reference ?: $this->order_number;
    }

    /** All orders paid together with this one (one per seller), this one included. */
    public function paymentGroup()
    {
        return $this->payment_reference
            ? static::where('payment_reference', $this->payment_reference)->orderBy('id')->get()
            : new \Illuminate\Database\Eloquent\Collection([$this]);
    }

    public function paymentWindowOpen(): bool
    {
        return $this->payment_status === 'unpaid'
            && $this->created_at?->gt(now()->subHours(self::PAYMENT_WINDOW_HOURS));
    }

    // Blockchain Methods
    public function isOnBlockchain(): bool
    {
        return !empty($this->blockchain_hash) && $this->blockchain_status === 'confirmed';
    }

    public function isBlockchainPending(): bool
    {
        return $this->blockchain_status === 'pending';
    }

    public function isBlockchainFailed(): bool
    {
        return $this->blockchain_status === 'failed';
    }

    public function getBlockchainUrlAttribute(): ?string
    {
        $explorer = config('blockchain.base.explorer_url');
        if (!$explorer || !$this->blockchain_hash) {
            return null;
        }

        return rtrim($explorer, '/') . '/tx/' . $this->blockchain_hash;
    }

    /**
     * Anchor this order's fingerprint on Base and store the result.
     *
     * Safe to call repeatedly (webhook, return page, retry sweep): a per-order lock stops overlapping
     * runs, and the broadcast tx hash is saved before waiting, so a slow block never leads to a
     * second anchor. A tx that is still in the mempool leaves the order 'pending' for the next retry.
     */
    public function anchorOnBase(\App\Services\BaseChainService $base): array
    {
        // Already on chain: re-sending would revert with AlreadyAnchored, so never downgrade the status.
        if ($this->blockchain_status === 'confirmed' && $this->blockchain_hash) {
            return ['success' => false, 'already_anchored' => true, 'error' => 'Order is already anchored in tx ' . $this->blockchain_hash];
        }

        $lock = \Illuminate\Support\Facades\Cache::lock('base:anchor:order:' . $this->id, 120);
        if (! $lock->get()) {
            return ['success' => false, 'in_progress' => true, 'error' => 'Anchoring is already running for this order'];
        }

        try {
            $this->refresh();
            if ($this->blockchain_status === 'confirmed' && $this->blockchain_hash) {
                return ['success' => false, 'already_anchored' => true, 'error' => 'Order is already anchored in tx ' . $this->blockchain_hash];
            }

            $dataHash = $base->fingerprint($this)['hash'];

            if (! $base->enabled()) {
                return $this->markAnchorFailed($dataHash, 'Base anchoring is disabled or not configured');
            }

            try {
                $txHash = $this->blockchain_metadata['pending_tx'] ?? null;

                if ($txHash) {
                    $receipt = $base->receipt($txHash);
                    if (! $receipt && $base->transactionKnown($txHash)) {
                        $receipt = $base->waitForReceipt($txHash);
                        if (! $receipt) {
                            return ['success' => false, 'pending' => true, 'tx_hash' => $txHash, 'error' => 'Transaction is not mined yet'];
                        }
                    }
                    if (! $receipt) {
                        $txHash = null; // dropped from the mempool: send again
                    }
                }

                if (! $txHash) {
                    $txHash = $base->submitAnchor($this, $dataHash);
                    $this->update([
                        'blockchain_status' => 'pending',
                        'transaction_data_hash' => $dataHash,
                        'blockchain_metadata' => ['pending_tx' => $txHash, 'submitted_at' => now()->toISOString()],
                    ]);
                    $receipt = $base->waitForReceipt($txHash);
                    if (! $receipt) {
                        return ['success' => false, 'pending' => true, 'tx_hash' => $txHash, 'error' => 'Transaction is not mined yet'];
                    }
                }

                if (hexdec($receipt['status']) !== 1) {
                    throw new \RuntimeException("Transaction {$txHash} reverted");
                }

                $chainId = (int) config('blockchain.base.chain_id');
                $this->update([
                    'blockchain_hash' => $txHash,
                    'blockchain_transaction_id' => (string) $chainId,
                    'block_number' => hexdec($receipt['blockNumber']),
                    'transaction_data_hash' => $dataHash,
                    'blockchain_metadata' => [
                        'network' => config('blockchain.base.network_name'),
                        'chain_id' => $chainId,
                        'contract' => config('blockchain.base.contract_address'),
                        'anchored_at' => now()->toISOString(),
                    ],
                    'blockchain_created_at' => now(),
                    'blockchain_status' => 'confirmed',
                ]);

                return ['success' => true, 'tx_hash' => $txHash, 'block_number' => $this->block_number, 'data_hash' => $dataHash, 'chain_id' => $chainId];
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Base anchor failed', ['order_id' => $this->id, 'error' => $e->getMessage()]);

                return $this->markAnchorFailed($dataHash, $e->getMessage());
            }
        } finally {
            $lock->release();
        }
    }

    private function markAnchorFailed(string $dataHash, string $error): array
    {
        $this->update([
            'blockchain_status' => 'failed',
            'transaction_data_hash' => $dataHash,
            'blockchain_metadata' => ['error' => $error, 'failed_at' => now()->toISOString()],
        ]);

        return ['success' => false, 'error' => $error, 'data_hash' => $dataHash];
    }

    /**
     * Paid orders that still need anchoring: failed ones, ones paid while Base was off, and
     * 'pending' ones whose run was cut short (older than a few minutes).
     */
    public function scopeAwaitingAnchor($query)
    {
        return $query->where('payment_status', 'paid')
            ->where(fn ($q) => $q->whereNull('blockchain_status')
                ->orWhere('blockchain_status', 'failed')
                ->orWhere(fn ($q) => $q->where('blockchain_status', 'pending')->where('updated_at', '<', now()->subMinutes(3))));
    }

    public function getBlockchainStatusBadgeAttribute(): string
    {
        return match($this->blockchain_status) {
            'confirmed' => 'success',
            'pending' => 'warning', 
            'failed' => 'danger',
            default => 'secondary'
        };
    }

    public function getBlockchainStatusLabelAttribute(): string
    {
        return match($this->blockchain_status) {
            'confirmed' => 'Tercatat di Blockchain',
            'pending' => 'Menunggu Konfirmasi',
            'failed' => 'Gagal Dicatat',
            default => 'Unknown'
        };
    }

    // Accessor untuk compatibility dengan view yang menggunakan 'total'
    public function getTotalAttribute()
    {
        return $this->total_price;
    }
}