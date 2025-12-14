<?php

namespace App\Services;

use App\Models\User;
use App\Models\RefreshToken;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Lcobucci\Clock\SystemClock;


class TokenService
{
    protected Configuration $jwt;

    public function __construct()
    {
        $this->jwt = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText(config('jwt.secret'))
        );
    }

    /**
     * Generate both access token (JWT) and refresh token
     */
    public function generateTokens(User $user, ?string $deviceName = null): array
    {
        $accessToken = $this->generateJwtForUser($user);

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
            'expires_in' => config('jwt.ttl') * 60,
            'refresh_expires_in' => config('auth.refresh_token_expires_days') * 86400,
        ];
    }

    protected function generateJwtForUser(User $user): string
    {
        $now = new \DateTimeImmutable();
        $ttl = (int) config('jwt.ttl');

        return $this->jwt->builder()
            ->issuedBy(config('app.url'))
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify("+{$ttl} minutes"))
            ->relatedTo((string) $user->id)
            ->withClaim('email', $user->email)
            ->withClaim('user_type', $user->user_type)
            ->getToken($this->jwt->signer(), $this->jwt->signingKey())
            ->toString();
    }

    protected function generateRefreshToken(
        int $userId,
        ?string $deviceName = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): RefreshToken {
        return RefreshToken::create([
            'user_id' => $userId,
            'token' => Str::random(64),
            'expires_at' => now()->addDays((int) config('auth.refresh_token_expires_days', 30)),
            'device_name' => $deviceName,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'last_used_at' => now(),
        ]);
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshAccessToken(string $refreshTokenString, bool $rotateRefreshToken = false): array
    {
        $refreshToken = RefreshToken::where('token', $refreshTokenString)->first();

        if (!$refreshToken || !$refreshToken->isValid()) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid refresh token');
        }

        $user = $refreshToken->user;

        if (!$user || !$user->is_active) {
            throw new UnauthorizedHttpException('Bearer', 'User invalid or inactive');
        }

        $refreshToken->updateLastUsed();

        $accessToken = $this->generateJwtForUser($user);

        $response = [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ];

        if ($rotateRefreshToken) {
            $refreshToken->revoke();

            $newRefreshToken = $this->generateRefreshToken(
                $user->id,
                $refreshToken->device_name,
                Request::ip(),
                Request::userAgent()
            );

            $response['refresh_token'] = $newRefreshToken->token;
            $response['refresh_expires_in'] = config('auth.refresh_token_expires_days') * 86400;
        }

        return $response;
    }

    /**
     * Validate JWT access token
     */
    /**
     * Validate JWT access token and return user data
     */
    public function validateToken(string $token): array
    {
        try {
            $parsedToken = $this->jwt->parser()->parse($token);

            $constraints = [
                new SignedWith($this->jwt->signer(), $this->jwt->signingKey()),
                new StrictValidAt(new SystemClock(new \DateTimeZone(\date_default_timezone_get())))
            ];

            if (!$this->jwt->validator()->validate($parsedToken, ...$constraints)) {
                throw new UnauthorizedHttpException('Bearer', 'Token signature or expiration invalid');
            }

            $userId = $parsedToken->claims()->get('sub');
            $email = $parsedToken->claims()->get('email');
            $userType = $parsedToken->claims()->get('user_type');

            return [
                'valid' => true,
                'user' => [
                    'id' => $userId,
                    'email' => $email,
                    'user_type' => $userType,
                ],
                'token_info' => [
                    'issued_at' => $parsedToken->claims()->get('iat')->format('Y-m-d H:i:s'),
                    'expires_at' => $parsedToken->claims()->get('exp')->format('Y-m-d H:i:s'),
                ],
            ];
        } catch (\Exception $e) {
            throw new UnauthorizedHttpException('Bearer', 'Token is invalid: ' . $e->getMessage());
        }
    }

    public function revokeRefreshToken(string $refreshTokenString): bool
    {
        $refreshToken = RefreshToken::where('token', $refreshTokenString)->first();

        if (!$refreshToken) {
            throw new UnauthorizedHttpException('Bearer', 'Refresh token not found');
        }

        $refreshToken->revoke();
        return true;
    }

    public function revokeAllUserTokens(int $userId): int
    {
        return RefreshToken::where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function getUserSessions(int $userId): array
    {
        return RefreshToken::where('user_id', $userId)
            ->valid()
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'device_name' => $token->device_name ?? 'Unknown Device',
                'ip_address' => $token->ip_address,
                'last_used_at' => $token->last_used_at?->toISOString(),
                'created_at' => $token->created_at->toISOString(),
                'expires_at' => $token->expires_at->toISOString(),
            ])
            ->toArray();
    }

    public function cleanupTokens(): array
    {
        $expired = RefreshToken::where('expires_at', '<', now()->subDays(7))->delete();
        $revoked = RefreshToken::whereNotNull('revoked_at')
            ->where('revoked_at', '<', now()->subDays(30))
            ->delete();

        return [
            'expired_deleted' => $expired,
            'revoked_deleted' => $revoked,
            'total_deleted' => $expired + $revoked,
        ];
    }

    public function getUserTokenStats(int $userId): array
    {
        $query = RefreshToken::where('user_id', $userId);

        return [
            'total_tokens' => $query->count(),
            'active_tokens' => (clone $query)->valid()->count(),
            'expired_tokens' => (clone $query)->expired()->count(),
            'revoked_tokens' => (clone $query)->revoked()->count(),
        ];
    }
}
