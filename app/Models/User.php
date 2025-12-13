<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
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

    // ==================== JWT METHODS ====================

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

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

    /**
     * Get items listed by this user (as seller)
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'seller_id');
    }

    /**
     * Get orders where user is the buyer
     */
    public function purchasedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    /**
     * Get orders where user is the seller
     */
    public function soldOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'seller_id');
    }

    /**
     * Get user's delivery addresses
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Get user's favorites
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * Get deliveries assigned to this user (as driver)
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'driver_id');
    }

    /**
     * Get user's notifications
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get driver application
     */
    public function driverApplication(): HasMany
    {
        return $this->hasMany(DriverApplication::class);
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

    /**
     * Check if user is a verified driver
     */
    public function isVerifiedDriver(): bool
    {
        return $this->is_driver && $this->driver_verified;
    }

    /**
     * Get user's default address
     */
    public function getDefaultAddress(): ?Address
    {
        return $this->addresses()->where('is_default', true)->first();
    }

    /**
     * Get total items sold
     */
    public function getTotalItemsSoldAttribute(): int
    {
        return $this->soldOrders()->whereIn('status', ['delivered', 'completed'])->count();
    }

    /**
     * Get total items purchased
     */
    public function getTotalItemsPurchasedAttribute(): int
    {
        return $this->purchasedOrders()->whereIn('status', ['delivered', 'completed'])->count();
    }
}
