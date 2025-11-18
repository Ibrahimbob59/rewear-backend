<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Get current authenticated user
     *
     * @OA\Get(
     *     path="/api/auth/me",
     *     tags={"User Management"},
     *     summary="Get current user profile",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(response=200, description="Profile retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            return response()->json([
                'success' => true,
                'message' => 'Profile retrieved successfully',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'name' => $user->name,
                        'phone' => $user->phone,
                        'user_type' => $user->user_type,
                        'profile_picture' => $user->profile_picture,
                        'bio' => $user->bio,
                        'city' => $user->city,
                        'latitude' => $user->latitude,
                        'longitude' => $user->longitude,
                        'is_driver' => $user->is_driver,
                        'driver_verified' => $user->driver_verified,
                        'email_verified' => $user->hasVerifiedEmail(),
                        'email_verified_at' => $user->email_verified_at?->toISOString(),
                        'last_login_at' => $user->last_login_at?->toISOString(),
                        'created_at' => $user->created_at->toISOString(),
                        'updated_at' => $user->updated_at->toISOString(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to retrieve profile', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve your profile. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred while fetching your profile data.',
            ], 500);
        }
    }

    /**
     * Update user profile
     *
     * @OA\Put(
     *     path="/api/auth/profile",
     *     tags={"User Management"},
     *     summary="Update user profile",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="phone", type="string", example="+96170123456"),
     *             @OA\Property(property="bio", type="string", example="Fashion enthusiast"),
     *             @OA\Property(property="city", type="string", example="Beirut"),
     *             @OA\Property(property="latitude", type="number", format="float", example=33.8886),
     *             @OA\Property(property="longitude", type="number", format="float", example=35.4955)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Profile updated successfully"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            $result = $this->authService->updateProfile($user, $request->validated());

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'user' => $result['user'],
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Failed to update profile', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update your profile. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred while updating your profile.',
            ], 500);
        }
    }

    /**
     * Change password
     *
     * @OA\Put(
     *     path="/api/auth/password",
     *     tags={"User Management"},
     *     summary="Change user password",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"old_password", "new_password"},
     *             @OA\Property(property="old_password", type="string", format="password", example="OldPassword123!"),
     *             @OA\Property(property="new_password", type="string", format="password", example="NewPassword456!"),
     *             @OA\Property(property="new_password_confirmation", type="string", format="password", example="NewPassword456!")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Password changed successfully"),
     *     @OA\Response(response=422, description="Validation error or incorrect old password"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            $result = $this->authService->changePassword(
                $user,
                $request->input('old_password'),
                $request->input('new_password')
            );

            return response()->json([
                'success' => true,
                'message' => $result['message'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Failed to change password', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to change your password. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred while changing your password.',
            ], 500);
        }
    }
}
