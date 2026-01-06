<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use App\Models\DriverApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class DeliveryService
{
    protected GoogleMapsService $googleMaps;
    protected NotificationService $notificationService;

    public function __construct(GoogleMapsService $googleMaps, NotificationService $notificationService)
    {
        $this->googleMaps = $googleMaps;
        $this->notificationService = $notificationService;
    }

    /**
     * Assign driver to delivery (auto or manual)
     *
     * @param Delivery $delivery
     * @param int|null $driverId
     * @return Delivery
     * @throws \Exception
     */
    public function assignDriver(Delivery $delivery, ?int $driverId = null): Delivery
    {
        DB::beginTransaction();

        try {
            if ($driverId) {
                // Manual assignment
                $driver = $this->validateDriver($driverId);
            } else {
                // Auto assignment - find best available driver
                $driver = $this->findBestAvailableDriver($delivery);

                if (!$driver) {
                    throw new \Exception('No available drivers found for this delivery');
                }
            }

            // Assign driver
            $delivery->update([
                'driver_id' => $driver->id,
                'status' => 'assigned',
                'assigned_at' => now(),
            ]);

            // Send notifications
            $this->notificationService->deliveryAssigned($delivery->fresh());

            DB::commit();

            Log::info('Driver assigned to delivery', [
                'delivery_id' => $delivery->id,
                'driver_id' => $driver->id,
                'assignment_type' => $driverId ? 'manual' : 'auto',
            ]);

            return $delivery->fresh();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to assign driver', [
                'delivery_id' => $delivery->id,
                'requested_driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Mark delivery as picked up
     *
     * @param Delivery $delivery
     * @param array $data Optional pickup data
     * @return Delivery
     */
    public function markAsPickedUp(Delivery $delivery, array $data = []): Delivery
    {
        DB::beginTransaction();

        try {
            // Update delivery status
            $delivery->update([
                'status' => 'in_transit',
                'picked_up_at' => now(),
                'notes' => $data['notes'] ?? $delivery->notes,
            ]);

            // Update order status
            $delivery->order()->update([
                'status' => 'in_delivery',
            ]);

            // Send notifications
            $this->notificationService->itemPickedUp($delivery);

            DB::commit();

            Log::info('Delivery marked as picked up', [
                'delivery_id' => $delivery->id,
                'driver_id' => $delivery->driver_id,
                'order_id' => $delivery->order_id,
            ]);

            return $delivery->fresh();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to mark delivery as picked up', [
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Mark delivery as completed
     *
     * @param Delivery $delivery
     * @param array $data Completion data
     * @return Delivery
     */
    public function markAsDelivered(Delivery $delivery, array $data = []): Delivery
    {
        DB::beginTransaction();

        try {
            // Update delivery status
            $delivery->update([
                'status' => 'delivered',
                'delivered_at' => now(),
                'notes' => $data['notes'] ?? $delivery->notes,
            ]);

            // Update order status
            $delivery->order()->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Credit driver earnings (this could be handled by a separate payment service)
            $this->creditDriverEarnings($delivery);

            // Send notifications
            $this->notificationService->deliveryCompleted($delivery);

            DB::commit();

            Log::info('Delivery marked as completed', [
                'delivery_id' => $delivery->id,
                'driver_id' => $delivery->driver_id,
                'order_id' => $delivery->order_id,
                'driver_earning' => $delivery->driver_earning,
            ]);

            return $delivery->fresh();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to mark delivery as completed', [
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Cancel delivery (only allowed before pickup)
     *
     * @param Delivery $delivery
     * @param string $reason
     * @return Delivery
     * @throws \Exception
     */
    public function cancelDelivery(Delivery $delivery, string $reason): Delivery
    {
        DB::beginTransaction();

        try {
            // CRITICAL: Only allow cancellation before pickup
            if ($delivery->picked_up_at !== null) {
                throw new \Exception('Cannot cancel delivery after item has been picked up');
            }

            // Update delivery status to cancelled
            $delivery->update([
                'status' => 'cancelled',
                'failure_reason' => $reason,
            ]);

            // Reset order status to pending for reassignment
            $delivery->order()->update([
                'status' => 'pending',
            ]);

            // Make item available again (safe since driver never picked it up)
            $delivery->order->item()->update([
                'status' => 'available',
                'sold_at' => null,
            ]);

            // Create new delivery record for reassignment
            $newDelivery = $this->createDeliveryFromOrder($delivery->order);

            // Send notifications
            $this->notificationService->deliveryCancelled($delivery, $reason);

            DB::commit();

            Log::info('Delivery cancelled before pickup', [
                'delivery_id' => $delivery->id,
                'driver_id' => $delivery->driver_id,
                'order_id' => $delivery->order_id,
                'cancellation_reason' => $reason,
                'new_delivery_id' => $newDelivery->id,
                'picked_up_at' => $delivery->picked_up_at, // Should be null
            ]);

            return $delivery->fresh();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel delivery', [
                'delivery_id' => $delivery->id,
                'picked_up_at' => $delivery->picked_up_at,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Find best available driver for delivery
     *
     * @param Delivery $delivery
     * @return User|null
     */
    protected function findBestAvailableDriver(Delivery $delivery): ?User
    {
        // Get all approved drivers
        $approvedDriverIds = DriverApplication::where('status', 'approved')
            ->pluck('user_id');

        if ($approvedDriverIds->isEmpty()) {
            return null;
        }

        // Find drivers who don't have active deliveries
        $availableDrivers = User::whereIn('id', $approvedDriverIds)
            ->whereDoesntHave('activeDeliveries', function ($query) {
                $query->whereIn('status', ['assigned', 'in_transit']);
            })
            ->get();

        if ($availableDrivers->isEmpty()) {
            return null;
        }

        // For now, just return the first available driver
        // In the future, this could be improved with proximity-based assignment
        return $availableDrivers->first();
    }

    /**
     * Validate if user can be assigned as driver
     *
     * @param int $driverId
     * @return User
     * @throws \Exception
     */
    protected function validateDriver(int $driverId): User
    {
        $driver = User::find($driverId);

        if (!$driver) {
            throw new \Exception('Driver not found');
        }

        // Check if user is approved driver
        $application = DriverApplication::where('user_id', $driverId)
            ->where('status', 'approved')
            ->first();

        if (!$application) {
            throw new \Exception('User is not an approved driver');
        }

        // Check if driver has active deliveries
        $activeDeliveries = Delivery::where('driver_id', $driverId)
            ->whereIn('status', ['assigned', 'in_transit'])
            ->count();

        if ($activeDeliveries >= 3) { // Max 3 concurrent deliveries
            throw new \Exception('Driver has reached maximum concurrent deliveries');
        }

        return $driver;
    }

    /**
     * Credit driver earnings (placeholder - could integrate with payment system)
     *
     * @param Delivery $delivery
     * @return void
     */
    protected function creditDriverEarnings(Delivery $delivery): void
    {
        // For now, just log the earning
        // In a real system, this would update driver's balance or integrate with payment system

        Log::info('Driver earnings credited', [
            'driver_id' => $delivery->driver_id,
            'delivery_id' => $delivery->id,
            'amount' => $delivery->driver_earning,
            'delivery_fee' => $delivery->delivery_fee,
            'platform_fee' => $delivery->platform_fee,
        ]);

        // Could add driver balance tracking here:
        // $driver = $delivery->driver;
        // $driver->increment('balance', $delivery->driver_earning);
    }

    /**
     * Get available drivers in area (future enhancement)
     *
     * @param float $latitude
     * @param float $longitude
     * @param int $radiusKm
     * @return Collection
     */
    public function getAvailableDriversInArea(float $latitude, float $longitude, int $radiusKm = 10): Collection
    {
        // This would use geographic queries to find nearby drivers
        // For now, return all available drivers

        $approvedDriverIds = DriverApplication::where('status', 'approved')
            ->pluck('user_id');

        return User::whereIn('id', $approvedDriverIds)
            ->whereDoesntHave('activeDeliveries', function ($query) {
                $query->whereIn('status', ['assigned', 'in_transit']);
            })
            ->get();
    }

    /**
     * Get driver statistics
     *
     * @param int $driverId
     * @return array
     */
    public function getDriverStats(int $driverId): array
    {
        $totalDeliveries = Delivery::where('driver_id', $driverId)
            ->where('status', 'delivered')
            ->count();

        $totalEarnings = Delivery::where('driver_id', $driverId)
            ->where('status', 'delivered')
            ->sum('driver_earning');

        $averageRating = 5.0; // Placeholder - would calculate from ratings

        $thisMonthDeliveries = Delivery::where('driver_id', $driverId)
            ->where('status', 'delivered')
            ->whereMonth('delivered_at', now()->month)
            ->whereYear('delivered_at', now()->year)
            ->count();

        $thisMonthEarnings = Delivery::where('driver_id', $driverId)
            ->where('status', 'delivered')
            ->whereMonth('delivered_at', now()->month)
            ->whereYear('delivered_at', now()->year)
            ->sum('driver_earning');

        return [
            'total_deliveries' => $totalDeliveries,
            'total_earnings' => (float)$totalEarnings,
            'average_rating' => $averageRating,
            'this_month_deliveries' => $thisMonthDeliveries,
            'this_month_earnings' => (float)$thisMonthEarnings,
            'success_rate' => $totalDeliveries > 0 ? 100 : 0, // Could calculate failure rate
        ];
    }

    /**
     * Create delivery record from order
     *
     * @param Order $order
     * @return Delivery
     */
    public function createDeliveryFromOrder(Order $order): Delivery
    {
        $item = $order->item;
        $seller = $item->seller;
        $deliveryAddress = $order->deliveryAddress;

        // Calculate distance and fees
        $routeData = $this->googleMaps->calculateDistance(
            $seller->latitude ?? 33.8886, // Default to Beirut if no coordinates
            $seller->longitude ?? 35.4955,
            $deliveryAddress->latitude,
            $deliveryAddress->longitude
        );

        return Delivery::create([
            'order_id' => $order->id,
            'driver_id' => null,
            'pickup_address' => $seller->address ?? "{$seller->city}, {$seller->country}",
            'pickup_latitude' => $seller->latitude,
            'pickup_longitude' => $seller->longitude,
            'delivery_address' => $deliveryAddress->full_address,
            'delivery_latitude' => $deliveryAddress->latitude,
            'delivery_longitude' => $deliveryAddress->longitude,
            'distance_km' => $routeData['distance_km'],
            'delivery_fee' => $routeData['delivery_fee'],
            'driver_earning' => round($routeData['delivery_fee'] * 0.75, 2),
            'platform_fee' => round($routeData['delivery_fee'] * 0.25, 2),
            'status' => 'pending',
        ]);
    }
}
