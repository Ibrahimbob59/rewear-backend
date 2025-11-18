<?php

namespace App\Services;

use App\Models\User;
use App\Models\EmailVerification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AuthService
{
    protected TokenService $tokenService;
    protected EmailVerificationService $emailVerificationService;

    public function __construct(
        TokenService $tokenService,
        EmailVerificationService $emailVerificationService
    ) {
        $this->tokenService = $tokenService;
        $this->emailVerificationService = $emailVerificationService;
    }

    /**
     * Send registration verification code to email
     *
     * @param string $email
     * @return array
     * @throws ValidationException
     * @throws TooManyRequestsHttpException
     */
    public function sendRegistrationCode(string $email): array
    {
        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['An account with this email already exists.']
            ]);
        }

        // Check rate limiting
        $this->emailVerificationService->checkRateLimit($email);

        // Generate and send verification code
        $verification = $this->emailVerificationService->generate($email);
        $this->emailVerificationService->sendVerificationEmail($email, $verification->code);

        return [
            'message' => 'Verification code sent to your email. The code expires in 5 minutes.',
            'expires_at' => $verification->expires_at->toISOString(),
        ];
    }

    /**
     * Register a new user with email verification
     *
     * @param array $data
     * @return array
     * @throws ValidationException
     * @throws BadRequestHttpException
     */
    public function register(array $data): array
    {
        // Verify the email verification code
        $this->emailVerificationService->verifyCode($data['email'], $data['code']);

        // Check if user already exists (double check)
        if (User::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => ['An account with this email already exists.']
            ]);
        }

        DB::beginTransaction();
        try {
            // Create the user
            $user = User::create([
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'name' => $data['name'],
                'phone' => $data['phone'],
                'user_type' => 'user', // Default type
                'email_verified_at' => now(), // Mark as verified since they verified the code
            ]);

            // Mark verification code as used
            EmailVerification::where('email', $data['email'])
                ->where('code', $data['code'])
                ->update(['verified_at' => now()]);

            // Generate tokens
            $tokens = $this->tokenService->generateTokens($user, $data['device_name'] ?? null);

            DB::commit();

            return [
                'message' => 'Registration successful',
                'user' => $this->formatUserResponse($user),
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'token_type' => 'Bearer',
                'expires_in' => $tokens['expires_in'],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Login user with email and password
     *
     * @param array $credentials
     * @return array
     * @throws UnauthorizedHttpException
     * @throws ValidationException
     */
    public function login(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        // Check if user exists
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['No account found with this email address.']
            ]);
        }

        // Check if account is locked
        if ($user->isLocked()) {
            $remainingMinutes = now()->diffInMinutes($user->locked_until);
            throw new UnauthorizedHttpException(
                'Bearer',
                "Account is locked due to too many failed login attempts. Please try again in {$remainingMinutes} minutes."
            );
        }

        // Check if email is verified
        if (!$user->hasVerifiedEmail()) {
            throw new UnauthorizedHttpException(
                'Bearer',
                'Please verify your email address before logging in.'
            );
        }

        // Verify password
        if (!Hash::check($credentials['password'], $user->password)) {
            $user->incrementLoginAttempts();

            $attemptsLeft = 5 - $user->login_attempts;

            if ($attemptsLeft > 0) {
                throw ValidationException::withMessages([
                    'password' => ["Invalid password. {$attemptsLeft} attempts remaining."]
                ]);
            } else {
                throw new UnauthorizedHttpException(
                    'Bearer',
                    'Account locked due to too many failed login attempts. Please try again in 15 minutes.'
                );
            }
        }

        // Successful login - reset attempts and update last login
        $user->resetLoginAttempts();
        $user->updateLastLogin();

        // Generate tokens
        $tokens = $this->tokenService->generateTokens($user, $credentials['device_name'] ?? null);

        return [
            'message' => 'Login successful',
            'user' => $this->formatUserResponse($user),
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => 'Bearer',
            'expires_in' => $tokens['expires_in'],
        ];
    }

    /**
     * Update user profile
     *
     * @param User $user
     * @param array $data
     * @return array
     */
    public function updateProfile(User $user, array $data): array
    {
        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        if (isset($data['phone'])) {
            $updateData['phone'] = $data['phone'];
        }

        if (isset($data['bio'])) {
            $updateData['bio'] = $data['bio'];
        }

        if (isset($data['city'])) {
            $updateData['city'] = $data['city'];
        }

        if (isset($data['latitude'])) {
            $updateData['latitude'] = $data['latitude'];
        }

        if (isset($data['longitude'])) {
            $updateData['longitude'] = $data['longitude'];
        }

        $user->update($updateData);

        return [
            'message' => 'Profile updated successfully',
            'user' => $this->formatUserResponse($user->fresh()),
        ];
    }

    /**
     * Change user password
     *
     * @param User $user
     * @param string $oldPassword
     * @param string $newPassword
     * @return array
     * @throws ValidationException
     */
    public function changePassword(User $user, string $oldPassword, string $newPassword): array
    {
        // Verify old password
        if (!Hash::check($oldPassword, $user->password)) {
            throw ValidationException::withMessages([
                'old_password' => ['The provided password does not match your current password.']
            ]);
        }

        // Update password
        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        // Optionally: Revoke all refresh tokens to force re-login on all devices
        // $this->tokenService->revokeAllUserTokens($user->id);

        return [
            'message' => 'Password changed successfully',
        ];
    }

    /**
     * Resend verification code
     *
     * @param string $email
     * @return array
     * @throws TooManyRequestsHttpException
     */
    public function resendVerificationCode(string $email): array
    {
        // Check rate limiting
        $this->emailVerificationService->checkRateLimit($email);

        // Generate and send new code
        $verification = $this->emailVerificationService->generate($email);
        $this->emailVerificationService->sendVerificationEmail($email, $verification->code);

        return [
            'message' => 'Verification code resent successfully',
            'expires_at' => $verification->expires_at->toISOString(),
        ];
    }

    /**
     * Format user response (remove sensitive data)
     *
     * @param User $user
     * @return array
     */
    protected function formatUserResponse(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'phone' => $user->phone,
            'user_type' => $user->user_type,
            'profile_picture' => $user->profile_picture,
            'bio' => $user->bio,
            'city' => $user->city,
            'is_driver' => $user->is_driver,
            'driver_verified' => $user->driver_verified,
            'email_verified' => $user->hasVerifiedEmail(),
            'created_at' => $user->created_at->toISOString(),
        ];
    }
}
