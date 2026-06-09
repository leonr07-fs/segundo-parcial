<?php

namespace Tests\Feature;

use App\Models\AsignacionCarrera\AsignacionCarrera;
use App\Models\AsignacionCarrera\Carrera;
use App\Models\GestionAcademica\Docente;
use App\Models\EvaluacionesResultados\Evaluacion;
use App\Models\GestionAcademica\Gestion;
use App\Models\GestionAcademica\Grupo;
use App\Models\GestionAcademica\GrupoMateria;
use App\Models\InscripcionPagos\Inscripcion;
use App\Models\GestionAcademica\Materia;
use App\Models\InscripcionPagos\Postulante;
use App\Models\EvaluacionesResultados\ResultadoCup;
use App\Models\Seguridad\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportesTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_admin_puede_consultar_catalogo_de_reportes(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/api/reportes/catalogo');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'data' => [
                    'reportes_estaticos',
                    'modulos_dinamicos',
                    'gestiones',
                    'carreras',
                    'materias',
                    'grupos',
                ],
            ]);
    }

    public function test_reporte_oficial_de_aprobados_solo_muestra_aprobados(): void
    {
        $gestion = Gestion::factory()->create(['nombre' => 'Semestre 1 2026']);
        $carrera = Carrera::factory()->create(['nombre' => 'Ingenieria en Sistemas']);

        $inscripcionAprobada = $this->crearInscripcionConPostulante($gestion, 'CUP-2026-00001', '111111');
        $inscripcionReprobada = $this->crearInscripcionConPostulante($gestion, 'CUP-2026-00002', '222222');

        ResultadoCup::create([
            'inscripcion_id' => $inscripcionAprobada->id,
            'promedio_final' => 82,
            'estado_final' => 'aprobado',
            'cerrado_en' => now(),
        ]);
        ResultadoCup::create([
            'inscripcion_id' => $inscripcionReprobada->id,
            'promedio_final' => 45,
            'estado_final' => 'reprobado',
            'cerrado_en' => now(),
        ]);
        AsignacionCarrera::create([
            'inscripcion_id' => $inscripcionAprobada->id,
            'carrera_id' => $carrera->id,
            'opcion_prioridad' => 1,
            'promedio_usado' => 82,
            'estado' => 'asignado',
            'asignado_en' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/reportes/estatico/aprobados?gestion_id={$gestion->id}");

        $response->assertOk()
            ->assertJsonPath('data.titulo', 'Lista oficial de aprobados')
            ->assertJsonPath('data.filas.0.codigo', 'CUP-2026-00001')
            ->assertJsonMissing(['codigo' => 'CUP-2026-00002']);
    }

    public function test_reporte_dinamico_respeta_columnas_y_filtro_de_gestion(): void
    {
        $gestionVigente = Gestion::factory()->create(['nombre' => 'Semestre 1 2026']);
        $gestionAnterior = Gestion::factory()->create(['nombre' => 'Semestre 2 2025', 'estado' => \App\Support\States\GestionState::CERRADA]);

        $this->crearInscripcionConPostulante($gestionVigente, 'CUP-2026-00003', '333333');
        $this->crearInscripcionConPostulante($gestionAnterior, 'CUP-2025-00004', '444444');

        $response = $this->actingAs($this->adminUser)->postJson('/api/reportes/dinamico', [
            'modulo' => 'postulantes',
            'columnas' => ['codigo', 'ci', 'gestion'],
            'filtros' => [
                'gestion_id' => $gestionVigente->id,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.columnas.0.key', 'codigo')
            ->assertJsonPath('data.filas.0.codigo', 'CUP-2026-00003')
            ->assertJsonMissing(['codigo' => 'CUP-2025-00004'])
            ->assertJsonMissing(['correo' => 'postulante333333@example.com']);
    }

    public function test_reporte_docentes_por_grupo_incluye_materia_docente_y_horarios(): void
    {
        $gestion = Gestion::factory()->create(['nombre' => 'Semestre 1 2026']);
        $grupo = Grupo::create([
            'gestion_id' => $gestion->id,
            'codigo' => 'G-01',
            'nombre' => 'Grupo 1',
            'cupo_maximo' => Grupo::CUPO_MAXIMO,
            'estado' => 'activo',
        ]);
        $materia = Materia::create([
            'codigo' => 'MAT',
            'nombre' => 'Matematica',
            'activa' => true,
        ]);
        $docente = Docente::create([
            'ci' => '987654',
            'nombres' => 'Ana',
            'apellidos' => 'Rojas',
            'correo' => 'ana.rojas@example.com',
            'telefono' => '70000000',
            'activo' => true,
        ]);
        GrupoMateria::create([
            'grupo_id' => $grupo->id,
            'materia_id' => $materia->id,
            'docente_id' => $docente->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/reportes/estatico/docentes-grupo?gestion_id={$gestion->id}");

        $response->assertOk()
            ->assertJsonPath('data.filas.0.grupo', 'G-01')
            ->assertJsonPath('data.filas.0.materia', 'Matematica')
            ->assertJsonPath('data.filas.0.docente', 'Ana Rojas');
    }

    private function crearInscripcionConPostulante(Gestion $gestion, string $codigo, string $ci): Inscripcion
    {
        $postulante = Postulante::factory()->create([
            'ci' => $ci,
            'correo' => "postulante{$ci}@example.com",
        ]);

        return Inscripcion::factory()->create([
            'postulante_id' => $postulante->id,
            'gestion_id' => $gestion->id,
            'codigo' => $codigo,
        ]);
    }
}
