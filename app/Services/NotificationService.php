<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * Create a notification for a user
     *
     * @param int|User $user
     * @param string $type
     * @param string $title
     * @param string $message
     * @param array|null $data
     * @return Notification
     */
    public function createNotification($user, string $type, string $title, string $message, ?array $data = null): Notification
    {
        $userId = $user instanceof User ? $user->id : $user;

        $notification = Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'is_read' => false,
        ]);

        Log::info('Notification created', [
            'user_id' => $userId,
            'type' => $type,
            'notification_id' => $notification->id,
        ]);

        return $notification;
    }

    /**
     * Send notification to multiple users
     *
     * @param Collection|array $users
     * @param string $type
     * @param string $title
     * @param string $message
     * @param array|null $data
     * @return Collection
     */
    public function notifyMultipleUsers($users, string $type, string $title, string $message, ?array $data = null): Collection
    {
        $notifications = collect();

        foreach ($users as $user) {
            $notifications->push($this->createNotification($user, $type, $title, $message, $data));
        }

        return $notifications;
    }

    // ==================== ORDER NOTIFICATIONS ====================

    /**
     * Notify when order is placed
     *
     * @param \App\Models\Order $order
     */
    public function orderPlaced(\App\Models\Order $order): void
    {
        // Notify seller
        $this->createNotification(
            $order->seller_id,
            'order_placed',
            'New Order Received!',
            "You received an order for \"{$order->item->title}\" from {$order->buyer->name}.",
            [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'item_title' => $order->item->title,
                'buyer_name' => $order->buyer->name,
                'total_amount' => $order->total_amount,
            ]
        );

        // Notify buyer (confirmation)
        $this->createNotification(
            $order->buyer_id,
            'order_confirmed',
            'Order Placed Successfully!',
            "Your order #{$order->order_number} has been placed. We'll find a driver for delivery.",
            [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'item_title' => $order->item->title,
                'seller_name' => $order->seller->name,
                'total_amount' => $order->total_amount,
            ]
        );
    }

    /**
     * Notify when item is sold
     *
     * @param \App\Models\Item $item
     * @param \App\Models\Order $order
     */
    public function itemSold(\App\Models\Item $item, \App\Models\Order $order): void
    {
        $this->createNotification(
            $item->seller_id,
            'item_sold',
            'Item Sold!',
            "Your item \"{$item->title}\" has been sold to {$order->buyer->name}.",
            [
                'item_id' => $item->id,
                'order_id' => $order->id,
                'item_title' => $item->title,
                'buyer_name' => $order->buyer->name,
                'sale_price' => $order->item_price,
            ]
        );
    }

    // ==================== DELIVERY NOTIFICATIONS ====================

    /**
     * Notify when delivery is assigned to driver
     *
     * @param \App\Models\Delivery $delivery
     */
    public function deliveryAssigned(\App\Models\Delivery $delivery): void
    {
        // Notify driver
        $this->createNotification(
            $delivery->driver_id,
            'delivery_assigned',
            'New Delivery Assignment!',
            "You've been assigned a delivery. Pickup from {$delivery->pickup_address}.",
            [
                'delivery_id' => $delivery->id,
                'order_id' => $delivery->order_id,
                'order_number' => $delivery->order->order_number,
                'pickup_address' => $delivery->pickup_address,
                'delivery_address' => $delivery->delivery_address,
                'delivery_fee' => $delivery->delivery_fee,
                'driver_earning' => $delivery->driver_earning,
            ]
        );

        // Notify buyer
        $this->createNotification(
            $delivery->order->buyer_id,
            'delivery_assigned',
            'Driver Assigned!',
            "A driver has been assigned to your order #{$delivery->order->order_number}.",
            [
                'delivery_id' => $delivery->id,
                'order_id' => $delivery->order_id,
                'order_number' => $delivery->order->order_number,
                'driver_name' => $delivery->driver->name,
                'estimated_pickup' => now()->addMinutes(30)->toIso8601String(),
            ]
        );

        // Notify seller
        $this->createNotification(
            $delivery->order->seller_id,
            'delivery_assigned',
            'Driver Coming for Pickup!',
            "A driver will come to pickup your item for order #{$delivery->order->order_number}.",
            [
                'delivery_id' => $delivery->id,
                'order_id' => $delivery->order_id,
                'order_number' => $delivery->order->order_number,
                'driver_name' => $delivery->driver->name,
                'estimated_pickup' => now()->addMinutes(30)->toIso8601String(),
            ]
        );
    }

    /**
     * Notify when item is picked up
     *
     * @param \App\Models\Delivery $delivery
     */
    public function itemPickedUp(\App\Models\Delivery $delivery): void
    {
        // Notify buyer
        $this->createNotification(
            $delivery->order->buyer_id,
            'item_picked_up',
            'Item Picked Up!',
            "Your item has been picked up and is on the way to you.",
            [
                'delivery_id' => $delivery->id,
                'order_id' => $delivery->order_id,
                'order_number' => $delivery->order->order_number,
                'driver_name' => $delivery->driver->name,
                'estimated_delivery' => now()->addMinutes($delivery->estimated_delivery_time ?? 45)->toIso8601String(),
            ]
        );

        // Notify seller
        $this->createNotification(
            $delivery->order->seller_id,
            'item_picked_up',
            'Item Picked Up Successfully!',
            "Your item has been picked up by the driver for delivery.",
            [
                'delivery_id' => $delivery->id,
                'order_id' => $delivery->order_id,
                'order_number' => $delivery->order->order_number,
                'driver_name' => $delivery->driver->name,
            ]
        );
    }

    /**
     * Notify when delivery is completed
     *
     * @param \App\Models\Delivery $delivery
     */
    public function deliveryCompleted(\App\Models\Delivery $delivery): void
    {
        // Notify buyer
        $this->createNotification(
            $delivery->order->buyer_id,
            'order_delivered',
            'Order Delivered!',
            "Your order #{$delivery->order->order_number} has been delivered successfully!",
            [
                'delivery_id' => $delivery->id,
                'order_id' => $delivery->order_id,
                'order_number' => $delivery->order->order_number,
                'delivered_at' => $delivery->delivered_at->toIso8601String(),
                'total_paid' => $delivery->order->total_amount,
            ]
        );

        // Notify seller
        $this->createNotification(
            $delivery->order->seller_id,
            'order_delivered',
            'Order Completed!',
            "Your item has been successfully delivered to the buyer.",
            [
                'delivery_id' => $delivery->id,
                'order_id' => $delivery->order_id,
                'order_number' => $delivery->order->order_number,
                'delivered_at' => $delivery->delivered_at->toIso8601String(),
                'sale_amount' => $delivery->order->item_price,
            ]
        );

        // Notify driver
        $this->createNotification(
            $delivery->driver_id,
            'delivery_completed',
            'Delivery Completed!',
            "Delivery completed successfully. You earned $" . $delivery->driver_earning . "!",
            [
                'delivery_id' => $delivery->id,
                'order_id' => $delivery->order_id,
                'order_number' => $delivery->order->order_number,
                'earning' => $delivery->driver_earning,
                'delivered_at' => $delivery->delivered_at->toIso8601String(),
            ]
        );
    }

    // ==================== DRIVER NOTIFICATIONS ====================

    /**
     * Notify when driver application is approved
     *
     * @param \App\Models\DriverApplication $application
     */
    public function driverApproved(\App\Models\DriverApplication $application): void
    {
        $this->createNotification(
            $application->user_id,
            'driver_approved',
            'Driver Application Approved!',
            'Congratulations! Your driver application has been approved. You can now start accepting deliveries.',
            [
                'application_id' => $application->id,
                'approved_at' => $application->reviewed_at->toIso8601String(),
                'reviewer' => $application->reviewedBy->name ?? 'Admin',
            ]
        );
    }

    /**
     * Notify when driver application is rejected
     *
     * @param \App\Models\DriverApplication $application
     */
    public function driverRejected(\App\Models\DriverApplication $application): void
    {
        $this->createNotification(
            $application->user_id,
            'driver_rejected',
            'Driver Application Update',
            'Your driver application has been reviewed. Please check the details and apply again if needed.',
            [
                'application_id' => $application->id,
                'rejection_reason' => $application->rejection_reason,
                'reviewed_at' => $application->reviewed_at->toIso8601String(),
            ]
        );
    }

    // ==================== CHARITY NOTIFICATIONS ====================

    /**
     * Notify charity about new donation
     *
     * @param \App\Models\Item $item
     * @param \App\Models\User $charity
     */
    public function donationOffered(\App\Models\Item $item, \App\Models\User $charity): void
    {
        $this->createNotification(
            $charity->id,
            'donation_offered',
            'New Donation Available!',
            "A new donation is available: \"{$item->title}\" from {$item->seller->name}.",
            [
                'item_id' => $item->id,
                'item_title' => $item->title,
                'donor_name' => $item->seller->name,
                'condition' => $item->condition,
                'category' => $item->category,
                'quantity' => $item->donation_quantity,
            ]
        );
    }

    /**
     * Notify when donation is accepted
     *
     * @param \App\Models\Item $item
     * @param \App\Models\Order $order
     */
    public function donationAccepted(\App\Models\Item $item, \App\Models\Order $order): void
    {
        // Notify donor
        $this->createNotification(
            $item->seller_id,
            'donation_accepted',
            'Donation Accepted!',
            "Your donation \"{$item->title}\" has been accepted by {$order->buyer->name}.",
            [
                'item_id' => $item->id,
                'order_id' => $order->id,
                'item_title' => $item->title,
                'charity_name' => $order->buyer->name,
                'order_number' => $order->order_number,
            ]
        );

        // Notify charity
        $this->createNotification(
            $order->buyer_id,
            'donation_confirmed',
            'Donation Confirmed!',
            "Donation pickup has been arranged for \"{$item->title}\".",
            [
                'item_id' => $item->id,
                'order_id' => $order->id,
                'item_title' => $item->title,
                'donor_name' => $item->seller->name,
                'order_number' => $order->order_number,
            ]
        );
    }

    // ==================== UTILITY METHODS ====================

    /**
     * Mark notification as read
     *
     * @param int $notificationId
     * @param int $userId
     * @return bool
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        return Notification::where('id', $notificationId)
                ->where('user_id', $userId)
                ->update(['is_read' => true, 'read_at' => now()]) > 0;
    }

    /**
     * Mark all notifications as read for user
     *
     * @param int $userId
     * @return int Number of notifications marked
     */
    public function markAllAsRead(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['is_read' => true, 'read_at' => now()]);
    }

    /**
     * Get unread notifications count
     *
     * @param int $userId
     * @return int
     */
    public function getUnreadCount(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Get notifications for user
     *
     * @param int $userId
     * @param int $limit
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getUserNotifications(int $userId, int $limit = 20)
    {
        return Notification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($limit);
    }
}
