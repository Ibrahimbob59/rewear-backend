<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Intervention\Image\Facades\Image;
use Exception;

/**
 * Firebase Storage Service
 * 
 * Uploads images directly to Firebase Storage using REST API
 * No package dependencies - pure HTTP approach
 * 
 * Setup Required:
 * 1. Add to .env:
 *    FIREBASE_STORAGE_BUCKET=your-project.appspot.com
 *    FIREBASE_API_KEY=your-web-api-key
 * 
 * 2. Firebase Console Setup:
 *    - Go to Storage > Rules
 *    - Set rules to allow uploads (see documentation)
 */
class FirebaseStorageService
{
    private string $storageBucket;
    private string $apiKey;
    private string $uploadUrl;

    public function __construct()
    {
        $this->storageBucket = config('services.firebase.storage_bucket');
        $this->apiKey = config('services.firebase.api_key');
        $this->uploadUrl = "https://firebasestorage.googleapis.com/v0/b/{$this->storageBucket}/o";
    }

    /**
     * Upload multiple item images to Firebase Storage
     * 
     * @param array $images Array of UploadedFile instances
     * @param int $itemId Item ID for organizing files
     * @param int $userId User ID for organizing files
     * @return array Array of uploaded image URLs
     * @throws Exception
     */
    public function uploadItemImages(array $images, int $itemId, int $userId): array
    {
        $uploadedUrls = [];

        foreach ($images as $index => $image) {
            if (!$image instanceof UploadedFile) {
                throw new Exception("Invalid image file at index {$index}");
            }

            // Validate image
            $this->validateImage($image);

            // Process and optimize image
            $processedImage = $this->processImage($image);

            // Generate unique filename
            $filename = $this->generateFilename($userId, $itemId, $index, $image->getClientOriginalExtension());

            // Upload to Firebase
            $url = $this->uploadToFirebase($processedImage, $filename);

            $uploadedUrls[] = [
                'url' => $url,
                'display_order' => $index,
                'is_primary' => $index === 0,
            ];
        }

        return $uploadedUrls;
    }

    /**
     * Delete an image from Firebase Storage
     * 
     * @param string $imageUrl Full Firebase Storage URL
     * @return bool Success status
     */
    public function deleteImage(string $imageUrl): bool
    {
        try {
            // Extract file path from URL
            $filePath = $this->extractFilePathFromUrl($imageUrl);

            if (!$filePath) {
                return false;
            }

            // Delete using Firebase REST API
            $response = Http::delete("{$this->uploadUrl}/{$filePath}?alt=media");

            return $response->successful();
        } catch (Exception $e) {
            report($e);
            return false;
        }
    }

    /**
     * Delete all images for an item
     * 
     * @param int $itemId Item ID
     * @param array $imageUrls Array of image URLs to delete
     * @return int Number of successfully deleted images
     */
    public function deleteItemImages(int $itemId, array $imageUrls): int
    {
        $deletedCount = 0;

        foreach ($imageUrls as $url) {
            if ($this->deleteImage($url)) {
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    /**
     * Validate uploaded image
     * 
     * @param UploadedFile $image
     * @throws Exception
     */
    private function validateImage(UploadedFile $image): void
    {
        // Check file size (max 5MB)
        if ($image->getSize() > 5 * 1024 * 1024) {
            throw new Exception('Image size must not exceed 5MB');
        }

        // Check file type
        $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        if (!in_array($image->getMimeType(), $allowedMimes)) {
            throw new Exception('Invalid image type. Allowed: JPEG, PNG, WebP');
        }

        // Validate it's actually an image
        if (!getimagesize($image->getRealPath())) {
            throw new Exception('File is not a valid image');
        }
    }

    /**
     * Process and optimize image
     * 
     * @param UploadedFile $image
     * @return string Processed image content
     */
    private function processImage(UploadedFile $image): string
    {
        // Load image using Intervention
        $img = Image::make($image->getRealPath());

        // Resize if too large (maintain aspect ratio)
        if ($img->width() > 1200 || $img->height() > 1200) {
            $img->resize(1200, 1200, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }

        // Optimize quality
        $img->orientate(); // Fix orientation based on EXIF data
        
        // Encode to JPEG with 85% quality
        return $img->encode('jpg', 85)->encoded;
    }

    /**
     * Generate unique filename for Firebase Storage
     * 
     * @param int $userId
     * @param int $itemId
     * @param int $index
     * @param string $extension
     * @return string
     */
    private function generateFilename(int $userId, int $itemId, int $index, string $extension): string
    {
        $timestamp = now()->format('YmdHis');
        $random = bin2hex(random_bytes(8));
        
        // Format: items/user_123/item_456/20251207123456_abc123def456_0.jpg
        return "items/user_{$userId}/item_{$itemId}/{$timestamp}_{$random}_{$index}.jpg";
    }

    /**
     * Upload file content to Firebase Storage
     * 
     * @param string $content File content
     * @param string $filename File path in Firebase
     * @return string Public URL of uploaded file
     * @throws Exception
     */
    private function uploadToFirebase(string $content, string $filename): string
    {
        try {
            // URL-encode the filename for Firebase
            $encodedFilename = str_replace('/', '%2F', $filename);

            // Upload URL
            $url = "{$this->uploadUrl}/{$encodedFilename}?uploadType=media&name={$filename}";

            // Upload with Authorization header
            $response = Http::withHeaders([
                'Content-Type' => 'image/jpeg',
            ])->withBody($content, 'image/jpeg')
              ->post($url);

            if (!$response->successful()) {
                throw new Exception('Firebase upload failed: ' . $response->body());
            }

            // Generate public URL
            return $this->generatePublicUrl($filename);

        } catch (Exception $e) {
            report($e);
            throw new Exception('Failed to upload image to Firebase: ' . $e->getMessage());
        }
    }

    /**
     * Generate public URL for uploaded file
     * 
     * @param string $filename
     * @return string
     */
    private function generatePublicUrl(string $filename): string
    {
        $encodedFilename = str_replace('/', '%2F', $filename);
        return "https://firebasestorage.googleapis.com/v0/b/{$this->storageBucket}/o/{$encodedFilename}?alt=media";
    }

    /**
     * Extract file path from Firebase URL
     * 
     * @param string $url
     * @return string|null
     */
    private function extractFilePathFromUrl(string $url): ?string
    {
        // Extract from URL like: https://firebasestorage.googleapis.com/v0/b/BUCKET/o/PATH?alt=media
        if (preg_match('/\/o\/(.+?)\?/', $url, $matches)) {
            return urldecode($matches[1]);
        }

        return null;
    }

    /**
     * Upload user profile picture
     * 
     * @param UploadedFile $image
     * @param int $userId
     * @return string Public URL
     */
    public function uploadProfilePicture(UploadedFile $image, int $userId): string
    {
        $this->validateImage($image);

        // Process image (square crop for profile pictures)
        $img = Image::make($image->getRealPath());
        
        // Crop to square
        $size = min($img->width(), $img->height());
        $img->crop($size, $size);
        
        // Resize to 400x400
        $img->resize(400, 400);
        
        // Encode
        $content = $img->encode('jpg', 85)->encoded;

        // Generate filename
        $timestamp = now()->format('YmdHis');
        $random = bin2hex(random_bytes(8));
        $filename = "profiles/user_{$userId}/{$timestamp}_{$random}.jpg";

        // Upload
        return $this->uploadToFirebase($content, $filename);
    }
}