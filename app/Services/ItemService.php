<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemImage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;

class ItemService
{
    protected FirebaseStorageService $firebaseStorage;

    public function __construct(FirebaseStorageService $firebaseStorage)
    {
        $this->firebaseStorage = $firebaseStorage;
    }

    /**
     * Get items with filters and pagination
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getItems(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Item::query()
            ->with(['seller:id,name,email,city', 'images'])
            ->where('status', 'available');

        // Apply filters
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['size'])) {
            $query->where('size', $filters['size']);
        }

        if (!empty($filters['condition'])) {
            $query->where('condition', $filters['condition']);
        }

        if (!empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        if (isset($filters['is_donation'])) {
            $query->where('is_donation', $filters['is_donation']);
        }

        // Price range (only for non-donations)
        if (!empty($filters['min_price']) || !empty($filters['max_price'])) {
            $query->where('is_donation', false);

            if (!empty($filters['min_price'])) {
                $query->where('price', '>=', $filters['min_price']);
            }

            if (!empty($filters['max_price'])) {
                $query->where('price', '<=', $filters['max_price']);
            }
        }

        // Search keyword
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('title', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%")
                    ->orWhere('brand', 'ILIKE', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'newest';

        switch ($sortBy) {
            case 'price_low':
                $query->where('is_donation', false)
                    ->orderBy('price', 'asc');
                break;
            case 'price_high':
                $query->where('is_donation', false)
                    ->orderBy('price', 'desc');
                break;
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'newest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        return $query->paginate($perPage);
    }

    /**
     * Get a single item by ID
     *
     * @param int $id
     * @param bool $incrementViews
     * @return Item|null
     */
    public function getItemById(int $id, bool $incrementViews = true): ?Item
    {
        $item = Item::with(['seller', 'images'])
            ->find($id);

        if ($item && $incrementViews) {
            $item->increment('views_count');
        }

        return $item;
    }

    /**
     * Create a new item listing
     *
     * @param array $data
     * @param array $images
     * @param User $seller
     * @return Item
     */
    public function createItem(array $data, User $seller, array $images): Item
    {
        DB::beginTransaction();

        try {
            // If donation, set quantity fields; otherwise default to 1
            if ($data['is_donation']) {
                $donationQuantity = $data['donation_quantity'] ?? 1;
                $data['donation_quantity'] = $donationQuantity;
                $data['donation_quantity_available'] = $donationQuantity;
                $data['price'] = null; // Donations have no price
            } else {
                // Sale items always have quantity = 1 (unique items)
                $data['donation_quantity'] = 1;
                $data['donation_quantity_available'] = 1;
            }

            // Create item
            $item = Item::create([
                'seller_id' => $seller->id,
                'title' => $data['title'],
                'description' => $data['description'],
                'category' => $data['category'],
                'size' => $data['size'] ?? null,
                'condition' => $data['condition'],
                'gender' => $data['gender'] ?? 'unisex',
                'brand' => $data['brand'] ?? null,
                'color' => $data['color'] ?? null,
                'price' => $data['price'] ?? null,
                'is_donation' => $data['is_donation'],
                'donation_quantity' => $data['donation_quantity'],           // NEW
                'status' => 'available',
                'views_count' => 0,
            ]);

            // Upload images to storage
            if (!empty($images)) {
                $uploadedImages = $this->firebaseStorage->uploadItemImages($images, $item->id);

                // Save image records
                foreach ($uploadedImages as $imageData) {
                    $item->images()->create($imageData);
                }
            }

            DB::commit();

            // Load relationships
            $item->load(['images', 'seller']);

            Log::info('Item created successfully', [
                'item_id' => $item->id,
                'seller_id' => $seller->id,
                'is_donation' => $item->is_donation,
                'donation_quantity' => $item->donation_quantity,
            ]);

            return $item;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create item', [
                'seller_id' => $seller->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Update an item
     *
     * @param Item $item
     * @param array $data
     * @param array|null $newImages
     * @return Item
     */
    public function updateItem(Item $item, array $data, ?array $newImages = null): Item
    {
        DB::beginTransaction();

        try {
            // If changing to donation, set price to null
            if (isset($data['is_donation']) && $data['is_donation']) {
                $data['price'] = null;
            }

            // Update item (only non-null values)
            $updateData = array_filter($data, function ($value) {
                return $value !== null;
            });

            $item->update($updateData);

            // Upload new images if provided
            if (!empty($newImages)) {
                $uploadedImages = $this->firebaseStorage->uploadItemImages($newImages, $item->id);

                foreach ($uploadedImages as $imageData) {
                    // Adjust display_order to be after existing images
                    $maxOrder = $item->images()->max('display_order') ?? -1;
                    $imageData['display_order'] += ($maxOrder + 1);
                    $imageData['is_primary'] = false; // Don't override existing primary

                    $item->images()->create($imageData);
                }
            }

            DB::commit();

            // Load relationships
            $item->load(['images', 'seller']);

            Log::info('Item updated successfully', [
                'item_id' => $item->id,
                'updated_fields' => array_keys($updateData),
            ]);

            return $item;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update item', [
                'item_id' => $item->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete an item (soft delete)
     *
     * @param Item $item
     * @return bool
     */
    public function deleteItem(Item $item): bool
    {
        try {
            // Soft delete the item
            $deleted = $item->delete();

            Log::info('Item deleted successfully', [
                'item_id' => $item->id,
                'seller_id' => $item->seller_id,
            ]);

            return $deleted;
        } catch (\Exception $e) {
            Log::error('Failed to delete item', [
                'item_id' => $item->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Toggle item status between available and sold
     *
     * @param Item $item
     * @return Item
     */
    public function toggleStatus(Item $item): Item
    {
        $newStatus = $item->status === 'available' ? 'sold' : 'available';

        $item->update([
            'status' => $newStatus,
            'sold_at' => $newStatus === 'sold' ? now() : null,
        ]);

        Log::info('Item status toggled', [
            'item_id' => $item->id,
            'old_status' => $item->status,
            'new_status' => $newStatus,
        ]);

        return $item->fresh(['images', 'seller']);
    }

    /**
     * Get user's listings
     *
     * @param int $userId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getUserListings(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Item::with(['images'])
            ->where('seller_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Delete a single item image
     *
     * @param ItemImage $image
     * @return bool
     */
    public function deleteItemImage(ItemImage $image): bool
    {
        DB::beginTransaction();

        try {
            $item = $image->item;
            $wasPrimary = $image->is_primary;

            // Delete from storage
            $this->firebaseStorage->deleteImage($image->image_url);

            // Delete record
            $image->delete();

            // If this was primary and there are other images, make first one primary
            if ($wasPrimary && $item->images()->count() > 0) {
                $item->images()->first()->update(['is_primary' => true]);
            }

            DB::commit();

            Log::info('Item image deleted', [
                'image_id' => $image->id,
                'item_id' => $item->id,
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete item image', [
                'image_id' => $image->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
