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
    ];

    protected $casts = [
        'pickup_latitude' => 'decimal:8',
        'pickup_longitude' => 'decimal:8',
        'delivery_latitude' => 'decimal:8',
        'delivery_longitude' => 'decimal:8',
        'distance_km' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'driver_earnings' => 'decimal:2',
        'platform_earnings' => 'decimal:2',
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
}
