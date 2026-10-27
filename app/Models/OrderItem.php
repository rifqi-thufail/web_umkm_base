<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    // Pastikan semua kolom ini sesuai dengan tabel Anda
    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price' // DIUBAH DARI 'harga'
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        // Keep showing products the seller has since removed from the catalog.
        return $this->belongsTo(Product::class)->withTrashed();
    }
}

