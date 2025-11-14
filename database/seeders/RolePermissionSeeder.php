<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Permissions
        $permissions = [
            // User Management
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',
            'delete_any_user',

            // Charity Management
            'create_charity',
            'edit_charity',
            'delete_charity',
            'view_charity',

            // Driver Management
            'approve_driver',
            'reject_driver',
            'view_driver_applications',

            // Item Management
            'create_item',
            'edit_own_item',
            'delete_own_item',
            'edit_any_item',
            'delete_any_item',
            'view_items',

            // Order Management
            'create_order',
            'view_own_orders',
            'view_any_order',
            'cancel_own_order',
            'cancel_any_order',

            // Delivery Management
            'accept_delivery',
            'complete_delivery',
            'view_deliveries',
            'assign_driver',

            // Admin Features
            'view_admin_panel',
            'view_analytics',
            'manage_platform',
            'view_admin_logs',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create Roles & Assign Permissions

        // 1. Admin Role (Super User - All Permissions)
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all());

        // 2. User Role (Regular Buyer/Seller)
        $userRole = Role::create(['name' => 'user']);
        $userRole->givePermissionTo([
            'view_users',
            'create_item',
            'edit_own_item',
            'delete_own_item',
            'view_items',
            'create_order',
            'view_own_orders',
            'cancel_own_order',
        ]);

        // 3. Charity Role (Can receive donations)
        $charityRole = Role::create(['name' => 'charity']);
        $charityRole->givePermissionTo([
            'view_charity',
            'view_items',
            'view_own_orders',
            'create_order', // For accepting donations
        ]);

        // 4. Driver Role (Delivery personnel)
        $driverRole = Role::create(['name' => 'driver']);
        $driverRole->givePermissionTo([
            'view_users',
            'accept_delivery',
            'complete_delivery',
            'view_deliveries',
            'create_item', // Drivers can also sell
            'edit_own_item',
            'delete_own_item',
            'view_items',
            'create_order',
            'view_own_orders',
            'cancel_own_order',
        ]);

        // Create default admin user if doesn't exist
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@rewear.com'],
            [
                'name' => 'ReWear Admin',
                'password' => bcrypt('Admin@12345'),
                'phone' => '+96170000000',
                'user_type' => 'user',
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        $adminUser->assignRole('admin');

        $this->command->info('✅ Roles and permissions created successfully!');
        $this->command->info('✅ Admin user created: admin@rewear.com / Admin@12345');
        $this->command->info('');
        $this->command->info('Roles created:');
        $this->command->info('  - admin (all permissions)');
        $this->command->info('  - user (basic user permissions)');
        $this->command->info('  - charity (charity-specific permissions)');
        $this->command->info('  - driver (driver + user permissions)');
    }
}
