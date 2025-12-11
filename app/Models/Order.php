<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
    ];

    protected $casts = [
        'item_price' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    protected $appends = [
        'driver_earnings',
        'platform_commission',
        'status_label',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the buyer
     */
    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * Get the seller
     */
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Get the item
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the delivery address
     */
    public function deliveryAddress()
    {
        return $this->belongsTo(Address::class, 'delivery_address_id');
    }

    /**
     * Get the delivery for this order
     */
    public function delivery()
    {
        return $this->hasOne(Delivery::class);
    }

    // ==================== ACCESSORS ====================

    /**
     * Calculate driver earnings (75% of delivery fee)
     */
    public function getDriverEarningsAttribute()
    {
        return round($this->delivery_fee * 0.75, 2);
    }

    /**
     * Calculate platform commission (25% of delivery fee)
     */
    public function getPlatformCommissionAttribute()
    {
        return round($this->delivery_fee * 0.25, 2);
    }

    /**
     * Get human-readable status
     */
    public function getStatusLabelAttribute()
    {
        return ucwords(str_replace('_', ' ', $this->status));
    }

    // ==================== QUERY SCOPES ====================

    /**
     * Scope for buyer's orders
     */
    public function scopeForBuyer($query, $buyerId)
    {
        return $query->where('buyer_id', $buyerId);
    }

    /**
     * Scope for seller's orders
     */
    public function scopeForSeller($query, $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }

    /**
     * Scope for orders by status
     */
    public function scopeByStatus($query, $status)
    {
        if (empty($status)) {
            return $query;
        }

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
     * Scope for confirmed orders
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    /**
     * Scope for in delivery orders
     */
    public function scopeInDelivery($query)
    {
        return $query->where('status', 'in_delivery');
    }

    /**
     * Scope for delivered orders
     */
    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    /**
     * Scope for completed orders
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for cancelled orders
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope for active orders (not cancelled or completed)
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['cancelled', 'completed']);
    }

    // ==================== METHODS ====================

    /**
     * Check if order can be cancelled
     */
    public function canBeCancelled()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if order can be confirmed by seller
     */
    public function canBeConfirmed()
    {
        return $this->status === 'pending';
    }

    /**
     * Confirm order (seller action)
     */
    public function confirm()
    {
        if (!$this->canBeConfirmed()) {
            throw new \Exception('Order cannot be confirmed in current status');
        }

        $this->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        // Create notification for buyer
        $this->buyer->notify('Order Confirmed', "Your order #{$this->order_number} has been confirmed by the seller.", 'order', "/orders/{$this->id}");
    }

    /**
     * Cancel order
     */
    public function cancel($reason = null)
    {
        if (!$this->canBeCancelled()) {
            throw new \Exception('Order cannot be cancelled in current status');
        }

        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        // Mark item as available again
        $this->item->markAsAvailable();

        // Notify seller
        $this->seller->notify('Order Cancelled', "Order #{$this->order_number} has been cancelled by the buyer.", 'order', "/orders/{$this->id}");
    }

    /**
     * Mark order as in delivery
     */
    public function markAsInDelivery()
    {
        $this->update([
            'status' => 'in_delivery',
        ]);

        // Notify buyer
        $this->buyer->notify('Order In Delivery', "Your order #{$this->order_number} is now being delivered.", 'order', "/orders/{$this->id}");
    }

    /**
     * Mark order as delivered
     */
    public function markAsDelivered()
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
            'payment_status' => 'paid', // COD payment collected
        ]);

        // Notify buyer
        $this->buyer->notify('Order Delivered', "Your order #{$this->order_number} has been delivered. Please confirm receipt.", 'order', "/orders/{$this->id}");
    }

    /**
     * Complete order (buyer confirms receipt)
     */
    public function complete()
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Mark item as sold/donated
        if ($this->item->is_donation) {
            $this->item->markAsDonated();
        } else {
            $this->item->markAsSold();
        }

        // Notify seller
        $this->seller->notify('Order Completed', "Order #{$this->order_number} has been completed.", 'order', "/orders/{$this->id}");
    }

    /**
     * Check if user is buyer
     */
    public function isBuyer($userId)
    {
        return $this->buyer_id == $userId;
    }

    /**
     * Check if user is seller
     */
    public function isSeller($userId)
    {
        return $this->seller_id == $userId;
    }

    /**
     * Check if user is involved in order (buyer or seller)
     */
    public function involvesUser($userId)
    {
        return $this->isBuyer($userId) || $this->isSeller($userId);
    }
}
