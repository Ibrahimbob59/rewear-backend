<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Item\CreateItemRequest;
use App\Http\Requests\Item\UpdateItemRequest;
use App\Http\Requests\Item\ItemFilterRequest;
use App\Http\Resources\ItemResource;
use App\Http\Resources\ItemCollection;
use App\Models\Item;
use App\Models\User;
use App\Services\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ItemController extends Controller
{
    protected ItemService $itemService;

    public function __construct(ItemService $itemService)
    {
        $this->itemService = $itemService;
    }

    /**
     * @OA\Get(
     *     path="/api/items",
     *     tags={"Items"},
     *     summary="List items with filters and pagination",
     *     description="Browse all available items with optional filters for category, size, price range, etc.",
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search keyword (title, description, brand)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter by category",
     *         required=false,
     *         @OA\Schema(type="string", enum={"tops","bottoms","dresses","outerwear","shoes","accessories","other"})
     *     ),
     *     @OA\Parameter(
     *         name="size",
     *         in="query",
     *         description="Filter by size",
     *         required=false,
     *         @OA\Schema(type="string", enum={"XS","S","M","L","XL","XXL","XXXL","One Size"})
     *     ),
     *     @OA\Parameter(
     *         name="condition",
     *         in="query",
     *         description="Filter by condition",
     *         required=false,
     *         @OA\Schema(type="string", enum={"new","like_new","good","fair"})
     *     ),
     *     @OA\Parameter(
     *         name="gender",
     *         in="query",
     *         description="Filter by gender",
     *         required=false,
     *         @OA\Schema(type="string", enum={"male","female","unisex"})
     *     ),
     *     @OA\Parameter(
     *         name="is_donation",
     *         in="query",
     *         description="Filter donations only",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="min_price",
     *         in="query",
     *         description="Minimum price",
     *         required=false,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="max_price",
     *         in="query",
     *         description="Maximum price",
     *         required=false,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort order",
     *         required=false,
     *         @OA\Schema(type="string", enum={"newest","oldest","price_low","price_high"})
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page (1-50)",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Items retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Items retrieved successfully"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
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
            Log::error('Failed to retrieve items', [
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
     * @OA\Get(
     *     path="/api/items/{id}",
     *     tags={"Items"},
     *     summary="Get single item details",
     *     description="Get detailed information about a specific item (increments view count)",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Item ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Item retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Item retrieved successfully"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="is_owner", type="boolean", example=false)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Item not found")
     * )
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
            Log::error('Failed to retrieve item', [
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
     * @OA\Post(
     *     path="/api/items",
     *     tags={"Items"},
     *     summary="Create a new item listing",
     *     description="Create a new item for sale or donation",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"title","description","category","condition","is_donation","images"},
     *                 @OA\Property(property="title", type="string", example="Blue Denim Jacket"),
     *                 @OA\Property(property="description", type="string", example="Gently used denim jacket"),
     *                 @OA\Property(property="category", type="string", enum={"tops","bottoms","dresses","outerwear","shoes","accessories","other"}),
     *                 @OA\Property(property="size", type="string", enum={"XS","S","M","L","XL","XXL","XXXL","One Size"}),
     *                 @OA\Property(property="condition", type="string", enum={"new","like_new","good","fair"}),
     *                 @OA\Property(property="gender", type="string", enum={"male","female","unisex"}),
     *                 @OA\Property(property="brand", type="string", example="Levi's"),
     *                 @OA\Property(property="color", type="string", example="Blue"),
     *                 @OA\Property(property="is_donation", type="boolean", example=false),
     *                 @OA\Property(property="price", type="number", format="float", example=25.00, description="Required if is_donation=false"),
     *                 @OA\Property(property="donation_quantity", type="integer", example=10, description="Required if is_donation=true. Number of items in donation batch"),
     *                 @OA\Property(property="images", type="array", @OA\Items(type="string", format="binary"), description="1-6 images")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Item created successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(CreateItemRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $images = $request->file('images', []);

            /** @var User $user */
            $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

           $item = $this->itemService->createItem($data, $user, $images);

            return response()->json([
                'success' => true,
                'message' => 'Item created successfully',
                'data' => new ItemResource($item),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create item', [
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
     * @OA\Put(
     *     path="/api/items/{id}",
     *     tags={"Items"},
     *     summary="Update an item",
     *     description="Update your own item listing (must be owner)",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Item ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="price", type="number", format="float"),
     *             @OA\Property(property="condition", type="string", enum={"new","like_new","good","fair"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Item updated successfully"),
     *     @OA\Response(response=403, description="Unauthorized to update this item"),
     *     @OA\Response(response=404, description="Item not found")
     * )
     */
    public function update(UpdateItemRequest $request, int $id): JsonResponse
    {
        try {
            $item = Item::withTrashed()->find($id);

            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found',
                ], 404);
            }

            if ($item->trashed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item is already deleted',
                ], 400);
            }

            if ($item->seller_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this item',
                ], 403);
            }

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
            Log::error('Failed to update item', [
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
     * @OA\Delete(
     *     path="/api/items/{id}",
     *     tags={"Items"},
     *     summary="Delete an item",
     *     description="Delete your own item (soft delete, must be owner)",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Item ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Item deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Item deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=400, description="Cannot delete item with active orders")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $item = Item::withTrashed()->find($id);

            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found',
                ], 404);
            }

            if ($item->trashed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item is already deleted',
                ], 400);
            }

            if ($item->seller_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this item',
                ], 403);
            }

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
            Log::error('Failed to delete item', [
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
     * @OA\Get(
     *     path="/api/items/my-listings",
     *     tags={"Items"},
     *     summary="Get current user's listings",
     *     description="Get all items listed by the authenticated user",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(response=200, description="Listings retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
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
            Log::error('Failed to retrieve user listings', [
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
     * @OA\Post(
     *     path="/api/items/{id}/toggle-status",
     *     tags={"Items"},
     *     summary="Toggle item status",
     *     description="Toggle item status between available and sold (must be owner)",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Item ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Status toggled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Item marked as sold"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized")
     * )
     */
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $item = Item::findOrFail($id);

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
            Log::error('Failed to toggle item status', [
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
