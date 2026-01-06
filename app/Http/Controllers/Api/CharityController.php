<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Charity\AcceptDonationRequest;
use App\Http\Requests\Charity\MarkDistributedRequest;
use App\Http\Resources\ItemResource;
use App\Http\Resources\OrderResource;
use App\Models\Item;
use App\Models\Order;
use App\Services\DonationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CharityController extends Controller
{
    protected DonationService $donationService;

    public function __construct(DonationService $donationService)
    {
        $this->donationService = $donationService;
    }

    /**
     * @OA\Get(
     *     path="/api/charity/dashboard",
     *     tags={"Charity"},
     *     summary="Get charity dashboard",
     *     description="Get charity's impact statistics and overview",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="impact_stats", type="object"),
     *                 @OA\Property(property="recent_donations", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="pending_distributions", type="integer", example=3)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Not a registered charity"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function dashboard(): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user->hasRole('charity')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a registered charity',
                ], 403);
            }

            // Get charity impact statistics
            $impactStats = $this->donationService->getCharityImpactStats($user);

            // Get recent donations (last 5)
            $recentDonations = $this->donationService->getCharityDonations($user, 5);

            // Count pending distributions
            $pendingDistributions = Order::where('buyer_id', $user->id)
                ->where('item_price', 0)
                ->where('status', 'completed')
                ->whereNull('distributed_at')
                ->count();

            return response()->json([
                'success' => true,
                'message' => 'Dashboard data retrieved successfully',
                'data' => [
                    'impact_stats' => $impactStats,
                    'recent_donations' => OrderResource::collection($recentDonations),
                    'pending_distributions' => $pendingDistributions,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve charity dashboard', [
                'charity_id' => auth()->id(),
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
     *     path="/api/charity/available-donations",
     *     tags={"Charity"},
     *     summary="Get available donations",
     *     description="Get list of available donation items",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter by category",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="condition",
     *         in="query",
     *         description="Filter by condition",
     *         required=false,
     *         @OA\Schema(type="string", enum={"new","like_new","good","fair"})
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search keyword",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Available donations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=403, description="Not a registered charity"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function availableDonations(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user->hasRole('charity')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a registered charity',
                ], 403);
            }

            $filters = $request->only(['category', 'condition', 'size', 'city', 'search']);
            $perPage = $request->input('per_page', 15);

            $donations = $this->donationService->getAvailableDonations($filters, $perPage);

            return response()->json([
                'success' => true,
                'message' => 'Available donations retrieved successfully',
                'data' => ItemResource::collection($donations),
                'meta' => [
                    'current_page' => $donations->currentPage(),
                    'total' => $donations->total(),
                    'per_page' => $donations->perPage(),
                    'last_page' => $donations->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve available donations', [
                'charity_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available donations',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/charity/accept-donation/{itemId}",
     *     tags={"Charity"},
     *     summary="Accept donation item",
     *     description="Accept a donation item for delivery to charity",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="itemId",
     *         in="path",
     *         description="Item ID to accept",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"delivery_address_id"},
     *             @OA\Property(property="delivery_address_id", type="integer", example=1, description="Charity's delivery address ID")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Donation accepted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Donation accepted successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Donation not available or already accepted"),
     *     @OA\Response(response=403, description="Not a registered charity"),
     *     @OA\Response(response=404, description="Item not found")
     * )
     */
    public function acceptDonation(AcceptDonationRequest $request, int $itemId): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user->hasRole('charity')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a registered charity',
                ], 403);
            }

            $item = Item::findOrFail($itemId);

            // Validate this is a donation item
            if (!$item->is_donation) {
                return response()->json([
                    'success' => false,
                    'message' => 'This item is not available for donation',
                ], 400);
            }

            $data = $request->validated();
            $deliveryAddress = $user->addresses()->findOrFail($data['delivery_address_id']);

            $order = $this->donationService->acceptDonation($item, $user, $deliveryAddress, $data);

            return response()->json([
                'success' => true,
                'message' => 'Donation accepted successfully! A driver will be assigned for pickup and delivery.',
                'data' => new OrderResource($order),
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to accept donation', [
                'charity_id' => auth()->id(),
                'item_id' => $itemId,
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
     *     path="/api/charity/my-donations",
     *     tags={"Charity"},
     *     summary="Get charity's received donations",
     *     description="Get all donations received by the charity",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by order status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending","confirmed","in_transit","completed"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Charity donations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=403, description="Not a registered charity"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function myDonations(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user->hasRole('charity')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a registered charity',
                ], 403);
            }

            $perPage = $request->input('per_page', 15);
            $donations = $this->donationService->getCharityDonations($user, $perPage);

            return response()->json([
                'success' => true,
                'message' => 'Donations retrieved successfully',
                'data' => OrderResource::collection($donations),
                'meta' => [
                    'current_page' => $donations->currentPage(),
                    'total' => $donations->total(),
                    'per_page' => $donations->perPage(),
                    'last_page' => $donations->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve charity donations', [
                'charity_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve donations',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/charity/mark-distributed/{orderId}",
     *     tags={"Charity"},
     *     summary="Mark donation as distributed",
     *     description="Mark a received donation as distributed to people in need",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="orderId",
     *         in="path",
     *         description="Order ID to mark as distributed",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"people_helped"},
     *             @OA\Property(property="people_helped", type="integer", example=5, description="Number of people helped"),
     *             @OA\Property(property="distribution_notes", type="string", example="Distributed to homeless shelter", description="Optional distribution notes")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Donation marked as distributed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Donation marked as distributed successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Order not eligible for distribution"),
     *     @OA\Response(response=403, description="Not authorized to mark this donation"),
     *     @OA\Response(response=404, description="Order not found")
     * )
     */
    public function markDistributed(MarkDistributedRequest $request, int $orderId): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user->hasRole('charity')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a registered charity',
                ], 403);
            }

            $order = Order::where('id', $orderId)
                ->where('buyer_id', $user->id)
                ->where('item_price', 0) // Must be donation
                ->firstOrFail();

            $data = $request->validated();
            $order = $this->donationService->markAsDistributed($order, $data);

            return response()->json([
                'success' => true,
                'message' => "Donation marked as distributed successfully! You helped {$data['people_helped']} people.",
                'data' => new OrderResource($order),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to mark donation as distributed', [
                'charity_id' => auth()->id(),
                'order_id' => $orderId,
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
     *     path="/api/charity/impact-stats",
     *     tags={"Charity"},
     *     summary="Get charity impact statistics",
     *     description="Get detailed impact statistics for the charity",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Impact statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_donations_received", type="integer", example=25),
     *                 @OA\Property(property="total_people_helped", type="integer", example=150),
     *                 @OA\Property(property="this_month_donations", type="integer", example=8),
     *                 @OA\Property(property="completion_rate", type="number", example=96.5)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Not a registered charity"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function impactStats(): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user->hasRole('charity')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a registered charity',
                ], 403);
            }

            $stats = $this->donationService->getCharityImpactStats($user);

            return response()->json([
                'success' => true,
                'message' => 'Impact statistics retrieved successfully',
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve charity impact stats', [
                'charity_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve impact statistics',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/charity/recommended-donations",
     *     tags={"Charity"},
     *     summary="Get recommended donations",
     *     description="Get donation recommendations based on charity's history",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Recommended donations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=403, description="Not a registered charity"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function recommendedDonations(): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user->hasRole('charity')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a registered charity',
                ], 403);
            }

            $recommendations = $this->donationService->getRecommendedDonations($user, 10);

            return response()->json([
                'success' => true,
                'message' => 'Recommended donations retrieved successfully',
                'data' => ItemResource::collection($recommendations),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve recommended donations', [
                'charity_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve recommendations',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
