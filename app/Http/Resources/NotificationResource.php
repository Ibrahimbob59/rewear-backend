<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
            'is_read' => $this->is_read,
            'icon' => $this->icon,
            'color' => $this->color,
            'time_ago' => $this->time_ago,
            'is_recent' => $this->isRecent(),
            'created_at' => $this->created_at->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
            'action_url' => $this->when(!empty($this->data['order_id']), function () {
                return match($this->type) {
                    'order_placed', 'order_confirmed', 'order_delivered' => '/orders/' . $this->data['order_id'],
                    'delivery_assigned', 'delivery_completed' => '/deliveries/' . ($this->data['delivery_id'] ?? ''),
                    'donation_accepted', 'donation_offered' => '/charity/donations',
                    'driver_approved', 'driver_rejected' => '/driver/application',
                    default => null,
                };
            }),
        ];
    }
}
