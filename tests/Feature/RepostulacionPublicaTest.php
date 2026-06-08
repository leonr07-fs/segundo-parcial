<?php

namespace Tests\Feature;

use App\Models\AsignacionCarrera\Carrera;
use App\Models\AsignacionCarrera\OpcionCarrera;
use App\Models\EvaluacionesResultados\ResultadoCup;
use App\Models\GestionAcademica\Gestion;
use App\Models\InscripcionPagos\Inscripcion;
use App\Models\InscripcionPagos\Postulante;
use App\Models\InscripcionPagos\ValidacionDocumental;
use App\Support\States\GestionState;
use App\Support\States\InscripcionState;
use App\Support\States\ValidacionDocumentalState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepostulacionPublicaTest extends TestCase
{
    use RefreshDatabase;

    public function test_reprobado_puede_validar_y_preparar_repostulacion(): void
    {
        $gestionAnterior = Gestion::factory()->create(['estado' => GestionState::CERRADA]);
        $gestionVigente = Gestion::factory()->create(['estado' => GestionState::INSCRIPCION]);

        $postulante = Postulante::factory()->create([
            'ci' => '7777777',
            'correo' => 'reprobado@cup.test',
        ]);

        $carrera1 = Carrera::factory()->create();
        $carrera2 = Carrera::factory()->create();

        $inscripcionAnterior = Inscripcion::factory()->create([
            'postulante_id' => $postulante->id,
            'gestion_id' => $gestionAnterior->id,
            'estado' => InscripcionState::FINALIZADO,
        ]);

        OpcionCarrera::create([
            'inscripcion_id' => $inscripcionAnterior->id,
            'carrera_id' => $carrera1->id,
            'prioridad' => 1,
        ]);
        OpcionCarrera::create([
            'inscripcion_id' => $inscripcionAnterior->id,
            'carrera_id' => $carrera2->id,
            'prioridad' => 2,
        ]);

        ValidacionDocumental::create([
            'inscripcion_id' => $inscripcionAnterior->id,
            'estado' => ValidacionDocumentalState::APROBADA,
            'validado_en' => now(),
        ]);

        ResultadoCup::create([
            'inscripcion_id' => $inscripcionAnterior->id,
            'promedio_final' => 45.5,
            'estado_final' => 'reprobado',
            'cerrado_en' => now(),
        ]);

        $payload = ['ci' => '7777777', 'correo' => 'reprobado@cup.test'];

        $this->postJson('/api/public/repostulacion/validar', $payload)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->postJson('/api/public/repostulacion/preparar', $payload)
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.inscripcion.estado', InscripcionState::DOCUMENTOS_APROBADOS);

        $this->assertDatabaseHas('inscripciones', [
            'postulante_id' => $postulante->id,
            'gestion_id' => $gestionVigente->id,
            'estado' => InscripcionState::DOCUMENTOS_APROBADOS,
        ]);
    }

    public function test_aprobado_no_puede_repostular(): void
    {
        $gestion = Gestion::factory()->create(['estado' => GestionState::CERRADA]);
        $postulante = Postulante::factory()->create([
            'ci' => '8888888',
            'correo' => 'aprobado@cup.test',
        ]);

        $inscripcion = Inscripcion::factory()->create([
            'postulante_id' => $postulante->id,
            'gestion_id' => $gestion->id,
            'estado' => InscripcionState::FINALIZADO,
        ]);

        ResultadoCup::create([
            'inscripcion_id' => $inscripcion->id,
            'promedio_final' => 85,
            'estado_final' => 'aprobado',
            'cerrado_en' => now(),
        ]);

        $this->postJson('/api/public/repostulacion/validar', [
            'ci' => '8888888',
            'correo' => 'aprobado@cup.test',
        ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    public function test_correo_incorrecto_es_rechazado(): void
    {
        $postulante = Postulante::factory()->create([
            'ci' => '9999999',
            'correo' => 'real@cup.test',
        ]);

        $this->postJson('/api/public/repostulacion/validar', [
            'ci' => '9999999',
            'correo' => 'otro@cup.test',
        ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }
}
