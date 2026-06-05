<?php

namespace Tests\Feature;

use App\Models\InscripcionPagos\Documento;
use App\Models\InscripcionPagos\Inscripcion;
use App\Models\InscripcionPagos\Postulante;
use App\Support\States\DocumentoState;
use App\Support\States\InscripcionState;
use App\Support\States\ValidacionDocumentalState;
use App\Services\PortalPostulante\PrevalidacionDocumentalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Cu03PrevalidacionOcrTest extends TestCase
{
    use RefreshDatabase;

    private PrevalidacionDocumentalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PrevalidacionDocumentalService::class);
    }

    /**
     * Test 1: Auto-aprobación exitosa cuando los documentos están al 100% correctos.
     */
    public function test_ocr_auto_aprueba_inscripcion_cuando_todo_coincide(): void
    {
        // Creamos un postulante normal (sin triggers de error/duda)
        $postulante = Postulante::factory()->create([
            'ci' => '1234567',
            'nombres' => 'Juan Carlos',
            'apellido_paterno' => 'Perez',
            'correo' => 'juan.perez@test.com',
        ]);

        $inscripcion = Inscripcion::factory()->create([
            'postulante_id' => $postulante->id,
            'estado' => InscripcionState::PREPOSTULADO,
        ]);

        // Creamos dos documentos obligatorios válidos en formato PDF (para evitar validaciones de tamaño de imagen)
        $docCi = Documento::factory()->create([
            'inscripcion_id' => $inscripcion->id,
            'tipo' => 'carnet_identidad',
            'archivo_path' => 'documentos/2026/test/ci.pdf',
            'estado' => DocumentoState::PENDIENTE,
        ]);

        $docLibreta = Documento::factory()->create([
            'inscripcion_id' => $inscripcion->id,
            'tipo' => 'libreta_digitalizada',
            'archivo_path' => 'documentos/2026/test/libreta.pdf',
            'estado' => DocumentoState::PENDIENTE,
        ]);

        // Aseguramos que los archivos físicos simulados "existan" para pasar las validaciones básicas de tamaño/extensión
        \Illuminate\Support\Facades\Storage::disk('public')->put('documentos/2026/test/ci.pdf', str_repeat('a', 1024));
        \Illuminate\Support\Facades\Storage::disk('public')->put('documentos/2026/test/libreta.pdf', str_repeat('a', 1024));

        // Ejecutamos la prevalidación
        $resultado = $this->service->prevalidarInscripcion($inscripcion);

        // Verificaciones
        $this->assertEquals(PrevalidacionDocumentalService::ESTADO_OK, $resultado['estado']);
        $this->assertEquals(0, $resultado['criticos']);
        $this->assertEquals(0, $resultado['observados']);
        $this->assertEquals(2, $resultado['ok']);

        // Verificamos que los documentos se hayan auto-aprobado
        $this->assertDatabaseHas('documentos', [
            'id' => $docCi->id,
            'estado' => DocumentoState::APROBADO,
        ]);

        $this->assertDatabaseHas('documentos', [
            'id' => $docLibreta->id,
            'estado' => DocumentoState::APROBADO,
        ]);

        // Verificamos que la validación documental global esté APROBADA
        $this->assertDatabaseHas('validaciones_documentales', [
            'inscripcion_id' => $inscripcion->id,
            'estado' => ValidacionDocumentalState::APROBADA,
            'validado_por' => null, // Aprobación por sistema (IA/OCR)
        ]);

        // Verificamos que la inscripción pase directamente a documentos_aprobados
        $this->assertDatabaseHas('inscripciones', [
            'id' => $inscripcion->id,
            'estado' => InscripcionState::DOCUMENTOS_APROBADOS,
        ]);
    }

    /**
     * Test 2: Rango de Duda (50%-89%). Se deriva a revisión manual del administrador.
     */
    public function test_ocr_deriva_a_revision_manual_si_hay_duda_menor(): void
    {
        // Creamos un postulante con trigger de duda en nombres
        $postulante = Postulante::factory()->create([
            'ci' => '1234567',
            'nombres' => 'Juan Carlos Duda',
            'apellido_paterno' => 'Perez',
            'correo' => 'juan.perez.duda@test.com',
        ]);

        $inscripcion = Inscripcion::factory()->create([
            'postulante_id' => $postulante->id,
            'estado' => InscripcionState::PREPOSTULADO,
        ]);

        $docCi = Documento::factory()->create([
            'inscripcion_id' => $inscripcion->id,
            'tipo' => 'carnet_identidad',
            'archivo_path' => 'documentos/2026/test/ci.pdf',
            'estado' => DocumentoState::PENDIENTE,
        ]);

        $docLibreta = Documento::factory()->create([
            'inscripcion_id' => $inscripcion->id,
            'tipo' => 'libreta_digitalizada',
            'archivo_path' => 'documentos/2026/test/libreta.pdf',
            'estado' => DocumentoState::PENDIENTE,
        ]);

        \Illuminate\Support\Facades\Storage::disk('public')->put('documentos/2026/test/ci.pdf', str_repeat('a', 1024));
        \Illuminate\Support\Facades\Storage::disk('public')->put('documentos/2026/test/libreta.pdf', str_repeat('a', 1024));

        $resultado = $this->service->prevalidarInscripcion($inscripcion);

        // El estado global de prevalidación debe ser observado
        $this->assertEquals(PrevalidacionDocumentalService::ESTADO_OBSERVADO, $resultado['estado']);
        $this->assertEquals(1, $resultado['observados']);

        // No se debe haber auto-aprobado el documento de CI
        $this->assertDatabaseHas('documentos', [
            'id' => $docCi->id,
            'estado' => DocumentoState::PENDIENTE, // Sigue pendiente de revisión manual
        ]);

        // Verificamos que se hayan cargado las observaciones de duda del OCR
        $docFresh = $docCi->fresh();
        $this->assertNotNull($docFresh->prevalidacion_observaciones);
        $this->assertTrue(collect($docFresh->prevalidacion_observaciones)->contains(function ($obs) {
            return str_contains($obs, 'OCR Duda: Confianza de coincidencia facial del 72%');
        }));

        // No debe existir validación documental global aprobada
        $this->assertDatabaseMissing('validaciones_documentales', [
            'inscripcion_id' => $inscripcion->id,
        ]);

        // La inscripción NO debe haber cambiado de estado a documentos_aprobados
        $this->assertDatabaseHas('inscripciones', [
            'id' => $inscripcion->id,
            'estado' => InscripcionState::PREPOSTULADO,
        ]);
    }

    /**
     * Test 3: Falla Crítica (<50%). El documento se marca como crítico y queda en cola.
     */
    public function test_ocr_marca_critico_si_hay_discrepancia_de_ci(): void
    {
        // Postulante con CI simulador de error
        $postulante = Postulante::factory()->create([
            'ci' => '9999999', // Discrepancia crítica
            'nombres' => 'Juan Carlos',
            'apellido_paterno' => 'Perez',
            'correo' => 'ocr_error_ci@test.com',
        ]);

        $inscripcion = Inscripcion::factory()->create([
            'postulante_id' => $postulante->id,
            'estado' => InscripcionState::PREPOSTULADO,
        ]);

        $docCi = Documento::factory()->create([
            'inscripcion_id' => $inscripcion->id,
            'tipo' => 'carnet_identidad',
            'archivo_path' => 'documentos/2026/test/ci.pdf',
            'estado' => DocumentoState::PENDIENTE,
        ]);

        \Illuminate\Support\Facades\Storage::disk('public')->put('documentos/2026/test/ci.pdf', str_repeat('a', 1024));

        $resultado = $this->service->prevalidarInscripcion($inscripcion);

        // Verificaciones
        $this->assertEquals(PrevalidacionDocumentalService::ESTADO_CRITICO, $resultado['estado']);
        $this->assertEquals(1, $resultado['criticos']);

        $docFresh = $docCi->fresh();
        $this->assertEquals(PrevalidacionDocumentalService::ESTADO_CRITICO, $docFresh->prevalidacion_estado);
        $this->assertTrue(collect($docFresh->prevalidacion_observaciones)->contains(function ($obs) {
            return str_contains($obs, 'OCR Discrepancia: El número de carnet extraído del documento');
        }));
    }
}
