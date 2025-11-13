<?php

namespace App\Services;

use App\Models\User;
use App\Models\RefreshToken;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Illuminate\Support\Facades\Request;

class TokenService
{
    /**
     * Generate both access token (JWT) and refresh token
     *
     * @param User $user
     * @param string|null $deviceName
     * @return array
     */
    public function generateTokens(User $user, ?string $deviceName = null): array
    {
        // Generate JWT access token
        $accessToken = JWTAuth::fromUser($user);

        // Generate refresh token
        $refreshToken = $this->generateRefreshToken(
            $user->id,
            $deviceName,
            Request::ip(),
            Request::userAgent()
        );

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken->token,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl') * 60, // Convert minutes to seconds
            'refresh_expires_in' => config('auth.refresh_token_expires_days') * 24 * 60 * 60, // Convert days to seconds
        ];
    }

    /**
     * Generate a new refresh token
     *
     * @param int $userId
     * @param string|null $deviceName
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @return RefreshToken
     */
    protected function generateRefreshToken(
        int $userId,
        ?string $deviceName = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): RefreshToken {
        return RefreshToken::create([
            'user_id' => $userId,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(config('auth.refresh_token_expires_days', 30)),
            'device_name' => $deviceName,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'last_used_at' => now(),
        ]);
    }

    /**
     * Refresh access token using refresh token
     *
     * @param string $refreshTokenString
     * @param bool $rotateRefreshToken
     * @return array
     * @throws UnauthorizedHttpException
     */
    public function refreshAccessToken(string $refreshTokenString, bool $rotateRefreshToken = false): array
    {
        // Find the refresh token
        $refreshToken = RefreshToken::where('token', $refreshTokenString)->first();

        if (!$refreshToken) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid refresh token');
        }

        // Check if token is valid
        if (!$refreshToken->isValid()) {
            throw new UnauthorizedHttpException(
                'Bearer',
                $refreshToken->isRevoked() ? 'Refresh token has been revoked' : 'Refresh token has expired'
            );
        }

        // Get the user
        $user = $refreshToken->user;

        if (!$user) {
            throw new UnauthorizedHttpException('Bearer', 'User not found');
        }

        // Check if user account is active
        if (!$user->is_active) {
            throw new UnauthorizedHttpException('Bearer', 'User account is inactive');
        }

        // Update last used timestamp
        $refreshToken->updateLastUsed();

        // Generate new access token
        $accessToken = JWTAuth::fromUser($user);

        $response = [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ];

        // Optionally rotate refresh token (more secure)
        if ($rotateRefreshToken) {
            // Revoke old refresh token
            $refreshToken->revoke();

            // Generate new refresh token
            $newRefreshToken = $this->generateRefreshToken(
                $user->id,
                $refreshToken->device_name,
                Request::ip(),
                Request::userAgent()
            );

            $response['refresh_token'] = $newRefreshToken->token;
            $response['refresh_expires_in'] = config('auth.refresh_token_expires_days') * 24 * 60 * 60;
        }

        return $response;
    }

    /**
     * Validate JWT access token
     *
     * @param string $token
     * @return array
     * @throws UnauthorizedHttpException
     */
    public function validateToken(string $token): array
    {
        try {
            // Set the token
            JWTAuth::setToken($token);

            // Attempt to authenticate
            $user = JWTAuth::authenticate();

            if (!$user) {
                throw new UnauthorizedHttpException('Bearer', 'Token is invalid or user not found');
            }

            // Get token payload
            $payload = JWTAuth::getPayload();

            return [
                'valid' => true,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'full_name' => $user->full_name,
                    'user_type' => $user->user_type,
                    'is_driver' => $user->is_driver,
                ],
                'token_info' => [
                    'issued_at' => date('Y-m-d H:i:s', $payload->get('iat')),
                    'expires_at' => date('Y-m-d H:i:s', $payload->get('exp')),
                    'expires_in' => $payload->get('exp') - time(),
                ],
            ];
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            throw new UnauthorizedHttpException('Bearer', 'Token has expired');
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            throw new UnauthorizedHttpException('Bearer', 'Token is invalid');
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            throw new UnauthorizedHttpException('Bearer', 'Token error: ' . $e->getMessage());
        }
    }

    /**
     * Revoke a single refresh token (logout from one device)
     *
     * @param string $refreshTokenString
     * @return bool
     * @throws UnauthorizedHttpException
     */
    public function revokeRefreshToken(string $refreshTokenString): bool
    {
        $refreshToken = RefreshToken::where('token', $refreshTokenString)->first();

        if (!$refreshToken) {
            throw new UnauthorizedHttpException('Bearer', 'Refresh token not found');
        }

        $refreshToken->revoke();

        return true;
    }

    /**
     * Revoke all refresh tokens for a user (logout from all devices)
     *
     * @param int $userId
     * @return int Number of tokens revoked
     */
    public function revokeAllUserTokens(int $userId): int
    {
        return RefreshToken::where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /**
     * Get all active sessions (refresh tokens) for a user
     *
     * @param int $userId
     * @return array
     */
    public function getUserSessions(int $userId): array
    {
        $tokens = RefreshToken::where('user_id', $userId)
            ->valid()
            ->orderBy('last_used_at', 'desc')
            ->get();

        return $tokens->map(function ($token) {
            return [
                'id' => $token->id,
                'device_name' => $token->device_name ?? 'Unknown Device',
                'ip_address' => $token->ip_address,
                'last_used_at' => $token->last_used_at?->toISOString(),
                'created_at' => $token->created_at->toISOString(),
                'expires_at' => $token->expires_at->toISOString(),
            ];
        })->toArray();
    }

    /**
     * Clean up expired and revoked tokens (for scheduled job)
     *
     * @return array
     */
    public function cleanupTokens(): array
    {
        // Delete expired tokens older than 7 days
        $expiredDeleted = RefreshToken::where('expires_at', '<', now()->subDays(7))
            ->delete();

        // Delete revoked tokens older than 30 days
        $revokedDeleted = RefreshToken::whereNotNull('revoked_at')
            ->where('revoked_at', '<', now()->subDays(30))
            ->delete();

        return [
            'expired_deleted' => $expiredDeleted,
            'revoked_deleted' => $revokedDeleted,
            'total_deleted' => $expiredDeleted + $revokedDeleted,
        ];
    }

    /**
     * Get token statistics for a user
     *
     * @param int $userId
     * @return array
     */
    public function getUserTokenStats(int $userId): array
    {
        $allTokens = RefreshToken::where('user_id', $userId);

        return [
            'total_tokens' => $allTokens->count(),
            'active_tokens' => $allTokens->clone()->valid()->count(),
            'expired_tokens' => $allTokens->clone()->expired()->count(),
            'revoked_tokens' => $allTokens->clone()->revoked()->count(),
        ];
    }
}
