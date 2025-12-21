<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the old constraint
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_user_type_check');

        // Add new constraint with 'admin' included
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_user_type_check CHECK (user_type IN ('user', 'charity', 'admin'))");
    }

    public function down(): void
    {
        // Drop the new constraint
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_user_type_check');

        // Restore old constraint
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_user_type_check CHECK (user_type IN ('user', 'charity'))");
    }
};
