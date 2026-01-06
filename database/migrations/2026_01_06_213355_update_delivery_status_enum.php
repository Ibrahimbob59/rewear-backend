<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update delivery status enum to include 'cancelled' and remove 'failed'
        Schema::table('deliveries', function (Blueprint $table) {
            // Drop the old enum constraint and recreate with new values
            $table->dropColumn('status');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'assigned',
                'in_transit',
                'delivered',
                'cancelled'  // New status, replaces 'failed'
            ])->default('pending')->after('platform_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore original enum with 'failed' status
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'assigned',
                'picked_up',
                'in_transit',
                'delivered',
                'failed'
            ])->default('pending')->after('platform_fee');
        });
    }
};
