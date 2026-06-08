<?php

namespace Tests\Feature;

use App\Models\Docentes\DocumentoDocente;
use App\Models\Docentes\SolicitudDocente;
use App\Models\GestionAcademica\Materia;
use App\Models\InscripcionPagos\Documento;
use App\Models\InscripcionPagos\Inscripcion;
use App\Models\Seguridad\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentoArchivoTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_puede_visualizar_documento_de_postulante(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $inscripcion = Inscripcion::factory()->create();
        $ruta = 'documentos/test/ci.pdf';
        Storage::disk('public')->put($ruta, '%PDF-1.4 test');

        $documento = Documento::factory()->create([
            'inscripcion_id' => $inscripcion->id,
            'archivo_path' => $ruta,
        ]);

        $this->actingAs($admin)
            ->get("/api/documentos-postulante/{$documento->id}/archivo")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_usuario_sin_rol_administrativo_no_puede_visualizar_documento(): void
    {
        Storage::fake('public');

        $postulanteUser = User::factory()->create(['role' => User::ROLE_POSTULANTE]);
        $documento = Documento::factory()->create([
            'archivo_path' => 'documentos/test/ci.pdf',
        ]);
        Storage::disk('public')->put('documentos/test/ci.pdf', 'contenido');

        $this->actingAs($postulanteUser)
            ->get("/api/documentos-postulante/{$documento->id}/archivo")
            ->assertForbidden();
    }

    public function test_invitado_no_puede_visualizar_documento(): void
    {
        $documento = Documento::factory()->create([
            'archivo_path' => 'documentos/test/ci.pdf',
        ]);

        $this->get("/api/documentos-postulante/{$documento->id}/archivo")
            ->assertUnauthorized();
    }

    public function test_admin_puede_visualizar_documento_de_docente(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $materia = Materia::create(['codigo' => 'MAT', 'nombre' => 'Matematica', 'activa' => true]);
        $solicitud = SolicitudDocente::create([
            'ci' => '7654321',
            'nombres' => 'Pedro',
            'apellidos' => 'Lopez',
            'correo' => 'pedro@cup.test',
            'materia_id' => $materia->id,
            'profesion' => 'Licenciado',
            'estado' => 'pendiente',
        ]);
        $ruta = 'docentes/solicitudes/test/titulo.pdf';
        Storage::disk('public')->put($ruta, '%PDF-1.4 test');

        $documento = DocumentoDocente::create([
            'solicitud_docente_id' => $solicitud->id,
            'tipo' => 'titulo_profesional',
            'archivo_path' => $ruta,
            'estado' => 'pendiente',
        ]);

        $this->actingAs($admin)
            ->get("/api/documentos-docentes/{$documento->id}/archivo")
            ->assertOk();
    }
}
