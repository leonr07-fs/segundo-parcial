<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_materia_id')->constrained('grupo_materias')->restrictOnDelete();
            $table->foreignId('inscripcion_id')->constrained('inscripciones')->restrictOnDelete();
            $table->foreignId('docente_id')->constrained('docentes')->restrictOnDelete();
            $table->date('fecha');
            $table->string('estado', 20);
            $table->text('observacion')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('registrado_en')->nullable();
            $table->timestamps();

            $table->unique(['grupo_materia_id', 'inscripcion_id', 'fecha'], 'asistencias_gm_inscripcion_fecha_unique');
        });

        DB::statement("ALTER TABLE asistencias ADD CONSTRAINT ck_asistencias_estado CHECK (estado IN ('presente','ausente','tardanza','justificado'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};
