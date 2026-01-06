<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverApplicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,
            'vehicle_type' => $this->vehicle_type,
            'vehicle_type_name' => $this->vehicle_type_name,
            'status' => $this->status,
            'status_name' => $this->status_name,
            'status_badge_class' => $this->status_badge_class,
            'rejection_reason' => $this->when($this->status === 'rejected', $this->rejection_reason),
            'documents' => [
                'id_document_url' => $this->id_document_url,
                'driving_license_url' => $this->driving_license_url,
                'vehicle_registration_url' => $this->vehicle_registration_url,
                'has_all_documents' => $this->hasAllDocuments(),
                'missing_documents' => $this->getMissingDocuments(),
            ],
            'completeness_percentage' => $this->getCompletenessPercentage(),
            'review_info' => [
                'reviewed_by' => $this->when($this->relationLoaded('reviewedBy') && $this->reviewedBy, [
                    'id' => $this->reviewedBy?->id,
                    'name' => $this->reviewedBy?->name,
                ]),
                'reviewed_at' => $this->reviewed_at?->toIso8601String(),
                'review_duration_days' => $this->review_duration,
            ],
            'timeline' => [
                'submitted_at' => $this->created_at->toIso8601String(),
                'days_since_application' => $this->days_since_application,
                'last_updated' => $this->updated_at->toIso8601String(),
            ],
            'user' => $this->when($this->relationLoaded('user'), [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
                'is_driver' => $this->user?->is_driver,
                'driver_verified' => $this->user?->driver_verified,
            ]),
        ];
    }
}
