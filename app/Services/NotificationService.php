<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send order created notification to seller
     * 
     * @param Order $order
     * @return Notification|null
     */
    public function sendOrderCreatedNotification(Order $order): ?Notification
    {
        try {
            return $order->seller->notify(
                'New Order Received',
                "You have received a new order #{$order->order_number} for {$order->item->title}.",
                'order',
                "/orders/{$order->id}",
                [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'item_title' => $order->item->title,
                    'buyer_name' => $order->buyer->name,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to send order created notification: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send order confirmed notification to buyer
     * 
     * @param Order $order
     * @return Notification|null
     */
    public function sendOrderConfirmedNotification(Order $order): ?Notification
    {
        try {
            return $order->buyer->notify(
                'Order Confirmed',
                "Your order #{$order->order_number} has been confirmed by the seller.",
                'order',
                "/orders/{$order->id}",
                [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to send order confirmed notification: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send order cancelled notification to seller
     * 
     * @param Order $order
     * @return Notification|null
     */
    public function sendOrderCancelledNotification(Order $order): ?Notification
    {
        try {
            return $order->seller->notify(
                'Order Cancelled',
                "Order #{$order->order_number} has been cancelled by the buyer.",
                'order',
                "/orders/{$order->id}",
                [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'reason' => $order->cancellation_reason,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to send order cancelled notification: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send delivery assigned notification to buyer
     * 
     * @param Order $order
     * @return Notification|null
     */
    public function sendDeliveryAssignedNotification(Order $order): ?Notification
    {
        try {
            $driverName = $order->delivery?->driver?->name ?? 'a driver';
            
            return $order->buyer->notify(
                'Delivery Assigned',
                "Your order #{$order->order_number} has been assigned to {$driverName}.",
                'delivery',
                "/orders/{$order->id}",
                [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'driver_name' => $driverName,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to send delivery assigned notification: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send order in delivery notification to buyer
     * 
     * @param Order $order
     * @return Notification|null
     */
    public function sendOrderInDeliveryNotification(Order $order): ?Notification
    {
        try {
            return $order->buyer->notify(
                'Order In Delivery',
                "Your order #{$order->order_number} is now being delivered.",
                'delivery',
                "/orders/{$order->id}",
                [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to send order in delivery notification: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send order delivered notification to buyer
     * 
     * @param Order $order
     * @return Notification|null
     */
    public function sendOrderDeliveredNotification(Order $order): ?Notification
    {
        try {
            return $order->buyer->notify(
                'Order Delivered',
                "Your order #{$order->order_number} has been delivered. Please confirm receipt.",
                'delivery',
                "/orders/{$order->id}",
                [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to send order delivered notification: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send order completed notification to seller
     * 
     * @param Order $order
     * @return Notification|null
     */
    public function sendOrderCompletedNotification(Order $order): ?Notification
    {
        try {
            return $order->seller->notify(
                'Order Completed',
                "Order #{$order->order_number} has been completed.",
                'order',
                "/orders/{$order->id}",
                [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to send order completed notification: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send item favorited notification to seller (optional)
     * 
     * @param User $seller
     * @param User $user
     * @param string $itemTitle
     * @return Notification|null
     */
    public function sendItemFavoritedNotification(User $seller, User $user, string $itemTitle): ?Notification
    {
        try {
            return $seller->notify(
                'Item Favorited',
                "{$user->name} favorited your item: {$itemTitle}",
                'system',
                null,
                [
                    'user_name' => $user->name,
                    'item_title' => $itemTitle,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to send item favorited notification: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send custom notification
     * 
     * @param User $user
     * @param string $title
     * @param string $message
     * @param string $type
     * @param string|null $actionUrl
     * @param array|null $data
     * @return Notification|null
     */
    public function send(User $user, string $title, string $message, string $type = 'system', ?string $actionUrl = null, ?array $data = null): ?Notification
    {
        try {
            return $user->notify($title, $message, $type, $actionUrl, $data);
        } catch (\Exception $e) {
            Log::error('Failed to send notification: ' . $e->getMessage());
            return null;
        }
    }
}
