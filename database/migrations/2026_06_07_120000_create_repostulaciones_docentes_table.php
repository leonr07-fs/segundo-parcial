<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repostulaciones_docentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('docente_id')->constrained('docentes')->restrictOnDelete();
            $table->foreignId('gestion_id')->constrained('gestiones')->restrictOnDelete();
            $table->string('estado', 40)->default('pendiente')->index();
            $table->text('observacion')->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable();
            $table->timestamps();

            $table->unique(['docente_id', 'gestion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repostulaciones_docentes');
    }
};
