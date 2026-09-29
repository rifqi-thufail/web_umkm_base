<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Seller extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'nama_koperasi',
        'email',
        'password',
        'kecamatan',
        'desa_kelurahan',
        'jenis_usaha',
        'no_hp',
        'alamat_toko',
        'deskripsi_toko',
        'foto_profil',
        'merchant_id',
        'is_active',
        'client_key',
        'server_key',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    // Relationship dengan Order
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Check if seller is active and has complete Midtrans configuration
     */
    public function isActivated()
    {
        // return $this->is_active && 
        //        !empty($this->merchant_id) && 
        //        !empty($this->client_key) && 
        //        !empty($this->server_key);
        return true; // Semua seller dianggap aktif untuk sementara
    }

    /**
     * Check if seller can manage products (CRUD operations)
     * Seller bisa kelola produk tanpa aktivasi penuh
     */
    public function canManageProducts(): bool
    {
        return true; // Semua seller bisa kelola produk
    }

    /**
     * Check if seller can receive payments
     * Memerlukan aktivasi penuh (merchant_id, client_key, server_key)
     */
    public function canReceivePayments(): bool
    {
        return $this->isActivated() && 
               !empty($this->client_key) && 
               !empty($this->server_key);
    }

    /**
     * Check if seller can sell products (legacy method)
     */
    public function canSell(): bool
    {
        return $this->canReceivePayments();
    }

    /**
     * Get activation status dengan detail
     */
    public function getActivationStatus(): array
    {
        $hasMerchantId = !empty($this->merchant_id);
        $hasClientKey = !empty($this->client_key);
        $hasServerKey = !empty($this->server_key);
        
        $completedFields = 0;
        if ($hasMerchantId) $completedFields++;
        if ($hasClientKey) $completedFields++;
        if ($hasServerKey) $completedFields++;
        
        $progressPercentage = round(($completedFields / 3) * 100);
        
        return [
            'can_manage_products' => $this->canManageProducts(),
            'can_receive_payments' => $this->canReceivePayments(),
            'has_merchant_id' => $hasMerchantId,
            'has_client_key' => $hasClientKey,
            'has_server_key' => $hasServerKey,
            'completed_fields' => $completedFields,
            'total_fields' => 3,
            'progress_percentage' => $progressPercentage,
            'is_fully_activated' => $this->canReceivePayments(),
        ];
    }

    /**
     * Check if seller has complete payment configuration
     */
    public function hasCompletePaymentConfig(): bool
    {
        return !empty($this->merchant_id) && 
               !empty($this->client_key) && 
               !empty($this->server_key);
    }
}
