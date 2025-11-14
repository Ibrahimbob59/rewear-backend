<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Services\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class TokenController extends Controller
{
    protected TokenService $tokenService;

    public function __construct(TokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    /**
     * Refresh access token using refresh token
     *
     * @OA\Post(
     *     path="/api/auth/refresh-token",
     *     tags={"Authentication"},
     *     summary="Refresh access token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"refresh_token"},
     *             @OA\Property(property="refresh_token", type="string", example="abc123..."),
     *             @OA\Property(property="rotate", type="boolean", example=false, description="Whether to rotate refresh token")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Token refreshed successfully"),
     *     @OA\Response(response=401, description="Invalid or expired refresh token")
     * )
     */
    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        try {
            $rotate = $request->boolean('rotate', false);

            $tokens = $this->tokenService->refreshAccessToken(
                $request->input('refresh_token'),
                $rotate
            );

            return response()->json([
                'success' => true,
                'message' => 'Access token refreshed successfully',
                'data' => $tokens,
            ]);
        } catch (UnauthorizedHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh token',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Validate JWT access token
     *
     * @OA\Post(
     *     path="/api/auth/validate",
     *     tags={"Authentication"},
     *     summary="Validate JWT token",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(response=200, description="Token is valid"),
     *     @OA\Response(response=401, description="Token is invalid or expired")
     * )
     */
    public function validateToken(Request $request): JsonResponse
    {
        try {
            // Get token from header
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'No token provided',
                ], 401);
            }

            $result = $this->tokenService->validateToken($token);

            return response()->json([
                'success' => true,
                'message' => 'Token is valid',
                'data' => $result,
            ]);
        } catch (UnauthorizedHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token validation failed',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Logout from current device (revoke refresh token)
     *
     * @OA\Post(
     *     path="/api/auth/logout",
     *     tags={"Authentication"},
     *     summary="Logout from current device",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"refresh_token"},
     *             @OA\Property(property="refresh_token", type="string", example="abc123...")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Logged out successfully"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function logout(RefreshTokenRequest $request): JsonResponse
    {
        try {
            $this->tokenService->revokeRefreshToken($request->input('refresh_token'));

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully',
            ]);
        } catch (UnauthorizedHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Logout from all devices (revoke all refresh tokens)
     *
     * @OA\Post(
     *     path="/api/auth/logout-all",
     *     tags={"Authentication"},
     *     summary="Logout from all devices",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(response=200, description="Logged out from all devices"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $count = $this->tokenService->revokeAllUserTokens($user->id);

            return response()->json([
                'success' => true,
                'message' => "Logged out from {$count} device(s) successfully",
                'data' => [
                    'revoked_tokens' => $count,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to logout from all devices',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get all active sessions (devices)
     *
     * @OA\Get(
     *     path="/api/auth/sessions",
     *     tags={"Authentication"},
     *     summary="Get all active sessions",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(response=200, description="Sessions retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function sessions(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $sessions = $this->tokenService->getUserSessions($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Sessions retrieved successfully',
                'data' => [
                    'sessions' => $sessions,
                    'total' => count($sessions),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sessions',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get token statistics for current user
     *
     * @OA\Get(
     *     path="/api/auth/token-stats",
     *     tags={"Authentication"},
     *     summary="Get token statistics",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(response=200, description="Statistics retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $stats = $this->tokenService->getUserTokenStats($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Token statistics retrieved successfully',
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
