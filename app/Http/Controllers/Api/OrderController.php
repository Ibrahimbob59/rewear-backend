<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CreateOrderRequest;
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
        $this->middleware('auth:api');
    }

    /**
     * POST /api/orders
     * Create new order
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $order = $this->orderService->createOrder($data, auth()->user());
            
            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => new OrderResource($order),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/orders
     * Get buyer's orders
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $status = $request->query('status');
            $perPage = $request->query('per_page', 20);
            
            $orders = $this->orderService->getBuyerOrders(auth()->user(), $status, $perPage);
            
            return response()->json([
                'success' => true,
                'message' => 'Orders retrieved successfully',
                'data' => new OrderCollection($orders),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve orders',
                'error' => $e->getMessage(),
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
            $status = $request->query('status');
            $perPage = $request->query('per_page', 20);
            
            $orders = $this->orderService->getSellerOrders(auth()->user(), $status, $perPage);
            
            return response()->json([
                'success' => true,
                'message' => 'Sales retrieved successfully',
                'data' => new OrderCollection($orders),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sales',
                'error' => $e->getMessage(),
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
            $order = $this->orderService->getOrderById($id);
            
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }
            
            // Check if user is involved in order
            if (!$order->involvesUser(auth()->id())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access',
                ], 403);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Order retrieved successfully',
                'data' => new OrderResource($order),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PATCH /api/orders/{id}/cancel
     * Cancel order
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        try {
            // Validate cancellation reason
            $request->validate([
                'reason' => 'nullable|string|max:500',
            ]);
            
            $order = $this->orderService->cancelOrder(
                $order,
                auth()->user(),
                $request->input('reason')
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully',
                'data' => new OrderResource($order),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}