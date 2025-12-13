<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the item this image belongs to
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Set this image as primary
     */
    public function setAsPrimary(): void
    {
        // Unset all other primary images for this item
        ItemImage::where('item_id', $this->item_id)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        // Set this one as primary
        $this->update(['is_primary' => true]);
    }
}
