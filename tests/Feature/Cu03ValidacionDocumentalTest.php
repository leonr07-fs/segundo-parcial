<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\Inscripcion;
use App\Models\User;
use App\Support\States\DocumentoState;
use App\Support\States\InscripcionState;
use App\Support\States\ValidacionDocumentalState;
use App\Support\TipoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Cu03ValidacionDocumentalTest extends TestCase
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

    private function crearInscripcionConDocumentosPendientes(): Inscripcion
    {
        $inscripcion = Inscripcion::factory()->create(['estado' => InscripcionState::PREPOSTULADO]);

        foreach (TipoDocumento::OBLIGATORIOS as $tipo) {
            Documento::factory()->create([
                'inscripcion_id' => $inscripcion->id,
                'tipo' => $tipo,
                'estado' => DocumentoState::PENDIENTE,
            ]);
        }

        return $inscripcion;
    }

    /* ================================================================== */
    /*  TEST 1: Lista de pendientes                                       */
    /* ================================================================== */

    public function test_admin_puede_ver_inscripciones_pendientes_de_validacion(): void
    {
        Inscripcion::factory()->count(2)->create(['estado' => InscripcionState::PREPOSTULADO]);
        Inscripcion::factory()->count(1)->create(['estado' => InscripcionState::DOCUMENTOS_PENDIENTES]);
        Inscripcion::factory()->count(3)->create(['estado' => InscripcionState::DOCUMENTOS_APROBADOS]); // No debe salir

        $response = $this->actingAs($this->admin)
            ->getJson('/api/inscripciones/pendientes-validacion');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(3, 'data.inscripciones');
    }

    /* ================================================================== */
    /*  TEST 2: Obtener expediente                                        */
    /* ================================================================== */

    public function test_admin_puede_obtener_expediente_documental(): void
    {
        $inscripcion = $this->crearInscripcionConDocumentosPendientes();

        $response = $this->actingAs($this->admin)
            ->getJson("/api/inscripciones/{$inscripcion->id}/documentos");

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.inscripcion.id', $inscripcion->id)
            ->assertJsonCount(4, 'data.documentos');
    }

    public function test_expediente_genera_placeholders_si_no_hay_documentos(): void
    {
        $inscripcion = Inscripcion::factory()->create(['estado' => InscripcionState::PREPOSTULADO]);
        // No creamos documentos

        $response = $this->actingAs($this->admin)
            ->getJson("/api/inscripciones/{$inscripcion->id}/documentos");

        $response->assertOk()
            ->assertJsonCount(4, 'data.documentos'); // Debe generar los 4 obligatorios
        $this->assertDatabaseCount('documentos', 4);
    }

    /* ================================================================== */
    /*  TEST 3: Aprobación Total                                          */
    /* ================================================================== */

    public function test_aprobacion_total_cambia_estados_correctamente(): void
    {
        $inscripcion = $this->crearInscripcionConDocumentosPendientes();
        $docs = $inscripcion->documentos;

        $payload = [
            'revisiones' => $docs->map(fn($d) => [
                'id' => $d->id,
                'estado' => DocumentoState::APROBADO,
                'observacion' => null,
            ])->toArray()
        ];

        $response = $this->actingAs($this->admin)
            ->postJson("/api/inscripciones/{$inscripcion->id}/documentos/validar", $payload);

        $response->assertOk()
            ->assertJsonPath('ok', true);

        // Validación global
        $this->assertDatabaseHas('validaciones_documentales', [
            'inscripcion_id' => $inscripcion->id,
            'estado' => ValidacionDocumentalState::APROBADA,
            'validado_por' => $this->admin->id,
        ]);

        // Inscripción sincronizada
        $this->assertDatabaseHas('inscripciones', [
            'id' => $inscripcion->id,
            'estado' => InscripcionState::DOCUMENTOS_APROBADOS,
        ]);

        // Documentos actualizados
        $this->assertEquals(4, Documento::where('inscripcion_id', $inscripcion->id)
            ->where('estado', DocumentoState::APROBADO)
            ->where('revisado_por', $this->admin->id)
            ->count());

        // Auditoría
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'validacion.documental.completada',
        ]);
    }

    /* ================================================================== */
    /*  TEST 4: Observación parcial (E1)                                  */
    /* ================================================================== */

    public function test_e1_documento_observado_deja_inscripcion_pendiente(): void
    {
        $inscripcion = $this->crearInscripcionConDocumentosPendientes();
        $docs = $inscripcion->documentos;

        $payload = [
            'revisiones' => [
                ['id' => $docs[0]->id, 'estado' => DocumentoState::APROBADO, 'observacion' => null],
                ['id' => $docs[1]->id, 'estado' => DocumentoState::APROBADO, 'observacion' => null],
                ['id' => $docs[2]->id, 'estado' => DocumentoState::APROBADO, 'observacion' => null],
                ['id' => $docs[3]->id, 'estado' => DocumentoState::OBSERVADO, 'observacion' => 'Foto borrosa'], // Observado
            ]
        ];

        $response = $this->actingAs($this->admin)
            ->postJson("/api/inscripciones/{$inscripcion->id}/documentos/validar", $payload);

        $response->assertOk();

        // Validación global observada
        $this->assertDatabaseHas('validaciones_documentales', [
            'inscripcion_id' => $inscripcion->id,
            'estado' => ValidacionDocumentalState::OBSERVADA,
        ]);

        // Inscripción sincronizada a documentos_pendientes
        $this->assertDatabaseHas('inscripciones', [
            'id' => $inscripcion->id,
            'estado' => InscripcionState::DOCUMENTOS_PENDIENTES,
        ]);
    }

    /* ================================================================== */
    /*  TEST 5: Rechazo                                                   */
    /* ================================================================== */

    public function test_documento_rechazado_cambia_validacion_a_rechazada(): void
    {
        $inscripcion = $this->crearInscripcionConDocumentosPendientes();
        $docs = $inscripcion->documentos;

        $payload = [
            'revisiones' => [
                ['id' => $docs[0]->id, 'estado' => DocumentoState::APROBADO, 'observacion' => null],
                ['id' => $docs[1]->id, 'estado' => DocumentoState::RECHAZADO, 'observacion' => 'Documento falso'], // Rechazado
            ]
        ];

        $response = $this->actingAs($this->admin)
            ->postJson("/api/inscripciones/{$inscripcion->id}/documentos/validar", $payload);

        $response->assertOk();

        $this->assertDatabaseHas('validaciones_documentales', [
            'inscripcion_id' => $inscripcion->id,
            'estado' => ValidacionDocumentalState::RECHAZADA,
        ]);
    }

    /* ================================================================== */
    /*  TEST 6: Validación de observaciones (FormRequest)                 */
    /* ================================================================== */

    public function test_requiere_observacion_si_estado_es_observado_o_rechazado(): void
    {
        $inscripcion = $this->crearInscripcionConDocumentosPendientes();
        $docs = $inscripcion->documentos;

        $payload = [
            'revisiones' => [
                ['id' => $docs[0]->id, 'estado' => DocumentoState::OBSERVADO, 'observacion' => ''], // Falla: falta observación
            ]
        ];

        $response = $this->actingAs($this->admin)
            ->postJson("/api/inscripciones/{$inscripcion->id}/documentos/validar", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['revisiones.0.observacion']);
    }

    /* ================================================================== */
    /*  TEST 7: Roles (E3)                                                */
    /* ================================================================== */

    public function test_e3_usuario_sin_rol_admin_no_puede_validar(): void
    {
        $inscripcion = $this->crearInscripcionConDocumentosPendientes();

        $response = $this->actingAs($this->docente)
            ->postJson("/api/inscripciones/{$inscripcion->id}/documentos/validar", ['revisiones' => []]);

        $response->assertForbidden(); // Middleware role:admin

        $responseGet = $this->actingAs($this->docente)
            ->getJson('/api/inscripciones/pendientes-validacion');

        $responseGet->assertForbidden();
    }
}
