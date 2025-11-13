<?php

namespace App\Services;

use App\Models\EmailVerification;
use App\Mail\VerificationCodeMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Illuminate\Validation\ValidationException;

class EmailVerificationService
{
    /**
     * Generate a new verification code for email
     *
     * @param string $email
     * @return EmailVerification
     */
    public function generate(string $email): EmailVerification
    {
        // Delete any existing unverified codes for this email
        EmailVerification::where('email', $email)
            ->whereNull('verified_at')
            ->delete();

        // Generate 6-digit code
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Create verification record
        return EmailVerification::create([
            'email' => $email,
            'code' => $code,
            'expires_at' => now()->addMinutes(config('auth.email_verification.expires_minutes', 5)),
            'attempts' => 0,
        ]);
    }

    /**
     * Send verification code via email
     *
     * @param string $email
     * @param string $code
     * @param string $type ('registration' or 'login')
     * @return bool
     */
    public function sendVerificationEmail(string $email, string $code, string $type = 'registration'): bool
    {
        try {
            Mail::to($email)->send(new VerificationCodeMail($code, $type));
            return true;
        } catch (\Exception $e) {
            // Log the error
            \Log::error('Failed to send verification email', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);

            throw new BadRequestHttpException(
                'Failed to send verification email. Please try again or contact support.'
            );
        }
    }

    /**
     * Verify the code for an email
     *
     * @param string $email
     * @param string $code
     * @return EmailVerification
     * @throws ValidationException
     */
    public function verifyCode(string $email, string $code): EmailVerification
    {
        $verification = EmailVerification::where('email', $email)
            ->where('code', $code)
            ->whereNull('verified_at')
            ->first();

        // Check if code exists
        if (!$verification) {
            throw ValidationException::withMessages([
                'code' => ['Invalid verification code.']
            ]);
        }

        // Check if too many attempts
        if ($verification->maxAttemptsReached()) {
            // Delete the verification to prevent further attempts
            $verification->delete();

            throw ValidationException::withMessages([
                'code' => ['Too many failed attempts. Please request a new code.']
            ]);
        }

        // Check if expired
        if ($verification->isExpired()) {
            throw ValidationException::withMessages([
                'code' => ['Verification code has expired. Please request a new code.']
            ]);
        }

        // Code is valid - mark as verified
        $verification->markAsVerified();

        return $verification;
    }

    /**
     * Check rate limit for sending verification codes
     *
     * @param string $email
     * @throws TooManyRequestsHttpException
     */
    public function checkRateLimit(string $email): void
    {
        $rateLimitWindow = config('auth.email_verification.rate_limit_window_minutes', 15);
        $maxCodes = config('auth.email_verification.max_codes_per_window', 5);

        $cacheKey = "verification_rate_limit:{$email}";

        // Get current count
        $attempts = Cache::get($cacheKey, 0);

        if ($attempts >= $maxCodes) {
            throw new TooManyRequestsHttpException(
                $rateLimitWindow * 60,
                "Too many verification code requests. Please try again in {$rateLimitWindow} minutes."
            );
        }

        // Increment counter
        if ($attempts === 0) {
            // First attempt - set expiry
            Cache::put($cacheKey, 1, now()->addMinutes($rateLimitWindow));
        } else {
            Cache::increment($cacheKey);
        }
    }

    /**
     * Get remaining attempts for verification code
     *
     * @param string $email
     * @param string $code
     * @return int
     */
    public function getRemainingAttempts(string $email, string $code): int
    {
        $verification = EmailVerification::where('email', $email)
            ->where('code', $code)
            ->whereNull('verified_at')
            ->first();

        if (!$verification) {
            return 0;
        }

        $maxAttempts = config('auth.email_verification.max_attempts', 5);
        return max(0, $maxAttempts - $verification->attempts);
    }

    /**
     * Clean up expired verification codes (for scheduled job)
     *
     * @return int Number of codes deleted
     */
    public function cleanupExpiredCodes(): int
    {
        // Delete codes expired more than 1 hour ago
        return EmailVerification::where('expires_at', '<', now()->subHour())
            ->delete();
    }

    /**
     * Clean up verified codes older than 24 hours (for scheduled job)
     *
     * @return int Number of codes deleted
     */
    public function cleanupVerifiedCodes(): int
    {
        return EmailVerification::whereNotNull('verified_at')
            ->where('verified_at', '<', now()->subDay())
            ->delete();
    }

    /**
     * Check if an email has a valid pending verification
     *
     * @param string $email
     * @return bool
     */
    public function hasPendingVerification(string $email): bool
    {
        return EmailVerification::where('email', $email)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Get verification status for an email
     *
     * @param string $email
     * @return array|null
     */
    public function getVerificationStatus(string $email): ?array
    {
        $verification = EmailVerification::where('email', $email)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$verification) {
            return null;
        }

        return [
            'has_pending' => true,
            'expires_at' => $verification->expires_at->toISOString(),
            'expires_in_seconds' => $verification->expires_at->diffInSeconds(now()),
            'attempts_used' => $verification->attempts,
            'attempts_remaining' => max(0, config('auth.email_verification.max_attempts', 5) - $verification->attempts),
        ];
    }

    /**
     * Resend verification code (generates new code)
     *
     * @param string $email
     * @return EmailVerification
     */
    public function resend(string $email): EmailVerification
    {
        $this->checkRateLimit($email);
        return $this->generate($email);
    }
}
