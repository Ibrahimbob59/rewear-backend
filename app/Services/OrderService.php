<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Item;
use App\Models\Address;
use App\Models\User;
use App\Services\Helpers\OrderNumberGenerator;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

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
            if (!$item->isAvailable()) {
                throw new \Exception('Item is not available for purchase');
            }

            // Validate buyer is not the seller
            if ($item->seller_id === $buyer->id) {
                throw new \Exception('You cannot buy your own item');
            }

            // Get delivery address
            $address = Address::findOrFail($data['delivery_address_id']);
            
            // Validate address belongs to buyer
            if (!$address->isOwnedBy($buyer->id)) {
                throw new \Exception('Invalid delivery address');
            }

            // Generate order number
            $orderNumber = OrderNumberGenerator::generate();

            // Calculate amounts
            $itemPrice = $item->is_donation ? 0 : $item->price;
            $deliveryFee = $data['delivery_fee']; // Calculated by frontend
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

            // Mark item as pending
            $item->markAsPending();

            // Notify seller
            $this->notificationService->sendOrderCreatedNotification($order);

            DB::commit();

            // Load relationships
            $order->load(['buyer', 'seller', 'item.images', 'deliveryAddress']);

            return $order;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create order: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get buyer's orders
     * 
     * @param User $buyer
     * @param string|null $status
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getBuyerOrders(User $buyer, ?string $status = null, int $perPage = 20)
    {
        $query = Order::with([
            'item.images',
            'seller:id,name,phone',
            'deliveryAddress',
            'delivery.driver:id,name,phone',
        ])
        ->forBuyer($buyer->id)
        ->orderBy('created_at', 'desc');

        if ($status) {
            $query->byStatus($status);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get seller's orders
     * 
     * @param User $seller
     * @param string|null $status
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getSellerOrders(User $seller, ?string $status = null, int $perPage = 20)
    {
        $query = Order::with([
            'item.images',
            'buyer:id,name,phone',
            'deliveryAddress',
            'delivery.driver:id,name,phone',
        ])
        ->forSeller($seller->id)
        ->orderBy('created_at', 'desc');

        if ($status) {
            $query->byStatus($status);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get order by ID with relationships
     * 
     * @param int $id
     * @return Order|null
     */
    public function getOrderById(int $id): ?Order
    {
        return Order::with([
            'buyer:id,name,email,phone',
            'seller:id,name,email,phone,location_lat,location_lng',
            'item.images',
            'deliveryAddress',
            'delivery.driver:id,name,phone',
        ])->find($id);
    }

    /**
     * Cancel an order
     * 
     * @param Order $order
     * @param User $user
     * @param string|null $reason
     * @return Order
     */
    public function cancelOrder(Order $order, User $user, ?string $reason = null): Order
    {
        DB::beginTransaction();

        try {
            // Validate user is the buyer
            if (!$order->isBuyer($user->id)) {
                throw new \Exception('Only buyer can cancel the order');
            }

            // Cancel order
            $order->cancel($reason);

            DB::commit();

            return $order->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel order: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Confirm order (seller action)
     * 
     * @param Order $order
     * @param User $user
     * @return Order
     */
    public function confirmOrder(Order $order, User $user): Order
    {
        DB::beginTransaction();

        try {
            // Validate user is the seller
            if (!$order->isSeller($user->id)) {
                throw new \Exception('Only seller can confirm the order');
            }

            // Confirm order
            $order->confirm();

            DB::commit();

            return $order->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to confirm order: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Complete order (buyer confirms receipt)
     * 
     * @param Order $order
     * @param User $user
     * @return Order
     */
    public function completeOrder(Order $order, User $user): Order
    {
        DB::beginTransaction();

        try {
            // Validate user is the buyer
            if (!$order->isBuyer($user->id)) {
                throw new \Exception('Only buyer can complete the order');
            }

            // Validate order is delivered
            if ($order->status !== 'delivered') {
                throw new \Exception('Order must be delivered before completion');
            }

            // Complete order
            $order->complete();

            DB::commit();

            return $order->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to complete order: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calculate delivery fee
     * Formula: (distance_km ÷ 4) × $1
     * 
     * @param float $distanceKm
     * @return float
     */
    public static function calculateDeliveryFee(float $distanceKm): float
    {
        return round(($distanceKm / 4) * 1, 2);
    }

    /**
     * Calculate driver earnings (75% of delivery fee)
     * 
     * @param float $deliveryFee
     * @return float
     */
    public static function calculateDriverEarnings(float $deliveryFee): float
    {
        return round($deliveryFee * 0.75, 2);
    }

    /**
     * Calculate platform commission (25% of delivery fee)
     * 
     * @param float $deliveryFee
     * @return float
     */
    public static function calculatePlatformCommission(float $deliveryFee): float
    {
        return round($deliveryFee * 0.25, 2);
    }
}