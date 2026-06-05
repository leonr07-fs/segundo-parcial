<?php

namespace Tests\Feature;

use App\Models\GestionAcademica\Aula;
use App\Models\GestionAcademica\Docente;
use App\Models\GestionAcademica\Gestion;
use App\Models\GestionAcademica\Grupo;
use App\Models\GestionAcademica\Horario;
use App\Models\InscripcionPagos\Inscripcion;
use App\Models\GestionAcademica\Materia;
use App\Models\InscripcionPagos\Postulante;
use App\Services\GruposDocentes\AsignacionAutomaticaService;
use App\Support\FicctAulas;
use App\Support\States\GestionState;
use App\Support\States\InscripcionState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AsignacionAutomaticaTest extends TestCase
{
    use RefreshDatabase;

    public function test_calcula_cantidad_de_grupos_sobre_cupo_maximo_de_setenta(): void
    {
        $this->crearParametrosBase();

        foreach ([[70, 1], [71, 2], [140, 2], [141, 3]] as $indice => [$totalInscritos, $gruposEsperados]) {
            $gestion = Gestion::create([
                'nombre' => "CUP grupos {$totalInscritos}",
                'anio' => 2026 + $indice,
                'periodo' => '1',
                'fecha_inicio' => now()->subMonth(),
                'fecha_fin' => now()->addMonths(3),
                'estado' => GestionState::INHABILITADA,
            ]);
            $this->crearInscripcionesPagadas($gestion, $totalInscritos);

            $propuesta = (new AsignacionAutomaticaService())->generarPropuesta($gestion->id);

            $this->assertSame($totalInscritos, $propuesta['total_inscripciones']);
            $this->assertSame($gruposEsperados, $propuesta['total_grupos']);
            $this->assertCount($gruposEsperados, $propuesta['grupos']);
            $this->assertTrue(collect($propuesta['grupos'])->every(
                fn (array $grupo) => $grupo['total_estudiantes'] <= 70
            ));
            $this->assertEmpty($propuesta['errores']);
        }
    }

    public function test_genera_y_confirma_grupos_horarios_y_docentes_priorizando_estudiantes(): void
    {
        $gestion = Gestion::factory()->inhabilitada()->create();

        $this->crearAulasFicct();

        Materia::create(['codigo' => 'MAT', 'nombre' => 'Matematica', 'activa' => true]);
        Materia::create(['codigo' => 'COM', 'nombre' => 'Computacion', 'activa' => true]);
        Materia::create(['codigo' => 'FIS', 'nombre' => 'Fisica', 'activa' => true]);
        Materia::create(['codigo' => 'ING', 'nombre' => 'Ingles', 'activa' => true]);

        Docente::create(['ci' => '100', 'nombres' => 'Docente', 'apellidos' => 'Matematica', 'correo' => 'mat@example.test', 'activo' => true]);
        Docente::create(['ci' => '150', 'nombres' => 'Docente', 'apellidos' => 'Computacion', 'correo' => 'com@example.test', 'activo' => true]);
        Docente::create(['ci' => '200', 'nombres' => 'Docente', 'apellidos' => 'Fisica', 'correo' => 'fis@example.test', 'activo' => true]);
        Docente::create(['ci' => '300', 'nombres' => 'Docente', 'apellidos' => 'Ingles', 'correo' => 'ing@example.test', 'activo' => true]);

        foreach (range(1, 75) as $index) {
            $postulante = Postulante::factory()->create();
            Inscripcion::factory()->create([
                'postulante_id' => $postulante->id,
                'gestion_id' => $gestion->id,
                'codigo' => sprintf('CUP-2026-%05d', $index),
                'fecha_inscripcion' => now()->addMinutes($index),
                'estado' => InscripcionState::PAGADO,
            ]);
        }

        $service = new AsignacionAutomaticaService();

        $propuesta = $service->generarPropuesta($gestion->id);

        $this->assertSame(75, $propuesta['total_inscripciones']);
        $this->assertCount(2, $propuesta['grupos']);
        $this->assertSame(70, $propuesta['grupos'][0]['total_estudiantes']);
        $this->assertSame(5, $propuesta['grupos'][1]['total_estudiantes']);
        $this->assertEmpty($propuesta['errores']);

        $resultado = $service->confirmarPropuesta($gestion->id);

        $this->assertSame(2, $resultado['grupos_creados']);
        $this->assertSame(75, $resultado['estudiantes_asignados']);

        $this->assertDatabaseHas('grupos', ['gestion_id' => $gestion->id, 'codigo' => 'G1', 'cupo_maximo' => 70]);
        $this->assertDatabaseHas('grupos', ['gestion_id' => $gestion->id, 'codigo' => 'G2', 'cupo_maximo' => 70]);
        $this->assertDatabaseCount('inscripcion_grupo', 75);

        $inglesSabado = Horario::query()
            ->where('dia_semana', 6)
            ->whereHas('grupoMateria.materia', fn ($query) => $query->where('nombre', 'like', '%Ingles%'))
            ->count();

        $this->assertSame(0, $inglesSabado);

        $sabados = Horario::query()->where('dia_semana', 6)->get();
        $this->assertNotEmpty($sabados);
        $this->assertTrue($sabados->every(fn (Horario $horario) => $horario->modalidad === 'virtual' && $horario->aula_id === null));

        $presenciales = Horario::query()->whereBetween('dia_semana', [1, 5])->get();
        $this->assertNotEmpty($presenciales);
        $this->assertTrue($presenciales->every(fn (Horario $horario) => $horario->modalidad === 'presencial' && $horario->aula_id !== null));

        $aulasComputacion = Horario::query()
            ->whereHas('grupoMateria.materia', fn ($query) => $query->where('codigo', 'COM'))
            ->with('aula')
            ->get()
            ->pluck('aula.codigo')
            ->unique()
            ->values();

        $this->assertNotEmpty($aulasComputacion);
        $this->assertTrue($aulasComputacion->every(fn (?string $codigo) => in_array($codigo, FicctAulas::LABORATORIOS_COMPUTACION, true)));

        $gruposPorDocente = Grupo::query()
            ->join('grupo_materias', 'grupo_materias.grupo_id', '=', 'grupos.id')
            ->selectRaw('grupo_materias.docente_id, count(distinct grupos.id) as total')
            ->where('grupos.gestion_id', $gestion->id)
            ->groupBy('grupo_materias.docente_id')
            ->pluck('total', 'docente_id');

        $this->assertTrue($gruposPorDocente->every(fn ($total) => $total <= 4));
    }

    public function test_con_tres_mil_postulantes_crea_cuarenta_y_tres_grupos_reutilizando_aulas_fijas(): void
    {
        $gestion = Gestion::factory()->inhabilitada()->create();
        $this->crearAulasFicct();
        $this->crearMateriasCup();
        $this->crearDocentes(60);
        $this->crearInscripcionesPagadas($gestion, 3000);

        $propuesta = (new AsignacionAutomaticaService())->generarPropuesta($gestion->id);

        $this->assertSame(3000, $propuesta['total_inscripciones']);
        $this->assertSame(43, $propuesta['total_grupos']);
        $this->assertCount(43, $propuesta['grupos']);
        $this->assertEmpty($propuesta['errores']);
        $this->assertEmpty($propuesta['advertencias']);

        $this->assertTrue(collect($propuesta['grupos'])->every(
            fn (array $grupo) => $grupo['total_estudiantes'] <= 70
                && in_array($grupo['aula_codigo'], [...FicctAulas::TEORICAS, FicctAulas::AUDITORIO], true)
        ));

        $aulasComputacion = collect($propuesta['grupos'])
            ->flatMap(fn (array $grupo) => $grupo['materias'])
            ->filter(fn (array $materia) => $materia['materia_nombre'] === 'Computacion')
            ->flatMap(fn (array $materia) => collect($materia['horarios'])->pluck('aula_codigo'))
            ->filter()
            ->unique()
            ->values();

        $this->assertNotEmpty($aulasComputacion);
        $this->assertTrue($aulasComputacion->every(fn (string $codigo) => in_array($codigo, FicctAulas::LABORATORIOS_COMPUTACION, true)));
    }

    private function crearParametrosBase(): void
    {
        $this->crearAulasFicct();
        $this->crearMateriasCup();
        $this->crearDocentes(4);
    }

    private function crearMateriasCup(): void
    {
        Materia::create(['codigo' => 'MAT', 'nombre' => 'Matematica', 'activa' => true]);
        Materia::create(['codigo' => 'COM', 'nombre' => 'Computacion', 'activa' => true]);
        Materia::create(['codigo' => 'FIS', 'nombre' => 'Fisica', 'activa' => true]);
        Materia::create(['codigo' => 'ING', 'nombre' => 'Ingles', 'activa' => true]);
    }

    private function crearDocentes(int $total): void
    {
        foreach (range(1, $total) as $index) {
            Docente::create([
                'ci' => sprintf('DOC-%03d', $index),
                'nombres' => 'Docente',
                'apellidos' => sprintf('CUP %03d', $index),
                'correo' => sprintf('docente%03d@example.test', $index),
                'activo' => true,
            ]);
        }
    }

    private function crearInscripcionesPagadas(Gestion $gestion, int $total): void
    {
        foreach (range(1, $total) as $index) {
            $postulante = Postulante::factory()->create();
            Inscripcion::create([
                'postulante_id' => $postulante->id,
                'gestion_id' => $gestion->id,
                'codigo' => sprintf('CUP-%s-%05d', $gestion->id, $index),
                'fecha_inscripcion' => now()->addMinutes($index),
                'estado' => InscripcionState::PAGADO,
            ]);
        }
    }

    private function crearAulasFicct(): void
    {
        foreach (FicctAulas::catalogo() as $aula) {
            Aula::create([
                'codigo' => $aula['codigo'],
                'nombre' => $aula['nombre'],
                'capacidad' => $aula['capacidad'],
                'ubicacion' => $aula['ubicacion'],
                'activa' => true,
            ]);
        }
    }
}
