<?php


namespace App\Services;

use App\Models\Item;
use App\Models\User;
use App\Models\Order;
use App\Models\Address;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class DonationService
{
    protected OrderService $orderService;
    protected NotificationService $notificationService;
    protected DeliveryService $deliveryService;

    public function __construct(
        OrderService        $orderService,
        NotificationService $notificationService,
        DeliveryService     $deliveryService
    )
    {
        $this->orderService = $orderService;
        $this->notificationService = $notificationService;
        $this->deliveryService = $deliveryService;
    }

    /**
     * Get available donations for charities
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getAvailableDonations(array $filters = [], int $perPage = 15)
    {
        $query = Item::with(['seller:id,name,email,city', 'images'])
            ->where('is_donation', true)
            ->where('status', 'available')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['condition'])) {
            $query->where('condition', $filters['condition']);
        }

        if (!empty($filters['size'])) {
            $query->where('size', $filters['size']);
        }

        if (!empty($filters['city'])) {
            $query->whereHas('seller', function ($q) use ($filters) {
                $q->where('city', $filters['city']);
            });
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%")
                    ->orWhere('brand', 'ILIKE', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Accept donation by charity
     *
     * @param Item $item
     * @param User $charity
     * @param Address $deliveryAddress
     * @param array $data
     * @return Order
     * @throws \Exception
     */
    public function acceptDonation(Item $item, User $charity, Address $deliveryAddress, array $data = []): Order
    {
        DB::beginTransaction();

        try {
            // Validate donation acceptance
            $this->validateDonationAcceptance($item, $charity);

            // Create order for donation (price = 0)
            // Note: OrderService will handle:
            // - Creating the order
            // - Creating the delivery record
            // - Marking the item as sold
            // - Sending notifications
            $orderData = [
                'item_id' => $item->id,
                'delivery_address_id' => $deliveryAddress->id,
                'delivery_fee' => 0,
            ];

            $order = $this->orderService->createOrder($charity, $orderData);

            // Reload order to get the delivery relationship
            $order->load('delivery');

            // Log donation acceptance
            Log::info('Donation accepted by charity', [
                'item_id' => $item->id,
                'charity_id' => $charity->id,
                'order_id' => $order->id,
                'delivery_id' => $order->delivery->id,
                'quantity' => $item->donation_quantity,
            ]);

            // Notify all other charities that this donation is no longer available
            $this->notifyOtherCharitiesItemTaken($item, $charity);

            DB::commit();

            return $order;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to accept donation', [
                'item_id' => $item->id,
                'charity_id' => $charity->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Mark donation as distributed (charity received and gave to people)
     *
     * @param Order $order
     * @param array $data
     * @return Order
     * @throws \Exception
     */
    public function markAsDistributed(Order $order, array $data): Order
    {
        DB::beginTransaction();

        try {
            // Validate order is for donation and delivered
            if (!$this->isDonationOrder($order)) {
                throw new \Exception('Order is not a donation order');
            }

            if ($order->status !== 'completed') {
                throw new \Exception('Order must be completed before marking as distributed');
            }

            // Update order with distribution info
            $order->update([
                'distribution_notes' => $data['distribution_notes'] ?? null,
                'people_helped' => $data['people_helped'] ?? 1,
                'distributed_at' => now(),
            ]);

            // Update impact statistics
            $this->updateImpactStatistics($order);

            DB::commit();

            Log::info('Donation marked as distributed', [
                'order_id' => $order->id,
                'charity_id' => $order->buyer_id,
                'people_helped' => $data['people_helped'] ?? 1,
                'item_title' => $order->item->title,
            ]);

            return $order->fresh();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to mark donation as distributed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get charity's donation history
     *
     * @param User $charity
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getCharityDonations(User $charity, int $perPage = 15)
    {
        return Order::with(['item.seller', 'item.images', 'delivery'])
            ->where('buyer_id', $charity->id)
            ->where('item_price', 0) // Donations have price = 0
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get charity impact statistics
     *
     * @param User $charity
     * @return array
     */
    public function getCharityImpactStats(User $charity): array
    {
        $donationOrders = Order::where('buyer_id', $charity->id)
            ->where('item_price', 0);

        $totalDonations = $donationOrders->count();
        $completedDonations = $donationOrders->where('status', 'completed')->count();
        $distributedDonations = $donationOrders->whereNotNull('distributed_at')->count();
        $totalPeopleHelped = $donationOrders->sum('people_helped');

        $thisMonthDonations = $donationOrders->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $thisMonthPeopleHelped = $donationOrders->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('people_helped');

        return [
            'total_donations_received' => $totalDonations,
            'completed_donations' => $completedDonations,
            'distributed_donations' => $distributedDonations,
            'total_people_helped' => $totalPeopleHelped ?: 0,
            'this_month_donations' => $thisMonthDonations,
            'this_month_people_helped' => $thisMonthPeopleHelped ?: 0,
            'completion_rate' => $totalDonations > 0 ? round(($completedDonations / $totalDonations) * 100, 2) : 0,
            'distribution_rate' => $completedDonations > 0 ? round(($distributedDonations / $completedDonations) * 100, 2) : 0,
        ];
    }

    /**
     * Get platform donation statistics
     *
     * @return array
     */
    public function getPlatformDonationStats(): array
    {
        $totalDonationItems = Item::where('is_donation', true)->count();
        $availableDonations = Item::where('is_donation', true)
            ->where('status', 'available')
            ->count();

        $donationOrders = Order::where('item_price', 0);
        $totalDonationOrders = $donationOrders->count();
        $completedDonations = $donationOrders->where('status', 'completed')->count();
        $totalPeopleHelped = $donationOrders->sum('people_helped');

        $activeCharities = User::role('charity')
            ->whereHas('orders', function ($query) {
                $query->where('item_price', 0);
            })
            ->count();

        $thisMonthDonations = $donationOrders->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return [
            'total_donation_items' => $totalDonationItems,
            'available_donations' => $availableDonations,
            'total_donation_orders' => $totalDonationOrders,
            'completed_donations' => $completedDonations,
            'total_people_helped' => $totalPeopleHelped ?: 0,
            'active_charities' => $activeCharities,
            'this_month_donations' => $thisMonthDonations,
            'completion_rate' => $totalDonationOrders > 0 ? round(($completedDonations / $totalDonationOrders) * 100, 2) : 0,
        ];
    }

    /**
     * Get donation categories statistics
     *
     * @return array
     */
    public function getDonationCategoriesStats(): array
    {
        return Item::where('is_donation', true)
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->orderBy('count', 'desc')
            ->get()
            ->pluck('count', 'category')
            ->toArray();
    }

    /**
     * Validate donation acceptance
     *
     * @param Item $item
     * @param User $charity
     * @throws \Exception
     */
    protected function validateDonationAcceptance(Item $item, User $charity): void
    {
        if (!$item->is_donation) {
            throw new \Exception('Item is not available for donation');
        }

        if ($item->status !== 'available') {
            throw new \Exception('Donation item is no longer available');
        }

        if (!$charity->hasRole('charity')) {
            throw new \Exception('Only registered charities can accept donations');
        }

        // Check if charity already has an order for this item
        $existingOrder = Order::where('buyer_id', $charity->id)
            ->where('item_id', $item->id)
            ->first();

        if ($existingOrder) {
            throw new \Exception('You have already requested this donation');
        }
    }

    /**
     * Check if order is for donation
     *
     * @param Order $order
     * @return bool
     */
    protected function isDonationOrder(Order $order): bool
    {
        return $order->item_price == 0 && $order->item->is_donation;
    }

    /**
     * Update platform impact statistics
     *
     * @param Order $order
     */
    protected function updateImpactStatistics(Order $order): void
    {
        // This could update a separate impact_statistics table
        // For now, we'll just log the impact

        Log::info('Impact statistics updated', [
            'charity_id' => $order->buyer_id,
            'people_helped' => $order->people_helped,
            'item_category' => $order->item->category,
            'distribution_date' => now(),
        ]);
    }

    /**
     * Notify other charities that item is no longer available
     *
     * @param Item $item
     * @param User $acceptingCharity
     */
    protected function notifyOtherCharitiesItemTaken(Item $item, User $acceptingCharity): void
    {
        // Get all charities except the one that accepted
        $otherCharities = User::role('charity')
            ->where('id', '!=', $acceptingCharity->id)
            ->get();

        foreach ($otherCharities as $charity) {
            $this->notificationService->createNotification(
                $charity->id,
                'donation_unavailable',
                'Donation No Longer Available',
                "The donation \"{$item->title}\" has been accepted by another charity.",
                [
                    'item_id' => $item->id,
                    'item_title' => $item->title,
                    'accepted_by' => $acceptingCharity->name,
                ]
            );
        }
    }

    /**
     * Get recommended donations for charity based on their history
     *
     * @param User $charity
     * @param int $limit
     * @return Collection
     */
    public function getRecommendedDonations(User $charity, int $limit = 10): Collection
    {
        // Get charity's most received categories
        $preferredCategories = Order::where('buyer_id', $charity->id)
            ->where('item_price', 0)
            ->join('items', 'orders.item_id', '=', 'items.id')
            ->selectRaw('items.category, COUNT(*) as count')
            ->groupBy('items.category')
            ->orderBy('count', 'desc')
            ->limit(3)
            ->pluck('category');

        // If charity has no history, return recent donations
        if ($preferredCategories->isEmpty()) {
            return Item::where('is_donation', true)
                ->where('status', 'available')
                ->with(['seller:id,name,city', 'images'])
                ->latest()
                ->limit($limit)
                ->get();
        }

        // Return donations in preferred categories
        return Item::where('is_donation', true)
            ->where('status', 'available')
            ->whereIn('category', $preferredCategories)
            ->with(['seller:id,name,city', 'images'])
            ->latest()
            ->limit($limit)
            ->get();
    }
}
