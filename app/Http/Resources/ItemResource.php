<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\Helpers\DistanceCalculator;

class ItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'size' => $this->size,
            'condition' => $this->condition,
            'gender' => $this->gender,
            'brand' => $this->brand,
            'color' => $this->color,
            'price' => $this->price,
            'formatted_price' => $this->formatted_price,
            'is_donation' => $this->is_donation,
            'status' => $this->status,
            'views_count' => $this->views_count,
            
            // Images
            'images' => $this->images->map(function ($image) {
                return [
                    'id' => $image->id,
                    'url' => $image->image_url,
                    'display_order' => $image->display_order,
                    'is_primary' => $image->is_primary,
                ];
            }),
            'main_image' => $this->main_image,
            
            // Seller info
            'seller' => [
                'id' => $this->seller->id,
                'name' => $this->seller->name,
                'location' => $this->when($this->seller->hasLocation(), [
                    'lat' => $this->seller->location_lat,
                    'lng' => $this->seller->location_lng,
                ]),
                'member_since' => $this->seller->created_at?->format('Y-m-d'),
            ],
            
            // User-specific data
            'is_favorited' => $this->when($user, $this->is_favorited),
            'favorites_count' => $this->favorites_count,
            'is_own_item' => $this->when($user, $this->seller_id === $user?->id),
            
            // Distance (if user has location)
            'distance' => $this->when(
                $user && $user->hasLocation() && isset($this->distance),
                function () {
                    return [
                        'km' => round($this->distance, 1),
                        'formatted' => DistanceCalculator::formatDistance($this->distance),
                    ];
                }
            ),
            
            // Timestamps
            'sold_at' => $this->sold_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
