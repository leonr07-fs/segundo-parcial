<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->string('prevalidacion_estado', 30)->nullable()->after('estado');
            $table->unsignedTinyInteger('prevalidacion_puntaje')->default(0)->after('prevalidacion_estado');
            $table->json('prevalidacion_observaciones')->nullable()->after('prevalidacion_puntaje');
            $table->timestamp('prevalidado_en')->nullable()->after('prevalidacion_observaciones');
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropColumn([
                'prevalidacion_estado',
                'prevalidacion_puntaje',
                'prevalidacion_observaciones',
                'prevalidado_en',
            ]);
        });
    }
};
