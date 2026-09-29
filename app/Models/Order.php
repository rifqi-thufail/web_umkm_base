<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'seller_id',
        'mid',
        'order_number',
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
            'expired' => 'Kadaluarsa',
            'refund' => 'Refund',
            default => ucfirst($this->payment_status),
        };
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
     */
    public function anchorOnBase(\App\Services\BaseChainService $base): array
    {
        // Already on chain: re-sending would revert with AlreadyAnchored, so never downgrade the status.
        if ($this->blockchain_status === 'confirmed' && $this->blockchain_hash) {
            return ['success' => false, 'already_anchored' => true, 'error' => 'Order is already anchored in tx ' . $this->blockchain_hash];
        }

        $this->update(['blockchain_status' => 'pending']);
        $result = $base->anchorOrder($this);

        if ($result['success']) {
            $this->update([
                'blockchain_hash' => $result['tx_hash'],
                'blockchain_transaction_id' => (string) $result['chain_id'],
                'block_number' => $result['block_number'],
                'transaction_data_hash' => $result['data_hash'],
                'blockchain_metadata' => [
                    'network' => config('blockchain.base.network_name'),
                    'chain_id' => $result['chain_id'],
                    'contract' => config('blockchain.base.contract_address'),
                    'anchored_at' => now()->toISOString(),
                ],
                'blockchain_created_at' => now(),
                'blockchain_status' => 'confirmed',
            ]);
        } else {
            $this->update([
                'blockchain_status' => 'failed',
                'transaction_data_hash' => $result['data_hash'] ?? null,
                'blockchain_metadata' => [
                    'error' => $result['error'],
                    'failed_at' => now()->toISOString(),
                ],
            ]);
        }

        return $result;
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