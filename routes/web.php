<?php

use Illuminate\Support\Facades\Route;
use App\Services\FirebaseStorageService;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Here is where you can register web routes for your application.
| These routes are loaded by the RouteServiceProvider and all of them
| will be assigned to the "web" middleware group.
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-firebase', function () {
    try {
        $service = app(\App\Services\FirebaseStorageService::class);
        return response()->json([
            'success' => true,
            'message' => 'Firebase Storage connected successfully!',
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
});
