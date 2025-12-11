<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isBuyer = $this->buyer_id === $user?->id;
        $isSeller = $this->seller_id === $user?->id;
        
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            
            // Item
            'item' => [
                'id' => $this->item->id,
                'title' => $this->item->title,
                'main_image' => $this->item->main_image,
                'size' => $this->item->size,
                'condition' => $this->item->condition,
                'is_donation' => $this->item->is_donation,
            ],
            
            // Buyer (show to seller only)
            'buyer' => $this->when($isSeller || !$user, [
                'id' => $this->buyer->id,
                'name' => $this->buyer->name,
                'phone' => $this->buyer->phone,
            ]),
            
            // Seller (show to buyer only)
            'seller' => $this->when($isBuyer || !$user, [
                'id' => $this->seller->id,
                'name' => $this->seller->name,
                'phone' => $this->seller->phone,
            ]),
            
            // Delivery address
            'delivery_address' => $this->when($this->deliveryAddress, [
                'id' => $this->deliveryAddress->id,
                'full_name' => $this->deliveryAddress->full_name,
                'phone' => $this->deliveryAddress->phone,
                'full_address' => $this->deliveryAddress->full_address,
                'city' => $this->deliveryAddress->city,
            ]),
            
            // Pricing
            'item_price' => $this->item_price,
            'delivery_fee' => $this->delivery_fee,
            'total_amount' => $this->total_amount,
            'driver_earnings' => $this->driver_earnings,
            'platform_commission' => $this->platform_commission,
            
            // Status
            'status' => $this->status,
            'status_label' => $this->status_label,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            
            // Delivery
            'delivery' => $this->when($this->delivery, [
                'id' => $this->delivery->id,
                'status' => $this->delivery->status,
                'driver' => $this->when($this->delivery->driver, [
                    'id' => $this->delivery->driver->id,
                    'name' => $this->delivery->driver->name,
                    'phone' => $this->delivery->driver->phone,
                ]),
            ]),
            
            // User role in this order
            'user_role' => $this->when($user, function () use ($isBuyer, $isSeller) {
                if ($isBuyer) return 'buyer';
                if ($isSeller) return 'seller';
                return null;
            }),
            
            // Actions available
            'can_cancel' => $this->when($user, $isBuyer && $this->canBeCancelled()),
            'can_confirm' => $this->when($user, $isSeller && $this->canBeConfirmed()),
            
            // Timestamps
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
