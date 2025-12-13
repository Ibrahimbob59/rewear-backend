<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Item;
use App\Http\Resources\ItemResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class FavoriteController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/favorites/{itemId}",
     *     tags={"Favorites"},
     *     summary="Add item to favorites",
     *     description="Add an item to your favorites list",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="itemId",
     *         in="path",
     *         description="Item ID to favorite",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Item added to favorites successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Item added to favorites"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="favorite_id", type="integer", example=1),
     *                 @OA\Property(property="item", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Item already favorited or cannot favorite own item"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Item not found")
     * )
     */
    public function store(int $itemId): JsonResponse
    {
        try {
            /** @var User $user */
            $user = auth()->user();

            // Check if item exists
            $item = Item::find($itemId);

            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found',
                ], 404);
            }

            // Cannot favorite own item
            if ($item->seller_id === $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot favorite your own item',
                ], 400);
            }

            // Check if already favorited
            $existingFavorite = Favorite::where('user_id', $user->id)
                ->where('item_id', $itemId)
                ->first();

            if ($existingFavorite) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item is already in your favorites',
                ], 400);
            }

            // Create favorite
            $favorite = Favorite::create([
                'user_id' => $user->id,
                'item_id' => $itemId,
            ]);

            Log::info('Item added to favorites', [
                'user_id' => $user->id,
                'item_id' => $itemId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Item added to favorites',
                'data' => [
                    'favorite_id' => $favorite->id,
                    'item' => new ItemResource($item->load(['seller', 'images'])),
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error adding item to favorites', [
                'user_id' => auth()->id(),
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add item to favorites',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/favorites/{itemId}",
     *     tags={"Favorites"},
     *     summary="Remove item from favorites",
     *     description="Remove an item from your favorites list",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="itemId",
     *         in="path",
     *         description="Item ID to unfavorite",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Item removed from favorites successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Item removed from favorites")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Item not found in favorites")
     * )
     */
    public function destroy(int $itemId): JsonResponse
    {
        try {
            /** @var User $user */
            $user = auth()->user();

            // Find the favorite
            $favorite = Favorite::where('user_id', $user->id)
                ->where('item_id', $itemId)
                ->first();

            if (!$favorite) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found in favorites',
                ], 404);
            }

            // Delete the favorite
            $favorite->delete();

            Log::info('Item removed from favorites', [
                'user_id' => $user->id,
                'item_id' => $itemId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Item removed from favorites',
            ]);

        } catch (\Exception $e) {
            Log::error('Error removing item from favorites', [
                'user_id' => auth()->id(),
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove item from favorites',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/favorites",
     *     tags={"Favorites"},
     *     summary="Get user's favorites",
     *     description="Get all items favorited by the authenticated user",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Favorites retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object")
     *             ),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="total", type="integer", example=5)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(): JsonResponse
    {
        try {
            /** @var User $user */
            $user = auth()->user();

            // Get user's favorites with item details
            $favorites = Favorite::where('user_id', $user->id)
                ->with([
                    'item' => function ($query) {
                        $query->with([
                            'seller:id,name,email,city',
                            'images',
                        ]);
                    }
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            // Filter out favorites where the item has been deleted
            $favorites = $favorites->filter(function ($favorite) {
                return $favorite->item !== null;
            });

            // Map to items
            $items = $favorites->map(function ($favorite) {
                return new ItemResource($favorite->item);
            });

            return response()->json([
                'success' => true,
                'message' => 'Favorites retrieved successfully',
                'data' => $items,
                'meta' => [
                    'total' => $items->count(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching favorites', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch favorites',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
