<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CompleteTestSeeder extends Seeder
{
    /**
     * Run the database seeds for Week 5-6 testing
     *
     * IMPORTANT: Run RolePermissionSeeder first!
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting Week 5-6 Complete Test Data Seeding...');
        $this->command->info('');

        // Check if roles exist (created by RolePermissionSeeder)
        if (!\Spatie\Permission\Models\Role::where('name', 'admin')->exists()) {
            $this->command->error('❌ Roles not found! Please run RolePermissionSeeder first:');
            $this->command->error('   php artisan db:seed --class=RolePermissionSeeder');
            $this->command->error('');
            $this->command->error('Then run this seeder again.');
            return;
        }

        // 1. Create test users with roles
        $this->command->info('👥 Creating test users and roles...');
        $this->call(TestUsersSeeder::class);
        $this->command->info('');

        // 2. Create addresses for users (required for orders)
        $this->command->info('📍 Creating test addresses...');
        $this->call(TestAddressesSeeder::class);
        $this->command->info('');

        // 3. Create test items (regular + donations)
        $this->command->info('📦 Creating test items...');
        $this->call(TestItemsSeeder::class);
        $this->command->info('');

        $this->command->info('🎉 Week 5-6 test data seeding completed!');
        $this->command->info('');
        $this->command->info('📋 What was created:');
        $this->command->info('✅ 4 test users with proper roles and permissions');
        $this->command->info('✅ 4 delivery addresses for order testing');
        $this->command->info('✅ 8 test items (3 for sale + 5 donations)');
        $this->command->info('');
        $this->command->info('🔑 Test Login Credentials:');
        $this->command->info('Admin:   admin@rewear.com   | password123 | Full platform access');
        $this->command->info('User:    user1@rewear.com   | password123 | Regular buyer/seller');
        $this->command->info('Charity: charity1@rewear.com | password123 | Can accept donations');
        $this->command->info('Driver:  driver1@rewear.com  | password123 | VERIFIED driver (can accept deliveries)');
        $this->command->info('');
        $this->command->info('📦 Test Items Available:');
        $this->command->info('FOR SALE: Winter Jacket ($45), Coach Handbag ($120), Nike Shoes ($65)');
        $this->command->info('DONATIONS: Winter bundle, School clothes, Baby clothes, Professional wear, Casual wear');
        $this->command->info('');
        $this->command->info('🚀 Ready for comprehensive Week 5-6 API testing!');
        $this->command->info('💡 Use the Postman collection or testing guide to start testing.');
        $this->command->info('');
        $this->command->info('🔗 Test Order Flow:');
        $this->command->info('1. Login as user1@rewear.com');
        $this->command->info('2. Create order for any sale item');
        $this->command->info('3. Confirm order as seller');
        $this->command->info('4. Accept delivery as driver1@rewear.com');
        $this->command->info('5. Complete pickup → delivery workflow');
        $this->command->info('');
        $this->command->info('❤️ Test Charity Flow:');
        $this->command->info('1. Login as charity1@rewear.com');
        $this->command->info('2. Browse and accept donation items');
        $this->command->info('3. Complete delivery to charity');
        $this->command->info('4. Mark as distributed with people helped count');
    }
}
