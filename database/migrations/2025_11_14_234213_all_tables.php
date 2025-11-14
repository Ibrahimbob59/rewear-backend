<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Complete ReWear Database Schema
     * Includes: Authentication, RBAC, Business Logic, and System Tables
     */
    public function up(): void
    {
        // =====================================================
        // 1. CORE AUTHENTICATION TABLES
        // =====================================================

        // Users Table - Main user accounts (buyers, sellers, charities, drivers)
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone', 20)->nullable()->unique();
            $table->enum('user_type', ['user', 'charity'])->default('user');

            // Profile Fields
            $table->string('profile_picture', 500)->nullable();
            $table->text('bio')->nullable();

            // Driver Fields
            $table->boolean('is_driver')->default(false);
            $table->boolean('driver_verified')->default(false);
            $table->timestamp('driver_verified_at')->nullable();

            // Charity Fields
            $table->string('organization_name')->nullable();
            $table->text('organization_description')->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->string('tax_id', 100)->nullable();

            // Location Fields
            $table->string('address', 500)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // Security & Status Fields
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->integer('login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();

            $table->rememberToken();
            $table->timestamps();

            // Indexes
            $table->index('email');
            $table->index('user_type');
            $table->index(['is_driver', 'driver_verified']);
            $table->index('is_active');
        });

        // Email Verifications Table - OTP verification codes
        Schema::create('email_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('email');
            $table->string('code', 6);
            $table->enum('purpose', ['registration', 'login', 'password_reset'])->default('registration');
            $table->integer('attempts')->default(0);
            $table->boolean('verified')->default(false);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('email');
            $table->index('code');
            $table->index('expires_at');
            $table->index(['email', 'verified']);
        });

        // Refresh Tokens Table - JWT refresh token management
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('token', 500)->unique();
            $table->string('device_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('token');
            $table->index('expires_at');
            $table->index(['user_id', 'revoked_at']);
        });

        // Password Reset Tokens Table - Laravel default
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // =====================================================
        // 2. SPATIE PERMISSION TABLES (RBAC)
        // =====================================================

        // Roles Table
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('guard_name');
            $table->timestamps();

            $table->index(['name', 'guard_name']);
        });

        // Permissions Table
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('guard_name');
            $table->timestamps();

            $table->index(['name', 'guard_name']);
        });

        // Model Has Roles - User-Role Relationship
        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_primary');
            $table->index(['model_id', 'model_type']);
        });

        // Model Has Permissions - User-Permission Relationship
        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained('permissions')->onDelete('cascade');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_primary');
            $table->index(['model_id', 'model_type']);
        });

        // Role Has Permissions - Role-Permission Relationship
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained('permissions')->onDelete('cascade');
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');

            $table->primary(['permission_id', 'role_id']);
        });

        // =====================================================
        // 3. LARAVEL SYSTEM TABLES
        // =====================================================

        // Cache Table - Application caching
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value');
            $table->integer('expiration');
        });

        // Cache Locks Table
        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        // Failed Jobs Table - Queue failure tracking
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        // Jobs Table - Queue jobs
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        // Job Batches Table
        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        // Sessions Table - Database session storage
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // =====================================================
        // 4. REWEAR BUSINESS LOGIC TABLES
        // =====================================================

        // Items Table - Clothing listings (sale or donation)
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->enum('category', [
                'tops',
                'bottoms',
                'dresses',
                'outerwear',
                'shoes',
                'accessories',
                'other'
            ]);
            $table->enum('size', ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', 'One Size'])->nullable();
            $table->enum('condition', ['new', 'like_new', 'good', 'fair'])->default('good');
            $table->enum('gender', ['male', 'female', 'unisex'])->default('unisex');
            $table->string('brand', 100)->nullable();
            $table->string('color', 50)->nullable();
            $table->decimal('price', 10, 2)->nullable(); // Null for donations
            $table->boolean('is_donation')->default(false);
            $table->enum('status', ['available', 'pending', 'sold', 'donated', 'cancelled'])->default('available');
            $table->integer('views_count')->default(0);
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('seller_id');
            $table->index('category');
            $table->index('status');
            $table->index('is_donation');
            $table->index(['status', 'is_donation']);
            $table->index('created_at');
        });

        // Item Images Table - Multiple photos per item
        Schema::create('item_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->onDelete('cascade');
            $table->string('image_url', 500);
            $table->integer('display_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            // Indexes
            $table->index('item_id');
            $table->index(['item_id', 'display_order']);
        });

        // Addresses Table - User delivery addresses
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('label', 50)->nullable(); // e.g., "Home", "Work"
            $table->string('full_name')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city', 100);
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index(['user_id', 'is_default']);
        });

        // Orders Table - Purchase transactions
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->foreignId('buyer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('items')->onDelete('cascade');
            $table->foreignId('delivery_address_id')->constrained('addresses')->onDelete('cascade');

            // Pricing
            $table->decimal('item_price', 10, 2)->default(0); // 0 for donations
            $table->decimal('delivery_fee', 10, 2);
            $table->decimal('total_amount', 10, 2);

            // Status & Payment
            $table->enum('status', [
                'pending',
                'confirmed',
                'in_delivery',
                'delivered',
                'completed',
                'cancelled'
            ])->default('pending');
            $table->enum('payment_method', ['cod'])->default('cod'); // Cash on Delivery only
            $table->enum('payment_status', ['pending', 'paid', 'refunded'])->default('pending');

            // Timestamps
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('order_number');
            $table->index('buyer_id');
            $table->index('seller_id');
            $table->index('status');
            $table->index('created_at');
        });

        // Deliveries Table - Delivery tracking
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('driver_id')->nullable()->constrained('users')->onDelete('set null');

            // Locations
            $table->string('pickup_address')->nullable();
            $table->decimal('pickup_latitude', 10, 8)->nullable();
            $table->decimal('pickup_longitude', 11, 8)->nullable();
            $table->string('delivery_address')->nullable();
            $table->decimal('delivery_latitude', 10, 8)->nullable();
            $table->decimal('delivery_longitude', 11, 8)->nullable();

            // Distance & Fees
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->decimal('delivery_fee', 10, 2);
            $table->decimal('driver_earning', 10, 2); // 75% of delivery_fee
            $table->decimal('platform_fee', 10, 2);   // 25% of delivery_fee

            // Status & Tracking
            $table->enum('status', [
                'pending',
                'assigned',
                'picked_up',
                'in_transit',
                'delivered',
                'failed'
            ])->default('pending');

            // Timestamps
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('order_id');
            $table->index('driver_id');
            $table->index('status');
            $table->index(['driver_id', 'status']);
        });

        // Driver Applications Table - Driver verification workflow
        Schema::create('driver_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Application Details
            $table->string('full_name');
            $table->string('phone', 20);
            $table->string('email');
            $table->text('address');
            $table->string('city', 100);

            // Documents (Firebase URLs)
            $table->string('id_document_url', 500)->nullable();
            $table->string('driving_license_url', 500)->nullable();
            $table->string('vehicle_registration_url', 500)->nullable();
            $table->string('vehicle_type', 50)->nullable(); // car, motorcycle, bicycle

            // Status
            $table->enum('status', ['pending', 'under_review', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('status');
            $table->index('reviewed_by');
        });

        // Favorites Table - User saved items
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('items')->onDelete('cascade');
            $table->timestamps();

            // Unique constraint - user can favorite an item only once
            $table->unique(['user_id', 'item_id']);

            // Indexes
            $table->index('user_id');
            $table->index('item_id');
        });

        // Notifications Table - User alerts
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('type', [
                'order_placed',
                'order_confirmed',
                'order_delivered',
                'item_sold',
                'donation_accepted',
                'delivery_assigned',
                'driver_approved',
                'general'
            ]);
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Additional metadata
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index(['user_id', 'is_read']);
            $table->index('created_at');
        });

        // Admin Logs Table - Audit trail
        Schema::create('admin_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade');
            $table->string('action'); // e.g., 'created_charity', 'approved_driver'
            $table->string('model_type')->nullable(); // e.g., 'App\Models\User'
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('changes')->nullable(); // Store old and new values
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('admin_id');
            $table->index(['model_type', 'model_id']);
            $table->index('created_at');
        });

        // Impact Stats Table - Platform statistics
        Schema::create('impact_stats', function (Blueprint $table) {
            $table->id();
            $table->integer('total_items_listed')->default(0);
            $table->integer('total_items_sold')->default(0);
            $table->integer('total_items_donated')->default(0);
            $table->integer('total_users')->default(0);
            $table->integer('total_charities')->default(0);
            $table->integer('total_drivers')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->decimal('total_driver_earnings', 12, 2)->default(0);
            $table->decimal('co2_saved_kg', 12, 2)->default(0); // Estimated CO2 saved
            $table->integer('people_helped')->default(0);
            $table->date('date')->unique(); // Daily stats
            $table->timestamps();

            // Indexes
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop in reverse order to respect foreign key constraints

        // Business Logic Tables
        Schema::dropIfExists('impact_stats');
        Schema::dropIfExists('admin_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('driver_applications');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('item_images');
        Schema::dropIfExists('items');

        // Laravel System Tables
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');

        // Spatie Permission Tables
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');

        // Authentication Tables
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('refresh_tokens');
        Schema::dropIfExists('email_verifications');
        Schema::dropIfExists('users');
    }
};
