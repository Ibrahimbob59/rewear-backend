<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
            'is_donation' => $this->is_donation,
            'status' => $this->status,
            'views_count' => $this->views_count,
            'sold_at' => $this->sold_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            // Relationships
            'seller' => [
                'id' => $this->seller->id,
                'name' => $this->seller->name,
                'email' => $this->seller->email,
                'city' => $this->seller->city,
            ],

            'images' => $this->images->map(function ($image) {
                return [
                    'id' => $image->id,
                    'url' => $image->image_url,
                    'display_order' => $image->display_order,
                    'is_primary' => $image->is_primary,
                ];
            }),

            // Computed fields
            'image_count' => $this->images->count(),
            'primary_image' => $this->images->where('is_primary', true)->first()?->image_url
                ?? $this->images->sortBy('display_order')->first()?->image_url,
        ];
    }
}
