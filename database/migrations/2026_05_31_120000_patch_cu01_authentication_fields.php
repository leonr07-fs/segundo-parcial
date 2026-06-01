<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega los campos necesarios para CU01 - Autenticacion de Usuario.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('postulante')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedTinyInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable()->index();
            $table->timestamp('last_login_at')->nullable();
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT ck_users_role CHECK (role IN ('admin','docente','postulante'))");

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 80)->index();
            $table->string('auditable_type', 120)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    /**
     * Revierte los campos y bitacora agregados para CU01.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS ck_users_role');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'is_active',
                'failed_login_attempts',
                'locked_until',
                'last_login_at',
            ]);
        });
    }
};
