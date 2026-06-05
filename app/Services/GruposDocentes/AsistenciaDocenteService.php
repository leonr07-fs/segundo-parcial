<?php

namespace App\Services\GruposDocentes;

use App\Models\AsistenciaDocente\Asistencia;
use App\Models\GestionAcademica\Docente;
use App\Models\GestionAcademica\GrupoMateria;
use App\Models\InscripcionPagos\Inscripcion;
use App\Models\Seguridad\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AsistenciaDocenteService
{
    public function obtenerDocente(User $user): ?Docente
    {
        return Docente::query()
            ->where('ci', $user->numero_registro)
            ->orWhere('correo', $user->email)
            ->first();
    }

    public function obtenerPlanilla(User $user, int $grupoMateriaId, string $fecha): array
    {
        $docente = $this->docenteAutorizado($user, $grupoMateriaId);
        $grupoMateria = GrupoMateria::with(['grupo.gestion', 'grupo.inscripciones.postulante', 'materia', 'horarios.aula'])
            ->findOrFail($grupoMateriaId);

        $asistencias = Asistencia::where('grupo_materia_id', $grupoMateria->id)
            ->whereDate('fecha', $fecha)
            ->get()
            ->keyBy('inscripcion_id');
        $resumenFecha = $this->resumenFecha($grupoMateria, $fecha);
        $resumenEstudiantes = $this->resumenEstudiantes($grupoMateria);

        return [
            'docente' => $docente,
            'grupo_materia' => [
                'id' => $grupoMateria->id,
                'grupo' => $grupoMateria->grupo?->codigo,
                'grupo_nombre' => $grupoMateria->grupo?->nombre,
                'materia' => $grupoMateria->materia?->nombre,
                'materia_codigo' => $grupoMateria->materia?->codigo,
                'gestion' => $grupoMateria->grupo?->gestion?->nombre,
            ],
            'fecha' => $fecha,
            'resumen' => $resumenFecha,
            'estudiantes' => $grupoMateria->grupo?->inscripciones
                ->map(function (Inscripcion $inscripcion) use ($asistencias, $resumenEstudiantes) {
                    $asistencia = $asistencias->get($inscripcion->id);
                    $resumen = $resumenEstudiantes[$inscripcion->id] ?? $this->resumenEstudianteVacio();

                    return [
                        'inscripcion_id' => $inscripcion->id,
                        'codigo' => $inscripcion->codigo,
                        'ci' => $inscripcion->postulante?->ci,
                        'postulante' => $this->nombrePostulante($inscripcion),
                        'estado' => $asistencia?->estado ?? 'pendiente',
                        'observacion' => $asistencia?->observacion,
                        'total_clases' => $resumen['total_clases'],
                        'asistencias_validas' => $resumen['asistencias_validas'],
                        'ausencias' => $resumen['ausencias'],
                        'porcentaje_asistencia' => $resumen['porcentaje_asistencia'],
                    ];
                })
                ->values() ?? collect(),
        ];
    }

    public function registrar(User $user, int $grupoMateriaId, string $fecha, array $asistencias): array
    {
        $docente = $this->docenteAutorizado($user, $grupoMateriaId);
        $grupoMateria = GrupoMateria::with(['grupo.inscripciones', 'materia'])->findOrFail($grupoMateriaId);
        $inscripcionesPermitidas = $grupoMateria->grupo?->inscripciones->pluck('id')->all() ?? [];

        $asistenciaYaRegistrada = Asistencia::where('grupo_materia_id', $grupoMateriaId)
            ->whereDate('fecha', $fecha)
            ->exists();

        if ($asistenciaYaRegistrada) {
            throw ValidationException::withMessages([
                'fecha' => 'La asistencia de este grupo y materia ya fue registrada para la fecha seleccionada.',
            ]);
        }

        return DB::transaction(function () use ($asistencias, $docente, $fecha, $grupoMateriaId, $inscripcionesPermitidas, $user, $grupoMateria) {
            $total = 0;
            $conteo = array_fill_keys(Asistencia::ESTADOS, 0);

            foreach ($asistencias as $item) {
                if (! in_array((int) $item['inscripcion_id'], $inscripcionesPermitidas, true)) {
                    throw new AuthorizationException('La inscripcion no pertenece al grupo seleccionado.');
                }

                Asistencia::updateOrCreate([
                    'grupo_materia_id' => $grupoMateriaId,
                    'inscripcion_id' => $item['inscripcion_id'],
                    'fecha' => $fecha,
                ], [
                    'docente_id' => $docente->id,
                    'estado' => $item['estado'],
                    'observacion' => $item['observacion'] ?? null,
                    'registrado_por' => $user->id,
                    'registrado_en' => now(),
                ]);

                $total++;
                $conteo[$item['estado']] = ($conteo[$item['estado']] ?? 0) + 1;
            }

            return [
                'total_registradas' => $total,
                'resumen' => [
                    'fecha' => $fecha,
                    'total_estudiantes' => count($inscripcionesPermitidas),
                    'registrados' => $total,
                    'pendientes' => max(0, count($inscripcionesPermitidas) - $total),
                    'asistencia_tomada' => $total >= count($inscripcionesPermitidas),
                    'presente' => $conteo['presente'] ?? 0,
                    'ausente' => $conteo['ausente'] ?? 0,
                    'tardanza' => $conteo['tardanza'] ?? 0,
                    'justificado' => $conteo['justificado'] ?? 0,
                    'porcentaje_asistencia' => $this->porcentajeAsistencia($conteo, max(1, count($inscripcionesPermitidas))),
                    'grupo' => $grupoMateria->grupo?->codigo,
                    'materia' => $grupoMateria->materia?->nombre,
                ],
            ];
        });
    }

    public function resumenDocente(User $user): array
    {
        $docente = $this->obtenerDocente($user);

        if (! $docente) {
            return [
                'clases_registradas' => 0,
                'registros_estudiantes' => 0,
                'porcentaje_asistencia_estudiantes' => 0,
                'presente' => 0,
                'ausente' => 0,
                'tardanza' => 0,
                'justificado' => 0,
            ];
        }

        $asistencias = Asistencia::where('docente_id', $docente->id)->get();
        $conteo = $asistencias->countBy('estado');
        $clases = $asistencias
            ->map(fn (Asistencia $asistencia) => $asistencia->grupo_materia_id.'|'.$asistencia->fecha?->toDateString())
            ->unique()
            ->count();

        return [
            'clases_registradas' => $clases,
            'registros_estudiantes' => $asistencias->count(),
            'porcentaje_asistencia_estudiantes' => $this->porcentajeAsistencia($conteo->all(), max(1, $asistencias->count())),
            'presente' => (int) ($conteo['presente'] ?? 0),
            'ausente' => (int) ($conteo['ausente'] ?? 0),
            'tardanza' => (int) ($conteo['tardanza'] ?? 0),
            'justificado' => (int) ($conteo['justificado'] ?? 0),
        ];
    }

    public function reporte(array $filtros = [])
    {
        $query = $this->consultaReporte($filtros)
            ->latest('fecha');

        return $query->paginate(20)->through(fn (Asistencia $asistencia) => [
            'id' => $asistencia->id,
            'fecha' => $asistencia->fecha?->toDateString(),
            'grupo' => $asistencia->grupoMateria?->grupo?->codigo,
            'materia' => $asistencia->grupoMateria?->materia?->nombre,
            'docente' => trim(($asistencia->docente?->nombres ?? '').' '.($asistencia->docente?->apellidos ?? '')),
            'codigo' => $asistencia->inscripcion?->codigo,
            'postulante' => $this->nombrePostulante($asistencia->inscripcion),
            'estado' => $asistencia->estado,
            'observacion' => $asistencia->observacion,
        ]);
    }

    public function resumenAdminPorDocente(array $filtros = []): array
    {
        return $this->consultaReporte($filtros)
            ->orderBy('docente_id')
            ->orderBy('grupo_materia_id')
            ->orderBy('fecha')
            ->get()
            ->groupBy('docente_id')
            ->map(function (Collection $asistenciasDocente) {
                $primera = $asistenciasDocente->first();
                $conteoDocente = $asistenciasDocente->countBy('estado')->all();

                return [
                    'docente_id' => $primera?->docente_id,
                    'docente' => trim(($primera?->docente?->nombres ?? '').' '.($primera?->docente?->apellidos ?? '')),
                    'clases_registradas' => $asistenciasDocente
                        ->map(fn (Asistencia $asistencia) => $asistencia->grupo_materia_id.'|'.$asistencia->fecha?->toDateString())
                        ->unique()
                        ->count(),
                    'registros_estudiantes' => $asistenciasDocente->count(),
                    'porcentaje_asistencia' => $this->porcentajeAsistencia($conteoDocente, max(1, $asistenciasDocente->count())),
                    'materias' => $asistenciasDocente
                        ->groupBy('grupo_materia_id')
                        ->map(function (Collection $asistenciasMateria) {
                            $primeraMateria = $asistenciasMateria->first();
                            $conteoMateria = $asistenciasMateria->countBy('estado')->all();

                            return [
                                'grupo_materia_id' => $primeraMateria?->grupo_materia_id,
                                'gestion' => $primeraMateria?->grupoMateria?->grupo?->gestion?->nombre,
                                'grupo' => $primeraMateria?->grupoMateria?->grupo?->codigo,
                                'materia' => $primeraMateria?->grupoMateria?->materia?->nombre,
                                'materia_codigo' => $primeraMateria?->grupoMateria?->materia?->codigo,
                                'clases_registradas' => $asistenciasMateria->pluck('fecha')->map(fn ($fecha) => $fecha?->toDateString())->unique()->count(),
                                'porcentaje_asistencia' => $this->porcentajeAsistencia($conteoMateria, max(1, $asistenciasMateria->count())),
                                'estudiantes' => $asistenciasMateria
                                    ->groupBy('inscripcion_id')
                                    ->map(function (Collection $asistenciasEstudiante) {
                                        $primeraEstudiante = $asistenciasEstudiante->first();
                                        $conteoEstudiante = $asistenciasEstudiante->countBy('estado')->all();

                                        return [
                                            'inscripcion_id' => $primeraEstudiante?->inscripcion_id,
                                            'codigo' => $primeraEstudiante?->inscripcion?->codigo,
                                            'postulante' => $this->nombrePostulante($primeraEstudiante?->inscripcion),
                                            'presente' => (int) ($conteoEstudiante['presente'] ?? 0),
                                            'ausente' => (int) ($conteoEstudiante['ausente'] ?? 0),
                                            'tardanza' => (int) ($conteoEstudiante['tardanza'] ?? 0),
                                            'justificado' => (int) ($conteoEstudiante['justificado'] ?? 0),
                                            'total_clases' => $asistenciasEstudiante->count(),
                                            'porcentaje_asistencia' => $this->porcentajeAsistencia($conteoEstudiante, max(1, $asistenciasEstudiante->count())),
                                        ];
                                    })
                                    ->values(),
                            ];
                        })
                        ->values(),
                ];
            })
            ->values()
            ->all();
    }

    private function consultaReporte(array $filtros = []): Builder
    {
        return Asistencia::query()
            ->with(['grupoMateria.grupo.gestion', 'grupoMateria.materia', 'inscripcion.postulante', 'docente'])
            ->when($filtros['fecha_desde'] ?? null, fn (Builder $query, $fecha) => $query->whereDate('fecha', '>=', $fecha))
            ->when($filtros['fecha_hasta'] ?? null, fn (Builder $query, $fecha) => $query->whereDate('fecha', '<=', $fecha))
            ->when($filtros['gestion_id'] ?? null, fn (Builder $query, $gestionId) => $query->whereHas('grupoMateria.grupo', fn (Builder $subquery) => $subquery->where('gestion_id', $gestionId)))
            ->when($filtros['docente_id'] ?? null, fn (Builder $query, $docenteId) => $query->where('docente_id', $docenteId));
    }

    private function docenteAutorizado(User $user, int $grupoMateriaId): Docente
    {
        $docente = $this->obtenerDocente($user);

        if (! $docente) {
            throw new AuthorizationException('No existe un docente vinculado al usuario autenticado.');
        }

        $pertenece = GrupoMateria::where('id', $grupoMateriaId)
            ->where('docente_id', $docente->id)
            ->exists();

        if (! $pertenece) {
            throw new AuthorizationException('El grupo materia no pertenece al docente autenticado.');
        }

        return $docente;
    }

    private function resumenFecha(GrupoMateria $grupoMateria, string $fecha): array
    {
        $totalEstudiantes = $grupoMateria->grupo?->inscripciones->count() ?? 0;
        $conteo = Asistencia::where('grupo_materia_id', $grupoMateria->id)
            ->whereDate('fecha', $fecha)
            ->get()
            ->countBy('estado')
            ->all();
        $registrados = array_sum($conteo);

        return [
            'total_estudiantes' => $totalEstudiantes,
            'registrados' => $registrados,
            'pendientes' => max(0, $totalEstudiantes - $registrados),
            'asistencia_tomada' => $totalEstudiantes > 0 && $registrados >= $totalEstudiantes,
            'presente' => (int) ($conteo['presente'] ?? 0),
            'ausente' => (int) ($conteo['ausente'] ?? 0),
            'tardanza' => (int) ($conteo['tardanza'] ?? 0),
            'justificado' => (int) ($conteo['justificado'] ?? 0),
            'porcentaje_asistencia' => $this->porcentajeAsistencia($conteo, max(1, $totalEstudiantes)),
        ];
    }

    /**
     * @return array<int, array<string, int|float>>
     */
    private function resumenEstudiantes(GrupoMateria $grupoMateria): array
    {
        $registros = Asistencia::where('grupo_materia_id', $grupoMateria->id)->get();
        $totalClases = $registros->pluck('fecha')->map(fn ($fecha) => $fecha?->toDateString())->unique()->filter()->count();

        return $registros
            ->groupBy('inscripcion_id')
            ->map(function (Collection $items) use ($totalClases) {
                $conteo = $items->countBy('estado')->all();
                $asistenciasValidas = ($conteo['presente'] ?? 0) + ($conteo['tardanza'] ?? 0) + ($conteo['justificado'] ?? 0);

                return [
                    'total_clases' => $totalClases,
                    'asistencias_validas' => $asistenciasValidas,
                    'ausencias' => (int) ($conteo['ausente'] ?? 0),
                    'porcentaje_asistencia' => $totalClases > 0 ? round(($asistenciasValidas / $totalClases) * 100, 2) : 0,
                ];
            })
            ->all();
    }

    private function resumenEstudianteVacio(): array
    {
        return [
            'total_clases' => 0,
            'asistencias_validas' => 0,
            'ausencias' => 0,
            'porcentaje_asistencia' => 0,
        ];
    }

    private function porcentajeAsistencia(array $conteo, int $total): float
    {
        $validas = ($conteo['presente'] ?? 0) + ($conteo['tardanza'] ?? 0) + ($conteo['justificado'] ?? 0);

        return round(($validas / $total) * 100, 2);
    }

    private function nombrePostulante(?Inscripcion $inscripcion): string
    {
        $postulante = $inscripcion?->postulante;

        if (! $postulante) {
            return 'Sin postulante';
        }

        return trim("{$postulante->nombres} {$postulante->apellido_paterno} {$postulante->apellido_materno}");
    }
}
