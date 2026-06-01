<?php

namespace Tests\Feature;

use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\User;
use App\Services\PagoService;
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
        Inscripcion::factory()->count(2)->create(['estado' => InscripcionState::DOCUMENTOS_APROBADOS]);
        Inscripcion::factory()->count(1)->create(['estado' => InscripcionState::PREPOSTULADO]); // No debe salir

        $response = $this->actingAs($this->admin)->getJson('/api/inscripciones/pendientes-pago');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(2, 'data.inscripciones');
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
            ->assertJsonPath('ok', true);

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
}
