<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'buyer_id',
        'seller_id',
        'item_id',
        'delivery_address_id',
        'item_price',
        'delivery_fee',
        'total_amount',
        'status',
        'payment_method',
        'payment_status',
        'confirmed_at',
        'delivered_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'distributed_at',
        'distribution_notes',
        'people_helped',
    ];

    protected $casts = [
        'item_price' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'distributed_at' => 'datetime',
        'people_helped' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the buyer (user)
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * Get the seller (user)
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Get the item
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the delivery address
     */
    public function deliveryAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'delivery_address_id');
    }

    /**
     * Get the delivery
     */
    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    // ==================== QUERY SCOPES ====================

    /**
     * Scope for buyer's orders
     */
    public function scopeForBuyer($query, int $buyerId)
    {
        return $query->where('buyer_id', $buyerId);
    }

    /**
     * Scope for seller's orders
     */
    public function scopeForSeller($query, int $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }

    /**
     * Scope for specific status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for pending orders
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for completed orders
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // ==================== HELPER METHODS ====================

    /**
     * Check if order belongs to user (buyer or seller)
     */
    public function belongsToUser(int $userId): bool
    {
        return $this->buyer_id === $userId || $this->seller_id === $userId;
    }

    /**
     * Check if user is the buyer
     */
    public function isBuyer(int $userId): bool
    {
        return $this->buyer_id === $userId;
    }

    /**
     * Check if user is the seller
     */
    public function isSeller(int $userId): bool
    {
        return $this->seller_id === $userId;
    }

    /**
     * Check if order can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']);
    }

    /**
     * Check if order is donation
     */
    public function isDonation(): bool
    {
        return $this->item_price == 0;
    }
}
