<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'image_url',
        'display_order',
        'is_primary',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'is_primary' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public $timestamps = true;

    /**
     * Get the item that owns this image
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Scope for primary image
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Set as primary image
     */
    public function setAsPrimary()
    {
        // Remove primary flag from other images
        $this->item->images()->update(['is_primary' => false]);
        
        // Set this as primary
        $this->update(['is_primary' => true]);
    }
}