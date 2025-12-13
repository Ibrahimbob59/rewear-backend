<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Item\CreateItemRequest;
use App\Http\Requests\Item\UpdateItemRequest;
use App\Http\Requests\Item\ItemFilterRequest;
use App\Http\Resources\ItemResource;
use App\Http\Resources\ItemCollection;
use App\Models\Item;
use App\Services\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    protected $itemService;

    public function __construct(ItemService $itemService)
    {
        $this->itemService = $itemService;
    }

    /**
     * GET /api/items
     * List items with filters and pagination
     */
    public function index(ItemFilterRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();
            $perPage = $request->input('per_page', 15);

            $items = $this->itemService->getItems($filters, $perPage);

            return response()->json([
                'success' => true,
                'message' => 'Items retrieved successfully',
                'data' => new ItemCollection($items),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'total' => $items->total(),
                    'per_page' => $items->perPage(),
                    'last_page' => $items->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to retrieve items', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve items',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * GET /api/items/{id}
     * Get single item details
     */
    public function show(int $id): JsonResponse
    {
        try {
            $item = $this->itemService->getItemById($id, true);

            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found',
                ], 404);
            }

            // Check if item belongs to current user (for is_owner flag)
            $isOwner = auth()->check() && auth()->id() === $item->seller_id;

            return response()->json([
                'success' => true,
                'message' => 'Item retrieved successfully',
                'data' => new ItemResource($item),
                'meta' => [
                    'is_owner' => $isOwner,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to retrieve item', [
                'item_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve item',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * POST /api/items
     * Create a new item listing
     */
    public function store(CreateItemRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $images = $request->file('images', []);
            $user = auth()->user();

            $item = $this->itemService->createItem($data, $images, $user);

            return response()->json([
                'success' => true,
                'message' => 'Item created successfully',
                'data' => new ItemResource($item),
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Failed to create item', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create item',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred while creating your listing',
            ], 500);
        }
    }

    /**
     * PUT /api/items/{id}
     * Update an item
     */
    public function update(UpdateItemRequest $request, int $id): JsonResponse
    {
        try {
            $item = Item::findOrFail($id);

            // Authorization check (must be owner)
            if ($item->seller_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this item',
                ], 403);
            }

            // Cannot update if item is not available
            if ($item->status !== 'available') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update item that is not available',
                ], 400);
            }

            $data = $request->validated();
            $newImages = $request->file('images', []);

            $updatedItem = $this->itemService->updateItem($item, $data, $newImages);

            return response()->json([
                'success' => true,
                'message' => 'Item updated successfully',
                'data' => new ItemResource($updatedItem),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to update item', [
                'item_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update item',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * DELETE /api/items/{id}
     * Delete an item (soft delete)
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $item = Item::findOrFail($id);

            // Authorization check (must be owner)
            if ($item->seller_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this item',
                ], 403);
            }

            // Cannot delete if item has active orders
            if (in_array($item->status, ['pending', 'sold', 'donated'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete item with active orders',
                ], 400);
            }

            $this->itemService->deleteItem($item);

            return response()->json([
                'success' => true,
                'message' => 'Item deleted successfully',
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to delete item', [
                'item_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete item',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * GET /api/items/my-listings
     * Get current user's listings
     */
    public function myListings(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 15);
            $items = $this->itemService->getUserListings(auth()->id(), $perPage);

            return response()->json([
                'success' => true,
                'message' => 'Your listings retrieved successfully',
                'data' => new ItemCollection($items),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'total' => $items->total(),
                    'per_page' => $items->perPage(),
                    'last_page' => $items->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to retrieve user listings', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve your listings',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * POST /api/items/{id}/toggle-status
     * Toggle item status between available and sold
     */
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $item = Item::findOrFail($id);

            // Authorization check (must be owner)
            if ($item->seller_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this item',
                ], 403);
            }

            $updatedItem = $this->itemService->toggleStatus($item);

            return response()->json([
                'success' => true,
                'message' => "Item marked as {$updatedItem->status}",
                'data' => new ItemResource($updatedItem),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to toggle item status', [
                'item_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update item status',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
