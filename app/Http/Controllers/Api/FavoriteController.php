<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Item;
use App\Http\Resources\ItemResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FavoriteController extends Controller
{
    /**
     * POST /api/favorites/{itemId}
     * Add an item to favorites
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

            \Log::info('Item added to favorites', [
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
            \Log::error('Error adding item to favorites', [
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
     * DELETE /api/favorites/{itemId}
     * Remove an item from favorites
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

            \Log::info('Item removed from favorites', [
                'user_id' => $user->id,
                'item_id' => $itemId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Item removed from favorites',
            ]);

        } catch (\Exception $e) {
            \Log::error('Error removing item from favorites', [
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
     * GET /api/favorites
     * Get all favorites for the authenticated user
     */
    public function index(): JsonResponse
    {
        try {
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
            \Log::error('Error fetching favorites', [
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
