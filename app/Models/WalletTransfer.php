<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'sender_id',
        'recipient_id',
        'sender_address',
        'recipient_address',
        'amount_wei',
        'amount_display',
        'nonce',
        'tx_hash',
        'status',
        'error_message',
        'block_number',
        'submitted_at',
        'confirmed_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'submitted_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'confirmed' => 'Berhasil',
            'pending' => 'Menunggu',
            'failed' => 'Gagal',
            default => ucfirst($this->status),
        };
    }
}
