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

class OrderController extends Controller
{
    protected $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * POST /api/orders
     * Create a new order
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $user = auth()->user();

            $order = $this->orderService->createOrder($data, $user);

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully',
                'data' => new OrderResource($order),
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Failed to create order', [
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
     * GET /api/orders
     * Get buyer's orders
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
            \Log::error('Failed to retrieve buyer orders', [
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
     * GET /api/orders/as-seller
     * Get seller's orders
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
            \Log::error('Failed to retrieve seller orders', [
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
     * GET /api/orders/{id}
     * Get order details
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
            \Log::error('Failed to retrieve order', [
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
     * PUT /api/orders/{id}/cancel
     * Cancel an order
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
            \Log::error('Failed to cancel order', [
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
