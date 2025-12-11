<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Favorite extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'item_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public $timestamps = true;

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the user who favorited
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the favorited item
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    // ==================== QUERY SCOPES ====================

    /**
     * Scope for user's favorites
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for specific item
     */
    public function scopeForItem($query, $itemId)
    {
        return $query->where('item_id', $itemId);
    }
}