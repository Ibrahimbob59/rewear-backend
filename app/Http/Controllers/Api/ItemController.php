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
        
        // Apply auth middleware
        $this->middleware('auth:api')->except(['index', 'show']);
        
        // Apply item ownership middleware for update/delete
        $this->middleware('item.ownership')->only(['update', 'destroy']);
    }

    /**
     * GET /api/items
     * List items with filters and pagination
     */
    public function index(ItemFilterRequest $request): JsonResponse
    {
        try {
            $filters = $request->getFilters();
            $perPage = $request->getPagination();
            
            $items = $this->itemService->getItems($filters, $perPage);
            
            return response()->json([
                'success' => true,
                'message' => 'Items retrieved successfully',
                'data' => new ItemCollection($items),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve items',
                'error' => $e->getMessage(),
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
            $item = $this->itemService->getItemById($id);
            
            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found',
                ], 404);
            }
            
            // Increment view count (async or skip if same user)
            if (!auth()->check() || $item->seller_id !== auth()->id()) {
                $this->itemService->incrementViews($item);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Item retrieved successfully',
                'data' => new ItemResource($item),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/items
     * Create new item listing
     */
    public function store(CreateItemRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $images = $request->file('images', []);
            
            $item = $this->itemService->createItem($data, $images, auth()->user());
            
            return response()->json([
                'success' => true,
                'message' => 'Item created successfully',
                'data' => new ItemResource($item),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT /api/items/{id}
     * Update item listing
     */
    public function update(UpdateItemRequest $request, Item $item): JsonResponse
    {
        try {
            // Ownership check is done by middleware
            
            // Check if item can be modified
            if (!$item->canBeModified()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item cannot be modified in current status',
                ], 422);
            }
            
            $data = $request->validated();
            $newImages = $request->file('images', []);
            
            $item = $this->itemService->updateItem($item, $data, $newImages);
            
            return response()->json([
                'success' => true,
                'message' => 'Item updated successfully',
                'data' => new ItemResource($item),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/items/{id}
     * Delete item listing
     */
    public function destroy(Item $item): JsonResponse
    {
        try {
            // Ownership check is done by middleware
            
            // Check if item can be deleted
            if (!$item->canBeModified()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item cannot be deleted in current status',
                ], 422);
            }
            
            $this->itemService->deleteItem($item);
            
            return response()->json([
                'success' => true,
                'message' => 'Item deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/items/my-listings
     * Get user's own listings
     */
    public function myListings(Request $request): JsonResponse
    {
        try {
            $status = $request->query('status'); // Filter by status
            $perPage = $request->query('per_page', 20);
            
            $query = auth()->user()->items()->with(['images'])->latest();
            
            if ($status) {
                $query->where('status', $status);
            }
            
            $items = $query->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'message' => 'Listings retrieved successfully',
                'data' => new ItemCollection($items),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve listings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PATCH /api/items/{id}/status
     * Toggle item status (available/cancelled)
     */
    public function toggleStatus(Request $request, Item $item): JsonResponse
    {
        try {
            // Check ownership
            if (!$item->isOwnedBy(auth()->id())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action',
                ], 403);
            }
            
            $request->validate([
                'status' => 'required|in:available,cancelled',
            ]);
            
            $item = $this->itemService->toggleStatus($item, $request->status);
            
            return response()->json([
                'success' => true,
                'message' => 'Item status updated successfully',
                'data' => new ItemResource($item),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}