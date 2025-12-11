<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'label',
        'full_name',
        'phone',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'is_default',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_default' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'full_address',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the user who owns this address
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get orders using this address
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'delivery_address_id');
    }

    // ==================== ACCESSORS ====================

    /**
     * Get full formatted address
     */
    public function getFullAddressAttribute()
    {
        $parts = array_filter([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    // ==================== QUERY SCOPES ====================

    /**
     * Scope for user's addresses
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for default address
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // ==================== METHODS ====================

    /**
     * Set this address as default
     */
    public function setAsDefault()
    {
        // Remove default flag from all user's addresses
        $this->user->addresses()->update(['is_default' => false]);

        // Set this as default
        $this->update(['is_default' => true]);
    }

    /**
     * Check if user owns this address
     */
    public function isOwnedBy($userId)
    {
        return $this->user_id == $userId;
    }

    /**
     * Check if address has coordinates
     */
    public function hasCoordinates()
    {
        return $this->latitude && $this->longitude;
    }
}
