<?php

namespace Tests\Feature;

use App\Models\Docentes\DocumentoDocente;
use App\Models\GestionAcademica\Materia;
use App\Models\Docentes\SolicitudDocente;
use App\Models\Seguridad\User;
use App\Services\GruposDocentes\SolicitudDocenteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SolicitudDocenteTest extends TestCase
{
    use RefreshDatabase;

    private function payloadDocente(array $overrides = []): array
    {
        return array_merge([
            'ci' => '1234567',
            'nombres' => 'Ana',
            'apellidos' => 'Rojas',
            'correo' => 'ana@example.test',
            'telefono' => '70000001',
            'materia_id' => Materia::create(['codigo' => 'MAT', 'nombre' => 'Matematica', 'activa' => true])->id,
            'profesion' => 'Ingeniera de Sistemas',
            'documentos' => [
                'ci' => UploadedFile::fake()->create('ci.pdf', 120, 'application/pdf'),
                'titulo_profesional' => UploadedFile::fake()->create('titulo.pdf', 120, 'application/pdf'),
                'diplomado' => UploadedFile::fake()->create('diplomado.pdf', 120, 'application/pdf'),
                'maestria' => UploadedFile::fake()->create('maestria.pdf', 120, 'application/pdf'),
                'cv' => UploadedFile::fake()->create('cv.pdf', 120, 'application/pdf'),
            ],
        ], $overrides);
    }

    public function test_docente_puede_postular_con_requisitos_obligatorios(): void
    {
        Storage::fake('public');
        $materia = Materia::create(['codigo' => 'MAT', 'nombre' => 'Matematica', 'activa' => true]);

        $service = new SolicitudDocenteService();

        $solicitud = $service->registrar([
            'ci' => '1234567',
            'nombres' => 'Ana',
            'apellidos' => 'Rojas',
            'correo' => 'ana@example.test',
            'telefono' => '70000001',
            'materia_id' => $materia->id,
            'profesion' => 'Ingeniera de Sistemas',
            'documentos' => [
                'ci' => UploadedFile::fake()->create('ci.pdf', 120, 'application/pdf'),
                'titulo_profesional' => UploadedFile::fake()->create('titulo.pdf', 120, 'application/pdf'),
                'diplomado' => UploadedFile::fake()->create('diplomado.pdf', 120, 'application/pdf'),
                'maestria' => UploadedFile::fake()->create('maestria.pdf', 120, 'application/pdf'),
                'cv' => UploadedFile::fake()->create('cv.pdf', 120, 'application/pdf'),
            ],
        ]);

        $this->assertSame('pendiente', $solicitud->estado);
        $this->assertDatabaseHas('solicitudes_docentes', [
            'ci' => '1234567',
            'correo' => 'ana@example.test',
            'materia_id' => $materia->id,
            'estado' => 'pendiente',
        ]);
        $this->assertDatabaseCount('documentos_docentes', 5);
    }

    public function test_rechaza_solicitud_docente_con_correo_ya_registrado_y_mensaje_humano(): void
    {
        Storage::fake('public');
        SolicitudDocente::create([
            'ci' => '1111111',
            'nombres' => 'Docente',
            'apellidos' => 'Registrado',
            'correo' => 'docente@example.test',
            'materia_id' => Materia::create(['codigo' => 'FIS', 'nombre' => 'Fisica', 'activa' => true])->id,
            'profesion' => 'Ingeniero de Sistemas',
            'estado' => 'pendiente',
        ]);

        $response = $this->postJson('/api/solicitudes-docentes', $this->payloadDocente([
            'ci' => '2222222',
            'correo' => 'docente@example.test',
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Revise los datos ingresados.')
            ->assertJsonPath('errors.correo.0', 'Este correo ya fue registrado en una solicitud docente.');

        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
    }

    public function test_no_aprueba_si_maestria_no_esta_aprobada(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $materia = Materia::create(['codigo' => 'FIS', 'nombre' => 'Fisica', 'activa' => true]);
        $solicitud = SolicitudDocente::create([
            'ci' => '7654321',
            'nombres' => 'Luis',
            'apellidos' => 'Mendez',
            'correo' => 'luis@example.test',
            'telefono' => '70000002',
            'materia_id' => $materia->id,
            'profesion' => 'Ingeniero Civil',
            'estado' => 'pendiente',
        ]);

        foreach (['ci', 'titulo_profesional', 'diplomado', 'cv'] as $tipo) {
            DocumentoDocente::create([
                'solicitud_docente_id' => $solicitud->id,
                'tipo' => $tipo,
                'archivo_path' => "docentes/{$tipo}.pdf",
                'estado' => 'aprobado',
            ]);
        }

        DocumentoDocente::create([
            'solicitud_docente_id' => $solicitud->id,
            'tipo' => 'maestria',
            'archivo_path' => 'docentes/maestria.pdf',
            'estado' => 'pendiente',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('maestria');

        (new SolicitudDocenteService())->aprobar($solicitud->id, $admin);
    }

    public function test_aprobar_crea_docente_usuario_y_marca_solicitud(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $materia = Materia::create(['codigo' => 'ING', 'nombre' => 'Ingles', 'activa' => true]);
        $solicitud = SolicitudDocente::create([
            'ci' => '1122334',
            'nombres' => 'Maria',
            'apellidos' => 'Quispe',
            'correo' => 'maria@example.test',
            'telefono' => '70000003',
            'materia_id' => $materia->id,
            'profesion' => 'Ingeniera Informatica',
            'estado' => 'pendiente',
        ]);

        foreach (['ci', 'titulo_profesional', 'diplomado', 'maestria', 'cv'] as $tipo) {
            DocumentoDocente::create([
                'solicitud_docente_id' => $solicitud->id,
                'tipo' => $tipo,
                'archivo_path' => "docentes/{$tipo}.pdf",
                'estado' => 'aprobado',
            ]);
        }

        $resultado = (new SolicitudDocenteService())->aprobar($solicitud->id, $admin);

        $this->assertDatabaseHas('docentes', [
            'ci' => '1122334',
            'correo' => 'maria@example.test',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'maria@example.test',
            'role' => User::ROLE_DOCENTE,
            'numero_registro' => '1122334',
        ]);
        $this->assertSame('1122334', $resultado['credenciales']['codigo_docente']);
        $this->assertSame('1122334', $resultado['credenciales']['password_temporal']);
        $this->assertDatabaseHas('solicitudes_docentes', [
            'id' => $solicitud->id,
            'estado' => 'aprobada',
            'revisado_por' => $admin->id,
        ]);
    }

    public function test_aprobar_ingles_permite_licenciado_o_profesor(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $materia = Materia::create(['codigo' => 'ING', 'nombre' => 'Ingles', 'activa' => true]);
        $solicitud = SolicitudDocente::create([
            'ci' => '1122339',
            'nombres' => 'Juan',
            'apellidos' => 'Perez',
            'correo' => 'juan@example.test',
            'telefono' => '70000009',
            'materia_id' => $materia->id,
            'profesion' => 'Licenciado en Filologia',
            'estado' => 'pendiente',
        ]);

        foreach (['ci', 'titulo_profesional', 'diplomado', 'maestria', 'cv'] as $tipo) {
            DocumentoDocente::create([
                'solicitud_docente_id' => $solicitud->id,
                'tipo' => $tipo,
                'archivo_path' => "docentes/{$tipo}.pdf",
                'estado' => 'aprobado',
            ]);
        }

        $resultado = (new SolicitudDocenteService())->aprobar($solicitud->id, $admin);

        $this->assertDatabaseHas('docentes', [
            'ci' => '1122339',
            'correo' => 'juan@example.test',
        ]);
        $this->assertDatabaseHas('solicitudes_docentes', [
            'id' => $solicitud->id,
            'estado' => 'aprobada',
        ]);
    }

    public function test_rechaza_profesor_por_ser_rango_menor(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $materia = Materia::create(['codigo' => 'ING', 'nombre' => 'Ingles', 'activa' => true]);
        $solicitud = SolicitudDocente::create([
            'ci' => '1122340',
            'nombres' => 'Jose',
            'correo' => 'jose@example.test',
            'materia_id' => $materia->id,
            'profesion' => 'Profesorado en Idioma Ingles',
            'estado' => 'pendiente',
        ]);

        foreach (['ci', 'titulo_profesional', 'diplomado', 'maestria', 'cv'] as $tipo) {
            DocumentoDocente::create(['solicitud_docente_id' => $solicitud->id, 'tipo' => $tipo, 'archivo_path' => "doc.pdf", 'estado' => 'aprobado']);
        }

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('minimo el grado');

        (new SolicitudDocenteService())->aprobar($solicitud->id, $admin);
    }

    public function test_rechaza_carrera_economica(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $materia = Materia::create(['codigo' => 'MAT', 'nombre' => 'Matematicas', 'activa' => true]);
        $solicitud = SolicitudDocente::create([
            'ci' => '1122341',
            'nombres' => 'Luis',
            'correo' => 'luis2@example.test',
            'materia_id' => $materia->id,
            'profesion' => 'Ingeniero Comercial',
            'estado' => 'pendiente',
        ]);

        foreach (['ci', 'titulo_profesional', 'diplomado', 'maestria', 'cv'] as $tipo) {
            DocumentoDocente::create(['solicitud_docente_id' => $solicitud->id, 'tipo' => $tipo, 'archivo_path' => "doc.pdf", 'estado' => 'aprobado']);
        }

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('area economica o con rango de profesor');

        (new SolicitudDocenteService())->aprobar($solicitud->id, $admin);
    }
}
