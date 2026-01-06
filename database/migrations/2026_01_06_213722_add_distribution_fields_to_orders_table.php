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
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('distributed_at')->nullable()->after('cancelled_at');
            $table->text('distribution_notes')->nullable()->after('distributed_at');
            $table->integer('people_helped')->nullable()->default(0)->after('distribution_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['distributed_at', 'distribution_notes', 'people_helped']);
        });
    }
};
