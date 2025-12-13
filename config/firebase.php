<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase API Key (Web API Key)
    |--------------------------------------------------------------------------
    |
    | Get this from Firebase Console → Project Settings → Web API Key
    |
    */
    'api_key' => env('FIREBASE_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Firebase Storage Bucket
    |--------------------------------------------------------------------------
    |
    | Your Firebase storage bucket name (e.g., your-project.appspot.com)
    |
    */
    'storage_bucket' => env('FIREBASE_STORAGE_BUCKET'),

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

        // Resize settings
        'resize' => [
            'max_width' => 1200,
            'max_height' => 1200,
            'quality' => 85,
        ],
    ],
];
