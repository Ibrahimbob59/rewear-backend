<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'user_type',
        'profile_picture',
        'bio',
        'is_driver',
        'driver_verified',
        'driver_verified_at',
        'organization_name',
        'organization_description',
        'registration_number',
        'tax_id',
        'address',
        'city',
        'country',
        'latitude',
        'longitude',
        'email_verified_at',
        'is_active',
        'last_login_at',
        'login_attempts',
        'locked_until',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'driver_verified_at' => 'datetime',
        'locked_until' => 'datetime',
        'is_driver' => 'boolean',
        'driver_verified' => 'boolean',
        'is_active' => 'boolean',
        'login_attempts' => 'integer',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'password' => 'hashed',
    ];

    // ==================== AUTH RELATIONSHIPS ====================

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function emailVerifications(): HasMany
    {
        return $this->hasMany(EmailVerification::class, 'email', 'email');
    }

    // ==================== MARKETPLACE RELATIONSHIPS ====================

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'seller_id');
    }

    public function purchasedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    public function soldOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'seller_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'driver_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function driverApplication(): HasMany
    {
        return $this->hasMany(DriverApplication::class);
    }

    public function activeDeliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'driver_id')
            ->whereIn('status', ['assigned', 'picked_up', 'in_transit']);
    }

    public function completedDeliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'driver_id')
            ->where('status', 'delivered');
    }

    // ==================== AUTH HELPER METHODS ====================

    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function incrementLoginAttempts(): void
    {
        $this->increment('login_attempts');

        if ($this->login_attempts >= 5) {
            $this->update(['locked_until' => now()->addMinutes(15)]);
        }
    }

    public function resetLoginAttempts(): void
    {
        $this->update([
            'login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
        ]);
    }

    // ==================== MARKETPLACE HELPER METHODS ====================

    public function isVerifiedDriver(): bool
    {
        return $this->is_driver && $this->driver_verified;
    }

    public function getDefaultAddress(): ?Address
    {
        return $this->addresses()->where('is_default', true)->first();
    }

    public function getTotalItemsSoldAttribute(): int
    {
        return $this->soldOrders()
            ->whereIn('status', ['delivered', 'completed'])
            ->count();
    }

    public function getTotalItemsPurchasedAttribute(): int
    {
        return $this->purchasedOrders()
            ->whereIn('status', ['delivered', 'completed'])
            ->count();
    }

    /**
     * Check if user can apply as driver
     */
    public function canApplyAsDriver(): bool
    {
        if ($this->driver_verified) {
            return false;
        }

        $pendingApplication = $this->driverApplication()
            ->whereIn('status', ['pending', 'under_review'])
            ->exists();

        return !$pendingApplication;
    }

    /**
     * Get driver statistics
     */
    public function getDriverStats(): array
    {
        $totalDeliveries = $this->completedDeliveries()->count();
        $totalEarnings = $this->completedDeliveries()->sum('driver_earning');
        $activeDeliveries = $this->activeDeliveries()->count();

        return [
            'total_deliveries' => $totalDeliveries,
            'total_earnings' => (float) $totalEarnings,
            'active_deliveries' => $activeDeliveries,
            'average_earning_per_delivery' => $totalDeliveries > 0 ? round($totalEarnings / $totalDeliveries, 2) : 0,
        ];
    }

    /**
     * Get charity impact statistics
     */
    public function getCharityStats(): array
    {
        if (!$this->hasRole('charity')) {
            return [];
        }

        $donationOrders = $this->purchasedOrders()->where('item_price', 0);
        $totalDonations = $donationOrders->count();
        $peopleHelped = $donationOrders->sum('people_helped') ?: 0;

        return [
            'total_donations_received' => $totalDonations,
            'total_people_helped' => $peopleHelped,
            'average_people_per_donation' => $totalDonations > 0 ? round($peopleHelped / $totalDonations, 1) : 0,
        ];
    }

    /**
     * Get unread notifications count
     */
    public function getUnreadNotificationsCountAttribute(): int
    {
        return $this->notifications()->where('is_read', false)->count();
    }

}
