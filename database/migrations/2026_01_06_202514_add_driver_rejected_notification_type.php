<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'driver_rejected' to the notifications type enum
        DB::statement("ALTER TABLE notifications DROP CONSTRAINT IF EXISTS notifications_type_check");
        DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_type_check CHECK (type IN ('order_placed', 'order_confirmed', 'order_delivered', 'item_sold', 'donation_accepted', 'delivery_assigned', 'driver_approved', 'driver_rejected', 'general'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'driver_rejected' from the notifications type enum
        DB::statement("ALTER TABLE notifications DROP CONSTRAINT IF EXISTS notifications_type_check");
        DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_type_check CHECK (type IN ('order_placed', 'order_confirmed', 'order_delivered', 'item_sold', 'donation_accepted', 'delivery_assigned', 'driver_approved', 'general'))");
    }
};
