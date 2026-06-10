<?php

namespace App\Services\ReportesExportaciones;

use App\Models\AsignacionCarrera\AsignacionCarrera;
use App\Models\AsistenciaDocente\Asistencia;
use App\Models\ReportesAuditoria\AuditLog;
use App\Models\AsignacionCarrera\Carrera;
use App\Models\EvaluacionesResultados\Evaluacion;
use App\Models\GestionAcademica\Gestion;
use App\Models\GestionAcademica\Grupo;
use App\Models\GestionAcademica\GrupoMateria;
use App\Models\InscripcionPagos\Inscripcion;
use App\Models\GestionAcademica\Materia;
use App\Models\InscripcionPagos\Pago;
use App\Models\EvaluacionesResultados\ResultadoCup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

use App\Services\GestionAcademica\GestionVigenteService;

/**
 * CU16, CU18 - Reportes Estáticos y Dinámicos
 *
 * Participantes (Diagrama de Secuencia):
 * - Control: DashboardController / ReporteController
 * - Control: ReporteService (Actual)
 * - Entity: Evaluacion, Grupo
 */
class ReporteService
{
    public function __construct(private readonly GestionVigenteService $gestionVigenteService)
    {
    }

    public function catalogo(): array
    {
        return [
            'reportes_estaticos' => [
                ['id' => 'aprobados', 'nombre' => 'Lista oficial de aprobados', 'tipo' => 'estatico'],
                ['id' => 'reprobados', 'nombre' => 'Lista oficial de reprobados', 'tipo' => 'estatico'],
                ['id' => 'promedios', 'nombre' => 'Promedios finales CUP', 'tipo' => 'estatico'],
                ['id' => 'materias', 'nombre' => 'Estadisticas por materia', 'tipo' => 'estatico'],
                ['id' => 'grupos', 'nombre' => 'Grupos y cupos', 'tipo' => 'estatico'],
                ['id' => 'docentes-grupo', 'nombre' => 'Docentes por grupo', 'tipo' => 'estatico'],
                ['id' => 'asistencias', 'nombre' => 'Asistencia docente', 'tipo' => 'estatico'],
                ['id' => 'asignacion-carrera', 'nombre' => 'Asignacion de carreras', 'tipo' => 'estatico'],
                ['id' => 'bitacora', 'nombre' => 'Bitacora auditora', 'tipo' => 'estatico'],
            ],
            'modulos_dinamicos' => $this->modulosDinamicos(),
            'gestiones' => Gestion::query()->orderByDesc('anio')->orderByDesc('id')->get(['id', 'nombre', 'anio', 'periodo', 'estado']),
            'carreras' => Carrera::query()->orderBy('nombre')->get(['id', 'nombre']),
            'materias' => Materia::query()->orderBy('nombre')->get(['id', 'nombre']),
            'grupos' => Grupo::query()->with('gestion:id,nombre')->orderBy('codigo')->get(['id', 'gestion_id', 'codigo', 'nombre']),
        ];
    }

    public function generarEstatico(string $tipo, array $filtros = []): array
    {
        return match ($tipo) {
            'aprobados' => $this->reporteResultados('Lista oficial de aprobados', 'aprobado', $filtros),
            'reprobados' => $this->reporteResultados('Lista oficial de reprobados', 'reprobado', $filtros),
            'promedios' => $this->reporteResultados('Promedios finales CUP', null, $filtros),
            'materias' => $this->reporteMaterias($filtros),
            'grupos' => $this->reporteGrupos($filtros),
            'docentes-grupo' => $this->reporteDocentesGrupo($filtros),
            'asistencias' => $this->reporteAsistencias($filtros),
            'asignacion-carrera' => $this->reporteAsignacionCarrera($filtros),
            'bitacora' => $this->reporteBitacora($filtros),
            default => throw new \InvalidArgumentException('Reporte no disponible.'),
        };
    }

    public function generarDinamico(string $modulo, array $columnas, array $filtros = []): array
    {
        $catalogo = $this->modulosDinamicos();

        if (! isset($catalogo[$modulo])) {
            throw new \InvalidArgumentException('Modulo de reporte no disponible.');
        }

        $columnasDisponibles = collect($catalogo[$modulo]['columnas']);
        $keysDisponibles = $columnasDisponibles->pluck('key')->all();
        $columnasValidas = collect($columnas)
            ->filter(fn ($columna) => in_array($columna, $keysDisponibles, true))
            ->values();

        if ($columnasValidas->isEmpty()) {
            $columnasValidas = $columnasDisponibles->take(5)->pluck('key')->values();
        }

        $filasCompletas = $this->filasDinamicas($modulo, $filtros);

        return [
            'titulo' => 'Reporte dinamico - '.$catalogo[$modulo]['nombre'],
            'tipo' => 'dinamico',
            'modulo' => $modulo,
            'columnas' => $columnasDisponibles
                ->whereIn('key', $columnasValidas->all())
                ->values()
                ->all(),
            'filas' => $filasCompletas
                ->map(fn (array $fila) => $columnasValidas
                    ->mapWithKeys(fn (string $key) => [$key => $fila[$key] ?? null])
                    ->all())
                ->values()
                ->all(),
            'filtros' => $filtros,
            'generado_en' => now()->toDateTimeString(),
        ];
    }

    private function reporteResultados(string $titulo, ?string $estado, array $filtros): array
    {
        $gestionId = $this->resolveGestionId($filtros);

        $query = ResultadoCup::query()
            ->with([
                'inscripcion.postulante',
                'inscripcion.gestion',
                'inscripcion.asignacionCarrera.carrera',
            ])
            ->when($estado, fn (Builder $query) => $query->where('estado_final', $estado))
            ->whereHas('inscripcion', fn (Builder $subquery) => $subquery->where('gestion_id', $gestionId))
            ->orderByDesc('promedio_final');

        $this->aplicarFechas($query, $filtros, 'cerrado_en');

        return $this->respuesta($titulo, [
            ['key' => 'codigo', 'label' => 'Codigo CUP'],
            ['key' => 'postulante', 'label' => 'Postulante'],
            ['key' => 'ci', 'label' => 'CI'],
            ['key' => 'promedio_final', 'label' => 'Promedio'],
            ['key' => 'estado_final', 'label' => 'Estado'],
            ['key' => 'carrera_asignada', 'label' => 'Carrera asignada'],
            ['key' => 'gestion', 'label' => 'Gestion'],
        ], $query->get()->map(fn (ResultadoCup $resultado) => [
            'codigo' => $resultado->inscripcion?->codigo,
            'postulante' => $this->nombrePostulante($resultado->inscripcion?->postulante),
            'ci' => $resultado->inscripcion?->postulante?->ci,
            'promedio_final' => (float) $resultado->promedio_final,
            'estado_final' => $resultado->estado_final,
            'carrera_asignada' => $resultado->inscripcion?->asignacionCarrera?->carrera?->nombre ?? 'Sin asignar',
            'gestion' => $resultado->inscripcion?->gestion?->nombre,
        ]), $filtros);
    }

    private function reporteMaterias(array $filtros): array
    {
        $gestionId = $this->resolveGestionId($filtros);

        $query = Evaluacion::query()
            ->with(['grupoMateria.materia', 'inscripcion.gestion'])
            ->whereHas('inscripcion', fn (Builder $subquery) => $subquery->where('gestion_id', $gestionId));

        $filas = $query->get()
            ->groupBy(fn (Evaluacion $evaluacion) => $evaluacion->grupoMateria?->materia?->nombre ?? 'Sin materia')
            ->map(function (Collection $evaluaciones, string $materia) {
                $promedios = $evaluaciones->pluck('promedio')->filter(fn ($promedio) => $promedio !== null);

                return [
                    'materia' => $materia,
                    'total_evaluaciones' => $evaluaciones->count(),
                    'promedio_general' => round((float) $promedios->avg(), 2),
                    'aprobados' => $evaluaciones->where('promedio', '>=', 60)->count(),
                    'reprobados' => $evaluaciones->where('promedio', '<', 60)->count(),
                ];
            })
            ->sortBy('materia')
            ->values();

        return $this->respuesta('Estadisticas por materia', [
            ['key' => 'materia', 'label' => 'Materia'],
            ['key' => 'total_evaluaciones', 'label' => 'Evaluaciones'],
            ['key' => 'promedio_general', 'label' => 'Promedio general'],
            ['key' => 'aprobados', 'label' => 'Aprobados'],
            ['key' => 'reprobados', 'label' => 'Reprobados'],
        ], $filas, $filtros);
    }

    private function reporteGrupos(array $filtros): array
    {
        $gestionId = $this->resolveGestionId($filtros);

        $query = Grupo::query()
            ->with(['gestion', 'aula'])
            ->withCount('inscripciones')
            ->where('gestion_id', $gestionId)
            ->orderBy('codigo');

        return $this->respuesta('Grupos y cupos', [
            ['key' => 'gestion', 'label' => 'Gestion'],
            ['key' => 'grupo', 'label' => 'Grupo'],
            ['key' => 'aula', 'label' => 'Aula'],
            ['key' => 'cupo_maximo', 'label' => 'Cupo maximo'],
            ['key' => 'estudiantes', 'label' => 'Estudiantes'],
            ['key' => 'estado', 'label' => 'Estado'],
        ], $query->get()->map(fn (Grupo $grupo) => [
            'gestion' => $grupo->gestion?->nombre,
            'grupo' => $grupo->codigo,
            'aula' => $grupo->aula?->codigo ?? 'Sin aula',
            'cupo_maximo' => $grupo->cupo_maximo,
            'estudiantes' => $grupo->inscripciones_count,
            'estado' => $grupo->estado,
        ]), $filtros);
    }

    private function reporteDocentesGrupo(array $filtros): array
    {
        $gestionId = $this->resolveGestionId($filtros);

        $query = GrupoMateria::query()
            ->with(['grupo.gestion', 'materia', 'docente', 'horarios'])
            ->whereHas('grupo', fn (Builder $subquery) => $subquery->where('gestion_id', $gestionId))
            ->orderBy('grupo_id');

        return $this->respuesta('Docentes por grupo', [
            ['key' => 'gestion', 'label' => 'Gestion'],
            ['key' => 'grupo', 'label' => 'Grupo'],
            ['key' => 'materia', 'label' => 'Materia'],
            ['key' => 'docente', 'label' => 'Docente'],
            ['key' => 'horarios', 'label' => 'Horarios'],
        ], $query->get()->map(fn (GrupoMateria $grupoMateria) => [
            'gestion' => $grupoMateria->grupo?->gestion?->nombre,
            'grupo' => $grupoMateria->grupo?->codigo,
            'materia' => $grupoMateria->materia?->nombre,
            'docente' => trim(($grupoMateria->docente?->nombres ?? '').' '.($grupoMateria->docente?->apellidos ?? '')) ?: 'Sin docente',
            'horarios' => $grupoMateria->horarios
                ->map(fn ($horario) => "{$horario->dia_semana} {$horario->hora_inicio}-{$horario->hora_fin}")
                ->implode(', ') ?: 'Sin horarios',
        ]), $filtros);
    }

    private function reporteAsignacionCarrera(array $filtros): array
    {
        $gestionId = $this->resolveGestionId($filtros);

        $query = AsignacionCarrera::query()
            ->with(['inscripcion.postulante', 'inscripcion.gestion', 'inscripcion.opcionesCarrera.carrera', 'carrera'])
            ->whereHas('inscripcion', fn (Builder $subquery) => $subquery->where('gestion_id', $gestionId))
            ->orderByDesc('promedio_usado');

        return $this->respuesta('Asignacion de carreras', [
            ['key' => 'codigo', 'label' => 'Codigo CUP'],
            ['key' => 'postulante', 'label' => 'Postulante'],
            ['key' => 'ci', 'label' => 'CI'],
            ['key' => 'promedio_usado', 'label' => 'Promedio'],
            ['key' => 'primera_opcion', 'label' => 'Primera opcion'],
            ['key' => 'segunda_opcion', 'label' => 'Segunda opcion'],
            ['key' => 'carrera_asignada', 'label' => 'Carrera asignada'],
            ['key' => 'estado', 'label' => 'Estado'],
        ], $query->get()->map(function (AsignacionCarrera $asignacion) {
            $opciones = $asignacion->inscripcion?->opcionesCarrera?->keyBy('prioridad') ?? collect();

            return [
                'codigo' => $asignacion->inscripcion?->codigo,
                'postulante' => $this->nombrePostulante($asignacion->inscripcion?->postulante),
                'ci' => $asignacion->inscripcion?->postulante?->ci,
                'promedio_usado' => (float) $asignacion->promedio_usado,
                'primera_opcion' => $opciones->get(1)?->carrera?->nombre ?? 'No registrada',
                'segunda_opcion' => $opciones->get(2)?->carrera?->nombre ?? 'No registrada',
                'carrera_asignada' => $asignacion->carrera?->nombre ?? 'Sin asignar',
                'estado' => $asignacion->estado,
            ];
        }), $filtros);
    }

    private function reporteAsistencias(array $filtros): array
    {
        $gestionId = $this->resolveGestionId($filtros);

        $query = Asistencia::query()
            ->with(['grupoMateria.grupo.gestion', 'grupoMateria.materia', 'inscripcion.postulante', 'docente'])
            ->whereHas('grupoMateria.grupo', fn (Builder $subquery) => $subquery->where('gestion_id', $gestionId))
            ->orderByDesc('fecha');

        $this->aplicarFechas($query, $filtros, 'fecha');

        return $this->respuesta('Asistencia docente', [
            ['key' => 'fecha', 'label' => 'Fecha'],
            ['key' => 'gestion', 'label' => 'Gestion'],
            ['key' => 'grupo', 'label' => 'Grupo'],
            ['key' => 'materia', 'label' => 'Materia'],
            ['key' => 'docente', 'label' => 'Docente'],
            ['key' => 'codigo', 'label' => 'Codigo CUP'],
            ['key' => 'postulante', 'label' => 'Postulante'],
            ['key' => 'estado', 'label' => 'Estado'],
            ['key' => 'observacion', 'label' => 'Observacion'],
        ], $query->limit(500)->get()->map(fn (Asistencia $asistencia) => [
            'fecha' => $asistencia->fecha?->toDateString(),
            'gestion' => $asistencia->grupoMateria?->grupo?->gestion?->nombre,
            'grupo' => $asistencia->grupoMateria?->grupo?->codigo,
            'materia' => $asistencia->grupoMateria?->materia?->nombre,
            'docente' => trim(($asistencia->docente?->nombres ?? '').' '.($asistencia->docente?->apellidos ?? '')) ?: 'Sin docente',
            'codigo' => $asistencia->inscripcion?->codigo,
            'postulante' => $this->nombrePostulante($asistencia->inscripcion?->postulante),
            'estado' => $asistencia->estado,
            'observacion' => $asistencia->observacion,
        ]), $filtros);
    }

    private function reporteBitacora(array $filtros): array
    {
        $query = AuditLog::query()->with('user')->latest();
        $this->aplicarFechas($query, $filtros, 'created_at');

        return $this->respuesta('Bitacora auditora', [
            ['key' => 'fecha', 'label' => 'Fecha'],
            ['key' => 'evento', 'label' => 'Evento'],
            ['key' => 'usuario', 'label' => 'Usuario'],
            ['key' => 'ip', 'label' => 'IP'],
            ['key' => 'detalle', 'label' => 'Detalle'],
        ], $query->limit(500)->get()->map(fn (AuditLog $log) => [
            'fecha' => $this->fecha($log->created_at),
            'evento' => $log->event,
            'usuario' => $log->user?->name ?? 'Sistema',
            'ip' => $log->ip_address,
            'detalle' => json_encode($log->metadata ?? [], JSON_UNESCAPED_UNICODE),
        ]), $filtros);
    }

    private function filasDinamicas(string $modulo, array $filtros): Collection
    {
        return match ($modulo) {
            'postulantes' => $this->filasPostulantes($filtros),
            'pagos' => $this->filasPagos($filtros),
            'evaluaciones' => $this->filasEvaluaciones($filtros),
            'grupos' => collect($this->reporteGrupos($filtros)['filas']),
            'docentes' => collect($this->reporteDocentesGrupo($filtros)['filas']),
            'asistencias' => collect($this->reporteAsistencias($filtros)['filas']),
            'asignaciones' => collect($this->reporteAsignacionCarrera($filtros)['filas']),
            default => collect(),
        };
    }

    private function filasPostulantes(array $filtros): Collection
    {
        $gestionId = $this->resolveGestionId($filtros);

        $query = Inscripcion::query()
            ->with(['postulante', 'gestion'])
            ->where('gestion_id', $gestionId)
            ->orderByDesc('id');

        $this->aplicarFechas($query, $filtros, 'created_at');

        return $query->limit(500)->get()->map(fn (Inscripcion $inscripcion) => [
            'codigo' => $inscripcion->codigo,
            'ci' => $inscripcion->postulante?->ci,
            'postulante' => $this->nombrePostulante($inscripcion->postulante),
            'correo' => $inscripcion->postulante?->correo,
            'telefono' => $inscripcion->postulante?->telefono,
            'ciudad' => $inscripcion->postulante?->ciudad,
            'gestion' => $inscripcion->gestion?->nombre,
            'estado' => $inscripcion->estado,
            'fecha_inscripcion' => $this->fecha($inscripcion->fecha_inscripcion),
        ]);
    }

    private function filasPagos(array $filtros): Collection
    {
        $gestionId = $this->resolveGestionId($filtros);

        $query = Pago::query()
            ->with(['inscripcion.postulante', 'inscripcion.gestion'])
            ->whereHas('inscripcion', fn (Builder $subquery) => $subquery->where('gestion_id', $gestionId))
            ->orderByDesc('id');

        $this->aplicarFechas($query, $filtros, 'pagado_en');

        return $query->limit(500)->get()->map(fn (Pago $pago) => [
            'codigo' => $pago->inscripcion?->codigo,
            'postulante' => $this->nombrePostulante($pago->inscripcion?->postulante),
            'monto' => (float) $pago->monto,
            'moneda' => $pago->moneda,
            'metodo' => $pago->metodo,
            'referencia' => $pago->referencia,
            'estado' => $pago->estado,
            'pagado_en' => $this->fecha($pago->pagado_en),
        ]);
    }

    private function filasEvaluaciones(array $filtros): Collection
    {
        $gestionId = $this->resolveGestionId($filtros);

        $query = Evaluacion::query()
            ->with(['inscripcion.postulante', 'inscripcion.gestion', 'grupoMateria.grupo', 'grupoMateria.materia'])
            ->whereHas('inscripcion', fn (Builder $subquery) => $subquery->where('gestion_id', $gestionId))
            ->when($filtros['materia_id'] ?? null, fn (Builder $query, $materiaId) => $query->whereHas('grupoMateria', fn (Builder $subquery) => $subquery->where('materia_id', $materiaId)))
            ->orderByDesc('id');

        $this->aplicarFechas($query, $filtros, 'registrado_en');

        return $query->limit(500)->get()->map(fn (Evaluacion $evaluacion) => [
            'codigo' => $evaluacion->inscripcion?->codigo,
            'postulante' => $this->nombrePostulante($evaluacion->inscripcion?->postulante),
            'materia' => $evaluacion->grupoMateria?->materia?->nombre,
            'grupo' => $evaluacion->grupoMateria?->grupo?->codigo,
            'examen_1' => $evaluacion->examen_1,
            'examen_2' => $evaluacion->examen_2,
            'examen_3' => $evaluacion->examen_3,
            'promedio' => $evaluacion->promedio,
            'estado' => $evaluacion->estado,
        ]);
    }

    private function modulosDinamicos(): array
    {
        return [
            'postulantes' => [
                'nombre' => 'Postulantes e inscripciones',
                'columnas' => [
                    ['key' => 'codigo', 'label' => 'Codigo CUP'],
                    ['key' => 'ci', 'label' => 'CI'],
                    ['key' => 'postulante', 'label' => 'Postulante'],
                    ['key' => 'correo', 'label' => 'Correo'],
                    ['key' => 'telefono', 'label' => 'Telefono'],
                    ['key' => 'ciudad', 'label' => 'Ciudad'],
                    ['key' => 'gestion', 'label' => 'Gestion'],
                    ['key' => 'estado', 'label' => 'Estado'],
                    ['key' => 'fecha_inscripcion', 'label' => 'Fecha inscripcion'],
                ],
            ],
            'pagos' => [
                'nombre' => 'Pagos',
                'columnas' => [
                    ['key' => 'codigo', 'label' => 'Codigo CUP'],
                    ['key' => 'postulante', 'label' => 'Postulante'],
                    ['key' => 'monto', 'label' => 'Monto'],
                    ['key' => 'moneda', 'label' => 'Moneda'],
                    ['key' => 'metodo', 'label' => 'Metodo'],
                    ['key' => 'referencia', 'label' => 'Referencia'],
                    ['key' => 'estado', 'label' => 'Estado'],
                    ['key' => 'pagado_en', 'label' => 'Fecha pago'],
                ],
            ],
            'evaluaciones' => [
                'nombre' => 'Evaluaciones y notas',
                'columnas' => [
                    ['key' => 'codigo', 'label' => 'Codigo CUP'],
                    ['key' => 'postulante', 'label' => 'Postulante'],
                    ['key' => 'materia', 'label' => 'Materia'],
                    ['key' => 'grupo', 'label' => 'Grupo'],
                    ['key' => 'examen_1', 'label' => 'Examen 1'],
                    ['key' => 'examen_2', 'label' => 'Examen 2'],
                    ['key' => 'examen_3', 'label' => 'Examen 3'],
                    ['key' => 'promedio', 'label' => 'Promedio'],
                    ['key' => 'estado', 'label' => 'Estado'],
                ],
            ],
            'grupos' => [
                'nombre' => 'Grupos y cupos',
                'columnas' => [
                    ['key' => 'gestion', 'label' => 'Gestion'],
                    ['key' => 'grupo', 'label' => 'Grupo'],
                    ['key' => 'aula', 'label' => 'Aula'],
                    ['key' => 'cupo_maximo', 'label' => 'Cupo maximo'],
                    ['key' => 'estudiantes', 'label' => 'Estudiantes'],
                    ['key' => 'estado', 'label' => 'Estado'],
                ],
            ],
            'docentes' => [
                'nombre' => 'Docentes por grupo',
                'columnas' => [
                    ['key' => 'gestion', 'label' => 'Gestion'],
                    ['key' => 'grupo', 'label' => 'Grupo'],
                    ['key' => 'materia', 'label' => 'Materia'],
                    ['key' => 'docente', 'label' => 'Docente'],
                    ['key' => 'horarios', 'label' => 'Horarios'],
                ],
            ],
            'asignaciones' => [
                'nombre' => 'Asignacion de carreras',
                'columnas' => [
                    ['key' => 'codigo', 'label' => 'Codigo CUP'],
                    ['key' => 'postulante', 'label' => 'Postulante'],
                    ['key' => 'ci', 'label' => 'CI'],
                    ['key' => 'promedio_usado', 'label' => 'Promedio'],
                    ['key' => 'primera_opcion', 'label' => 'Primera opcion'],
                    ['key' => 'segunda_opcion', 'label' => 'Segunda opcion'],
                    ['key' => 'carrera_asignada', 'label' => 'Carrera asignada'],
                    ['key' => 'estado', 'label' => 'Estado'],
                ],
            ],
            'asistencias' => [
                'nombre' => 'Asistencia docente',
                'columnas' => [
                    ['key' => 'fecha', 'label' => 'Fecha'],
                    ['key' => 'gestion', 'label' => 'Gestion'],
                    ['key' => 'grupo', 'label' => 'Grupo'],
                    ['key' => 'materia', 'label' => 'Materia'],
                    ['key' => 'docente', 'label' => 'Docente'],
                    ['key' => 'codigo', 'label' => 'Codigo CUP'],
                    ['key' => 'postulante', 'label' => 'Postulante'],
                    ['key' => 'estado', 'label' => 'Estado'],
                    ['key' => 'observacion', 'label' => 'Observacion'],
                ],
            ],
        ];
    }

    private function respuesta(string $titulo, array $columnas, Collection $filas, array $filtros): array
    {
        return [
            'titulo' => $titulo,
            'tipo' => 'estatico',
            'columnas' => $columnas,
            'filas' => $filas->values()->all(),
            'filtros' => $filtros,
            'generado_en' => now()->toDateTimeString(),
        ];
    }

    private function aplicarFechas(Builder $query, array $filtros, string $columna): void
    {
        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate($columna, '>=', $filtros['fecha_desde']);
        }

        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate($columna, '<=', $filtros['fecha_hasta']);
        }
    }

    private function nombrePostulante($postulante): string
    {
        if (! $postulante) {
            return 'Sin postulante';
        }

        return trim("{$postulante->nombres} {$postulante->apellido_paterno} {$postulante->apellido_materno}");
    }

    private function fecha($fecha): ?string
    {
        if (! $fecha) {
            return null;
        }

        return method_exists($fecha, 'toDateTimeString') ? $fecha->toDateTimeString() : (string) $fecha;
    }

    private function resolveGestionId(array $filtros): int
    {
        $gestionVigente = $this->gestionVigenteService->actual();
        $gestionId = $gestionVigente?->id ?? 0;

        if (isset($filtros['gestion_id']) && (int) $filtros['gestion_id'] !== $gestionId) {
            return 0;
        }

        return $gestionId;
    }
}
