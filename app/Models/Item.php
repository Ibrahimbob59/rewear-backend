<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'seller_id',
        'title',
        'description',
        'category',
        'size',
        'condition',
        'gender',
        'brand',
        'color',
        'price',
        'is_donation',
        'donation_quantity',
        'status',
        'views_count',
        'sold_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_donation' => 'boolean',
        'donation_quantity' => 'integer',           // NEW
        'views_count' => 'integer',
        'sold_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the seller (user) of this item
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Get the item images
     */
    public function images(): HasMany
    {
        return $this->hasMany(ItemImage::class)->orderBy('display_order');
    }

    /**
     * Get the orders for this item
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get users who favorited this item
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    // ==================== QUERY SCOPES ====================

    /**
     * Scope for available items only
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    /**
     * Scope for donation items
     */
    public function scopeDonations($query)
    {
        return $query->where('is_donation', true);
    }

    /**
     * Scope for sale items
     */
    public function scopeForSale($query)
    {
        return $query->where('is_donation', false);
    }

    /**
     * Scope for specific category
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for seller's items
     */
    public function scopeForSeller($query, int $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Check if item is available
     */
    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    /**
     * Mark item as pending
     */
    public function markAsPending(): void
    {
        $this->update(['status' => 'pending']);
    }

    /**
     * Mark item as sold
     */
    public function markAsSold(): void
    {
        $this->update([
            'status' => 'sold',
            'sold_at' => now(),
        ]);
    }

    /**
     * Mark item as donated
     */
    public function markAsDonated(): void
    {
        $this->update([
            'status' => 'donated',
            'sold_at' => now(),
        ]);
    }

    /**
     * Mark item as available again
     */
    public function markAsAvailable(): void
    {
        $this->update([
            'status' => 'available',
            'sold_at' => null,
        ]);
    }

    /**
     * Get primary image
     */
    public function getPrimaryImageAttribute(): ?string
    {
        return $this->images->where('is_primary', true)->first()?->image_url
            ?? $this->images->sortBy('display_order')->first()?->image_url;
    }

    /**
     * Check if user is the seller
     */
    public function isOwnedBy(int $userId): bool
    {
        return $this->seller_id === $userId;
    }

    /**
     * Check if item is favorited by user
     */
    public function isFavoritedBy(int $userId): bool
    {
        return $this->favorites()->where('user_id', $userId)->exists();
    }
    /**
     * Check if donation has available items
     */
    public function hasDonationAvailable(): bool
    {
        return $this->is_donation && $this->donation_quantity_available > 0;
    }

    /**
     * Decrease donation quantity
     */
    public function decrementDonationQuantity(int $amount = 1): void
    {
        if (!$this->is_donation) {
            return;
        }

        $this->decrement('donation_quantity_available', $amount);

        // If all donated, mark as donated
        if ($this->donation_quantity_available === 0) {
            $this->update(['status' => 'donated']);
        }
    }

    /**
     * Get donation percentage claimed
     */
    public function getDonationPercentageClaimedAttribute(): int
    {
        if (!$this->is_donation || $this->donation_quantity === 0) {
            return 0;
        }

        $claimed = $this->donation_quantity - $this->donation_quantity_available;
        return (int) (($claimed / $this->donation_quantity) * 100);
    }
}
