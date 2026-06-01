<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Drop existing check constraint if it exists
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS ck_users_role');

        // 2. Add the updated check constraint including 'autoridad' and 'coordinador'
        DB::statement("ALTER TABLE users ADD CONSTRAINT ck_users_role CHECK (role IN ('admin','docente','postulante','autoridad','coordinador'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS ck_users_role');

        // Revert back to the original constraint
        DB::statement("ALTER TABLE users ADD CONSTRAINT ck_users_role CHECK (role IN ('admin','docente','postulante'))");
    }
};
