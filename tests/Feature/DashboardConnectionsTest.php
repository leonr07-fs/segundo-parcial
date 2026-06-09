<?php

namespace Tests\Feature;

use App\Models\AsignacionCarrera\AsignacionCarrera;
use App\Models\GestionAcademica\Aula;
use App\Models\AsignacionCarrera\Carrera;
use App\Models\GestionAcademica\Docente;
use App\Models\EvaluacionesResultados\Evaluacion;
use App\Models\GestionAcademica\Gestion;
use App\Models\GestionAcademica\Grupo;
use App\Models\GestionAcademica\Horario;
use App\Models\InscripcionPagos\Inscripcion;
use App\Models\GestionAcademica\Materia;
use App\Models\InscripcionPagos\Postulante;
use App\Models\Seguridad\User;
use App\Support\States\GestionState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardConnectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_puede_abrir_vista_de_consulta_de_notas(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get('/admin/notas')
            ->assertOk();
    }

    public function test_admin_dashboard_expone_resumen_del_proceso(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $gestionAnterior = Gestion::factory()->create(['nombre' => 'Semestre 1 2026', 'estado' => GestionState::CERRADA]);
        $gestion = Gestion::factory()->inscripcion()->create(['nombre' => 'Semestre 2 2026']);
        $postulanteHistorico = Postulante::factory()->create();
        $inscripcionHistorica = Inscripcion::create([
            'postulante_id' => $postulanteHistorico->id,
            'gestion_id' => $gestionAnterior->id,
            'codigo' => 'CUP-2026-00000',
            'fecha_inscripcion' => now()->subMonth(),
            'estado' => 'inscrito',
        ]);
        $postulante = Postulante::factory()->create();
        $inscripcion = Inscripcion::create([
            'postulante_id' => $postulante->id,
            'gestion_id' => $gestion->id,
            'codigo' => 'CUP-2026-00001',
            'fecha_inscripcion' => now(),
            'estado' => 'prepostulado',
        ]);
        $materia = Materia::create(['codigo' => 'MAT-DASH', 'nombre' => 'Dashboard']);
        $grupoAnterior = Grupo::create(['gestion_id' => $gestionAnterior->id, 'codigo' => 'OLD']);
        $grupoAnterior->materias()->attach($materia->id);
        $grupoMateriaAnterior = \App\Models\GestionAcademica\GrupoMateria::where('grupo_id', $grupoAnterior->id)->firstOrFail();
        Evaluacion::create([
            'inscripcion_id' => $inscripcionHistorica->id,
            'grupo_materia_id' => $grupoMateriaAnterior->id,
            'estado' => 'pendiente',
        ]);
        $grupoActual = Grupo::create(['gestion_id' => $gestion->id, 'codigo' => 'ACT']);
        $grupoActual->materias()->attach($materia->id);
        $grupoMateriaActual = \App\Models\GestionAcademica\GrupoMateria::where('grupo_id', $grupoActual->id)->firstOrFail();
        Evaluacion::create([
            'inscripcion_id' => $inscripcion->id,
            'grupo_materia_id' => $grupoMateriaActual->id,
            'estado' => 'pendiente',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.gestion_activa.nombre', 'Semestre 2 2026')
            ->assertJsonPath('data.resumen.postulantes', 1)
            ->assertJsonPath('data.resumen.inscripciones', 1)
            ->assertJsonPath('data.resumen.evaluaciones', 1)
            ->assertJsonPath('data.resumen.evaluaciones_pendientes', 1);
    }

    public function test_admin_postulantes_sin_filtro_solo_muestra_gestion_vigente(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $gestionAnterior = Gestion::factory()->create(['estado' => GestionState::CERRADA]);
        $gestionVigente = Gestion::factory()->inscripcion()->create();
        $postulanteHistorico = Postulante::factory()->create(['ci' => 'OLD-1']);
        $postulanteVigente = Postulante::factory()->create(['ci' => 'NEW-1']);

        Inscripcion::create([
            'postulante_id' => $postulanteHistorico->id,
            'gestion_id' => $gestionAnterior->id,
            'codigo' => 'CUP-2025-00001',
            'fecha_inscripcion' => now()->subMonth(),
            'estado' => 'inscrito',
        ]);
        Inscripcion::create([
            'postulante_id' => $postulanteVigente->id,
            'gestion_id' => $gestionVigente->id,
            'codigo' => 'CUP-2026-00001',
            'fecha_inscripcion' => now(),
            'estado' => 'prepostulado',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/admin/postulantes')
            ->assertOk()
            ->assertJsonCount(1, 'data.postulantes.data')
            ->assertJsonPath('data.postulantes.data.0.ci', 'NEW-1');
    }

    public function test_docente_dashboard_expone_carga_academica_asignada(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_DOCENTE,
            'numero_registro' => 'DOC-1',
            'email' => 'docente@cup.test',
        ]);
        $docente = Docente::create([
            'ci' => 'DOC-1',
            'nombres' => 'Ana',
            'apellidos' => 'Rojas',
            'correo' => 'docente@cup.test',
        ]);
        $gestionAnterior = Gestion::factory()->create(['nombre' => 'CUP 2025', 'estado' => GestionState::CERRADA]);
        $gestion = Gestion::factory()->inscripcion()->create(['nombre' => 'CUP 2026']);
        $aula = Aula::create(['codigo' => 'A-1', 'nombre' => 'Aula 1', 'capacidad' => 30]);
        $grupoAnterior = Grupo::create(['gestion_id' => $gestionAnterior->id, 'codigo' => 'G0', 'aula_id' => $aula->id]);
        $materiaAnterior = Materia::create(['codigo' => 'OLD-100', 'nombre' => 'Historica']);
        $grupoAnterior->materias()->attach($materiaAnterior->id, ['docente_id' => $docente->id]);
        $grupo = Grupo::create(['gestion_id' => $gestion->id, 'codigo' => 'G1', 'aula_id' => $aula->id]);
        $materia = Materia::create(['codigo' => 'MAT-100', 'nombre' => 'Matematicas']);
        $grupo->materias()->attach($materia->id, ['docente_id' => $docente->id]);

        $this->actingAs($user)
            ->getJson('/api/docente/carga')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.docente.ci', 'DOC-1')
            ->assertJsonCount(1, 'data.carga')
            ->assertJsonPath('data.carga.0.materia', 'Matematicas')
            ->assertJsonPath('data.carga.0.grupo', 'G1');
    }

    public function test_postulante_dashboard_expone_informacion_academica(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_POSTULANTE,
            'numero_registro' => '1234567',
        ]);
        $postulante = Postulante::factory()->create(['ci' => '1234567']);
        $gestion = Gestion::factory()->create(['nombre' => 'CUP 2026']);
        $grupo = Grupo::create(['gestion_id' => $gestion->id, 'codigo' => 'G1']);
        $materia = Materia::create(['codigo' => 'COM-100', 'nombre' => 'Computacion']);
        $grupo->materias()->attach($materia->id);
        $grupoMateria = \App\Models\GestionAcademica\GrupoMateria::where('grupo_id', $grupo->id)
            ->where('materia_id', $materia->id)
            ->firstOrFail();
        $inscripcion = Inscripcion::create([
            'postulante_id' => $postulante->id,
            'gestion_id' => $gestion->id,
            'codigo' => 'CUP-2026-00002',
            'fecha_inscripcion' => now(),
            'estado' => 'prepostulado',
        ]);
        $grupo->inscripciones()->attach($inscripcion->id);
        Evaluacion::create([
            'inscripcion_id' => $inscripcion->id,
            'grupo_materia_id' => $grupoMateria->id,
            'examen_1' => 80,
            'promedio' => 80,
            'estado' => 'aprobado',
        ]);
        $carrera = Carrera::create(['codigo' => 'INF', 'nombre' => 'Informatica']);
        AsignacionCarrera::create([
            'inscripcion_id' => $inscripcion->id,
            'carrera_id' => $carrera->id,
            'estado' => 'asignado',
        ]);

        $this->actingAs($user)
            ->getJson('/api/postulante/academico')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.inscripcion.gestion', 'CUP 2026')
            ->assertJsonPath('data.grupo.codigo', 'G1')
            ->assertJsonPath('data.evaluaciones.0.materia', 'Computacion')
            ->assertJsonPath('data.asignacion_carrera.carrera', 'Informatica');
    }

    public function test_postulante_dashboard_muestra_habilitacion_y_bloquea_siguiente_examen_si_reprueba_una_materia_cup(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_POSTULANTE,
            'numero_registro' => '7654321',
        ]);
        $postulante = Postulante::factory()->create(['ci' => '7654321']);
        $gestion = Gestion::factory()->create(['nombre' => 'CUP 2026']);
        $aula = Aula::create(['codigo' => '236-12', 'nombre' => 'Aula 236-12', 'capacidad' => 40]);
        $grupo = Grupo::create(['gestion_id' => $gestion->id, 'codigo' => 'A', 'aula_id' => $aula->id]);
        $inscripcion = Inscripcion::create([
            'postulante_id' => $postulante->id,
            'gestion_id' => $gestion->id,
            'codigo' => 'CUP-2026-00003',
            'fecha_inscripcion' => now(),
            'estado' => 'inscrito',
        ]);
        $grupo->inscripciones()->attach($inscripcion->id);

        foreach (['MAT' => 'Matematica', 'COM' => 'Computacion', 'ING' => 'Ingles', 'FIS' => 'Fisica'] as $codigo => $nombre) {
            $materia = Materia::create(['codigo' => $codigo, 'nombre' => $nombre]);
            $grupo->materias()->attach($materia->id);
            $grupoMateria = \App\Models\GestionAcademica\GrupoMateria::where('grupo_id', $grupo->id)
                ->where('materia_id', $materia->id)
                ->firstOrFail();

            Horario::create([
                'grupo_materia_id' => $grupoMateria->id,
                'aula_id' => $aula->id,
                'dia_semana' => 1,
                'hora_inicio' => '08:00',
                'hora_fin' => '10:00',
                'modalidad' => 'PRESENCIAL',
            ]);

            Evaluacion::create([
                'inscripcion_id' => $inscripcion->id,
                'grupo_materia_id' => $grupoMateria->id,
                'examen_1' => $codigo === 'FIS' ? 45 : 75,
                'promedio' => $codigo === 'FIS' ? 45 : 75,
                'estado' => $codigo === 'FIS' ? 'reprobado' : 'aprobado',
            ]);
        }

        $this->actingAs($user)
            ->getJson('/api/postulante/academico')
            ->assertOk()
            ->assertJsonPath('data.examen_cup.estado', 'no_habilitado')
            ->assertJsonPath('data.examen_cup.siguiente_examen', null)
            ->assertJsonPath('data.examen_cup.motivo', 'Reprobo Fisica en el promedio final.')
            ->assertJsonCount(4, 'data.materias_cup')
            ->assertJsonPath('data.materias_cup.3.materia', 'Fisica')
            ->assertJsonPath('data.materias_cup.3.habilitacion', 'no_habilitado');
    }
}
