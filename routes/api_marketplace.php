<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AddressController;

/*
|--------------------------------------------------------------------------
| API Routes - Marketplace (Weeks 3-4)
|--------------------------------------------------------------------------
|
| Add these routes to your existing routes/api.php file
|
*/

// ==================== ITEMS ====================
// Public routes
Route::get('/items', [ItemController::class, 'index']); // List items with filters
Route::get('/items/{id}', [ItemController::class, 'show']); // Get single item

// Protected routes (require authentication)
Route::middleware('auth:api')->group(function () {
    Route::post('/items', [ItemController::class, 'store']); // Create item
    Route::put('/items/{item}', [ItemController::class, 'update']); // Update item
    Route::delete('/items/{item}', [ItemController::class, 'destroy']); // Delete item
    Route::get('/items/my-listings', [ItemController::class, 'myListings']); // User's listings
    Route::patch('/items/{item}/status', [ItemController::class, 'toggleStatus']); // Toggle status
});

// ==================== FAVORITES ====================
Route::middleware('auth:api')->group(function () {
    Route::post('/favorites/{itemId}', [FavoriteController::class, 'store']); // Add to favorites
    Route::delete('/favorites/{itemId}', [FavoriteController::class, 'destroy']); // Remove from favorites
    Route::get('/favorites', [FavoriteController::class, 'index']); // Get user's favorites
});

// ==================== ORDERS ====================
Route::middleware('auth:api')->group(function () {
    Route::post('/orders', [OrderController::class, 'store']); // Create order
    Route::get('/orders', [OrderController::class, 'index']); // Get buyer's orders
    Route::get('/orders/as-seller', [OrderController::class, 'asSeller']); // Get seller's orders
    Route::get('/orders/{id}', [OrderController::class, 'show']); // Get order details
    Route::patch('/orders/{order}/cancel', [OrderController::class, 'cancel']); // Cancel order
});

// ==================== ADDRESSES ====================
Route::middleware('auth:api')->group(function () {
    Route::post('/addresses', [AddressController::class, 'store']); // Create address
    Route::get('/addresses', [AddressController::class, 'index']); // Get user's addresses
    Route::get('/addresses/{address}', [AddressController::class, 'show']); // Get single address
    Route::put('/addresses/{address}', [AddressController::class, 'update']); // Update address
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy']); // Delete address
    Route::patch('/addresses/{address}/default', [AddressController::class, 'setDefault']); // Set as default
});

/*
|--------------------------------------------------------------------------
| Middleware Registration
|--------------------------------------------------------------------------
|
| Add this to app/Http/Kernel.php in the $middlewareAliases array:
|
| 'item.ownership' => \App\Http\Middleware\ItemOwnership::class,
|
*/
