<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\AssignDriverRequest;
use App\Http\Requests\Delivery\UpdateDeliveryStatusRequest;
use App\Http\Resources\DeliveryResource;
use App\Models\Delivery;
use App\Services\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeliveryController extends Controller
{
    protected DeliveryService $deliveryService;

    public function __construct(DeliveryService $deliveryService)
    {
        $this->deliveryService = $deliveryService;
    }

    /**
     * @OA\Get(
     *     path="/api/deliveries",
     *     tags={"Deliveries"},
     *     summary="List all deliveries (admin only)",
     *     description="Get paginated list of all deliveries for admin management",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Deliveries per page (1-50)",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by delivery status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending","assigned","in_transit","delivered","cancelled"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deliveries retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Deliveries retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=403, description="Admin access required")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Ensure user is admin
            if (!auth()->user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin access required',
                ], 403);
            }

            $perPage = min((int) $request->input('per_page', 15), 50);
            $status = $request->input('status');

            $query = Delivery::with(['order.item', 'order.buyer', 'order.seller', 'driver'])
                ->orderBy('created_at', 'desc');

            if ($status) {
                $query->where('status', $status);
            }

            $deliveries = $query->paginate($perPage);

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
            Log::error('Failed to retrieve deliveries', [
                'user_id' => auth()->id(),
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
     *     path="/api/deliveries/{id}",
     *     tags={"Deliveries"},
     *     summary="Get delivery details",
     *     description="Get detailed information about a specific delivery",
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
     *         description="Delivery details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Delivery details retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Access denied"),
     *     @OA\Response(response=404, description="Delivery not found")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $delivery = Delivery::with(['order.item', 'order.buyer', 'order.seller', 'driver'])->find($id);

            if (!$delivery) {
                return response()->json([
                    'success' => false,
                    'message' => 'Delivery not found',
                ], 404);
            }

            $user = auth()->user();

            // Check access permissions
            $hasAccess = $user->hasRole('admin') ||
                $delivery->driver_id === $user->id ||
                $delivery->order->buyer_id === $user->id ||
                $delivery->order->seller_id === $user->id;

            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Delivery details retrieved successfully',
                'data' => new DeliveryResource($delivery),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve delivery details', [
                'delivery_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve delivery details',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/deliveries/{id}/assign-driver",
     *     tags={"Deliveries"},
     *     summary="Assign driver to delivery (admin only)",
     *     description="Manually assign a specific driver to a delivery",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Delivery ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"driver_id"},
     *             @OA\Property(property="driver_id", type="integer", example=5, description="ID of the driver to assign")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Driver assigned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Driver assigned successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Cannot assign driver or driver not available"),
     *     @OA\Response(response=403, description="Admin access required"),
     *     @OA\Response(response=404, description="Delivery not found")
     * )
     */
    public function assignDriver(AssignDriverRequest $request, int $id): JsonResponse
    {
        try {
            $delivery = Delivery::findOrFail($id);

            if ($delivery->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only assign driver to pending deliveries',
                ], 400);
            }

            $driverId = $request->validated()['driver_id'];
            $delivery = $this->deliveryService->assignDriver($delivery, $driverId);

            return response()->json([
                'success' => true,
                'message' => 'Driver assigned successfully',
                'data' => new DeliveryResource($delivery),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to assign driver to delivery', [
                'delivery_id' => $id,
                'driver_id' => $request->input('driver_id'),
                'admin_id' => auth()->id(),
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
     * @OA\Post(
     *     path="/api/deliveries/{id}/pickup",
     *     tags={"Deliveries"},
     *     summary="Mark delivery as picked up",
     *     description="Mark delivery as picked up by the assigned driver",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Delivery ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="notes", type="string", example="Item picked up from seller", description="Optional pickup notes")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery marked as picked up successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Delivery marked as picked up"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Only assigned driver can mark as picked up"),
     *     @OA\Response(response=404, description="Delivery not found")
     * )
     */
    public function markAsPickedUp(UpdateDeliveryStatusRequest $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $delivery = Delivery::findOrFail($id);

            // Only the assigned driver can mark as picked up
            if ($delivery->driver_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the assigned driver can mark delivery as picked up',
                ], 403);
            }

            if ($delivery->status !== 'assigned') {
                return response()->json([
                    'success' => false,
                    'message' => 'Delivery must be in assigned status to mark as picked up',
                ], 400);
            }

            $data = $request->validated();
            $delivery = $this->deliveryService->markAsPickedUp($delivery, $data);

            return response()->json([
                'success' => true,
                'message' => 'Delivery marked as picked up successfully',
                'data' => new DeliveryResource($delivery),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to mark delivery as picked up', [
                'delivery_id' => $id,
                'user_id' => auth()->id(),
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
     * @OA\Post(
     *     path="/api/deliveries/{id}/deliver",
     *     tags={"Deliveries"},
     *     summary="Mark delivery as delivered",
     *     description="Mark delivery as completed by the driver",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Delivery ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="notes", type="string", example="Delivered to customer", description="Optional delivery notes")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery marked as delivered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Delivery completed successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Only assigned driver can mark as delivered"),
     *     @OA\Response(response=404, description="Delivery not found")
     * )
     */
    public function markAsDelivered(UpdateDeliveryStatusRequest $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $delivery = Delivery::findOrFail($id);

            // Only the assigned driver can mark as delivered
            if ($delivery->driver_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the assigned driver can mark delivery as delivered',
                ], 403);
            }

            if ($delivery->status !== 'in_transit') {
                return response()->json([
                    'success' => false,
                    'message' => 'Delivery must be in transit to mark as delivered',
                ], 400);
            }

            $data = $request->validated();
            $delivery = $this->deliveryService->markAsDelivered($delivery, $data);

            return response()->json([
                'success' => true,
                'message' => 'Delivery completed successfully. Earnings have been credited.',
                'data' => new DeliveryResource($delivery),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to mark delivery as delivered', [
                'delivery_id' => $id,
                'user_id' => auth()->id(),
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
     * @OA\Post(
     *     path="/api/deliveries/{id}/cancel",
     *     tags={"Deliveries"},
     *     summary="Cancel delivery (before pickup only)",
     *     description="Cancel a delivery before item pickup. Cannot be done after pickup.",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Delivery ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(property="reason", type="string", example="Seller not available", description="Reason for delivery cancellation")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Delivery cancelled. A new delivery has been created for reassignment."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Cannot cancel delivery after pickup"),
     *     @OA\Response(response=403, description="Only assigned driver or admin can cancel"),
     *     @OA\Response(response=404, description="Delivery not found")
     * )
     */
    public function cancelDelivery(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $delivery = Delivery::findOrFail($id);

            // Only the assigned driver or admin can cancel
            if ($delivery->driver_id !== $user->id && !$user->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to cancel this delivery',
                ], 403);
            }

            $request->validate([
                'reason' => 'required|string|max:255',
            ]);

            $reason = $request->input('reason');
            $delivery = $this->deliveryService->cancelDelivery($delivery, $reason);

            return response()->json([
                'success' => true,
                'message' => 'Delivery cancelled successfully. A new delivery has been created for reassignment.',
                'data' => new DeliveryResource($delivery),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to cancel delivery', [
                'delivery_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 400);
        }
    }
}
