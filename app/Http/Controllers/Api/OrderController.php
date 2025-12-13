<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Http\Requests\Order\CancelOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderCollection;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * @OA\Post(
     *     path="/api/orders",
     *     tags={"Orders"},
     *     summary="Create a new order",
     *     description="Place an order for an item (Cash on Delivery)",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"item_id","delivery_address_id","delivery_fee"},
     *             @OA\Property(property="item_id", type="integer", example=1, description="ID of the item to order"),
     *             @OA\Property(property="delivery_address_id", type="integer", example=1, description="ID of delivery address"),
     *             @OA\Property(property="delivery_fee", type="number", format="float", example=2.50, description="Delivery fee calculated by frontend")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Order placed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Order placed successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="order_number", type="string", example="RW-20251213-00001"),
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="item_price", type="number", format="float", example=25.00),
     *                 @OA\Property(property="delivery_fee", type="number", format="float", example=2.50),
     *                 @OA\Property(property="total_amount", type="number", format="float", example=27.50),
     *                 @OA\Property(property="payment_method", type="string", example="cod"),
     *                 @OA\Property(property="buyer", type="object"),
     *                 @OA\Property(property="seller", type="object"),
     *                 @OA\Property(property="item", type="object"),
     *                 @OA\Property(property="delivery", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Cannot order own item or item not available"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Item or address not found")
     * )
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            $order = $this->orderService->createOrder($data, $user);

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully',
                'data' => new OrderResource($order),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create order', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : 'Failed to place order',
            ], 400);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/orders",
     *     tags={"Orders"},
     *     summary="Get buyer's orders",
     *     description="Get all orders where the authenticated user is the buyer",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Orders per page (1-50)",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Orders retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Orders retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="orders", type="array",
     *                     @OA\Items(type="object")
     *                 )
     *             ),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 15);
            $orders = $this->orderService->getBuyerOrders(auth()->id(), $perPage);

            return response()->json([
                'success' => true,
                'message' => 'Orders retrieved successfully',
                'data' => new OrderCollection($orders),
                'meta' => [
                    'current_page' => $orders->currentPage(),
                    'total' => $orders->total(),
                    'per_page' => $orders->perPage(),
                    'last_page' => $orders->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve buyer orders', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve orders',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/orders/as-seller",
     *     tags={"Orders"},
     *     summary="Get seller's orders",
     *     description="Get all orders where the authenticated user is the seller",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Orders per page (1-50)",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sales retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sales retrieved successfully"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function asSeller(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 15);
            $orders = $this->orderService->getSellerOrders(auth()->id(), $perPage);

            return response()->json([
                'success' => true,
                'message' => 'Sales retrieved successfully',
                'data' => new OrderCollection($orders),
                'meta' => [
                    'current_page' => $orders->currentPage(),
                    'total' => $orders->total(),
                    'per_page' => $orders->perPage(),
                    'last_page' => $orders->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve seller orders', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sales',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/orders/{id}",
     *     tags={"Orders"},
     *     summary="Get order details",
     *     description="Get detailed information about a specific order (buyer or seller only)",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Order ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Order retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Order not found")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $order = $this->orderService->getOrderById($id, auth()->id());

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Order retrieved successfully',
                'data' => new OrderResource($order),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve order', [
                'order_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve order',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/orders/{id}/cancel",
     *     tags={"Orders"},
     *     summary="Cancel an order",
     *     description="Cancel an order (buyer only, only pending/confirmed orders)",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Order ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(property="reason", type="string", maxLength=500, example="Changed my mind, found a better option")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Order cancelled successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="status", type="string", example="cancelled"),
     *                 @OA\Property(property="cancelled_at", type="string", format="date-time"),
     *                 @OA\Property(property="cancellation_reason", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Cannot cancel order at this stage"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Only buyer can cancel order"),
     *     @OA\Response(response=404, description="Order not found")
     * )
     */
    public function cancel(CancelOrderRequest $request, int $id): JsonResponse
    {
        try {
            $order = Order::findOrFail($id);

            // Authorization: only buyer can cancel
            if ($order->buyer_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to cancel this order',
                ], 403);
            }

            $data = $request->validated();
            $cancelledOrder = $this->orderService->cancelOrder($order, $data['reason']);

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully',
                'data' => new OrderResource($cancelledOrder),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cancel order', [
                'order_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : 'Failed to cancel order',
            ], 400);
        }
    }
}
