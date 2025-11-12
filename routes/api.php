<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\TokenController;
use App\Http\Controllers\Auth\ProfileController;

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

// Public routes (no authentication required)
Route::prefix('auth')->group(function () {

    // Registration Flow
    Route::post('/register-code', [RegisterController::class, 'requestCode'])
        ->name('auth.register.code');

    Route::post('/register', [RegisterController::class, 'register'])
        ->name('auth.register');

    // Login Flow
    Route::post('/login-code', [LoginController::class, 'requestCode'])
        ->name('auth.login.code');

    Route::post('/login', [LoginController::class, 'login'])
        ->name('auth.login');

    // Email Verification
    Route::post('/resend-code', [EmailVerificationController::class, 'resendCode'])
        ->name('auth.resend.code');

    // Token Management
    Route::post('/refresh-token', [TokenController::class, 'refresh'])
        ->name('auth.refresh');

    Route::post('/validate', [TokenController::class, 'validate'])
        ->name('auth.validate');
});

// Protected routes (authentication required)
Route::middleware('auth:api')->prefix('auth')->group(function () {

    // Profile
    Route::get('/me', [ProfileController::class, 'me'])
        ->name('auth.me');

    Route::put('/profile', [ProfileController::class, 'update'])
        ->name('auth.profile.update');

    Route::put('/password', [ProfileController::class, 'changePassword'])
        ->name('auth.password.change');

    // Logout
    Route::post('/logout', [TokenController::class, 'logout'])
        ->name('auth.logout');

    Route::post('/logout-all', [TokenController::class, 'logoutAll'])
        ->name('auth.logout.all');
});

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'ReWear API is running',
        'timestamp' => now()->toISOString(),
    ]);
})->name('health');
    