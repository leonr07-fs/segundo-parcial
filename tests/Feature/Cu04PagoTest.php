<?php

namespace Tests\Feature;

use App\Models\InscripcionPagos\Inscripcion;
use App\Models\InscripcionPagos\Pago;
use App\Models\Seguridad\User;
use App\Services\PortalPostulante\PagoService;
use App\Support\States\InscripcionState;
use App\Support\States\PagoState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Cu04PagoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $docente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->docente = User::factory()->create(['role' => 'docente']);
    }

    /* ================================================================== */
    /*  TEST 1: Listar pendientes de pago                                 */
    /* ================================================================== */

    public function test_admin_puede_listar_inscripciones_pendientes_de_pago(): void
    {
        $gestion = \App\Models\GestionAcademica\Gestion::factory()->create(['estado' => \App\Support\States\GestionState::INSCRIPCION]);
        Inscripcion::factory()->count(2)->create(['gestion_id' => $gestion->id, 'estado' => InscripcionState::DOCUMENTOS_APROBADOS]);
        Inscripcion::factory()->count(1)->create(['gestion_id' => $gestion->id, 'estado' => InscripcionState::PREPOSTULADO]); // No debe salir

        $response = $this->actingAs($this->admin)->getJson('/api/inscripciones/pendientes-pago');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(2, 'data.inscripciones');
    }

    public function test_admin_puede_listar_inscripciones_pagadas(): void
    {
        $gestion = \App\Models\GestionAcademica\Gestion::factory()->create(['estado' => \App\Support\States\GestionState::INSCRIPCION]);
        Inscripcion::factory()->count(2)->create(['gestion_id' => $gestion->id, 'estado' => InscripcionState::DOCUMENTOS_APROBADOS]);
        Inscripcion::factory()->count(3)->create(['gestion_id' => $gestion->id, 'estado' => InscripcionState::PAGADO]);

        $response = $this->actingAs($this->admin)->getJson('/api/inscripciones/pendientes-pago?estado=pagados');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(3, 'data.inscripciones');
    }

    public function test_admin_puede_listar_todas_las_inscripciones_de_pago(): void
    {
        $gestion = \App\Models\GestionAcademica\Gestion::factory()->create(['estado' => \App\Support\States\GestionState::INSCRIPCION]);
        Inscripcion::factory()->count(2)->create(['gestion_id' => $gestion->id, 'estado' => InscripcionState::DOCUMENTOS_APROBADOS]);
        Inscripcion::factory()->count(3)->create(['gestion_id' => $gestion->id, 'estado' => InscripcionState::PAGADO]);

        $response = $this->actingAs($this->admin)->getJson('/api/inscripciones/pendientes-pago?estado=todos');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(5, 'data.inscripciones');
    }

    public function test_admin_puede_obtener_detalles_de_pago_registrado(): void
    {
        $inscripcion = Inscripcion::factory()->create(['estado' => InscripcionState::PAGADO]);
        $pago = \App\Models\InscripcionPagos\Pago::factory()->create([
            'inscripcion_id' => $inscripcion->id,
            'monto' => 300.00,
            'metodo' => 'paypal',
            'referencia' => 'ORDER-123',
            'estado' => PagoState::APROBADO,
        ]);
        $recibo = \App\Models\InscripcionPagos\Recibo::factory()->create([
            'pago_id' => $pago->id,
            'numero' => 'REC-123',
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/inscripciones/{$inscripcion->id}/pagos");

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.inscripcion.id', $inscripcion->id)
            ->assertJsonPath('data.pago.referencia', 'ORDER-123')
            ->assertJsonPath('data.recibo.numero', 'REC-123');
    }

    /* ================================================================== */
    /*  TEST 2: Registro exitoso                                          */
    /* ================================================================== */

    public function test_registro_exitoso_crea_pago_recibo_y_cambia_inscripcion_a_pagado(): void
    {
        $inscripcion = Inscripcion::factory()->create(['estado' => InscripcionState::DOCUMENTOS_APROBADOS]);

        $payload = [
            'monto' => PagoService::ARANCEL_CUP,
            'metodo' => 'Transferencia Bancaria',
            'referencia' => 'TX-123456789',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson("/api/inscripciones/{$inscripcion->id}/pagos", $payload);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'data' => [
                    'pago',
                    'recibo',
                    'credenciales' => [
                        'numero_registro',
                        'password_temporal',
                        'correo_enviado',
                    ],
                ],
            ]);

        $numeroRegistro = $response->json('data.credenciales.numero_registro');
        $passwordTemporal = $response->json('data.credenciales.password_temporal');
        $this->assertMatchesRegularExpression('/^\d{9}$/', $numeroRegistro);
        $this->assertSame($inscripcion->postulante->ci, $passwordTemporal);

        // Validar que se creó el pago aprobado
        $this->assertDatabaseHas('pagos', [
            'inscripcion_id' => $inscripcion->id,
            'monto' => PagoService::ARANCEL_CUP,
            'metodo' => 'Transferencia Bancaria',
            'referencia' => 'TX-123456789',
            'estado' => PagoState::APROBADO,
        ]);

        $pago = Pago::where('referencia', 'TX-123456789')->first();

        // Validar recibo
        $this->assertDatabaseHas('recibos', [
            'pago_id' => $pago->id,
            'emitido_por' => $this->admin->id,
        ]);

        // Validar estado de inscripción
        $this->assertDatabaseHas('inscripciones', [
            'id' => $inscripcion->id,
            'estado' => InscripcionState::PAGADO,
        ]);

        // Validar auditoría
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'pago.registrado',
            'user_id' => $this->admin->id,
        ]);

        // Validar usuario creado
        $postulante = $inscripcion->postulante;
        $this->assertDatabaseHas('users', [
            'email' => $postulante->correo,
            'role' => User::ROLE_POSTULANTE,
            'is_active' => true,
        ]);

        // Validar inicio de sesión del postulante con sus nuevas credenciales
        $this->postJson('/logout');

        $loginResponse = $this->postJson('/login', [
            'numero_registro' => $numeroRegistro,
            'password' => $passwordTemporal,
        ]);
        $loginResponse->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.user.role', User::ROLE_POSTULANTE);

        // Validar auditoría de credenciales
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'postulante.credenciales.emitidas',
            'user_id' => $this->admin->id,
        ]);
    }

    /* ================================================================== */
    /*  TEST 3: Excepción E1 - Referencia duplicada                       */
    /* ================================================================== */

    public function test_e1_referencia_duplicada_falla_validacion(): void
    {
        $inscripcion1 = Inscripcion::factory()->create(['estado' => InscripcionState::DOCUMENTOS_APROBADOS]);
        $inscripcion2 = Inscripcion::factory()->create(['estado' => InscripcionState::DOCUMENTOS_APROBADOS]);

        Pago::factory()->create([
            'inscripcion_id' => $inscripcion1->id,
            'referencia' => 'TX-DUPLICADA',
        ]);

        $payload = [
            'monto' => PagoService::ARANCEL_CUP,
            'metodo' => 'Caja',
            'referencia' => 'TX-DUPLICADA',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson("/api/inscripciones/{$inscripcion2->id}/pagos", $payload);

        // Falla validación del FormRequest (unique)
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['referencia']);
    }

    /* ================================================================== */
    /*  TEST 4: Excepción E3 - Monto inválido                             */
    /* ================================================================== */

    public function test_e3_monto_diferente_al_arancel_lanza_domain_exception(): void
    {
        $inscripcion = Inscripcion::factory()->create(['estado' => InscripcionState::DOCUMENTOS_APROBADOS]);

        $payload = [
            'monto' => 150.00, // Arancel es 300
            'metodo' => 'Transferencia Bancaria',
            'referencia' => 'TX-9999',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson("/api/inscripciones/{$inscripcion->id}/pagos", $payload);

        $response->assertStatus(422) // DomainException manejada en controller
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'El monto ingresado (150.00 BOB) no coincide con el arancel del CUP (300.00 BOB).');

        $this->assertDatabaseMissing('pagos', ['referencia' => 'TX-9999']);
    }

    /* ================================================================== */
    /*  TEST 5: Estado incorrecto de inscripción                          */
    /* ================================================================== */

    public function test_falla_si_inscripcion_no_esta_en_documentos_aprobados(): void
    {
        $inscripcion = Inscripcion::factory()->create(['estado' => InscripcionState::PREPOSTULADO]);

        $payload = [
            'monto' => PagoService::ARANCEL_CUP,
            'metodo' => 'Transferencia Bancaria',
            'referencia' => 'TX-0001',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson("/api/inscripciones/{$inscripcion->id}/pagos", $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'La inscripción no está habilitada para registrar pagos.');
    }

    /* ================================================================== */
    /*  TEST 6: Roles (E3 seguridad)                                      */
    /* ================================================================== */

    public function test_usuario_sin_rol_admin_no_puede_registrar_pagos(): void
    {
        $inscripcion = Inscripcion::factory()->create(['estado' => InscripcionState::DOCUMENTOS_APROBADOS]);

        $payload = [
            'monto' => PagoService::ARANCEL_CUP,
            'metodo' => 'Efectivo',
            'referencia' => 'TX-123',
        ];

        $response = $this->actingAs($this->docente)
            ->postJson("/api/inscripciones/{$inscripcion->id}/pagos", $payload);

        $response->assertForbidden();

        $responseGet = $this->actingAs($this->docente)
            ->getJson('/api/inscripciones/pendientes-pago');

        $responseGet->assertForbidden();
    }

    /* ================================================================== */
    /*  TEST 7: Seguridad de Colisiones y Roles                           */
    /* ================================================================== */

    public function test_postulacion_falla_si_correo_coincide_con_usuario_existente(): void
    {
        // Crear un usuario con el correo que intentará registrar el postulante
        User::factory()->create([
            'email' => 'admin@cup.test',
            'role' => User::ROLE_ADMIN,
        ]);

        $payload = [
            'gestion_id' => \App\Models\GestionAcademica\Gestion::factory()->create(['estado' => 'inscripcion'])->id,
            'ci' => '12345678',
            'nombres' => 'Juan',
            'apellido_paterno' => 'Perez',
            'fecha_nacimiento' => '2000-01-01',
            'genero' => 'masculino',
            'correo' => 'admin@cup.test',
            'telefono' => '70000000',
            'colegio_procedencia' => 'Colegio Nacional',
            'ciudad' => 'Santa Cruz',
            'carrera_primera_opcion_id' => \App\Models\AsignacionCarrera\Carrera::factory()->create(['activa' => true])->id,
            'carrera_segunda_opcion_id' => \App\Models\AsignacionCarrera\Carrera::factory()->create(['activa' => true])->id,
            'foto_ci' => \Illuminate\Http\UploadedFile::fake()->create('ci.jpg', 500),
            'foto_libreta' => \Illuminate\Http\UploadedFile::fake()->create('libreta.jpg', 500),
        ];

        $response = $this->postJson('/api/postulaciones', $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['correo']);
    }

    public function test_postulacion_falla_si_ci_coincide_con_usuario_existente(): void
    {
        // Crear un usuario cuyo número de registro es igual al CI que usará el postulante
        User::factory()->create([
            'numero_registro' => '87654321',
            'role' => User::ROLE_DOCENTE,
        ]);

        $payload = [
            'gestion_id' => \App\Models\GestionAcademica\Gestion::factory()->create(['estado' => 'inscripcion'])->id,
            'ci' => '87654321',
            'nombres' => 'Juan',
            'apellido_paterno' => 'Perez',
            'fecha_nacimiento' => '2000-01-01',
            'genero' => 'masculino',
            'correo' => 'juan@test.com',
            'telefono' => '70000000',
            'colegio_procedencia' => 'Colegio Nacional',
            'ciudad' => 'Santa Cruz',
            'carrera_primera_opcion_id' => \App\Models\AsignacionCarrera\Carrera::factory()->create(['activa' => true])->id,
            'carrera_segunda_opcion_id' => \App\Models\AsignacionCarrera\Carrera::factory()->create(['activa' => true])->id,
            'foto_ci' => \Illuminate\Http\UploadedFile::fake()->create('ci.jpg', 500),
            'foto_libreta' => \Illuminate\Http\UploadedFile::fake()->create('libreta.jpg', 500),
        ];

        $response = $this->postJson('/api/postulaciones', $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ci']);
    }

    public function test_pago_falla_si_email_postulante_coincide_con_rol_diferente(): void
    {
        $inscripcion = Inscripcion::factory()->create(['estado' => InscripcionState::DOCUMENTOS_APROBADOS]);
        
        // Asignamos al postulante el mismo correo que un admin existente
        $postulante = $inscripcion->postulante;
        $postulante->update(['correo' => 'admin@cup.test']);

        User::factory()->create([
            'email' => 'admin@cup.test',
            'role' => User::ROLE_ADMIN,
        ]);

        $payload = [
            'monto' => PagoService::ARANCEL_CUP,
            'metodo' => 'Transferencia Bancaria',
            'referencia' => 'TX-98765',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson("/api/inscripciones/{$inscripcion->id}/pagos", $payload);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'El correo del postulante pertenece a un usuario con un rol diferente.');
    }
}
