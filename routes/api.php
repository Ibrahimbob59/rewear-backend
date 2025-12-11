<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\TokenController;
use App\Http\Controllers\Api\Auth\ProfileController;
use App\Http\Controllers\Api\UserManagementController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AddressController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public Routes (No Authentication Required)
Route::prefix('auth')->group(function () {
    // Registration
    Route::post('/register-code', [RegisterController::class, 'requestCode']);
    Route::post('/register', [RegisterController::class, 'register']);
    Route::post('/resend-code', [RegisterController::class, 'resendCode']);

    // Login
    Route::post('/login', [LoginController::class, 'login']);

    // Token Management (Public)
    Route::post('/refresh-token', [TokenController::class, 'refreshToken']);
    Route::post('/validate', [TokenController::class, 'validateToken']);
});

// Protected Routes (Authentication Required)
Route::middleware('auth:api')->group(function () {

    // Profile Management
    Route::prefix('auth')->group(function () {
        Route::get('/me', [ProfileController::class, 'me']);
        Route::put('/profile', [ProfileController::class, 'updateProfile']);
        Route::put('/password', [ProfileController::class, 'changePassword']);

        // Logout
        Route::post('/logout', [TokenController::class, 'logout']);
        Route::post('/logout-all', [TokenController::class, 'logoutAll']);

        // Sessions & Token Stats
        Route::get('/sessions', [TokenController::class, 'getSessions']);
        Route::get('/token-stats', [TokenController::class, 'getTokenStats']);
    });

    // User Management (Self)
    Route::prefix('user')->group(function () {
        Route::delete('/delete-account', [UserManagementController::class, 'deleteSelfAccount']);
    });

    // Admin Routes (Requires Admin Role)
    Route::middleware('admin')->prefix('admin')->group(function () {

        // User Management (Admin)
        Route::prefix('users')->group(function () {
            Route::get('/', [UserManagementController::class, 'getAllUsers']);
            Route::delete('/{userId}', [UserManagementController::class, 'deleteUserByAdmin']);
        });

        // Charity Management
        Route::prefix('charity')->group(function () {
            Route::post('/create', [AdminController::class, 'createCharity']);
        });

        Route::get('/charities', [AdminController::class, 'getCharities']);

        // Analytics & Statistics
        Route::get('/stats', [AdminController::class, 'getStats']);
    });
});

// Public routes (no auth)
Route::get('/items', [ItemController::class, 'index']);
Route::get('/items/{id}', [ItemController::class, 'show']);

// Protected routes (auth required)
Route::middleware(['auth:api'])->group(function () {
    
    // Items
    Route::prefix('items')->group(function () {
        Route::post('/', [ItemController::class, 'store']);
        Route::get('/my-listings', [ItemController::class, 'myListings']);
        Route::put('/{id}', [ItemController::class, 'update']);
        Route::delete('/{id}', [ItemController::class, 'destroy']);
        Route::post('/{id}/toggle-status', [ItemController::class, 'toggleStatus']);
    });

    // Favorites
    Route::prefix('favorites')->group(function () {
        Route::get('/', [FavoriteController::class, 'index']);
        Route::post('/{itemId}', [FavoriteController::class, 'store']);
        Route::delete('/{itemId}', [FavoriteController::class, 'destroy']);
    });

    // Orders
    Route::prefix('orders')->group(function () {
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/as-seller', [OrderController::class, 'asSeller']);
        Route::get('/{id}', [OrderController::class, 'show']);
        Route::put('/{id}/cancel', [OrderController::class, 'cancel']);
    });

    // Addresses
    Route::prefix('addresses')->group(function () {
        Route::get('/', [AddressController::class, 'index']);
        Route::post('/', [AddressController::class, 'store']);
        Route::put('/{id}', [AddressController::class, 'update']);
        Route::delete('/{id}', [AddressController::class, 'destroy']);
    });
});

// Health Check Route (Useful for monitoring)
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'ReWear API is running',
        'timestamp' => now()->toIso8601String(),
    ]);
});
