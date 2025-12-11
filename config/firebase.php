<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Credentials
    |--------------------------------------------------------------------------
    |
    | Path to your Firebase service account credentials JSON file
    |
    */
    'credentials' => env('FIREBASE_CREDENTIALS', storage_path('firebase-credentials.json')),

    /*
    |--------------------------------------------------------------------------
    | Firebase Storage Bucket
    |--------------------------------------------------------------------------
    |
    | Your Firebase storage bucket name (e.g., your-project.appspot.com)
    |
    */
    'storage_bucket' => env('FIREBASE_STORAGE_BUCKET', ''),

    /*
    |--------------------------------------------------------------------------
    | Storage Path
    |--------------------------------------------------------------------------
    |
    | Base path for storing files in Firebase Storage
    |
    */
    'storage_path' => [
        'items' => 'items', // items/{itemId}/{filename}
        'temp' => 'temp',   // temporary uploads
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Settings
    |--------------------------------------------------------------------------
    */
    'images' => [
        'max_size' => 5 * 1024 * 1024, // 5MB in bytes
        'allowed_types' => ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'max_count' => 6,
        'min_count' => 1,
        
        // Resize settings (optional)
        'resize' => [
            'enabled' => true,
            'max_width' => 1920,
            'max_height' => 1920,
            'quality' => 85,
        ],
        
        // Thumbnail settings
        'thumbnail' => [
            'enabled' => true,
            'width' => 400,
            'height' => 400,
            'quality' => 80,
        ],
    ],
];