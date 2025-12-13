<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,

            // Pricing
            'item_price' => $this->item_price,
            'delivery_fee' => $this->delivery_fee,
            'total_amount' => $this->total_amount,

            // Timestamps
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            // Relationships
            'buyer' => [
                'id' => $this->buyer->id,
                'name' => $this->buyer->name,
                'email' => $this->buyer->email,
                'city' => $this->buyer->city,
            ],

            'seller' => [
                'id' => $this->seller->id,
                'name' => $this->seller->name,
                'email' => $this->seller->email,
                'city' => $this->seller->city,
            ],

            'item' => [
                'id' => $this->item->id,
                'title' => $this->item->title,
                'description' => $this->item->description,
                'category' => $this->item->category,
                'size' => $this->item->size,
                'condition' => $this->item->condition,
                'is_donation' => $this->item->is_donation,
                'primary_image' => $this->item->images->where('is_primary', true)->first()?->image_url
                    ?? $this->item->images->sortBy('display_order')->first()?->image_url,
            ],

            'delivery_address' => [
                'id' => $this->deliveryAddress->id,
                'full_name' => $this->deliveryAddress->full_name,
                'phone' => $this->deliveryAddress->phone,
                'address_line1' => $this->deliveryAddress->address_line1,
                'address_line2' => $this->deliveryAddress->address_line2,
                'city' => $this->deliveryAddress->city,
                'state' => $this->deliveryAddress->state,
                'postal_code' => $this->deliveryAddress->postal_code,
                'country' => $this->deliveryAddress->country,
            ],

            'delivery' => $this->when($this->relationLoaded('delivery') && $this->delivery, [
                'id' => $this->delivery?->id,
                'status' => $this->delivery?->status,
                'distance_km' => $this->delivery?->distance_km,
                'driver' => $this->when($this->delivery && $this->delivery->driver, [
                    'id' => $this->delivery?->driver?->id,
                    'name' => $this->delivery?->driver?->name,
                    'phone' => $this->delivery?->driver?->phone,
                ]),
                'assigned_at' => $this->delivery?->assigned_at?->toIso8601String(),
                'picked_up_at' => $this->delivery?->picked_up_at?->toIso8601String(),
                'delivered_at' => $this->delivery?->delivered_at?->toIso8601String(),
            ]),
        ];
    }
}
