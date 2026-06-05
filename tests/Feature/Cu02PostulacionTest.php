<?php

namespace Tests\Feature;

use App\Models\AsignacionCarrera\Carrera;
use App\Models\GestionAcademica\Gestion;
use App\Models\InscripcionPagos\Inscripcion;
use App\Models\InscripcionPagos\Postulante;
use App\Support\States\GestionState;
use App\Support\States\InscripcionState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Cu02PostulacionTest extends TestCase
{
    use RefreshDatabase;

    /* ------------------------------------------------------------------ */
    /*  Datos base reutilizables                                          */
    /* ------------------------------------------------------------------ */

    private function datosPostulante(array $overrides = []): array
    {
        return array_merge([
            'ci' => '9876543',
            'complemento' => null,
            'nombres' => 'Maria',
            'apellido_paterno' => 'Lopez',
            'apellido_materno' => 'Garcia',
            'fecha_nacimiento' => '2005-03-15',
            'genero' => 'femenino',
            'correo' => 'maria.lopez@test.com',
            'telefono' => '71234567',
            'direccion' => 'Av. Principal 123',
            'colegio_procedencia' => 'Colegio Nacional',
            'ciudad' => 'Santa Cruz',
        ], $overrides);
    }

    private function datosCompletos(Gestion $gestion, Carrera $primera, Carrera $segunda, array $overrides = []): array
    {
        return array_merge(
            $this->datosPostulante(),
            [
                'gestion_id' => $gestion->id,
                'carrera_primera_opcion_id' => $primera->id,
                'carrera_segunda_opcion_id' => $segunda->id,
                'foto_ci' => \Illuminate\Http\UploadedFile::fake()->create('ci.pdf', 120, 'application/pdf'),
                'foto_libreta' => \Illuminate\Http\UploadedFile::fake()->create('libreta.pdf', 120, 'application/pdf'),
            ],
            $overrides
        );
    }

    /* ================================================================== */
    /*  TEST 1: Flujo principal exitoso                                   */
    /* ================================================================== */

    public function test_flujo_principal_crea_postulante_inscripcion_y_dos_opciones_correctamente(): void
    {
        $gestion = Gestion::factory()->inscripcion()->create(['anio' => 2026]);
        $carrera1 = Carrera::factory()->create(['nombre' => 'Ingeniería Informática']);
        $carrera2 = Carrera::factory()->create(['nombre' => 'Ingeniería de Sistemas']);

        $payload = $this->datosCompletos($gestion, $carrera1, $carrera2);

        $response = $this->postJson('/api/postulaciones', $payload);

        $response->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Solicitud enviada correctamente. Sera revisada por administracion. Si es aceptada, recibira su numero de registro y contrasena por correo electronico.')
            ->assertJsonPath('data.inscripcion.estado', InscripcionState::PREPOSTULADO);

        /* Postulante creado */
        $this->assertDatabaseHas('postulantes', [
            'ci' => '9876543',
            'nombres' => 'Maria',
            'apellido_paterno' => 'Lopez',
            'correo' => 'maria.lopez@test.com',
            'colegio_procedencia' => 'Colegio Nacional',
            'ciudad' => 'Santa Cruz',
        ]);

        /* Inscripción creada con código CUP-2026-XXXXX */
        $inscripcion = Inscripcion::where('gestion_id', $gestion->id)->first();
        $this->assertNotNull($inscripcion);
        $this->assertStringStartsWith('CUP-2026-', $inscripcion->codigo);
        $this->assertEquals(InscripcionState::PREPOSTULADO, $inscripcion->estado);

        /* Dos opciones de carrera */
        $this->assertDatabaseHas('opciones_carrera', [
            'inscripcion_id' => $inscripcion->id,
            'carrera_id' => $carrera1->id,
            'prioridad' => 1,
        ]);
        $this->assertDatabaseHas('opciones_carrera', [
            'inscripcion_id' => $inscripcion->id,
            'carrera_id' => $carrera2->id,
            'prioridad' => 2,
        ]);

        $this->assertDatabaseHas('documentos', [
            'inscripcion_id' => $inscripcion->id,
            'tipo' => 'carnet_identidad',
            'estado' => 'pendiente',
        ]);
        $this->assertDatabaseHas('documentos', [
            'inscripcion_id' => $inscripcion->id,
            'tipo' => 'libreta_digitalizada',
            'estado' => 'pendiente',
        ]);

        $this->assertNotNull($inscripcion->documentos()->first()->prevalidacion_estado);

        /* Auditoría */
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'postulacion.registrada',
        ]);
    }

    public function test_prevalidacion_documental_observa_archivos_demasiado_pequenos(): void
    {
        $gestion = Gestion::factory()->inscripcion()->create(['anio' => 2026]);
        $carrera1 = Carrera::factory()->create();
        $carrera2 = Carrera::factory()->create();

        $payload = $this->datosCompletos($gestion, $carrera1, $carrera2, [
            'foto_ci' => \Illuminate\Http\UploadedFile::fake()->create('ci.pdf', 0, 'application/pdf'),
        ]);

        $this->postJson('/api/postulaciones', $payload)->assertCreated();

        $inscripcion = Inscripcion::where('gestion_id', $gestion->id)->first();

        $this->assertDatabaseHas('documentos', [
            'inscripcion_id' => $inscripcion->id,
            'tipo' => 'carnet_identidad',
            'prevalidacion_estado' => 'observado',
            'prevalidacion_puntaje' => 65,
        ]);
    }

    /* ================================================================== */
    /*  TEST 2: E1 — Duplicidad bloqueada                                 */
    /* ================================================================== */

    public function test_e1_no_permite_inscripcion_duplicada_en_misma_gestion(): void
    {
        $gestion = Gestion::factory()->inscripcion()->create(['anio' => 2026]);
        $carrera1 = Carrera::factory()->create();
        $carrera2 = Carrera::factory()->create();

        $payload = $this->datosCompletos($gestion, $carrera1, $carrera2);

        /* Primera postulación exitosa */
        $this->postJson('/api/postulaciones', $payload)->assertCreated();

        /* Segunda postulación con mismo CI y misma gestión debe fallar */
        $response = $this->postJson('/api/postulaciones', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Ya existe una inscripción del postulante en esta gestión académica.');

        /* Solo una inscripción */
        $this->assertDatabaseCount('inscripciones', 1);
    }

    public function test_rechaza_correo_ya_afiliado_a_otro_postulante_con_mensaje_humano(): void
    {
        $gestion = Gestion::factory()->inscripcion()->create(['anio' => 2026]);
        $carrera1 = Carrera::factory()->create();
        $carrera2 = Carrera::factory()->create();
        Postulante::factory()->create([
            'ci' => '1111111',
            'correo' => 'registrado@gmail.com',
        ]);

        $payload = $this->datosCompletos($gestion, $carrera1, $carrera2, [
            'ci' => '2222222',
            'correo' => 'registrado@gmail.com',
        ]);

        $response = $this->postJson('/api/postulaciones', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Revise los datos ingresados.')
            ->assertJsonPath('errors.correo.0', 'Este correo electronico ya fue registrado por otro postulante.');

        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertDatabaseCount('inscripciones', 0);
    }

    /* ================================================================== */
    /*  TEST 3: E2 — Opciones de carrera iguales                          */
    /* ================================================================== */

    public function test_e2_rechaza_primera_y_segunda_opcion_iguales(): void
    {
        $gestion = Gestion::factory()->inscripcion()->create();
        $carrera = Carrera::factory()->create();

        $payload = $this->datosCompletos($gestion, $carrera, $carrera);

        $response = $this->postJson('/api/postulaciones', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['carrera_segunda_opcion_id']);

        /* No se creó inscripción */
        $this->assertDatabaseCount('inscripciones', 0);
    }

    /* ================================================================== */
    /*  TEST 4: E3 — Datos incompletos                                    */
    /* ================================================================== */

    public function test_e3_rechaza_datos_incompletos_y_muestra_campos_observados(): void
    {
        $gestion = Gestion::factory()->inscripcion()->create();

        $response = $this->postJson('/api/postulaciones', [
            'gestion_id' => $gestion->id,
            /* Faltan todos los campos personales y opciones */
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    'ci',
                    'nombres',
                    'apellido_paterno',
                    'fecha_nacimiento',
                    'genero',
                    'correo',
                    'telefono',
                    'colegio_procedencia',
                    'ciudad',
                    'carrera_primera_opcion_id',
                    'carrera_segunda_opcion_id',
                ],
            ]);

        $this->assertDatabaseCount('postulantes', 0);
        $this->assertDatabaseCount('inscripciones', 0);
    }

    /* ================================================================== */
    /*  TEST 5: Gestión no habilitada                                     */
    /* ================================================================== */

    public function test_rechaza_postulacion_si_gestion_no_esta_habilitada(): void
    {
        $gestion = Gestion::factory()->planificada()->create();
        $carrera1 = Carrera::factory()->create();
        $carrera2 = Carrera::factory()->create();

        $payload = $this->datosCompletos($gestion, $carrera1, $carrera2);

        $response = $this->postJson('/api/postulaciones', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'La gestión seleccionada no está habilitada para inscripción.');

        $this->assertDatabaseCount('inscripciones', 0);
    }

    /* ================================================================== */
    /*  TEST 6: Carrera inactiva rechazada                                */
    /* ================================================================== */

    public function test_rechaza_postulacion_si_carrera_no_esta_activa(): void
    {
        $gestion = Gestion::factory()->inscripcion()->create();
        $carreraActiva = Carrera::factory()->create();
        $carreraInactiva = Carrera::factory()->inactiva()->create();

        $payload = $this->datosCompletos($gestion, $carreraActiva, $carreraInactiva);

        $response = $this->postJson('/api/postulaciones', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->assertDatabaseCount('inscripciones', 0);
    }

    /* ================================================================== */
    /*  TEST 7: Endpoint GET create retorna datos del formulario          */
    /* ================================================================== */

    public function test_endpoint_create_retorna_gestion_y_carreras(): void
    {
        $gestion = Gestion::factory()->inscripcion()->create();
        Carrera::factory()->count(3)->create();

        $response = $this->getJson('/api/postulaciones/create');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'data' => [
                    'gestion' => ['id', 'nombre', 'anio'],
                    'carreras',
                ],
            ]);

        $this->assertCount(3, $response->json('data.carreras'));
    }

    /* ================================================================== */
    /*  TEST 8: Endpoint GET create sin gestión habilitada                */
    /* ================================================================== */

    public function test_endpoint_create_retorna_404_sin_gestion_habilitada(): void
    {
        Gestion::factory()->planificada()->create();

        $response = $this->getJson('/api/postulaciones/create');

        $response->assertNotFound()
            ->assertJsonPath('ok', false);
    }
}
