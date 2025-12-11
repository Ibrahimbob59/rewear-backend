<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Item;
use App\Http\Resources\FavoriteResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FavoriteController extends Controller
{
    /**
     * Add an item to favorites.
     *
     * @param int $itemId
     * @return JsonResponse
     */
    public function store(int $itemId): JsonResponse
    {
        try {
            $user = auth()->user();

            // Check if item exists and is available
            $item = Item::find($itemId);

            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found',
                ], 404);
            }

            // Check if item is deleted (soft deleted)
            if ($item->trashed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This item is no longer available',
                ], 404);
            }

            // Optional: Check if user is trying to favorite their own item
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
                'favorite_id' => $favorite->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Item added to favorites',
                'data' => new FavoriteResource($favorite->load('item')),
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error adding item to favorites', [
                'user_id' => auth()->id(),
                'item_id' => $itemId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add item to favorites. Please try again.',
            ], 500);
        }
    }

    /**
     * Remove an item from favorites.
     *
     * @param int $itemId
     * @return JsonResponse
     */
    public function destroy(int $itemId): JsonResponse
    {
        try {
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
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error removing item from favorites', [
                'user_id' => auth()->id(),
                'item_id' => $itemId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove item from favorites. Please try again.',
            ], 500);
        }
    }

    /**
     * Get all favorites for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            // Get user's favorites with item details
            $favorites = Favorite::where('user_id', $user->id)
                ->with([
                    'item' => function ($query) {
                        $query->with([
                            'seller:id,full_name,email,city',
                            'images' => function ($q) {
                                $q->where('is_primary', true)
                                    ->orWhere('display_order', 0)
                                    ->orderBy('display_order')
                                    ->limit(1);
                            }
                        ]);
                    }
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            // Filter out favorites where the item has been deleted
            $favorites = $favorites->filter(function ($favorite) {
                return $favorite->item !== null;
            });

            return response()->json([
                'success' => true,
                'data' => FavoriteResource::collection($favorites),
                'meta' => [
                    'total' => $favorites->count(),
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching favorites', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch favorites. Please try again.',
            ], 500);
        }
    }

    /**
     * Check if an item is favorited by the authenticated user.
     *
     * @param int $itemId
     * @return JsonResponse
     */
    public function check(int $itemId): JsonResponse
    {
        try {
            $user = auth()->user();

            $isFavorited = Favorite::where('user_id', $user->id)
                ->where('item_id', $itemId)
                ->exists();

            return response()->json([
                'success' => true,
                'data' => [
                    'is_favorited' => $isFavorited,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error checking favorite status', [
                'user_id' => auth()->id(),
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check favorite status',
            ], 500);
        }
    }

    /**
     * Toggle favorite status for an item.
     * If favorited, remove it. If not favorited, add it.
     *
     * @param int $itemId
     * @return JsonResponse
     */
    public function toggle(int $itemId): JsonResponse
    {
        try {
            $user = auth()->user();

            // Check if item exists
            $item = Item::find($itemId);

            if (!$item || $item->trashed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found',
                ], 404);
            }

            // Check if already favorited
            $favorite = Favorite::where('user_id', $user->id)
                ->where('item_id', $itemId)
                ->first();

            if ($favorite) {
                // Remove from favorites
                $favorite->delete();

                Log::info('Item removed from favorites (toggle)', [
                    'user_id' => $user->id,
                    'item_id' => $itemId,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Item removed from favorites',
                    'data' => [
                        'is_favorited' => false,
                    ],
                ], 200);
            } else {
                // Add to favorites
                $favorite = Favorite::create([
                    'user_id' => $user->id,
                    'item_id' => $itemId,
                ]);

                Log::info('Item added to favorites (toggle)', [
                    'user_id' => $user->id,
                    'item_id' => $itemId,
                    'favorite_id' => $favorite->id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Item added to favorites',
                    'data' => [
                        'is_favorited' => true,
                        'favorite' => new FavoriteResource($favorite->load('item')),
                    ],
                ], 201);
            }

        } catch (\Exception $e) {
            Log::error('Error toggling favorite', [
                'user_id' => auth()->id(),
                'item_id' => $itemId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle favorite. Please try again.',
            ], 500);
        }
    }

    /**
     * Get count of user's favorites.
     *
     * @return JsonResponse
     */
    public function count(): JsonResponse
    {
        try {
            $user = auth()->user();

            $count = Favorite::where('user_id', $user->id)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'count' => $count,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error getting favorites count', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get favorites count',
            ], 500);
        }
    }

    /**
     * Clear all favorites for the authenticated user.
     *
     * @return JsonResponse
     */
    public function clear(): JsonResponse
    {
        try {
            $user = auth()->user();

            $deletedCount = Favorite::where('user_id', $user->id)->delete();

            Log::info('All favorites cleared', [
                'user_id' => $user->id,
                'count' => $deletedCount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'All favorites cleared',
                'data' => [
                    'deleted_count' => $deletedCount,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error clearing favorites', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear favorites. Please try again.',
            ], 500);
        }
    }
}
