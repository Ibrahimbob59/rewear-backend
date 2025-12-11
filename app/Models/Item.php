<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

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
        'status',
        'views_count',
        'sold_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_donation' => 'boolean',
        'views_count' => 'integer',
        'sold_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    protected $appends = [
        'is_favorited',
        'favorites_count',
        'main_image',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the seller who owns this item
     */
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Get all images for this item
     */
    public function images()
    {
        return $this->hasMany(ItemImage::class)->orderBy('display_order');
    }

    /**
     * Get the first/main image
     */
    public function mainImage()
    {
        return $this->hasOne(ItemImage::class)->orderBy('display_order');
    }

    /**
     * Get all users who favorited this item
     */
    public function favoritedBy()
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }

    /**
     * Get favorites for this item
     */
    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * Get orders for this item
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // ==================== ACCESSORS ====================

    /**
     * Check if current user has favorited this item
     */
    public function getIsFavoritedAttribute()
    {
        if (!auth()->check()) {
            return false;
        }

        return $this->favoritedBy()->where('user_id', auth()->id())->exists();
    }

    /**
     * Get favorites count
     */
    public function getFavoritesCountAttribute()
    {
        return $this->favorites()->count();
    }

    /**
     * Get main image URL
     */
    public function getMainImageAttribute()
    {
        return $this->mainImage?->image_url;
    }

    /**
     * Get formatted price (null for donations)
     */
    public function getFormattedPriceAttribute()
    {
        if ($this->is_donation) {
            return 'Donation';
        }

        return $this->price ? '$' . number_format($this->price, 2) : '$0.00';
    }

    // ==================== QUERY SCOPES ====================

    /**
     * Scope for available items (not sold/cancelled/pending)
     */
    public function scopeAvailable(Builder $query)
    {
        return $query->where('status', 'available');
    }

    /**
     * Scope for items belonging to a specific seller
     */
    public function scopeBySeller(Builder $query, $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }

    /**
     * Scope for filtering by category
     */
    public function scopeByCategory(Builder $query, $category)
    {
        if (empty($category)) {
            return $query;
        }

        return $query->where('category', $category);
    }

    /**
     * Scope for filtering by price range
     */
    public function scopeByPriceRange(Builder $query, $minPrice = null, $maxPrice = null)
    {
        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }

        return $query;
    }

    /**
     * Scope for filtering by size
     */
    public function scopeBySize(Builder $query, $size)
    {
        if (empty($size)) {
            return $query;
        }

        return $query->where('size', $size);
    }

    /**
     * Scope for filtering by condition
     */
    public function scopeByCondition(Builder $query, $condition)
    {
        if (empty($condition)) {
            return $query;
        }

        return $query->where('condition', $condition);
    }

    /**
     * Scope for filtering by gender
     */
    public function scopeByGender(Builder $query, $gender)
    {
        if (empty($gender)) {
            return $query;
        }

        return $query->where('gender', $gender);
    }

    /**
     * Scope for donation items
     */
    public function scopeDonations(Builder $query)
    {
        return $query->where('is_donation', true);
    }

    /**
     * Scope for sale items (not donations)
     */
    public function scopeForSale(Builder $query)
    {
        return $query->where('is_donation', false);
    }

    /**
     * Scope for searching by keyword
     */
    public function scopeSearch(Builder $query, $keyword)
    {
        if (empty($keyword)) {
            return $query;
        }

        return $query->where(function ($q) use ($keyword) {
            $q->where('title', 'ILIKE', "%{$keyword}%")
              ->orWhere('description', 'ILIKE', "%{$keyword}%")
              ->orWhere('brand', 'ILIKE', "%{$keyword}%")
              ->orWhere('color', 'ILIKE', "%{$keyword}%");
        });
    }

    /**
     * Scope for items near a location (using Haversine formula)
     * 
     * @param Builder $query
     * @param float $latitude User's latitude
     * @param float $longitude User's longitude
     * @param float $radiusKm Radius in kilometers
     */
    public function scopeNearby(Builder $query, $latitude, $longitude, $radiusKm = 50)
    {
        // Haversine formula for distance calculation
        // Distance in kilometers
        $haversine = "(6371 * acos(cos(radians(?)) * cos(radians(users.location_lat)) * cos(radians(users.location_lng) - radians(?)) + sin(radians(?)) * sin(radians(users.location_lat))))";

        return $query->join('users', 'items.seller_id', '=', 'users.id')
            ->whereNotNull('users.location_lat')
            ->whereNotNull('users.location_lng')
            ->whereRaw("{$haversine} <= ?", [$latitude, $longitude, $latitude, $radiusKm])
            ->select('items.*')
            ->selectRaw("{$haversine} AS distance", [$latitude, $longitude, $latitude]);
    }

    /**
     * Scope for sorting
     */
    public function scopeSorted(Builder $query, $sort = 'newest')
    {
        switch ($sort) {
            case 'price_low':
                return $query->orderBy('price', 'asc');
            
            case 'price_high':
                return $query->orderBy('price', 'desc');
            
            case 'distance':
                // Distance should be already calculated in nearby scope
                return $query->orderBy('distance', 'asc');
            
            case 'oldest':
                return $query->orderBy('created_at', 'asc');
            
            case 'newest':
            default:
                return $query->orderBy('created_at', 'desc');
        }
    }

    // ==================== METHODS ====================

    /**
     * Increment view count
     */
    public function incrementViews()
    {
        $this->increment('views_count');
    }

    /**
     * Mark item as sold
     */
    public function markAsSold()
    {
        $this->update([
            'status' => 'sold',
            'sold_at' => now(),
        ]);
    }

    /**
     * Mark item as donated
     */
    public function markAsDonated()
    {
        $this->update([
            'status' => 'donated',
            'sold_at' => now(),
        ]);
    }

    /**
     * Mark item as available
     */
    public function markAsAvailable()
    {
        $this->update([
            'status' => 'available',
            'sold_at' => null,
        ]);
    }

    /**
     * Mark item as pending (during order)
     */
    public function markAsPending()
    {
        $this->update([
            'status' => 'pending',
        ]);
    }

    /**
     * Cancel item (make unavailable)
     */
    public function cancel()
    {
        $this->update([
            'status' => 'cancelled',
        ]);
    }

    /**
     * Check if item is available for purchase
     */
    public function isAvailable()
    {
        return $this->status === 'available';
    }

    /**
     * Check if user owns this item
     */
    public function isOwnedBy($userId)
    {
        return $this->seller_id == $userId;
    }

    /**
     * Check if item can be edited/deleted
     */
    public function canBeModified()
    {
        return in_array($this->status, ['available', 'cancelled']);
    }
}