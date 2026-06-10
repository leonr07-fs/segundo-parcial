<?php

namespace App\Services\GruposDocentes;

use App\Models\GestionAcademica\Aula;
use App\Models\GestionAcademica\Docente;
use App\Models\GestionAcademica\Gestion;
use App\Models\GestionAcademica\Grupo;
use App\Models\GestionAcademica\GrupoMateria;
use App\Models\GestionAcademica\Horario;
use App\Models\InscripcionPagos\Inscripcion;
use App\Models\GestionAcademica\Materia;
use App\Support\FicctAulas;
use App\Support\States\GestionState;
use App\Support\States\InscripcionState;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * CU13 - Asignar grupos, materias, docentes, aulas y horarios
 *
 * Participantes del CU13 (Diagrama de Secuencia):
 * - Control: AsignacionAutomaticaController
 * - Control: AsignacionAutomaticaService (Actual)
 * - Entity: Grupo
 */
class AsignacionAutomaticaService
{
    private const MODALIDAD_PRESENCIAL = 'presencial';
    private const MODALIDAD_VIRTUAL = 'virtual';

    /**
     * @return array<string, mixed>
     */
    public function generarPropuesta(int $gestionId): array
    {
        $gestion = Gestion::findOrFail($gestionId);
        $errores = [];
        $advertencias = [];

        if ($gestion->estado !== GestionState::INHABILITADA) {
            $errores[] = 'La asignacion automatica solo se puede ejecutar cuando la gestion esta inhabilitada para nuevas inscripciones.';
        }

        if (Grupo::where('gestion_id', $gestion->id)->where('estado', 'asignado')->exists()) {
            $errores[] = 'Esta gestion ya tiene grupos automaticos confirmados.';
        }

        $inscripciones = $this->inscripcionesAsignables($gestion->id);
        $materias = $this->materiasCupActivas();
        $aulas = Aula::where('activa', true)
            ->whereIn('codigo', FicctAulas::TODAS)
            ->orderBy('codigo')
            ->get();
        $docentes = Docente::where('activo', true)->orderBy('id')->get();

        if ($inscripciones->isEmpty()) {
            $errores[] = 'No hay inscripciones pagadas o inscritas para asignar.';
        }

        if ($materias->isEmpty()) {
            $errores[] = 'No hay materias activas para generar horarios.';
        }

        if ($aulas->isEmpty()) {
            $errores[] = 'No hay aulas activas para clases presenciales.';
        }

        if ($docentes->isEmpty()) {
            $errores[] = 'No hay docentes activos para asignar materias.';
        }

        $cantidadGrupos = (int) ceil(max(1, $inscripciones->count()) / Grupo::CUPO_MAXIMO);

        $grupos = $errores === []
            ? $this->armarGrupos($inscripciones, $materias, $aulas, $docentes, $errores, $advertencias)
            : [];

        return [
            'gestion' => [
                'id' => $gestion->id,
                'nombre' => $gestion->nombre,
                'estado' => $gestion->estado,
            ],
            'total_inscripciones' => $inscripciones->count(),
            'total_grupos' => count($grupos),
            'grupos' => $grupos,
            'errores' => $errores,
            'advertencias' => $advertencias,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function confirmarPropuesta(int $gestionId): array
    {
        return DB::transaction(function () use ($gestionId) {
            $propuesta = $this->generarPropuesta($gestionId);

            if ($propuesta['errores'] !== []) {
                throw new RuntimeException(implode(' ', $propuesta['errores']));
            }

            $gruposCreados = 0;
            $estudiantesAsignados = 0;
            $horariosCreados = 0;

            foreach ($propuesta['grupos'] as $grupoPropuesto) {
                $grupo = Grupo::create([
                    'gestion_id' => $gestionId,
                    'codigo' => $grupoPropuesto['codigo'],
                    'nombre' => $grupoPropuesto['nombre'],
                    'cupo_maximo' => Grupo::CUPO_MAXIMO,
                    'aula_id' => $grupoPropuesto['aula_id'],
                    'estado' => 'asignado',
                ]);

                $gruposCreados++;

                foreach ($grupoPropuesto['inscripcion_ids'] as $inscripcionId) {
                    $grupo->inscripciones()->attach($inscripcionId, [
                        'estado' => 'asignado',
                        'asignado_en' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $estudiantesAsignados++;
                }

                foreach ($grupoPropuesto['materias'] as $materiaPropuesta) {
                    $grupoMateria = GrupoMateria::create([
                        'grupo_id' => $grupo->id,
                        'materia_id' => $materiaPropuesta['materia_id'],
                        'docente_id' => $materiaPropuesta['docente_id'],
                    ]);

                    foreach ($materiaPropuesta['horarios'] as $horarioPropuesto) {
                        $grupoMateria->horarios()->create([
                            'aula_id' => $horarioPropuesto['aula_id'],
                            'dia_semana' => $horarioPropuesto['dia_semana'],
                            'hora_inicio' => $horarioPropuesto['hora_inicio'],
                            'hora_fin' => $horarioPropuesto['hora_fin'],
                            'modalidad' => $horarioPropuesto['modalidad'],
                        ]);
                        $horariosCreados++;
                    }
                }
            }

            Gestion::whereKey($gestionId)->update(['estado' => GestionState::INHABILITADA]);

            return [
                'grupos_creados' => $gruposCreados,
                'estudiantes_asignados' => $estudiantesAsignados,
                'horarios_creados' => $horariosCreados,
            ];
        });
    }

    /**
     * [CU13] Asignar rezagados a los grupos ya existentes.
     * Solo funciona si existen grupos creados.
     * @return array<string, int>
     */
    public function asignarRezagados(int $gestionId): array
    {
        return DB::transaction(function () use ($gestionId) {
            $gruposExistentes = Grupo::where('gestion_id', $gestionId)
                ->where('estado', 'asignado')
                ->withCount('inscripciones')
                ->orderBy('codigo')
                ->get();

            if ($gruposExistentes->isEmpty()) {
                throw new RuntimeException('No existen grupos generados para esta gestion. Debe generar la asignacion automatica primero.');
            }

            $inscripcionesRezagadas = $this->inscripcionesAsignables($gestionId);

            if ($inscripcionesRezagadas->isEmpty()) {
                throw new RuntimeException('No hay postulantes rezagados para asignar (no hay inscripciones pagadas/inscritas sin grupo).');
            }

            $estudiantesAsignados = 0;
            $sinCupo = 0;

            foreach ($inscripcionesRezagadas as $inscripcion) {
                $asignado = false;
                foreach ($gruposExistentes as $grupo) {
                    if ($grupo->inscripciones_count < Grupo::CUPO_MAXIMO) {
                        $grupo->inscripciones()->attach($inscripcion->id, [
                            'estado' => 'asignado',
                            'asignado_en' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $grupo->inscripciones_count++; // Actualizar el contador en memoria
                        $estudiantesAsignados++;
                        $asignado = true;
                        break;
                    }
                }

                if (!$asignado) {
                    $sinCupo++;
                }
            }

            return [
                'total_rezagados' => $inscripcionesRezagadas->count(),
                'estudiantes_asignados' => $estudiantesAsignados,
                'sin_cupo' => $sinCupo,
            ];
        });
    }

    private function inscripcionesAsignables(int $gestionId): Collection
    {
        return Inscripcion::query()
            ->where('gestion_id', $gestionId)
            ->whereIn('estado', [InscripcionState::PAGADO, InscripcionState::INSCRITO])
            ->whereDoesntHave('grupos')
            ->orderBy('fecha_inscripcion')
            ->orderBy('id')
            ->get();
    }

    private function materiasCupActivas(): Collection
    {
        $orden = ['matematica', 'computacion', 'ingles', 'fisica'];
        $codigosPreferidos = ['MAT', 'COM', 'ING', 'FIS'];

        $materiasAgrupadas = Materia::query()
            ->where('activa', true)
            ->where(function ($query) use ($codigosPreferidos) {
                $query->whereIn('codigo', $codigosPreferidos)
                    ->orWhereIn('nombre', ['Matematica', 'Matematicas', 'Computacion', 'Ingles', 'Fisica']);
            })
            ->orderByRaw("CASE WHEN codigo IN ('MAT','COM','ING','FIS') THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Materia $materia) => $this->normalizarMateria($materia->nombre));

        return collect($materiasAgrupadas->all())
            ->only($orden)
            ->map(fn (Collection $materias) => $materias->first())
            ->sortBy(fn (Materia $materia) => array_search($this->normalizarMateria($materia->nombre), $orden, true))
            ->values();
    }

    private function armarGrupos(Collection $inscripciones, Collection $materias, Collection $aulas, Collection $docentes, array &$errores, array &$advertencias): array
    {
        $grupos = [];
        $ocupacionAulas = $this->ocupacionAulasExistente();
        $ocupacionDocentes = $this->ocupacionDocentesExistente();
        $cargaDocentes = $this->cargaDocentesExistente();

        foreach ($inscripciones->chunk(Grupo::CUPO_MAXIMO)->values() as $indice => $lote) {
            $aula = $this->aulaBaseParaGrupo($aulas, $indice);
            $codigoGrupo = 'G' . ($indice + 1);
            $ocupacionGrupo = [];
            $materiasGrupo = [];
            $aulasPorCodigo = $aulas->keyBy('codigo');

            foreach ($materias as $materia) {
                $docente = $this->elegirDocente($docentes, $cargaDocentes, $ocupacionDocentes, $ocupacionGrupo, $materia, $codigoGrupo);

                if ($docente === null) {
                    $errores[] = "No hay docente disponible para {$materia->nombre} en {$codigoGrupo} sin superar cuatro grupos.";
                    continue;
                }

                $cargaDocentes[$docente->id][$codigoGrupo] = true;

                $horarios = [];
                $aulasMateria = $this->aulasParaMateria($aulasPorCodigo, $materia);
                $bloquesOrdenados = $this->ordenarBloquesParaGrupo($this->bloquesPresenciales(), $ocupacionGrupo);
                $presencial = $this->buscarAulaYHorarioDisponible(
                    $aulasMateria,
                    $bloquesOrdenados,
                    $ocupacionGrupo,
                    $ocupacionAulas,
                    $ocupacionDocentes[$docente->id] ?? []
                );

                if ($presencial === null) {
                    $errores[] = "No hay aula y bloque presencial disponible para {$materia->nombre} en {$codigoGrupo}.";
                    continue;
                }

                $aulaPresencial = $presencial['aula'];
                $bloquePresencial = $presencial['bloque'];

                $this->marcarOcupacion($ocupacionGrupo, $bloquePresencial);
                $this->marcarOcupacion($ocupacionAulas[$aulaPresencial->id], $bloquePresencial);
                $this->marcarOcupacion($ocupacionDocentes[$docente->id], $bloquePresencial);

                $horarios[] = [
                    'dia_semana' => $bloquePresencial['dia_semana'],
                    'hora_inicio' => $bloquePresencial['hora_inicio'],
                    'hora_fin' => $bloquePresencial['hora_fin'],
                    'modalidad' => self::MODALIDAD_PRESENCIAL,
                    'aula_id' => $aulaPresencial->id,
                    'aula_codigo' => $aulaPresencial->codigo,
                ];

                if (! $this->esIngles($materia) && ! $this->esComputacion($materia)) {
                    $virtual = $this->buscarHorarioDisponible(
                        $this->bloquesSabadoVirtual(),
                        $ocupacionGrupo,
                        [],
                        $ocupacionDocentes[$docente->id] ?? []
                    );

                    if ($virtual === null) {
                        $advertencias[] = "No se encontro bloque virtual de sabado para {$materia->nombre} en {$codigoGrupo}.";
                    } else {
                        $this->marcarOcupacion($ocupacionGrupo, $virtual);
                        $this->marcarOcupacion($ocupacionDocentes[$docente->id], $virtual);

                        $horarios[] = [
                            'dia_semana' => $virtual['dia_semana'],
                            'hora_inicio' => $virtual['hora_inicio'],
                            'hora_fin' => $virtual['hora_fin'],
                            'modalidad' => self::MODALIDAD_VIRTUAL,
                            'aula_id' => null,
                            'aula_codigo' => null,
                        ];
                    }
                }

                $materiasGrupo[] = [
                    'materia_id' => $materia->id,
                    'materia_nombre' => $materia->nombre,
                    'docente_id' => $docente->id,
                    'docente_nombre' => trim($docente->nombres . ' ' . ($docente->apellidos ?? '')),
                    'horarios' => $horarios,
                ];
            }

            $grupos[] = [
                'codigo' => $codigoGrupo,
                'nombre' => "Grupo {$codigoGrupo}",
                'aula_id' => $aula->id,
                'aula_codigo' => $aula->codigo,
                'total_estudiantes' => $lote->count(),
                'inscripcion_ids' => $lote->pluck('id')->values()->all(),
                'materias' => $materiasGrupo,
            ];
        }

        return $grupos;
    }

    private function elegirDocente(Collection $docentes, array $cargaDocentes, array $ocupacionDocentes, array $ocupacionGrupo, Materia $materia, string $codigoGrupo): ?Docente
    {
        return $docentes
            ->sortBy(fn (Docente $docente) => count($cargaDocentes[$docente->id] ?? []))
            ->first(function (Docente $docente) use ($cargaDocentes, $ocupacionDocentes, $ocupacionGrupo) {
                if (count($cargaDocentes[$docente->id] ?? []) >= 4) {
                    return false;
                }

                foreach ($this->bloquesPresenciales() as $bloque) {
                    if (! $this->choca($bloque, $ocupacionGrupo) && ! $this->choca($bloque, $ocupacionDocentes[$docente->id] ?? [])) {
                        return true;
                    }
                }

                return false;
            });
    }

    private function aulaBaseParaGrupo(Collection $aulas, int $indice): Aula
    {
        $aulasBase = $aulas
            ->filter(fn (Aula $aula) => in_array($aula->codigo, [...FicctAulas::TEORICAS, FicctAulas::AUDITORIO], true))
            ->values();

        return $aulasBase->isNotEmpty()
            ? $aulasBase[$indice % $aulasBase->count()]
            : $aulas[$indice % $aulas->count()];
    }

    private function aulasParaMateria(Collection $aulasPorCodigo, Materia $materia): Collection
    {
        $identificador = FicctAulas::esComputacion($materia->codigo) || FicctAulas::esComputacion($materia->nombre)
            ? 'COM'
            : $materia->nombre;

        return collect(FicctAulas::codigosParaMateria($identificador))
            ->map(fn (string $codigo) => $aulasPorCodigo->get($codigo))
            ->filter()
            ->values();
    }

    private function ocupacionAulasExistente(): array
    {
        $ocupacion = [];

        Horario::query()
            ->whereNotNull('aula_id')
            ->get()
            ->each(function (Horario $horario) use (&$ocupacion) {
                $this->marcarOcupacion($ocupacion[$horario->aula_id], [
                    'dia_semana' => $horario->dia_semana,
                    'hora_inicio' => substr((string) $horario->hora_inicio, 0, 5),
                    'hora_fin' => substr((string) $horario->hora_fin, 0, 5),
                ]);
            });

        return $ocupacion;
    }

    private function ocupacionDocentesExistente(): array
    {
        $ocupacion = [];

        Horario::query()
            ->with('grupoMateria')
            ->get()
            ->each(function (Horario $horario) use (&$ocupacion) {
                $docenteId = $horario->grupoMateria?->docente_id;

                if ($docenteId === null) {
                    return;
                }

                $this->marcarOcupacion($ocupacion[$docenteId], [
                    'dia_semana' => $horario->dia_semana,
                    'hora_inicio' => substr((string) $horario->hora_inicio, 0, 5),
                    'hora_fin' => substr((string) $horario->hora_fin, 0, 5),
                ]);
            });

        return $ocupacion;
    }

    private function cargaDocentesExistente(): array
    {
        $carga = [];

        GrupoMateria::query()
            ->with('grupo')
            ->whereNotNull('docente_id')
            ->get()
            ->each(function (GrupoMateria $grupoMateria) use (&$carga) {
                $grupoId = $grupoMateria->grupo?->id;

                if ($grupoId !== null) {
                    $carga[$grupoMateria->docente_id][$grupoId] = true;
                }
            });

        return $carga;
    }

    private function buscarHorarioDisponible(array $bloques, array $ocupacionGrupo, array $ocupacionAula, array $ocupacionDocente): ?array
    {
        foreach ($bloques as $bloque) {
            if ($this->choca($bloque, $ocupacionGrupo)) {
                continue;
            }

            if ($ocupacionAula !== [] && $this->choca($bloque, $ocupacionAula)) {
                continue;
            }

            if ($this->choca($bloque, $ocupacionDocente)) {
                continue;
            }

            return $bloque;
        }

        return null;
    }

    private function buscarAulaYHorarioDisponible(Collection $aulas, array $bloques, array $ocupacionGrupo, array $ocupacionAulas, array $ocupacionDocente): ?array
    {
        foreach ($aulas as $aula) {
            $bloque = $this->buscarHorarioDisponible(
                $bloques,
                $ocupacionGrupo,
                $ocupacionAulas[$aula->id] ?? [],
                $ocupacionDocente
            );

            if ($bloque !== null) {
                return ['aula' => $aula, 'bloque' => $bloque];
            }
        }

        return null;
    }

    private function choca(array $bloque, array $ocupacion): bool
    {
        foreach ($ocupacion[$bloque['dia_semana']] ?? [] as $ocupado) {
            if ($bloque['hora_inicio'] < $ocupado['hora_fin'] && $bloque['hora_fin'] > $ocupado['hora_inicio']) {
                return true;
            }
        }

        return false;
    }

    private function marcarOcupacion(?array &$ocupacion, array $bloque): void
    {
        if ($ocupacion === null) {
            $ocupacion = [];
        }

        $ocupacion[$bloque['dia_semana']][] = [
            'hora_inicio' => $bloque['hora_inicio'],
            'hora_fin' => $bloque['hora_fin'],
        ];
    }

    private function bloquesPresenciales(): array
    {
        $bloques = [];

        foreach ([1, 2, 3, 4, 5] as $dia) {
            foreach ([['08:00', '10:00'], ['10:00', '12:00'], ['12:00', '14:00'], ['14:00', '16:00'], ['16:00', '18:00']] as [$inicio, $fin]) {
                $bloques[] = ['dia_semana' => $dia, 'hora_inicio' => $inicio, 'hora_fin' => $fin];
            }
        }

        return $bloques;
    }

    private function bloquesSabadoVirtual(): array
    {
        return [
            ['dia_semana' => 6, 'hora_inicio' => '08:00', 'hora_fin' => '10:00'],
            ['dia_semana' => 6, 'hora_inicio' => '10:00', 'hora_fin' => '12:00'],
            ['dia_semana' => 6, 'hora_inicio' => '12:00', 'hora_fin' => '14:00'],
            ['dia_semana' => 6, 'hora_inicio' => '14:00', 'hora_fin' => '16:00'],
            ['dia_semana' => 6, 'hora_inicio' => '16:00', 'hora_fin' => '18:00'],
        ];
    }

    private function esIngles(Materia $materia): bool
    {
        return str_contains($this->normalizarMateria($materia->nombre), 'ingles')
            || strtoupper($materia->codigo) === 'ING';
    }

    private function esComputacion(Materia $materia): bool
    {
        return FicctAulas::esComputacion($materia->codigo) || FicctAulas::esComputacion($materia->nombre);
    }

    private function normalizarMateria(string $nombre): string
    {
        $normalizado = strtolower($nombre);
        $normalizado = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $normalizado);
        $normalizado = preg_replace('/[^a-z0-9]+/', '', $normalizado) ?? $normalizado;

        return $normalizado === 'matematicas' ? 'matematica' : $normalizado;
    }

    private function ordenarBloquesParaGrupo(array $bloques, array $ocupacionGrupo): array
    {
        $diasOcupados = [];
        foreach ([1, 2, 3, 4, 5] as $dia) {
            if (!empty($ocupacionGrupo[$dia])) {
                $diasOcupados[] = $dia;
            }
        }

        usort($bloques, function ($a, $b) use ($diasOcupados) {
            $aOcupado = in_array($a['dia_semana'], $diasOcupados, true);
            $bOcupado = in_array($b['dia_semana'], $diasOcupados, true);

            if ($aOcupado !== $bOcupado) {
                return $aOcupado ? 1 : -1;
            }

            if ($a['dia_semana'] !== $b['dia_semana']) {
                return $a['dia_semana'] <=> $b['dia_semana'];
            }
            return $a['hora_inicio'] <=> $b['hora_inicio'];
        });

        return $bloques;
    }
}
