<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterCodeRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResendCodeRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class RegisterController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Step 1: Request verification code for registration
     *
     * @OA\Post(
     *     path="/api/auth/register-code",
     *     tags={"Authentication"},
     *     summary="Request email verification code for registration",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Verification code sent"),
     *     @OA\Response(response=422, description="Email already exists"),
     *     @OA\Response(response=429, description="Too many requests")
     * )
     */
    public function requestCode(RegisterCodeRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->sendRegistrationCode($request->input('email'));

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'expires_at' => $result['expires_at'],
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (TooManyRequestsHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 429);
        } catch (\Exception $e) {
            \Log::error('Failed to send verification code', [
                'email' => $request->input('email'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification code. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred while sending the verification code.',
            ], 500);
        }
    }

    /**
     * Step 2: Register new user with verification code
     *
     * @OA\Post(
     *     path="/api/auth/register",
     *     tags={"Authentication"},
     *     summary="Register a new user",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password", "name", "phone", "code"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="SecurePassword123!"),
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="phone", type="string", example="+96170123456"),
     *             @OA\Property(property="code", type="string", example="123456"),
     *             @OA\Property(property="device_name", type="string", example="iPhone 13")
     *         )
     *     ),
     *     @OA\Response(response=201, description="User registered successfully"),
     *     @OA\Response(response=400, description="Invalid verification code"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->register($request->validated());

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'user' => $result['user'],
                    'access_token' => $result['access_token'],
                    'refresh_token' => $result['refresh_token'],
                    'token_type' => $result['token_type'],
                    'expires_in' => $result['expires_in'],
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Registration failed', [
                'email' => $request->validated()['email'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Registration failed. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred during registration. Please verify your information and try again.',
            ], 500);
        }
    }

    /**
     * Resend verification code
     *
     * @OA\Post(
     *     path="/api/auth/resend-code",
     *     tags={"Authentication"},
     *     summary="Resend verification code",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Code resent successfully"),
     *     @OA\Response(response=429, description="Too many requests")
     * )
     */
    public function resendCode(ResendCodeRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->resendVerificationCode($request->input('email'));

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'expires_at' => $result['expires_at'],
                ],
            ]);
        } catch (TooManyRequestsHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 429);
        } catch (\Exception $e) {
            \Log::error('Failed to resend verification code', [
                'email' => $request->input('email'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resend verification code. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred while resending the verification code.',
            ], 500);
        }
    }
}
