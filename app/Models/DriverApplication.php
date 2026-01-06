<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'full_name',
        'phone',
        'email',
        'address',
        'city',
        'vehicle_type',
        'id_document_url',
        'driving_license_url',
        'vehicle_registration_url',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Available statuses
    const STATUS_PENDING = 'pending';
    const STATUS_UNDER_REVIEW = 'under_review';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    // Available vehicle types
    const VEHICLE_CAR = 'car';
    const VEHICLE_MOTORCYCLE = 'motorcycle';
    const VEHICLE_BICYCLE = 'bicycle';

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the user who submitted this application
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin who reviewed this application
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ==================== QUERY SCOPES ====================

    /**
     * Scope for pending applications
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for approved applications
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope for rejected applications
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope for under review applications
     */
    public function scopeUnderReview($query)
    {
        return $query->where('status', self::STATUS_UNDER_REVIEW);
    }

    /**
     * Scope for specific vehicle type
     */
    public function scopeVehicleType($query, string $type)
    {
        return $query->where('vehicle_type', $type);
    }

    /**
     * Scope for recent applications
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ==================== HELPER METHODS ====================

    /**
     * Check if application is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if application is approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if application is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if application is under review
     */
    public function isUnderReview(): bool
    {
        return $this->status === self::STATUS_UNDER_REVIEW;
    }

    /**
     * Get status badge class for UI
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'bg-yellow-100 text-yellow-800',
            self::STATUS_UNDER_REVIEW => 'bg-blue-100 text-blue-800',
            self::STATUS_APPROVED => 'bg-green-100 text-green-800',
            self::STATUS_REJECTED => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get status display name
     */
    public function getStatusNameAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pending Review',
            self::STATUS_UNDER_REVIEW => 'Under Review',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get vehicle type display name
     */
    public function getVehicleTypeNameAttribute(): string
    {
        return match($this->vehicle_type) {
            self::VEHICLE_CAR => 'Car',
            self::VEHICLE_MOTORCYCLE => 'Motorcycle',
            self::VEHICLE_BICYCLE => 'Bicycle',
            default => ucfirst($this->vehicle_type),
        };
    }

    /**
     * Check if all required documents are uploaded
     */
    public function hasAllDocuments(): bool
    {
        return !empty($this->id_document_url) &&
            !empty($this->driving_license_url);
        // vehicle_registration_url is optional for bicycles
    }

    /**
     * Get missing documents list
     */
    public function getMissingDocuments(): array
    {
        $missing = [];

        if (empty($this->id_document_url)) {
            $missing[] = 'ID Document';
        }

        if (empty($this->driving_license_url)) {
            $missing[] = 'Driving License';
        }

        if ($this->vehicle_type !== self::VEHICLE_BICYCLE && empty($this->vehicle_registration_url)) {
            $missing[] = 'Vehicle Registration';
        }

        return $missing;
    }

    /**
     * Get application completeness percentage
     */
    public function getCompletenessPercentage(): float
    {
        $requiredFields = [
            'full_name', 'phone', 'email', 'address', 'city', 'vehicle_type'
        ];

        $requiredDocuments = ['id_document_url', 'driving_license_url'];

        if ($this->vehicle_type !== self::VEHICLE_BICYCLE) {
            $requiredDocuments[] = 'vehicle_registration_url';
        }

        $totalRequired = count($requiredFields) + count($requiredDocuments);
        $completed = 0;

        // Check required fields
        foreach ($requiredFields as $field) {
            if (!empty($this->$field)) {
                $completed++;
            }
        }

        // Check required documents
        foreach ($requiredDocuments as $document) {
            if (!empty($this->$document)) {
                $completed++;
            }
        }

        return round(($completed / $totalRequired) * 100, 2);
    }

    /**
     * Get review duration in days
     */
    public function getReviewDurationAttribute(): ?int
    {
        if (!$this->reviewed_at) {
            return null;
        }

        return $this->created_at->diffInDays($this->reviewed_at);
    }

    /**
     * Get days since application
     */
    public function getDaysSinceApplicationAttribute(): int
    {
        return $this->created_at->diffInDays(now());
    }

    /**
     * Get all possible statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
        ];
    }

    /**
     * Get all possible vehicle types
     */
    public static function getVehicleTypes(): array
    {
        return [
            self::VEHICLE_CAR,
            self::VEHICLE_MOTORCYCLE,
            self::VEHICLE_BICYCLE,
        ];
    }
}
