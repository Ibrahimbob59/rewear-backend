<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LoginCodeRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class LoginController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Login with email and password
     *
     * @OA\Post(
     *     path="/api/auth/login",
     *     tags={"Authentication"},
     *     summary="Login with email and password",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="SecurePassword123!"),
     *             @OA\Property(property="device_name", type="string", example="iPhone 13")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Login successful"),
     *     @OA\Response(response=401, description="Invalid credentials or account locked"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login($request->validated());

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
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (UnauthorizedHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred during login',
            ], 500);
        }
    }

    /**
     * Request login verification code (optional OTP for login)
     *
     * @OA\Post(
     *     path="/api/auth/login-code",
     *     tags={"Authentication"},
     *     summary="Request verification code for login (optional)",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Verification code sent"),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=429, description="Too many requests")
     * )
     */
    public function requestCode(LoginCodeRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->sendLoginCode($request->input('email'));

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
        } catch (UnauthorizedHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 401);
        } catch (TooManyRequestsHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 429);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification code',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
