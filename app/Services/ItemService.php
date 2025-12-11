<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemImage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ItemService
{
    protected $firebaseStorage;

    public function __construct(FirebaseStorageService $firebaseStorage)
    {
        $this->firebaseStorage = $firebaseStorage;
    }

    /**
     * Create a new item listing
     * 
     * @param array $data
     * @param array $images
     * @param User $seller
     * @return Item
     */
    public function createItem(array $data, array $images, User $seller): Item
    {
        DB::beginTransaction();

        try {
            // If it's a donation, ensure price is null
            if ($data['is_donation']) {
                $data['price'] = null;
            }

            // Create item
            $item = $seller->items()->create([
                'title' => $data['title'],
                'description' => $data['description'],
                'category' => $data['category'],
                'size' => $data['size'],
                'condition' => $data['condition'],
                'gender' => $data['gender'] ?? 'unisex',
                'brand' => $data['brand'] ?? null,
                'color' => $data['color'] ?? null,
                'price' => $data['price'] ?? null,
                'is_donation' => $data['is_donation'],
                'status' => 'available',
                'views_count' => 0,
            ]);

            // Upload images to Firebase
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

            return $item;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create item: ' . $e->getMessage());
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

            // Update item
            $item->update(array_filter($data, function ($value) {
                return $value !== null;
            }));

            // Upload new images if provided
            if (!empty($newImages)) {
                $uploadedImages = $this->firebaseStorage->uploadItemImages($newImages, $item->id);
                
                foreach ($uploadedImages as $imageData) {
                    $item->images()->create($imageData);
                }
            }

            DB::commit();

            // Load relationships
            $item->load(['images', 'seller']);

            return $item;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update item: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete an item and its images
     * 
     * @param Item $item
     * @return bool
     */
    public function deleteItem(Item $item): bool
    {
        DB::beginTransaction();

        try {
            // Delete images from Firebase
            foreach ($item->images as $image) {
                $this->firebaseStorage->deleteImage($image->image_url);
            }

            // Delete item (soft delete)
            $item->delete();

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete item: ' . $e->getMessage());
            throw $e;
        }
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
            // Delete from Firebase
            $this->firebaseStorage->deleteImage($image->image_url);

            // Delete record
            $image->delete();

            // If this was primary and there are other images, make first one primary
            $item = $image->item;
            if ($image->is_primary && $item->images()->count() > 0) {
                $item->images()->first()->setAsPrimary();
            }

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete item image: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get filtered and paginated items
     * 
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getItems(array $filters, int $perPage = 20)
    {
        $query = Item::query()
            ->with(['images', 'seller:id,name,location_lat,location_lng'])
            ->available(); // Only available items

        // Apply filters
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['category'])) {
            $query->byCategory($filters['category']);
        }

        if (isset($filters['min_price']) || isset($filters['max_price'])) {
            $query->byPriceRange($filters['min_price'] ?? null, $filters['max_price'] ?? null);
        }

        if (!empty($filters['size'])) {
            $query->bySize($filters['size']);
        }

        if (!empty($filters['condition'])) {
            $query->byCondition($filters['condition']);
        }

        if (!empty($filters['gender'])) {
            $query->byGender($filters['gender']);
        }

        if (isset($filters['is_donation'])) {
            if ($filters['is_donation']) {
                $query->donations();
            } else {
                $query->forSale();
            }
        }

        // Location filter (if user location provided)
        if (!empty($filters['latitude']) && !empty($filters['longitude'])) {
            $radius = $filters['radius'] ?? 50; // Default 50km
            $query->nearby($filters['latitude'], $filters['longitude'], $radius);
        }

        // Sorting
        $sort = $filters['sort'] ?? 'newest';
        $query->sorted($sort);

        return $query->paginate($perPage);
    }

    /**
     * Get item by ID with relationships
     * 
     * @param int $id
     * @return Item|null
     */
    public function getItemById(int $id): ?Item
    {
        return Item::with([
            'images',
            'seller:id,name,email,phone,location_lat,location_lng,created_at',
        ])->find($id);
    }

    /**
     * Increment item views
     * 
     * @param Item $item
     * @return void
     */
    public function incrementViews(Item $item): void
    {
        $item->incrementViews();
    }

    /**
     * Toggle item status
     * 
     * @param Item $item
     * @param string $status
     * @return Item
     */
    public function toggleStatus(Item $item, string $status): Item
    {
        $allowedStatuses = ['available', 'cancelled'];
        
        if (!in_array($status, $allowedStatuses)) {
            throw new \Exception('Invalid status');
        }

        if ($status === 'available') {
            $item->markAsAvailable();
        } elseif ($status === 'cancelled') {
            $item->cancel();
        }

        return $item->fresh();
    }
}
