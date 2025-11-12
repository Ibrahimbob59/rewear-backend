<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_type',
        'email',
        'email_verified',
        'email_verified_at',
        'phone',
        'phone_verified',
        'password',
        'full_name',
        'profile_picture',
        'bio',
        'location_lat',
        'location_lng',
        'city',
        // Driver fields
        'is_driver',
        'driver_verified',
        'driver_vehicle_type',
        'driver_earnings',
        'driver_pending_payout',
        'driver_total_deliveries',
        // Charity fields
        'charity_organization_name',
        'charity_items_received',
        'charity_people_helped',
        // Statistics
        'items_listed',
        'items_sold',
        'items_bought',
        // Auth fields
        'last_login_at',
        'login_attempts',
        'locked_until',
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

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified' => 'boolean',
            'email_verified_at' => 'datetime',
            'phone_verified' => 'boolean',
            'is_driver' => 'boolean',
            'driver_verified' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'location_lat' => 'decimal:8',
            'location_lng' => 'decimal:8',
            'driver_earnings' => 'decimal:2',
            'driver_pending_payout' => 'decimal:2',
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [
            'email' => $this->email,
            'user_type' => $this->user_type,
            'is_driver' => $this->is_driver,
        ];
    }

    /**
     * Get the user's refresh tokens.
     */
    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    /**
     * Get active (non-revoked, non-expired) refresh tokens.
     */
    public function activeRefreshTokens(): HasMany
    {
        return $this->refreshTokens()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Check if user account is locked.
     */
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Check if email is verified.
     */
    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Check if user is a charity.
     */
    public function isCharity(): bool
    {
        return $this->user_type === 'charity';
    }

    /**
     * Check if user is a verified driver.
     */
    public function isVerifiedDriver(): bool
    {
        return $this->is_driver && $this->driver_verified;
    }

    /**
     * Increment login attempts.
     */
    public function incrementLoginAttempts(): void
    {
        $this->increment('login_attempts');

        // Lock account after 5 failed attempts for 15 minutes
        if ($this->login_attempts >= 5) {
            $this->update([
                'locked_until' => now()->addMinutes(15)
            ]);
        }
    }

    /**
     * Reset login attempts.
     */
    public function resetLoginAttempts(): void
    {
        $this->update([
            'login_attempts' => 0,
            'locked_until' => null,
        ]);
    }

    /**
     * Update last login timestamp.
     */
    public function updateLastLogin(): void
    {
        $this->update([
            'last_login_at' => now(),
        ]);
    }
}
