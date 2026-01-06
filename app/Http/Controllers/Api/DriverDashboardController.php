<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryResource;
use App\Models\Delivery;
use App\Services\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DriverDashboardController extends Controller
{
    protected DeliveryService $deliveryService;

    public function __construct(DeliveryService $deliveryService)
    {
        $this->deliveryService = $deliveryService;
    }

    /**
     * @OA\Get(
     *     path="/api/driver/dashboard",
     *     tags={"Driver Dashboard"},
     *     summary="Get driver dashboard overview",
     *     description="Get driver's statistics and overview",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="stats", type="object"),
     *                 @OA\Property(property="active_deliveries", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="available_deliveries", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Not a verified driver"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function dashboard(): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user->isVerifiedDriver()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a verified driver',
                ], 403);
            }

            // Get driver statistics
            $stats = $this->deliveryService->getDriverStats($user->id);

            // Get active deliveries
            $activeDeliveries = Delivery::with(['order.buyer', 'order.seller', 'order.item.images'])
                ->where('driver_id', $user->id)
                ->whereIn('status', ['assigned', 'in_transit'])
                ->orderBy('assigned_at')
                ->get();

            // Get available deliveries (pending assignments nearby)
            $availableDeliveries = Delivery::with(['order.buyer', 'order.seller', 'order.item.images'])
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Dashboard data retrieved successfully',
                'data' => [
                    'stats' => $stats,
                    'active_deliveries' => DeliveryResource::collection($activeDeliveries),
                    'available_deliveries' => DeliveryResource::collection($availableDeliveries),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve driver dashboard', [
                'driver_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard data',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/driver/deliveries",
     *     tags={"Driver Dashboard"},
     *     summary="Get driver's deliveries",
     *     description="Get all deliveries assigned to the driver",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by delivery status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"assigned","picked_up","in_transit","delivered"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Driver deliveries retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=403, description="Not a verified driver"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function myDeliveries(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user->isVerifiedDriver()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a verified driver',
                ], 403);
            }

            $query = Delivery::with(['order.buyer', 'order.seller', 'order.item.images'])
                ->where('driver_id', $user->id);

            // Apply status filter if provided
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            $deliveries = $query->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 15));

            return response()->json([
                'success' => true,
                'message' => 'Deliveries retrieved successfully',
                'data' => DeliveryResource::collection($deliveries),
                'meta' => [
                    'current_page' => $deliveries->currentPage(),
                    'total' => $deliveries->total(),
                    'per_page' => $deliveries->perPage(),
                    'last_page' => $deliveries->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve driver deliveries', [
                'driver_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve deliveries',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/driver/available-deliveries",
     *     tags={"Driver Dashboard"},
     *     summary="Get available deliveries",
     *     description="Get pending deliveries available for assignment",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Available deliveries retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=403, description="Not a verified driver"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function availableDeliveries(): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user->isVerifiedDriver()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a verified driver',
                ], 403);
            }

            // Get pending deliveries ordered by creation time
            $deliveries = Delivery::with(['order.buyer', 'order.seller', 'order.item.images'])
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Available deliveries retrieved successfully',
                'data' => DeliveryResource::collection($deliveries),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve available deliveries', [
                'driver_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available deliveries',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/driver/accept-delivery/{id}",
     *     tags={"Driver Dashboard"},
     *     summary="Accept delivery assignment",
     *     description="Accept an available delivery for assignment",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Delivery ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery accepted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Delivery accepted successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Delivery not available or driver has too many active deliveries"),
     *     @OA\Response(response=403, description="Not a verified driver"),
     *     @OA\Response(response=404, description="Delivery not found")
     * )
     */
    public function acceptDelivery(int $id): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user->isVerifiedDriver()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a verified driver',
                ], 403);
            }

            $delivery = Delivery::findOrFail($id);

            if ($delivery->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This delivery is no longer available',
                ], 400);
            }

            // Check if driver has too many active deliveries
            $activeDeliveries = $user->activeDeliveries()->count();
            if ($activeDeliveries >= 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have reached the maximum number of active deliveries (3)',
                ], 400);
            }

            // Assign the driver to this delivery
            $delivery = $this->deliveryService->assignDriver($delivery, $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Delivery accepted successfully! Please go to the pickup location.',
                'data' => new DeliveryResource($delivery),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to accept delivery', [
                'delivery_id' => $id,
                'driver_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 400);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/driver/earnings",
     *     tags={"Driver Dashboard"},
     *     summary="Get driver earnings",
     *     description="Get driver's earning statistics and history",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Earnings retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_earnings", type="number", example=125.50),
     *                 @OA\Property(property="this_month_earnings", type="number", example=45.25),
     *                 @OA\Property(property="total_deliveries", type="integer", example=23),
     *                 @OA\Property(property="this_month_deliveries", type="integer", example=8),
     *                 @OA\Property(property="average_earning_per_delivery", type="number", example=5.46)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Not a verified driver"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function earnings(): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user->isVerifiedDriver()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a verified driver',
                ], 403);
            }

            $stats = $this->deliveryService->getDriverStats($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Earnings retrieved successfully',
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve driver earnings', [
                'driver_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve earnings',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
