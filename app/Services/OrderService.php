<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Item;
use App\Models\Address;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderService
{
    protected DeliveryService $deliveryService;
    protected NotificationService $notificationService;

    public function __construct(DeliveryService $deliveryService, NotificationService $notificationService)
    {
        $this->deliveryService = $deliveryService;
        $this->notificationService = $notificationService;
    }

    /**
     * Create a new order
     *
     * @param User $buyer
     * @param array $data
     * @return Order
     * @throws \Exception
     */
    public function createOrder(User $buyer, array $data): Order
    {
        DB::beginTransaction();

        try {
            // Get item and validate
            $item = Item::with('seller')->find($data['item_id']);

            if (!$item) {
                throw new \Exception('Item not found');
            }

            if ($item->trashed()) {
                throw new \Exception('This item has been deleted and is no longer available');
            }

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
            $deliveryFee = $data['delivery_fee'] ?? 0;
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

            // Create delivery record using DeliveryService
            $delivery = $this->deliveryService->createDeliveryFromOrder($order);

            // Mark item as sold for donations, pending for sales
            $item->update([
                'status' => $item->is_donation ? 'sold' : 'pending',
                'sold_at' => $item->is_donation ? now() : null,
            ]);

            // Send notifications using NotificationService
            $this->notificationService->orderPlaced($order);

            if ($item->is_donation) {
                $this->notificationService->donationAccepted($item, $order);
            } else {
                $this->notificationService->itemSold($item, $order);
            }

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
                'is_donation' => $item->is_donation,
            ]);

            return $order;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create order', [
                'buyer_id' => $buyer->id,
                'item_id' => $data['item_id'] ?? null,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Cancel an order
     *
     * @param Order $order
     * @param string $reason
     * @return Order
     * @throws \Exception
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

            // Cancel delivery if exists (only works before pickup)
            if ($order->delivery) {
                try {
                    $this->deliveryService->cancelDelivery($order->delivery, $reason);
                } catch (\Exception $e) {
                    // If delivery can't be cancelled (e.g., after pickup), log but don't fail order cancellation
                    Log::warning('Could not cancel delivery during order cancellation', [
                        'order_id' => $order->id,
                        'delivery_id' => $order->delivery->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Send cancellation notification
            $this->notificationService->createNotification(
                $order->seller_id,
                'order_cancelled',
                'Order Cancelled',
                "Order #{$order->order_number} has been cancelled. Reason: {$reason}",
                [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'reason' => $reason,
                ]
            );

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
     * Confirm order (seller confirms they have the item ready)
     *
     * @param Order $order
     * @return Order
     * @throws \Exception
     */
    public function confirmOrder(Order $order): Order
    {
        DB::beginTransaction();

        try {
            if ($order->status !== 'pending') {
                throw new \Exception('Order cannot be confirmed at this stage');
            }

            $order->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ]);

            // Try to auto-assign a driver
            if ($order->delivery && $order->delivery->status === 'pending') {
                try {
                    $this->deliveryService->assignDriver($order->delivery);
                } catch (\Exception $e) {
                    // Log but don't fail the order confirmation
                    Log::warning('Failed to auto-assign driver', [
                        'order_id' => $order->id,
                        'delivery_id' => $order->delivery->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();

            return $order->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
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
        return Order::with(['seller:id,name,email,city', 'item.images', 'deliveryAddress', 'delivery.driver'])
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
        return Order::with(['buyer:id,name,email,city', 'item.images', 'deliveryAddress', 'delivery.driver'])
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
     * Generate unique order number
     * Format: RW-YYYYMMDD-XXXXX
     *
     * @return string
     */
    protected function generateOrderNumber(): string
    {
        $date = now()->format('Ymd');

        // Get the highest sequence number used today (including deleted/cancelled)
        $lastOrder = Order::withTrashed()
            ->where('order_number', 'LIKE', "RW-{$date}-%")
            ->orderBy('order_number', 'desc')
            ->first();

        if ($lastOrder) {
            // Extract sequence from last order number (RW-20251214-00002 -> 00002)
            $lastSequence = (int) substr($lastOrder->order_number, -5);
            $sequence = str_pad($lastSequence + 1, 5, '0', STR_PAD_LEFT);
        } else {
            $sequence = '00001';
        }

        return "RW-{$date}-{$sequence}";
    }

    /**
     * Get order statistics for admin
     *
     * @return array
     */
    public function getOrderStatistics(): array
    {
        $totalOrders = Order::count();
        $pendingOrders = Order::where('status', 'pending')->count();
        $confirmedOrders = Order::where('status', 'confirmed')->count();
        $completedOrders = Order::where('status', 'completed')->count();
        $cancelledOrders = Order::where('status', 'cancelled')->count();

        $totalRevenue = Order::where('status', 'completed')->sum('total_amount');
        $totalDeliveryFees = Order::where('status', 'completed')->sum('delivery_fee');

        $thisMonthOrders = Order::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $thisMonthRevenue = Order::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('status', 'completed')
            ->sum('total_amount');

        return [
            'total_orders' => $totalOrders,
            'pending_orders' => $pendingOrders,
            'confirmed_orders' => $confirmedOrders,
            'completed_orders' => $completedOrders,
            'cancelled_orders' => $cancelledOrders,
            'total_revenue' => (float) $totalRevenue,
            'total_delivery_fees' => (float) $totalDeliveryFees,
            'this_month_orders' => $thisMonthOrders,
            'this_month_revenue' => (float) $thisMonthRevenue,
            'completion_rate' => $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 2) : 0,
            'cancellation_rate' => $totalOrders > 0 ? round(($cancelledOrders / $totalOrders) * 100, 2) : 0,
        ];
    }
}
