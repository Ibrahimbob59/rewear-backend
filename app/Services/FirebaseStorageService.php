<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Storage as FirebaseStorage;
use Google\Cloud\Storage\StorageClient;

/**
 * Firebase Storage Service
 *
 * Handles image uploads to Firebase Cloud Storage
 * Requires: composer require kreait/firebase-php
 */
class FirebaseStorageService
{
    protected $storage;
    protected $bucket;
    protected $bucketName;

    public function __construct()
    {
        try {
            // Initialize Firebase
            $factory = (new Factory)
                ->withServiceAccount(config('firebase.credentials.file'))
                ->withDefaultStorageBucket(config('firebase.storage.bucket'));

            $this->storage = $factory->createStorage();
            $this->bucket = $this->storage->getBucket();
            $this->bucketName = config('firebase.storage.bucket');
        } catch (\Exception $e) {
            Log::error('Failed to initialize Firebase Storage', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Firebase Storage initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Upload multiple item images to Firebase Storage
     *
     * @param array $images Array of UploadedFile instances
     * @param int $itemId
     * @return array Array of image data with Firebase URLs
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
                Log::error('Failed to upload image to Firebase', [
                    'item_id' => $itemId,
                    'index' => $index,
                    'error' => $e->getMessage(),
                ]);
                // Continue with other images even if one fails
            }
        }

        return $uploadedImages;
    }

    /**
     * Upload a single image to Firebase Storage
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
            throw new \Exception('Invalid image file');
        }

        // Generate unique filename
        $filename = $this->generateFilename($image, $itemId);

        // Define Firebase storage path
        $firebasePath = "items/{$itemId}/{$filename}";

        // Get file contents
        $fileContents = file_get_contents($image->getRealPath());

        // Adding token
        $token = (string) \Illuminate\Support\Str::uuid();

        // Upload to Firebase
        $object = $this->bucket->upload($fileContents, [
        'name' => $firebasePath,
        'metadata' => [
            'contentType' => $image->getMimeType(),
            'metadata' => [
                'firebaseStorageDownloadTokens' => $token,

                'itemId' => (string)$itemId,
                'originalName' => $image->getClientOriginalName(),
                'uploadedAt' => now()->toIso8601String(),
            ],
        ],
    ]);


        // Make the file publicly accessible
        $object->update([
            'acl' => [],
        ], [
            'predefinedAcl' => 'publicRead'
        ]);

        // Get public URL
        $publicUrl = $this->getPublicUrl($firebasePath, $token);


        Log::info('Image uploaded to Firebase successfully', [
            'item_id' => $itemId,
            'path' => $firebasePath,
            'url' => $publicUrl,
        ]);

        return [
            'image_url' => $publicUrl,
            'display_order' => $index,
            'is_primary' => $index === 0, // First image is primary
        ];
    }

    /**
     * Delete an image from Firebase Storage
     *
     * @param string $imageUrl
     * @return bool
     */
    public function deleteImage(string $imageUrl): bool
    {
        try {
            // Extract path from URL
            $path = $this->extractPathFromUrl($imageUrl);

            if (!$path) {
                Log::warning('Could not extract path from URL', ['url' => $imageUrl]);
                return false;
            }

            // Delete from Firebase
            $object = $this->bucket->object($path);

            if ($object->exists()) {
                $object->delete();
                Log::info('Image deleted from Firebase', ['path' => $path]);
                return true;
            }

            return true; // Already deleted
        } catch (\Exception $e) {
            Log::error('Failed to delete image from Firebase', [
                'url' => $imageUrl,
                'error' => $e->getMessage(),
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
            $prefix = "items/{$itemId}/";

            // List all objects with this prefix
            $objects = $this->bucket->objects([
                'prefix' => $prefix,
            ]);

            // Delete each object
            foreach ($objects as $object) {
                $object->delete();
            }

            Log::info('All images deleted for item', ['item_id' => $itemId]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete item images from Firebase', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
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
        // Check if file is valid
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
     * Get public URL for a Firebase Storage object
     *
     * @param string $path
     * @return string
     */
    protected function getPublicUrl(string $path, string $token): string
    {
        return sprintf(
            'https://firebasestorage.googleapis.com/v0/b/%s/o/%s?alt=media&token=%s',
            $this->bucketName,
            urlencode($path),
            $token
        );
    }

    /**
     * Extract storage path from Firebase URL
     *
     * @param string $url
     * @return string|null
     */
    protected function extractPathFromUrl(string $url): ?string
    {
        // Parse Firebase Storage URL
        // Format: https://firebasestorage.googleapis.com/v0/b/{bucket}/o/{path}?alt=media

        if (preg_match('/\/o\/(.+?)\?alt=media/', $url, $matches)) {
            return urldecode($matches[1]);
        }

        return null;
    }

    /**
     * Get signed URL (for temporary access)
     *
     * @param string $path
     * @param int $expirationMinutes
     * @return string
     */
    public function getSignedUrl(string $path, int $expirationMinutes = 60): string
    {
        $object = $this->bucket->object($path);

        $expiresAt = new \DateTime();
        $expiresAt->add(new \DateInterval("PT{$expirationMinutes}M"));

        return $object->signedUrl($expiresAt);
    }

    /**
     * Check if object exists in Firebase Storage
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool
    {
        try {
            $object = $this->bucket->object($path);
            return $object->exists();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get object metadata
     *
     * @param string $path
     * @return array|null
     */
    public function getMetadata(string $path): ?array
    {
        try {
            $object = $this->bucket->object($path);

            if (!$object->exists()) {
                return null;
            }

            return $object->info();
        } catch (\Exception $e) {
            Log::error('Failed to get object metadata', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
