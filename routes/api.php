<?php

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
use App\Http\Controllers\Api\DriverApplicationController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\DriverDashboardController;
use App\Http\Controllers\Api\CharityController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\GoogleMapsController;
use App\Http\Controllers\Admin\AdminDriverController;


/*
|--------------------------------------------------------------------------
| API Routes - ReWear Backend
|--------------------------------------------------------------------------
|
| Complete API routes for authentication and marketplace features
|
*/

// ==================== AUTHENTICATION ROUTES (PUBLIC) ====================

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

// ==================== MARKETPLACE ROUTES (PUBLIC) ====================

// Public item browsing (no authentication required)
Route::get('/items', [ItemController::class, 'index']);
Route::get('/items/{id}', [ItemController::class, 'show'])->whereNumber('id');

// Public platform statistics (no authentication required)
Route::get('/admin/stats', [AdminController::class, 'getStats']);

// ==================== PROTECTED ROUTES (AUTHENTICATION REQUIRED) ====================

Route::middleware('auth:api')->group(function () {

    // ==================== AUTHENTICATION PROTECTED ROUTES ====================

    Route::prefix('auth')->group(function () {
        // Profile Management
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

    // ==================== ITEMS ROUTES ====================

    Route::prefix('items')->group(function () {
        Route::post('/', [ItemController::class, 'store']);
        Route::get('/my-listings', [ItemController::class, 'myListings']);
        Route::put('/{id}', [ItemController::class, 'update']);
        Route::delete('/{id}', [ItemController::class, 'destroy']);
        Route::post('/{id}/toggle-status', [ItemController::class, 'toggleStatus']);
    });

    // ==================== FAVORITES ROUTES ====================

    Route::prefix('favorites')->group(function () {
        Route::get('/', [FavoriteController::class, 'index']);
        Route::post('/{itemId}', [FavoriteController::class, 'store']);
        Route::delete('/{itemId}', [FavoriteController::class, 'destroy']);
    });

    // ==================== ORDERS ROUTES ====================

    Route::prefix('orders')->group(function () {
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/as-seller', [OrderController::class, 'asSeller']);
        Route::get('/{id}', [OrderController::class, 'show']);
        Route::put('/{id}/cancel', [OrderController::class, 'cancel']);
        Route::post('/{id}/confirm', [OrderController::class, 'confirm']);
    });

    // ==================== ADDRESSES ROUTES ====================

    Route::prefix('addresses')->group(function () {
        Route::get('/', [AddressController::class, 'index']);
        Route::post('/', [AddressController::class, 'store']);
        Route::put('/{id}', [AddressController::class, 'update']);
        Route::delete('/{id}', [AddressController::class, 'destroy']);
    });


    // ==================== DRIVER APPLICATION ROUTES ====================

    Route::prefix('driver-applications')->group(function () {
        Route::post('/', [DriverApplicationController::class, 'store']);
        Route::get('/my-application', [DriverApplicationController::class, 'myApplication']);
        Route::get('/eligibility', [DriverApplicationController::class, 'checkEligibility']);
    });

    // ==================== DELIVERY ROUTES ====================

    Route::prefix('deliveries')->group(function () {
        Route::get('/', [DeliveryController::class, 'index']); // Admin only
        Route::get('/{id}', [DeliveryController::class, 'show']);
        Route::post('/{id}/assign-driver', [DeliveryController::class, 'assignDriver']); // Admin only
        Route::post('/{id}/pickup', [DeliveryController::class, 'markAsPickedUp']);
        Route::post('/{id}/deliver', [DeliveryController::class, 'markAsDelivered']);
        Route::post('/{id}/fail', [DeliveryController::class, 'markAsFailed']);
    });

    // ==================== DRIVER DASHBOARD ROUTES ====================

    Route::prefix('driver')->middleware('verified_driver')->group(function () {
        Route::get('/dashboard', [DriverDashboardController::class, 'dashboard']);
        Route::get('/deliveries', [DriverDashboardController::class, 'myDeliveries']);
        Route::get('/available-deliveries', [DriverDashboardController::class, 'availableDeliveries']);
        Route::post('/accept-delivery/{id}', [DriverDashboardController::class, 'acceptDelivery']);
        Route::get('/earnings', [DriverDashboardController::class, 'earnings']);
    });

    // ==================== CHARITY ROUTES ====================

    Route::prefix('charity')->middleware('role:charity')->group(function () {
        Route::get('/dashboard', [CharityController::class, 'dashboard']);
        Route::get('/available-donations', [CharityController::class, 'availableDonations']);
        Route::post('/accept-donation/{itemId}', [CharityController::class, 'acceptDonation']);
        Route::get('/my-donations', [CharityController::class, 'myDonations']);
        Route::post('/mark-distributed/{orderId}', [CharityController::class, 'markDistributed']);
        Route::get('/impact-stats', [CharityController::class, 'impactStats']);
        Route::get('/recommended-donations', [CharityController::class, 'recommendedDonations']);
    });

    // ==================== NOTIFICATIONS ROUTES ====================

    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/{id}/mark-read', [NotificationController::class, 'markAsRead']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
        Route::delete('/clear-all', [NotificationController::class, 'clearAll']);
        Route::post('/test', [NotificationController::class, 'sendTestNotification']); // Dev only
    });

    // ==================== MAPS & DELIVERY CALCULATION ROUTES ====================

    Route::prefix('maps')->group(function () {
        Route::post('/calculate-delivery-fee', [GoogleMapsController::class, 'calculateDeliveryFee']);
        Route::post('/validate-coordinates', [GoogleMapsController::class, 'validateCoordinates']);
        Route::get('/service-areas', [GoogleMapsController::class, 'serviceAreas']);
    });

    // Calculate delivery fee (direct route for frontend convenience)
    Route::post('/calculate-delivery-fee', [GoogleMapsController::class, 'calculateDeliveryFee']);


    // ==================== ADMIN ROUTES ====================

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

        // ==================== ADMIN DRIVER MANAGEMENT ====================

        Route::prefix('driver-applications')->group(function () {
            Route::get('/', [AdminDriverController::class, 'applications']);
            Route::get('/stats', [AdminDriverController::class, 'applicationStats']);
            Route::get('/{id}', [AdminDriverController::class, 'applicationDetails']);
            Route::post('/{id}/approve', [AdminDriverController::class, 'approveApplication']);
            Route::post('/{id}/reject', [AdminDriverController::class, 'rejectApplication']);
            Route::post('/{id}/set-under-review', [AdminDriverController::class, 'setUnderReview']);
        });

        Route::prefix('drivers')->group(function () {
            Route::get('/', [AdminDriverController::class, 'drivers']);
        });

        // ==================== ADMIN DELIVERY MANAGEMENT ====================

        Route::prefix('deliveries')->group(function () {
            Route::get('/stats', function () {
                // This could be a dedicated AdminDeliveryController method
                $stats = [
                    'total_deliveries' => \App\Models\Delivery::count(),
                    'pending_deliveries' => \App\Models\Delivery::where('status', 'pending')->count(),
                    'active_deliveries' => \App\Models\Delivery::whereIn('status', ['assigned', 'in_transit'])->count(),
                    'completed_deliveries' => \App\Models\Delivery::where('status', 'delivered')->count(),
                    'failed_deliveries' => \App\Models\Delivery::where('status', 'failed')->count(),
                    'total_revenue' => \App\Models\Delivery::where('status', 'delivered')->sum('delivery_fee'),
                    'driver_earnings' => \App\Models\Delivery::where('status', 'delivered')->sum('driver_earning'),
                    'platform_revenue' => \App\Models\Delivery::where('status', 'delivered')->sum('platform_fee'),
                ];

                return response()->json([
                    'success' => true,
                    'message' => 'Delivery statistics retrieved successfully',
                    'data' => $stats,
                ]);
            });
        });

        // ==================== ADMIN DONATION STATISTICS ====================

        Route::prefix('donations')->group(function () {
            Route::get('/stats', function () {
                // Use DonationService for platform statistics
                $donationService = app(\App\Services\DonationService::class);
                $stats = $donationService->getPlatformDonationStats();
                $categoryStats = $donationService->getDonationCategoriesStats();

                return response()->json([
                    'success' => true,
                    'message' => 'Donation statistics retrieved successfully',
                    'data' => [
                        'platform_stats' => $stats,
                        'category_breakdown' => $categoryStats,
                    ],
                ]);
            });
        });
    });
});



// ==================== HEALTH CHECK ====================

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'ReWear API is running',
        'version' => '1.0.0',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::get('/test-google-maps', function () {
    try {
        $googleMaps = app(\App\Services\GoogleMapsService::class);

        $result = $googleMaps->calculateDistance(
            33.8959, // Hamra, Beirut
            35.4769,
            33.8886, // Ashrafieh, Beirut
            35.5095
        );

        return response()->json([
            'status' => 'success',
            'api_key_works' => true,
            'result' => $result
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'api_key_works' => false,
            'error' => $e->getMessage()
        ]);
    }
});
