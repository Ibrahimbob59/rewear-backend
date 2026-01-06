<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class TestUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Creating Week 5-6 test users...');

        // Ensure roles exist (should be created by RolePermissionSeeder)
        $this->ensureRolesExist();

        // Create test users
        $this->createTestUsers();
    }

    /**
     * Ensure all required roles exist
     */
    private function ensureRolesExist(): void
    {
        $requiredRoles = ['admin', 'user', 'charity', 'driver'];

        foreach ($requiredRoles as $roleName) {
            if (!Role::where('name', $roleName)->exists()) {
                $this->command->warn("⚠️  Role '{$roleName}' doesn't exist. Run RolePermissionSeeder first.");
                return;
            }
        }

        $this->command->info('✅ All required roles exist');
    }

    /**
     * Create test users for Week 5-6 testing
     */
    private function createTestUsers(): void
    {
        $testUsers = [
            [
                'name' => 'Admin User',
                'email' => 'admin@rewear.com',
                'password' => 'password123',
                'phone' => '+96170111111',
                'user_type' => 'user', // Admin is user type with admin role
                'role' => 'admin',
                'address' => 'Admin Office, Hamra Street, Beirut',
                'city' => 'Beirut',
                'country' => 'Lebanon',
                'latitude' => 33.8959,
                'longitude' => 35.4769,
            ],
            [
                'name' => 'Test User One',
                'email' => 'user1@rewear.com',
                'password' => 'password123',
                'phone' => '+96170222222',
                'user_type' => 'user',
                'role' => 'user',
                'bio' => 'Love sustainable fashion and helping the environment!',
                'address' => 'Hamra Street 123, Beirut',
                'city' => 'Beirut',
                'country' => 'Lebanon',
                'latitude' => 33.8959,
                'longitude' => 35.4769,
            ],
            [
                'name' => 'Beirut Charity Organization',
                'email' => 'charity1@rewear.com',
                'password' => 'password123',
                'phone' => '+96170333333',
                'user_type' => 'charity',
                'role' => 'charity',
                'organization_name' => 'Beirut Charity Organization',
                'organization_description' => 'Non-profit organization dedicated to helping families in need across Lebanon. We provide clothing, food, and essential supplies to underprivileged communities.',
                'registration_number' => 'BCO2024001',
                'tax_id' => 'TAX-BCO-001',
                'address' => 'Ashrafieh District, Charity Center Building, Beirut',
                'city' => 'Beirut',
                'country' => 'Lebanon',
                'latitude' => 33.8886,
                'longitude' => 35.5095,
            ],
            [
                'name' => 'Mohammad Driver',
                'email' => 'driver1@rewear.com',
                'password' => 'password123',
                'phone' => '+96170444444',
                'user_type' => 'user',
                'role' => 'driver',
                'bio' => 'Experienced delivery driver committed to helping sustainable fashion reach everyone.',
                'address' => 'Mar Mikhael, Beirut',
                'city' => 'Beirut',
                'country' => 'Lebanon',
                'latitude' => 33.8854,
                'longitude' => 35.5108,
                // Driver specific fields
                'is_driver' => true,
                'driver_verified' => true,
                'driver_verified_at' => now(),
            ],
        ];

        foreach ($testUsers as $userData) {
            // Check if user already exists
            $existingUser = User::where('email', $userData['email'])->first();

            if ($existingUser) {
                $this->command->warn("⚠️  User {$userData['email']} already exists, skipping...");
                continue;
            }

            // Create user
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['password']),
                'phone' => $userData['phone'],
                'user_type' => $userData['user_type'],
                'profile_picture' => null,
                'bio' => $userData['bio'] ?? null,
                'is_driver' => $userData['is_driver'] ?? false,
                'driver_verified' => $userData['driver_verified'] ?? false,
                'driver_verified_at' => $userData['driver_verified_at'] ?? null,
                'organization_name' => $userData['organization_name'] ?? null,
                'organization_description' => $userData['organization_description'] ?? null,
                'registration_number' => $userData['registration_number'] ?? null,
                'tax_id' => $userData['tax_id'] ?? null,
                'address' => $userData['address'] ?? null,
                'city' => $userData['city'] ?? null,
                'country' => $userData['country'] ?? null,
                'latitude' => $userData['latitude'] ?? null,
                'longitude' => $userData['longitude'] ?? null,
                'email_verified_at' => now(),
                'is_active' => true,
                'last_login_at' => null,
                'login_attempts' => 0,
                'locked_until' => null,
            ]);

            // Assign role
            $user->assignRole($userData['role']);

            $this->command->info("✅ Created {$userData['role']}: {$userData['email']}");
        }

        $this->command->info('');
        $this->command->info('🎉 All test users created successfully!');
        $this->command->info('');
        $this->command->info('📋 Test Users Created:');
        $this->command->info('Admin:   admin@rewear.com   | password123 | Full platform access');
        $this->command->info('User:    user1@rewear.com   | password123 | Regular buyer/seller');
        $this->command->info('Charity: charity1@rewear.com | password123 | Can accept donations');
        $this->command->info('Driver:  driver1@rewear.com  | password123 | VERIFIED driver (can accept deliveries)');
        $this->command->info('');
        $this->command->info('🚀 Ready for Week 5-6 API testing!');
    }
}
