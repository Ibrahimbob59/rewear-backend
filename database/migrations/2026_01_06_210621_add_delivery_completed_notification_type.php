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
        // Add delivery_completed notification type
        DB::statement("ALTER TABLE notifications DROP CONSTRAINT IF EXISTS notifications_type_check");
        DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_type_check CHECK (type IN ('order_placed', 'order_confirmed', 'order_delivered', 'item_sold', 'donation_accepted', 'delivery_assigned', 'driver_approved', 'driver_rejected', 'item_picked_up', 'donation_offered', 'delivery_completed', 'general'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove delivery_completed notification type
        DB::statement("ALTER TABLE notifications DROP CONSTRAINT IF EXISTS notifications_type_check");
        DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_type_check CHECK (type IN ('order_placed', 'order_confirmed', 'order_delivered', 'item_sold', 'donation_accepted', 'delivery_assigned', 'driver_approved', 'driver_rejected', 'item_picked_up', 'donation_offered', 'general'))");
    }
};
