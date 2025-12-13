<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FirebaseStorageService
{
    /**
     * Upload multiple item images to storage
     *
     * @param array $images Array of UploadedFile instances
     * @param int $itemId
     * @return array Array of image data
     */
    public function uploadItemImages(array $images, int $itemId): array
    {
        $uploadedImages = [];

        foreach ($images as $index => $image) {
            try {
                $imageData = $this->uploadSingleImage($image, $itemId, $index);
                if ($imageData) {
                    $uploadedImages[] = $imageData;
                }
            } catch (\Exception $e) {
                Log::error('Failed to upload image', [
                    'item_id' => $itemId,
                    'index' => $index,
                    'error' => $e->getMessage()
                ]);
                // Continue with other images
            }
        }

        return $uploadedImages;
    }

    /**
     * Upload a single image
     *
     * @param UploadedFile $image
     * @param int $itemId
     * @param int $index
     * @return array|null
     */
    protected function uploadSingleImage(UploadedFile $image, int $itemId, int $index): ?array
    {
        // Validate image
        if (!$this->isValidImage($image)) {
            return null;
        }

        // Generate unique filename
        $filename = $this->generateFilename($image, $itemId);

        // Define storage path
        $path = "items/{$itemId}/{$filename}";

        // For now, store locally (can be replaced with Firebase SDK later)
        // Using public disk for easy access
        $storedPath = Storage::disk('public')->putFileAs(
            "items/{$itemId}",
            $image,
            $filename
        );

        if (!$storedPath) {
            return null;
        }

        // Generate URL
        $url = Storage::disk('public')->url($storedPath);

        return [
            'image_url' => $url,
            'display_order' => $index,
            'is_primary' => $index === 0, // First image is primary
        ];
    }

    /**
     * Delete an image from storage
     *
     * @param string $imageUrl
     * @return bool
     */
    public function deleteImage(string $imageUrl): bool
    {
        try {
            // Extract path from URL
            $path = $this->extractPathFromUrl($imageUrl);

            if ($path && Storage::disk('public')->exists($path)) {
                return Storage::disk('public')->delete($path);
            }

            return true; // Already deleted or doesn't exist
        } catch (\Exception $e) {
            Log::error('Failed to delete image', [
                'url' => $imageUrl,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Delete all images for an item
     *
     * @param int $itemId
     * @return bool
     */
    public function deleteItemImages(int $itemId): bool
    {
        try {
            $directory = "items/{$itemId}";

            if (Storage::disk('public')->exists($directory)) {
                return Storage::disk('public')->deleteDirectory($directory);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete item images', [
                'item_id' => $itemId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Validate image file
     *
     * @param UploadedFile $image
     * @return bool
     */
    protected function isValidImage(UploadedFile $image): bool
    {
        // Check if it's an image
        if (!$image->isValid()) {
            return false;
        }

        // Check mime type
        $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        if (!in_array($image->getMimeType(), $allowedMimes)) {
            return false;
        }

        // Check file size (max 5MB)
        $maxSize = 5 * 1024 * 1024; // 5MB in bytes
        if ($image->getSize() > $maxSize) {
            return false;
        }

        return true;
    }

    /**
     * Generate unique filename for image
     *
     * @param UploadedFile $image
     * @param int $itemId
     * @return string
     */
    protected function generateFilename(UploadedFile $image, int $itemId): string
    {
        $extension = $image->getClientOriginalExtension();
        $timestamp = now()->timestamp;
        $random = Str::random(8);

        return "item_{$itemId}_{$timestamp}_{$random}.{$extension}";
    }

    /**
     * Extract storage path from URL
     *
     * @param string $url
     * @return string|null
     */
    protected function extractPathFromUrl(string $url): ?string
    {
        // For local storage: extract path after 'storage/'
        if (strpos($url, 'storage/') !== false) {
            $parts = explode('storage/', $url);
            return $parts[1] ?? null;
        }

        return null;
    }

    /**
     * Get image URL (for compatibility)
     *
     * @param string $path
     * @return string
     */
    public function getImageUrl(string $path): string
    {
        return Storage::disk('public')->url($path);
    }
}
