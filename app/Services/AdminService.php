<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\CharityCredentialsMail;
use Illuminate\Validation\ValidationException;

class AdminService
{
    /**
     * Create a new charity account
     *
     * @param array $data
     * @param int $adminId
     * @return array
     * @throws ValidationException
     */
    public function createCharity(array $data, int $adminId): array
    {
        // Check if email already exists
        if (User::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => ['This email is already registered.']
            ]);
        }

        // Check if phone already exists
        if (User::where('phone', $data['phone'])->exists()) {
            throw ValidationException::withMessages([
                'phone' => ['This phone number is already registered.']
            ]);
        }

        // Generate password if not provided
        $plainPassword = $data['password'] ?? $this->generateSecurePassword();

        // Create charity account
        $charity = User::create([
            'email' => $data['email'],
            'password' => Hash::make($plainPassword),
            'full_name' => $data['organization_name'],
            'phone' => $data['phone'],
            'user_type' => 'charity',
            'city' => $data['city'] ?? null,
            'location_lat' => $data['location_lat'] ?? null,
            'location_lng' => $data['location_lng'] ?? null,
            'bio' => $data['bio'] ?? null,
            'email_verified_at' => now(), // Auto-verify charity emails
            'email_verified' => true,
        ]);

        // Send credentials email to charity
        try {
            Mail::to($charity->email)->send(
                new CharityCredentialsMail($charity, $plainPassword)
            );
        } catch (\Exception $e) {
            // Log email failure but don't fail the request
            \Log::warning('Failed to send charity credentials email', [
                'charity_id' => $charity->id,
                'error' => $e->getMessage()
            ]);
        }

        return [
            'message' => 'Charity account created successfully. Credentials have been sent via email.',
            'charity' => $charity,
            'password' => $plainPassword, // Return password for admin to share
        ];
    }

    /**
     * Generate a secure random password
     *
     * @return string
     */
    private function generateSecurePassword(): string
    {
        // Generate a secure 12-character password
        // Format: CapitalLetter + lowercase + numbers + special char
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '!@#$%';

        $password =
            $uppercase[random_int(0, strlen($uppercase) - 1)] .
            $lowercase[random_int(0, strlen($lowercase) - 1)] .
            $numbers[random_int(0, strlen($numbers) - 1)] .
            $special[random_int(0, strlen($special) - 1)];

        // Add 8 more random characters
        $allChars = $uppercase . $lowercase . $numbers . $special;
        for ($i = 0; $i < 8; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // Shuffle the password
        return str_shuffle($password);
    }

    /**
     * Get admin dashboard statistics
     *
     * @return array
     */
    public function getDashboardStats(): array
    {
        return [
            'total_users' => User::where('user_type', 'user')->count(),
            'total_charities' => User::where('user_type', 'charity')->count(),
            'total_drivers' => User::where('is_driver', true)
                ->where('driver_verified', true)
                ->count(),
            'pending_drivers' => User::where('is_driver', true)
                ->where('driver_verified', false)
                ->count(),
            'verified_emails' => User::whereNotNull('email_verified_at')->count(),
            'total_accounts' => User::count(),
        ];
    }
}
