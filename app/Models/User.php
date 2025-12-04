<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Models\DriverApplication;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
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
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
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

    /**
     * Boot method to set default values
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if (empty($user->user_type)) {
                $user->user_type = 'user';
            }
            if (empty($user->is_active)) {
                $user->is_active = true;
            }
        });

        // Delete profile picture when user is deleted
        static::deleting(function ($user) {
            if ($user->profile_picture && Storage::disk('public')->exists($user->profile_picture)) {
                Storage::disk('public')->delete($user->profile_picture);
            }
        });
    }

    /**
     * Relationships
     */

    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function emailVerifications()
    {
        return $this->hasMany(EmailVerification::class);
    }

    public function items()
    {
        return $this->hasMany(Item::class, 'seller_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function driverApplications()
    {
        return $this->hasMany(DriverApplication::class);
    }

    public function deliveriesAsDriver()
    {
        return $this->hasMany(Delivery::class, 'driver_id');
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Scopes
     */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    public function scopeCharities($query)
    {
        return $query->where('user_type', 'charity');
    }

    public function scopeDrivers($query)
    {
        return $query->where('is_driver', true)
                     ->where('driver_verified', true);
    }

    /**
     * Helper Methods
     */

    public function isCharity(): bool
    {
        return $this->user_type === 'charity';
    }

    public function isVerifiedDriver(): bool
    {
        return $this->is_driver && $this->driver_verified;
    }

    public function incrementLoginAttempts(): void
    {
        $this->increment('login_attempts');

        // Lock account after 5 failed attempts for 30 minutes
        if ($this->login_attempts >= 5) {
            $this->locked_until = now()->addMinutes(30);
            $this->save();
        }
    }

    public function resetLoginAttempts(): void
    {
        $this->login_attempts = 0;
        $this->locked_until = null;
        $this->save();
    }

    public function updateLastLogin(): void
    {
        $this->last_login_at = now();
        $this->save();
    }

    /**
     * Get profile picture URL
     */
    public function getProfilePictureUrlAttribute(): ?string
    {
        if ($this->profile_picture) {
            return Storage::disk('public')->url($this->profile_picture);
        }
        return null;
    }

    /**
     * Check if user is admin using Spatie roles
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Get full address string
     */
    public function getFullAddressAttribute(): ?string
    {
        if ($this->address) {
            return trim($this->address . ', ' . $this->city . ', ' . $this->country);
        }
        return null;
    }

    /**
     * Check if account is locked
     */
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
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
}
