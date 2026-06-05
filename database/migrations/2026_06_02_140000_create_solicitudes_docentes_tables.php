<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_docentes', function (Blueprint $table) {
            $table->id();
            $table->string('ci', 30);
            $table->string('nombres', 120);
            $table->string('apellidos', 120)->nullable();
            $table->string('correo', 150);
            $table->string('telefono', 30)->nullable();
            $table->foreignId('materia_id')->constrained('materias')->restrictOnDelete();
            $table->string('profesion', 150);
            $table->string('estado', 40)->default('pendiente')->index();
            $table->text('observacion')->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable();
            $table->timestamps();

            $table->unique(['ci', 'materia_id']);
            $table->unique(['correo', 'materia_id']);
        });

        Schema::create('documentos_docentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_docente_id')->constrained('solicitudes_docentes')->cascadeOnDelete();
            $table->string('tipo', 60);
            $table->string('archivo_path', 255);
            $table->string('estado', 40)->default('pendiente')->index();
            $table->text('observacion')->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable();
            $table->timestamps();

            $table->unique(['solicitud_docente_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_docentes');
        Schema::dropIfExists('solicitudes_docentes');
    }
};
