<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'driver_id',
        'pickup_address',
        'pickup_latitude',
        'pickup_longitude',
        'delivery_address',
        'delivery_latitude',
        'delivery_longitude',
        'distance_km',
        'delivery_fee',
        'driver_earning',
        'platform_fee',
        'status',
        'assigned_at',
        'picked_up_at',
        'delivered_at',
        'notes',
        'failure_reason', // Now used for cancellation reason
    ];

    protected $casts = [
        'pickup_latitude' => 'decimal:8',
        'pickup_longitude' => 'decimal:8',
        'delivery_latitude' => 'decimal:8',
        'delivery_longitude' => 'decimal:8',
        'distance_km' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'driver_earning' => 'decimal:2',  // Fixed from 'driver_earnings'
        'platform_fee' => 'decimal:2',    // Fixed from 'platform_earnings'
        'assigned_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the order
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the driver (user)
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    // ==================== QUERY SCOPES ====================

    /**
     * Scope for pending deliveries
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for driver's deliveries
     */
    public function scopeForDriver($query, int $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    /**
     * Scope for assigned deliveries
     */
    public function scopeAssigned($query)
    {
        return $query->whereNotNull('driver_id')->where('status', 'assigned');
    }

    /**
     * Scope for cancelled deliveries
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope for active deliveries (assigned or in transit)
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['assigned', 'in_transit']);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Assign driver to delivery
     */
    public function assignDriver(int $driverId): void
    {
        $this->update([
            'driver_id' => $driverId,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);
    }

    /**
     * Mark as picked up
     */
    public function markAsPickedUp(): void
    {
        $this->update([
            'status' => 'in_transit',
            'picked_up_at' => now(),
        ]);
    }

    /**
     * Mark as delivered
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    /**
     * Cancel delivery (only before pickup)
     */
    public function cancelDelivery(string $reason): bool
    {
        // Only allow cancellation before pickup
        if ($this->picked_up_at !== null) {
            return false;
        }

        $this->update([
            'status' => 'cancelled',
            'failure_reason' => $reason,
        ]);

        return true;
    }

    /**
     * Check if delivery can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return $this->picked_up_at === null && in_array($this->status, ['pending', 'assigned']);
    }

    /**
     * Check if delivery is before pickup
     */
    public function isBeforePickup(): bool
    {
        return $this->picked_up_at === null;
    }

    /**
     * Check if delivery is after pickup
     */
    public function isAfterPickup(): bool
    {
        return $this->picked_up_at !== null;
    }

    /**
     * Get delivery status display name
     */
    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Pending Assignment',
            'assigned' => 'Driver Assigned',
            'in_transit' => 'In Transit',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }
}
