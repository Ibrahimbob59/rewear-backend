<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'status_display' => ucfirst(str_replace('_', ' ', $this->status)),

            // Location Information
            'pickup_address' => $this->pickup_address,
            'delivery_address' => $this->delivery_address,
            'pickup_coordinates' => [
                'latitude' => $this->pickup_latitude,
                'longitude' => $this->pickup_longitude,
            ],
            'delivery_coordinates' => [
                'latitude' => $this->delivery_latitude,
                'longitude' => $this->delivery_longitude,
            ],

            // Distance & Financial Info
            'distance_km' => $this->distance_km,
            'delivery_fee' => $this->delivery_fee,
            'driver_earning' => $this->driver_earning,
            'platform_fee' => $this->platform_fee,

            // Timeline
            'timeline' => [
                'created_at' => $this->created_at->toIso8601String(),
                'assigned_at' => $this->assigned_at?->toIso8601String(),
                'picked_up_at' => $this->picked_up_at?->toIso8601String(),
                'delivered_at' => $this->delivered_at?->toIso8601String(),
                'estimated_delivery_time' => $this->when($this->status === 'in_transit',
                    $this->picked_up_at?->addMinutes(45)->toIso8601String()
                ),
            ],

            // Driver Information
            'driver' => $this->when($this->relationLoaded('driver') && $this->driver, [
                'id' => $this->driver?->id,
                'name' => $this->driver?->name,
                'phone' => $this->driver?->phone,
                'vehicle_type' => $this->driver?->driverApplication()->where('status', 'approved')->first()?->vehicle_type,
            ]),

            // Order Information
            'order' => $this->when($this->relationLoaded('order'), [
                'id' => $this->order?->id,
                'order_number' => $this->order?->order_number,
                'total_amount' => $this->order?->total_amount,
                'item_price' => $this->order?->item_price,
                'is_donation' => $this->order?->item_price == 0,

                // Buyer Information
                'buyer' => $this->when($this->order && $this->order->relationLoaded('buyer'), [
                    'id' => $this->order?->buyer?->id,
                    'name' => $this->order?->buyer?->name,
                    'phone' => $this->order?->buyer?->phone,
                    'is_charity' => $this->order?->buyer?->hasRole('charity'),
                ]),

                // Seller Information
                'seller' => $this->when($this->order && $this->order->relationLoaded('seller'), [
                    'id' => $this->order?->seller?->id,
                    'name' => $this->order?->seller?->name,
                    'phone' => $this->order?->seller?->phone,
                    'city' => $this->order?->seller?->city,
                ]),

                // Item Information
                'item' => $this->when($this->order && $this->order->relationLoaded('item'), [
                    'id' => $this->order?->item?->id,
                    'title' => $this->order?->item?->title,
                    'category' => $this->order?->item?->category,
                    'condition' => $this->order?->item?->condition,
                    'is_donation' => $this->order?->item?->is_donation,
                    'donation_quantity' => $this->order?->item?->donation_quantity,
                    'primary_image' => $this->when(
                        $this->order &&
                        $this->order->item &&
                        $this->order->item->relationLoaded('images'),
                        $this->order?->item?->images?->where('is_primary', true)?->first()?->image_url ??
                        $this->order?->item?->images?->sortBy('display_order')?->first()?->image_url
                    ),
                ]),
            ]),

            // Notes & Additional Info
            'delivery_notes' => $this->delivery_notes,
            'failure_reason' => $this->when($this->status === 'failed', $this->failure_reason),

            // Status Indicators
            'can_be_picked_up' => $this->status === 'assigned',
            'can_be_delivered' => $this->status === 'in_transit',
            'is_completed' => $this->status === 'delivered',
            'is_failed' => $this->status === 'failed',

            // Duration Calculations
            'pickup_duration_minutes' => $this->when($this->assigned_at && $this->picked_up_at,
                fn() => $this->assigned_at->diffInMinutes($this->picked_up_at)
            ),
            'delivery_duration_minutes' => $this->when($this->picked_up_at && $this->delivered_at,
                fn() => $this->picked_up_at->diffInMinutes($this->delivered_at)
            ),
            'total_duration_minutes' => $this->when($this->assigned_at && $this->delivered_at,
                fn() => $this->assigned_at->diffInMinutes($this->delivered_at)
            ),
        ];
    }
}
