<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the user this notification belongs to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ==================== QUERY SCOPES ====================

    /**
     * Scope for unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope for read notifications
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope for specific notification type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for recent notifications
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ==================== HELPER METHODS ====================

    /**
     * Mark notification as read
     */
    public function markAsRead(): void
    {
        if (!$this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }
    }

    /**
     * Check if notification is recent (within 24 hours)
     */
    public function isRecent(): bool
    {
        return $this->created_at->gt(now()->subHours(24));
    }

    /**
     * Get formatted time ago
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Get notification icon based on type
     */
    public function getIconAttribute(): string
    {
        return match($this->type) {
            'order_placed' => 'shopping-bag',
            'order_confirmed' => 'check-circle',
            'order_delivered' => 'truck',
            'item_sold' => 'dollar-sign',
            'donation_accepted' => 'heart',
            'delivery_assigned' => 'user-check',
            'driver_approved' => 'shield-check',
            'driver_rejected' => 'shield-x',
            'general' => 'bell',
            default => 'info',
        };
    }

    /**
     * Get notification color based on type
     */
    public function getColorAttribute(): string
    {
        return match($this->type) {
            'order_placed', 'order_confirmed' => 'green',
            'order_delivered', 'item_sold' => 'blue',
            'donation_accepted', 'donation_offered' => 'purple',
            'delivery_assigned', 'driver_approved' => 'emerald',
            'driver_rejected' => 'red',
            'general' => 'gray',
            default => 'blue',
        };
    }
}
