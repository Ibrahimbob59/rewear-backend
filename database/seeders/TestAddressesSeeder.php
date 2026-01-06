<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Address;

class TestAddressesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📍 Creating test addresses for Week 5-6 testing...');

        $this->createTestAddresses();
    }

    /**
     * Create test addresses for test users
     */
    private function createTestAddresses(): void
    {
        // Get test users
        $users = [
            'user1@rewear.com' => User::where('email', 'user1@rewear.com')->first(),
            'charity1@rewear.com' => User::where('email', 'charity1@rewear.com')->first(),
            'driver1@rewear.com' => User::where('email', 'driver1@rewear.com')->first(),
        ];

        foreach ($users as $email => $user) {
            if (!$user) {
                $this->command->error("❌ User {$email} not found. Run Week56TestUsersSeeder first.");
                return;
            }
        }

        $addresses = [
            // User1 addresses (2 addresses for testing)
            [
                'user_id' => $users['user1@rewear.com']->id,
                'label' => 'Home',
                'full_name' => 'Test User One',
                'phone' => '+96170222222',
                'address_line1' => 'Hamra Street 123',
                'address_line2' => 'Building A, Floor 2',
                'city' => 'Beirut',
                'state' => 'Mount Lebanon',
                'postal_code' => '1103',
                'country' => 'Lebanon',
                'latitude' => 33.8959,
                'longitude' => 35.4769,
                'is_default' => true,
            ],
            [
                'user_id' => $users['user1@rewear.com']->id,
                'label' => 'Work',
                'full_name' => 'Test User One',
                'phone' => '+96170222222',
                'address_line1' => 'Verdun Street 456',
                'address_line2' => 'Office Building, 3rd Floor',
                'city' => 'Beirut',
                'state' => 'Mount Lebanon',
                'postal_code' => '1103',
                'country' => 'Lebanon',
                'latitude' => 33.8704,
                'longitude' => 35.4822,
                'is_default' => false,
            ],

            // Charity address
            [
                'user_id' => $users['charity1@rewear.com']->id,
                'label' => 'Organization Headquarters',
                'full_name' => 'Beirut Charity Organization',
                'phone' => '+96170333333',
                'address_line1' => 'Ashrafieh District, Charity Center Building',
                'address_line2' => 'Main Reception Hall',
                'city' => 'Beirut',
                'state' => 'Mount Lebanon',
                'postal_code' => '1104',
                'country' => 'Lebanon',
                'latitude' => 33.8886,
                'longitude' => 35.5095,
                'is_default' => true,
            ],

            // Driver address
            [
                'user_id' => $users['driver1@rewear.com']->id,
                'label' => 'Home',
                'full_name' => 'Mohammad Driver',
                'phone' => '+96170444444',
                'address_line1' => 'Mar Mikhael District',
                'address_line2' => 'Apartment 5B, Building C',
                'city' => 'Beirut',
                'state' => 'Mount Lebanon',
                'postal_code' => '1104',
                'country' => 'Lebanon',
                'latitude' => 33.8854,
                'longitude' => 35.5108,
                'is_default' => true,
            ],
        ];

        foreach ($addresses as $addressData) {
            // Check if address already exists
            $existingAddress = Address::where('user_id', $addressData['user_id'])
                ->where('address_line1', $addressData['address_line1'])
                ->first();

            if ($existingAddress) {
                continue;
            }

            Address::create($addressData);
        }

        $this->command->info('✅ Test addresses created successfully!');
        $this->command->info('📍 Addresses created for:');
        $this->command->info('   - user1@rewear.com (2 addresses: Home, Work)');
        $this->command->info('   - charity1@rewear.com (1 address: Organization HQ)');
        $this->command->info('   - driver1@rewear.com (1 address: Home)');
        $this->command->info('🚀 Ready for order and delivery testing!');
    }
}
