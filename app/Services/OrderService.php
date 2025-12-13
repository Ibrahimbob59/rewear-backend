<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Delivery;
use App\Models\Item;
use App\Models\Address;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderService
{
    /**
     * Create a new order
     *
     * @param array $data
     * @param User $buyer
     * @return Order
     */
    public function createOrder(array $data, User $buyer): Order
    {
        DB::beginTransaction();

        try {
            // Get item and validate
            $item = Item::with('seller')->findOrFail($data['item_id']);

            // Validate item is available
            if ($item->status !== 'available') {
                throw new \Exception('Item is not available for purchase');
            }

            // Validate buyer is not the seller
            if ($item->seller_id === $buyer->id) {
                throw new \Exception('You cannot buy your own item');
            }

            // Get delivery address
            $address = Address::findOrFail($data['delivery_address_id']);

            // Validate address belongs to buyer
            if ($address->user_id !== $buyer->id) {
                throw new \Exception('Invalid delivery address');
            }

            // Generate order number: RW-YYYYMMDD-XXXXX
            $orderNumber = $this->generateOrderNumber();

            // Calculate amounts
            $itemPrice = $item->is_donation ? 0 : $item->price;
            $deliveryFee = $data['delivery_fee'];
            $totalAmount = $itemPrice + $deliveryFee;

            // Create order
            $order = Order::create([
                'order_number' => $orderNumber,
                'buyer_id' => $buyer->id,
                'seller_id' => $item->seller_id,
                'item_id' => $item->id,
                'delivery_address_id' => $address->id,
                'item_price' => $itemPrice,
                'delivery_fee' => $deliveryFee,
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'payment_method' => 'cod',
                'payment_status' => 'pending',
            ]);

            // Create delivery record
            $this->createDeliveryRecord($order, $item, $address);

            // Mark item as pending/sold
            $item->update([
                'status' => $item->is_donation ? 'donated' : 'sold',
                'sold_at' => now(),
            ]);

            // Create notifications
            $this->createOrderNotifications($order, $item);

            DB::commit();

            // Load relationships
            $order->load(['buyer', 'seller', 'item.images', 'deliveryAddress', 'delivery']);

            Log::info('Order created successfully', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'buyer_id' => $buyer->id,
                'seller_id' => $item->seller_id,
                'item_id' => $item->id,
                'total_amount' => $totalAmount,
            ]);

            return $order;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create order', [
                'buyer_id' => $buyer->id,
                'item_id' => $data['item_id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Get buyer's orders
     *
     * @param int $buyerId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getBuyerOrders(int $buyerId, int $perPage = 15): LengthAwarePaginator
    {
        return Order::with(['seller:id,name,email,city', 'item.images', 'deliveryAddress', 'delivery'])
            ->where('buyer_id', $buyerId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get seller's orders
     *
     * @param int $sellerId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getSellerOrders(int $sellerId, int $perPage = 15): LengthAwarePaginator
    {
        return Order::with(['buyer:id,name,email,city', 'item.images', 'deliveryAddress', 'delivery'])
            ->where('seller_id', $sellerId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get single order by ID
     *
     * @param int $orderId
     * @param int $userId
     * @return Order|null
     */
    public function getOrderById(int $orderId, int $userId): ?Order
    {
        return Order::with(['buyer', 'seller', 'item.images', 'deliveryAddress', 'delivery.driver'])
            ->where(function($query) use ($userId) {
                $query->where('buyer_id', $userId)
                    ->orWhere('seller_id', $userId);
            })
            ->find($orderId);
    }

    /**
     * Cancel an order
     *
     * @param Order $order
     * @param string $reason
     * @return Order
     */
    public function cancelOrder(Order $order, string $reason): Order
    {
        DB::beginTransaction();

        try {
            // Validate order can be cancelled
            if (!in_array($order->status, ['pending', 'confirmed'])) {
                throw new \Exception('Order cannot be cancelled at this stage');
            }

            // Update order status
            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            // Make item available again
            $order->item()->update([
                'status' => 'available',
                'sold_at' => null,
            ]);

            // Update delivery if exists
            if ($order->delivery) {
                $order->delivery->update([
                    'status' => 'cancelled',
                ]);
            }

            // Notify seller
            $this->createCancellationNotification($order);

            DB::commit();

            Log::info('Order cancelled', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'reason' => $reason,
            ]);

            return $order->fresh(['buyer', 'seller', 'item', 'delivery']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate unique order number
     * Format: RW-YYYYMMDD-XXXXX
     *
     * @return string
     */
    protected function generateOrderNumber(): string
    {
        $date = now()->format('Ymd');

        // Get count of orders today
        $todayCount = Order::whereDate('created_at', today())->count();

        // Increment and pad with zeros
        $sequence = str_pad($todayCount + 1, 5, '0', STR_PAD_LEFT);

        return "RW-{$date}-{$sequence}";
    }

    /**
     * Create delivery record for order
     *
     * @param Order $order
     * @param Item $item
     * @param Address $deliveryAddress
     * @return Delivery
     */
    protected function createDeliveryRecord(Order $order, Item $item, Address $deliveryAddress): Delivery
    {
        // Get seller's address (from user's city/lat/lng)
        $seller = $item->seller;

        return Delivery::create([
            'order_id' => $order->id,
            'driver_id' => null, // Will be assigned later
            'pickup_address' => $seller->address ?? "{$seller->city}, {$seller->country}",
            'pickup_latitude' => $seller->latitude,
            'pickup_longitude' => $seller->longitude,
            'delivery_address' => $deliveryAddress->address_line1 . ', ' . $deliveryAddress->city,
            'delivery_latitude' => $deliveryAddress->latitude,
            'delivery_longitude' => $deliveryAddress->longitude,
            'distance_km' => $this->calculateDistance(
                $seller->latitude,
                $seller->longitude,
                $deliveryAddress->latitude,
                $deliveryAddress->longitude
            ),
            'delivery_fee' => $order->delivery_fee,
            'driver_earnings' => $order->delivery_fee * 0.75, // 75% to driver
            'platform_earnings' => $order->delivery_fee * 0.25, // 25% to platform
            'status' => 'pending',
        ]);
    }

    /**
     * Calculate distance between two coordinates (Haversine formula)
     *
     * @param float $lat1
     * @param float $lng1
     * @param float $lat2
     * @param float $lng2
     * @return float Distance in kilometers
     */
    protected function calculateDistance($lat1, $lng1, $lat2, $lng2): float
    {
        if (is_null($lat1) || is_null($lng1) || is_null($lat2) || is_null($lng2)) {
            return 0;
        }

        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat/2) * sin($dLat/2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng/2) * sin($dLng/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        $distance = $earthRadius * $c;

        return round($distance, 2);
    }

    /**
     * Create notifications for order
     *
     * @param Order $order
     * @param Item $item
     */
    protected function createOrderNotifications(Order $order, Item $item): void
    {
        // Notify seller
        Notification::create([
            'user_id' => $order->seller_id,
            'type' => 'order_created',
            'title' => 'New Order Received',
            'message' => "You have a new order for: {$item->title}",
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'item_id' => $item->id,
            ],
        ]);

        // Notify buyer (confirmation)
        Notification::create([
            'user_id' => $order->buyer_id,
            'type' => 'order_created',
            'title' => 'Order Placed Successfully',
            'message' => "Your order for {$item->title} has been placed successfully",
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'item_id' => $item->id,
            ],
        ]);
    }

    /**
     * Create cancellation notification
     *
     * @param Order $order
     */
    protected function createCancellationNotification(Order $order): void
    {
        // Notify seller
        Notification::create([
            'user_id' => $order->seller_id,
            'type' => 'order_cancelled',
            'title' => 'Order Cancelled',
            'message' => "Order {$order->order_number} has been cancelled",
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
        ]);
    }
}
