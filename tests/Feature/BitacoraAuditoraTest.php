<?php

namespace Tests\Feature;

use App\Models\ReportesAuditoria\AuditLog;
use App\Models\AsignacionCarrera\Carrera;
use App\Models\EvaluacionesResultados\Evaluacion;
use App\Models\GestionAcademica\Gestion;
use App\Models\GestionAcademica\Grupo;
use App\Models\GestionAcademica\GrupoMateria;
use App\Models\InscripcionPagos\Inscripcion;
use App\Models\GestionAcademica\Materia;
use App\Models\InscripcionPagos\Postulante;
use App\Models\Seguridad\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BitacoraAuditoraTest extends TestCase
{
    use RefreshDatabase;

    public function test_bitacora_devuelve_resumen_logs_normalizados_y_notas(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        AuditLog::create([
            'user_id' => $admin->id,
            'event' => 'postulacion.registrada',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'metadata' => [
                'tabla' => 'inscripciones',
                'registro_id' => 10,
                'codigo' => 'CUP-2026-00001',
            ],
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        AuditLog::create([
            'user_id' => $admin->id,
            'event' => 'evaluacion.importada',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'metadata' => [
                'tabla' => 'evaluaciones',
                'registro_id' => 20,
                'filas_procesadas' => 2,
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $gestion = Gestion::factory()->create();
        $postulante = Postulante::factory()->create();
        $inscripcion = Inscripcion::factory()->create([
            'postulante_id' => $postulante->id,
            'gestion_id' => $gestion->id,
        ]);
        $carrera = Carrera::factory()->create();
        $materia = Materia::create(['codigo' => 'MAT', 'nombre' => 'Matematica', 'activa' => true]);
        $grupo = Grupo::create([
            'gestion_id' => $gestion->id,
            'codigo' => 'G1',
            'nombre' => 'Grupo 1',
            'cupo_maximo' => 70,
            'estado' => 'configuracion',
        ]);
        $grupoMateria = GrupoMateria::create([
            'grupo_id' => $grupo->id,
            'materia_id' => $materia->id,
        ]);

        Evaluacion::create([
            'inscripcion_id' => $inscripcion->id,
            'grupo_materia_id' => $grupoMateria->id,
            'examen_1' => 80,
            'examen_2' => 70,
            'examen_3' => 90,
            'promedio' => 80,
            'estado' => 'registrado',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/bitacora');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.resumen.total_movimientos', 2)
            ->assertJsonPath('data.resumen.usuarios_activos', 1)
            ->assertJsonPath('data.resumen.tablas_intervenidas', 2)
            ->assertJsonPath('data.logs.0.accion', 'evaluacion.importada')
            ->assertJsonPath('data.logs.0.tabla', 'evaluaciones')
            ->assertJsonPath('data.logs.0.registro_id', 20)
            ->assertJsonPath('data.notas.promedios.examen_1', 80)
            ->assertJsonPath('data.notas.promedios.general', 80);

        $this->assertNotNull($carrera);
    }
}
