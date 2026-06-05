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
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS ck_users_role');

        // Recreamos la restricción de rol excluyendo al cajero
        DB::statement("ALTER TABLE users ADD CONSTRAINT ck_users_role CHECK (role IN ('admin','docente','postulante','autoridad','coordinador'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS ck_users_role');

        // Restauramos la restricción incluyendo de nuevo al cajero
        DB::statement("ALTER TABLE users ADD CONSTRAINT ck_users_role CHECK (role IN ('admin','docente','postulante','autoridad','coordinador','cajero'))");
    }
};
