<?php

namespace Tests\Feature;

use App\Models\GestionAcademica\Aula;
use App\Models\GestionAcademica\Docente;
use App\Models\GestionAcademica\Gestion;
use App\Models\GestionAcademica\Grupo;
use App\Models\GestionAcademica\GrupoMateria;
use App\Models\InscripcionPagos\Inscripcion;
use App\Models\GestionAcademica\Materia;
use App\Models\InscripcionPagos\Postulante;
use App\Models\Seguridad\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AsistenciaDocenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_docente_puede_ver_estudiantes_de_su_grupo_materia_para_asistencia(): void
    {
        [$user, $grupoMateria, $inscripcion] = $this->crearCargaDocente();

        $this->actingAs($user)
            ->getJson("/api/docente/asistencias/{$grupoMateria->id}?fecha=2026-06-04")
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.grupo_materia.materia', 'Matematica')
            ->assertJsonPath('data.estudiantes.0.inscripcion_id', $inscripcion->id)
            ->assertJsonPath('data.estudiantes.0.estado', 'pendiente')
            ->assertJsonPath('data.resumen.total_estudiantes', 1)
            ->assertJsonPath('data.resumen.registrados', 0)
            ->assertJsonPath('data.estudiantes.0.porcentaje_asistencia', 0);
    }

    public function test_docente_puede_registrar_asistencia_de_su_grupo(): void
    {
        [$user, $grupoMateria, $inscripcion] = $this->crearCargaDocente();

        $this->actingAs($user)
            ->postJson("/api/docente/asistencias/{$grupoMateria->id}", [
                'fecha' => '2026-06-04',
                'asistencias' => [
                    [
                        'inscripcion_id' => $inscripcion->id,
                        'estado' => 'presente',
                        'observacion' => 'Ingreso puntual.',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.total_registradas', 1)
            ->assertJsonPath('data.resumen.asistencia_tomada', true)
            ->assertJsonPath('data.resumen.porcentaje_asistencia', 100);

        $this->assertDatabaseHas('asistencias', [
            'grupo_materia_id' => $grupoMateria->id,
            'inscripcion_id' => $inscripcion->id,
            'estado' => 'presente',
            'observacion' => 'Ingreso puntual.',
        ]);
    }

    public function test_docente_no_puede_registrar_dos_veces_la_misma_asistencia_del_dia(): void
    {
        [$user, $grupoMateria, $inscripcion] = $this->crearCargaDocente();

        $payload = [
            'fecha' => '2026-06-04',
            'asistencias' => [
                [
                    'inscripcion_id' => $inscripcion->id,
                    'estado' => 'presente',
                ],
            ],
        ];

        $this->actingAs($user)
            ->postJson("/api/docente/asistencias/{$grupoMateria->id}", $payload)
            ->assertOk();

        $this->actingAs($user)
            ->postJson("/api/docente/asistencias/{$grupoMateria->id}", $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'La asistencia de este grupo y materia ya fue registrada para la fecha seleccionada.');

        $this->assertDatabaseCount('asistencias', 1);
    }

    public function test_docente_no_puede_registrar_asistencia_de_grupo_ajeno(): void
    {
        [$user] = $this->crearCargaDocente();
        [, $grupoMateriaAjeno, $inscripcionAjena] = $this->crearCargaDocente('DOC-2', 'otro.docente@example.com');

        $this->actingAs($user)
            ->postJson("/api/docente/asistencias/{$grupoMateriaAjeno->id}", [
                'fecha' => '2026-06-04',
                'asistencias' => [
                    [
                        'inscripcion_id' => $inscripcionAjena->id,
                        'estado' => 'presente',
                    ],
                ],
            ])
            ->assertForbidden();
    }

    public function test_admin_puede_consultar_reporte_de_asistencias(): void
    {
        [$user, $grupoMateria, $inscripcion] = $this->crearCargaDocente();

        $this->actingAs($user)
            ->postJson("/api/docente/asistencias/{$grupoMateria->id}", [
                'fecha' => '2026-06-04',
                'asistencias' => [
                    [
                        'inscripcion_id' => $inscripcion->id,
                        'estado' => 'ausente',
                    ],
                ],
            ])
            ->assertOk();

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->getJson('/api/admin/asistencias?fecha_desde=2026-06-01&fecha_hasta=2026-06-30')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.asistencias.data.0.estado', 'ausente')
            ->assertJsonPath('data.asistencias.data.0.materia', 'Matematica')
            ->assertJsonPath('data.resumen_docentes.0.docente', 'Ana Rojas')
            ->assertJsonPath('data.resumen_docentes.0.materias.0.materia', 'Matematica')
            ->assertJsonPath('data.resumen_docentes.0.materias.0.estudiantes.0.codigo', $inscripcion->codigo)
            ->assertJsonPath('data.resumen_docentes.0.materias.0.estudiantes.0.porcentaje_asistencia', 0);
    }

    public function test_docente_ve_porcentaje_historico_de_asistencia_por_estudiante(): void
    {
        [$user, $grupoMateria, $inscripcion] = $this->crearCargaDocente();

        $this->actingAs($user)
            ->postJson("/api/docente/asistencias/{$grupoMateria->id}", [
                'fecha' => '2026-06-04',
                'asistencias' => [
                    [
                        'inscripcion_id' => $inscripcion->id,
                        'estado' => 'presente',
                    ],
                ],
            ])
            ->assertOk();

        $this->actingAs($user)
            ->postJson("/api/docente/asistencias/{$grupoMateria->id}", [
                'fecha' => '2026-06-05',
                'asistencias' => [
                    [
                        'inscripcion_id' => $inscripcion->id,
                        'estado' => 'ausente',
                    ],
                ],
            ])
            ->assertOk();

        $this->actingAs($user)
            ->getJson("/api/docente/asistencias/{$grupoMateria->id}?fecha=2026-06-05")
            ->assertOk()
            ->assertJsonPath('data.resumen.registrados', 1)
            ->assertJsonPath('data.resumen.porcentaje_asistencia', 0)
            ->assertJsonPath('data.estudiantes.0.total_clases', 2)
            ->assertJsonPath('data.estudiantes.0.asistencias_validas', 1)
            ->assertJsonPath('data.estudiantes.0.ausencias', 1)
            ->assertJsonPath('data.estudiantes.0.porcentaje_asistencia', 50);
    }

    private function crearCargaDocente(string $ci = 'DOC-1', string $correo = 'docente@example.com'): array
    {
        $user = User::factory()->create([
            'role' => User::ROLE_DOCENTE,
            'numero_registro' => $ci,
            'email' => $correo,
        ]);
        $docente = Docente::create([
            'ci' => $ci,
            'nombres' => 'Ana',
            'apellidos' => 'Rojas',
            'correo' => $correo,
            'activo' => true,
        ]);
        $gestion = Gestion::factory()->create(['nombre' => "CUP 2026 {$ci}"]);
        $aula = Aula::create(['codigo' => "A-{$ci}", 'nombre' => 'Aula 1', 'capacidad' => 70]);
        $grupo = Grupo::create(['gestion_id' => $gestion->id, 'codigo' => "G-{$ci}", 'aula_id' => $aula->id]);
        $materia = Materia::create(['codigo' => "MAT-{$ci}", 'nombre' => 'Matematica']);
        $grupo->materias()->attach($materia->id, ['docente_id' => $docente->id]);
        $grupoMateria = GrupoMateria::where('grupo_id', $grupo->id)->where('materia_id', $materia->id)->firstOrFail();
        $postulante = Postulante::factory()->create();
        $inscripcion = Inscripcion::create([
            'postulante_id' => $postulante->id,
            'gestion_id' => $gestion->id,
            'codigo' => "CUP-2026-{$postulante->id}",
            'fecha_inscripcion' => now(),
            'estado' => 'inscrito',
        ]);
        $grupo->inscripciones()->attach($inscripcion->id);

        return [$user, $grupoMateria, $inscripcion];
    }
}
