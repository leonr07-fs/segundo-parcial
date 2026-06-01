<?php

namespace Tests\Feature;

use App\Models\Carrera;
use App\Models\CupoCarrera;
use App\Models\Gestion;
use App\Models\Inscripcion;
use App\Models\OpcionCarrera;
use App\Models\Postulante;
use App\Models\ResultadoCup;
use App\Support\States\AsignacionCarreraState;
use App\Support\States\InscripcionState;
use App\Services\AsignacionCarreraService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Cu12AsignacionCarreraTest extends TestCase
{
    use RefreshDatabase;

    private AsignacionCarreraService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AsignacionCarreraService();
    }

    private function prepararPostulante(Gestion $gestion, float $promedio, int $id1, int $id2): void
    {
        $postulante = Postulante::factory()->create();
        $inscripcion = Inscripcion::factory()->create([
            'postulante_id' => $postulante->id,
            'gestion_id' => $gestion->id,
            'estado' => InscripcionState::PAGADO,
        ]);

        OpcionCarrera::insert([
            'inscripcion_id' => $inscripcion->id,
            'carrera_id' => $id1,
            'prioridad' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        OpcionCarrera::insert([
            'inscripcion_id' => $inscripcion->id,
            'carrera_id' => $id2,
            'prioridad' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Simular CU11
        ResultadoCup::insert([
            'inscripcion_id' => $inscripcion->id,
            'promedio_final' => $promedio,
            'estado_final' => 'aprobado',
            'cerrado_en' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_asigna_primera_opcion_y_descuenta_cupo(): void
    {
        $gestion = Gestion::factory()->create(['estado' => 'activa']);
        $carreraSistemas = Carrera::factory()->create(['codigo' => 'SIS']);
        $carreraRedes = Carrera::factory()->create(['codigo' => 'RED']);

        CupoCarrera::insert([
            'gestion_id' => $gestion->id, 'carrera_id' => $carreraSistemas->id, 'cupo_total' => 10, 'cupo_disponible' => 10
        ]);
        CupoCarrera::insert([
            'gestion_id' => $gestion->id, 'carrera_id' => $carreraRedes->id, 'cupo_total' => 5, 'cupo_disponible' => 5
        ]);

        // Postulante 1: 90.00
        $this->prepararPostulante($gestion, 90.00, $carreraSistemas->id, $carreraRedes->id);

        $stats = $this->service->ejecutarAsignacion($gestion->id);

        $this->assertEquals(1, $stats['asignados_1ra']);
        
        $this->assertDatabaseHas('asignaciones_carrera', [
            'carrera_id' => $carreraSistemas->id,
            'opcion_prioridad' => 1,
            'estado' => AsignacionCarreraState::ASIGNADO
        ]);

        // Verificar descuento de cupo
        $this->assertDatabaseHas('cupos_carrera', [
            'carrera_id' => $carreraSistemas->id,
            'cupo_disponible' => 9
        ]);
    }

    public function test_asigna_segunda_opcion_si_primera_agotada(): void
    {
        $gestion = Gestion::factory()->create(['estado' => 'activa']);
        $carreraSistemas = Carrera::factory()->create();
        $carreraRedes = Carrera::factory()->create();

        CupoCarrera::insert([
            'gestion_id' => $gestion->id, 'carrera_id' => $carreraSistemas->id, 'cupo_total' => 1, 'cupo_disponible' => 1
        ]);
        CupoCarrera::insert([
            'gestion_id' => $gestion->id, 'carrera_id' => $carreraRedes->id, 'cupo_total' => 5, 'cupo_disponible' => 5
        ]);

        // Postulante A: 95.00 (Ocupa el único cupo de Sistemas)
        $this->prepararPostulante($gestion, 95.00, $carreraSistemas->id, $carreraRedes->id);
        
        // Postulante B: 85.00 (Quiere sistemas pero se agotó, entra a redes)
        $this->prepararPostulante($gestion, 85.00, $carreraSistemas->id, $carreraRedes->id);

        $stats = $this->service->ejecutarAsignacion($gestion->id);

        $this->assertEquals(1, $stats['asignados_1ra']);
        $this->assertEquals(1, $stats['asignados_2da']);
        $this->assertEquals(0, $stats['sin_cupo']);

        $this->assertDatabaseHas('cupos_carrera', [
            'carrera_id' => $carreraSistemas->id,
            'cupo_disponible' => 0
        ]);
        $this->assertDatabaseHas('cupos_carrera', [
            'carrera_id' => $carreraRedes->id,
            'cupo_disponible' => 4
        ]);
    }

    public function test_estudiante_queda_sin_cupo_si_ambas_opciones_estan_llenas(): void
    {
        $gestion = Gestion::factory()->create(['estado' => 'activa']);
        $carreraSistemas = Carrera::factory()->create();
        $carreraRedes = Carrera::factory()->create();

        // Cero cupos
        CupoCarrera::insert([
            'gestion_id' => $gestion->id, 'carrera_id' => $carreraSistemas->id, 'cupo_total' => 0, 'cupo_disponible' => 0
        ]);
        CupoCarrera::insert([
            'gestion_id' => $gestion->id, 'carrera_id' => $carreraRedes->id, 'cupo_total' => 0, 'cupo_disponible' => 0
        ]);

        $this->prepararPostulante($gestion, 80.00, $carreraSistemas->id, $carreraRedes->id);

        $stats = $this->service->ejecutarAsignacion($gestion->id);

        $this->assertEquals(1, $stats['sin_cupo']);

        $this->assertDatabaseHas('asignaciones_carrera', [
            'carrera_id' => null,
            'estado' => AsignacionCarreraState::SIN_CUPO
        ]);
    }
}
