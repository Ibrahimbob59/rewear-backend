<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Credentials
    |--------------------------------------------------------------------------
    |
    | Path to your Firebase service account JSON file
    | Download from: Firebase Console > Project Settings > Service Accounts
    |
    */
    'credentials' => [
        'file' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase/firebase-credentials.json')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Storage Bucket
    |--------------------------------------------------------------------------
    |
    | Your Firebase Storage bucket name
    | Format: your-project-id.appspot.com
    |
    */
    'storage' => [
        'bucket' => env('FIREBASE_STORAGE_BUCKET', 'rewear-app.appspot.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Realtime Database URL (if needed later)
    |--------------------------------------------------------------------------
    */
    'database' => [
        'url' => env('FIREBASE_DATABASE_URL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Project ID
    |--------------------------------------------------------------------------
    */
    'project_id' => env('FIREBASE_PROJECT_ID', ''),
];
