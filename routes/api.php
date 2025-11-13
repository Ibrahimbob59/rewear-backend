<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TokenController;
use App\Http\Controllers\Auth\ProfileController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'ReWear API is running',
        'timestamp' => now()->toISOString(),
    ]);
})->name('health');

// Public routes (no authentication required)
Route::prefix('auth')->group(function () {

    // Registration Flow
    Route::post('/register-code', [RegisterController::class, 'requestCode'])
        ->name('auth.register.code');

    Route::post('/register', [RegisterController::class, 'register'])
        ->name('auth.register');

    Route::post('/resend-code', [RegisterController::class, 'resendCode'])
        ->name('auth.resend.code');

    // Login Flow
    Route::post('/login', [LoginController::class, 'login'])
        ->name('auth.login');

    Route::post('/login-code', [LoginController::class, 'requestCode'])
        ->name('auth.login.code');

    // Token Management (Public)
    Route::post('/refresh-token', [TokenController::class, 'refresh'])
        ->name('auth.refresh');

    Route::post('/validate', [TokenController::class, 'validateToken'])
        ->name('auth.validate');
});

// Protected routes (authentication required)
Route::middleware('auth:api')->prefix('auth')->group(function () {

    // Profile Management
    Route::get('/me', [ProfileController::class, 'me'])
        ->name('auth.me');

    Route::put('/profile', [ProfileController::class, 'update'])
        ->name('auth.profile.update');

    Route::put('/password', [ProfileController::class, 'changePassword'])
        ->name('auth.password.change');

    // Token Management (Protected)
    Route::post('/logout', [TokenController::class, 'logout'])
        ->name('auth.logout');

    Route::post('/logout-all', [TokenController::class, 'logoutAll'])
        ->name('auth.logout.all');

    Route::get('/sessions', [TokenController::class, 'sessions'])
        ->name('auth.sessions');

    Route::get('/token-stats', [TokenController::class, 'stats'])
        ->name('auth.token.stats');
});
