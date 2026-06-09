<?php

namespace App\Http\Controllers\ReportesExportaciones;

use App\Http\Controllers\Controller;

use App\Models\AsignacionCarrera\AsignacionCarrera;
use App\Models\GestionAcademica\Docente;
use App\Models\EvaluacionesResultados\Evaluacion;
use App\Models\GestionAcademica\Gestion;
use App\Models\GestionAcademica\GrupoMateria;
use App\Models\InscripcionPagos\Inscripcion;
use App\Models\InscripcionPagos\Postulante;
use App\Services\GestionAcademica\CupExamenService;
use App\Services\GestionAcademica\GestionVigenteService;
use App\Services\GruposDocentes\AsistenciaDocenteService;
use App\Support\States\GestionState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * [CU16] Consultar carga docente / [CU17] Consultar información postulante / [CU18] Dashboard
 * Vinculación UML: Centraliza el dashboard general administrativo, la carga docente asignada y el portal del postulante.
 */

class DashboardController extends Controller
{
    public function __construct(private readonly GestionVigenteService $gestionVigenteService)
    {
    }

    public function admin(): JsonResponse
    {
        $gestionActiva = $this->gestionVigenteService->actual();
        $gestionId = $gestionActiva?->id;

        $inscripcionesVigentes = Inscripcion::query()
            ->when($gestionId, fn ($query) => $query->where('gestion_id', $gestionId))
            ->when(! $gestionId, fn ($query) => $query->whereRaw('1 = 0'));

        $evaluacionesVigentes = Evaluacion::query()
            ->whereHas('inscripcion', fn ($query) => $gestionId
                ? $query->where('gestion_id', $gestionId)
                : $query->whereRaw('1 = 0'));

        return response()->json([
            'ok' => true,
            'data' => [
                'gestion_activa' => $gestionActiva,
                'resumen' => [
                    'postulantes' => (clone $inscripcionesVigentes)->distinct('postulante_id')->count('postulante_id'),
                    'inscripciones' => (clone $inscripcionesVigentes)->count(),
                    'evaluaciones' => (clone $evaluacionesVigentes)->count(),
                    'asignaciones_carrera' => AsignacionCarrera::whereHas('inscripcion', fn ($query) => $gestionId
                        ? $query->where('gestion_id', $gestionId)
                        : $query->whereRaw('1 = 0'))->count(),
                    'evaluaciones_pendientes' => (clone $evaluacionesVigentes)
                        ->whereIn('estado', ['pendiente', 'incompleto', 'observado'])
                        ->count(),
                ],
            ],
        ]);
    }

    public function docente(Request $request): JsonResponse
    {
        $user = $request->user();

        $docente = Docente::where('ci', $user->numero_registro)
            ->orWhere('correo', $user->email)
            ->first();

        if ($docente !== null && ! $this->gestionVigenteService->docentePuedeAcceder($docente)) {
            return response()->json([
                'ok' => false,
                'message' => 'No pertenece a la gestion vigente. Debe realizar una repostulacion docente desde la pagina inicial.',
            ], 403);
        }

        if ($docente === null) {
            return response()->json([
                'ok' => true,
                'message' => 'No existe un registro docente vinculado a este usuario.',
                'data' => [
                    'docente' => null,
                    'carga' => [],
                ],
            ]);
        }

        $carga = GrupoMateria::with(['materia', 'grupo.gestion', 'grupo.aula', 'horarios.aula'])
            ->where('docente_id', $docente->id)
            ->whereHas('grupo', fn ($query) => $query->where('gestion_id', $this->gestionVigenteService->actual()?->id ?? 0))
            ->orderBy('grupo_id')
            ->get()
            ->map(fn (GrupoMateria $grupoMateria) => [
                'id' => $grupoMateria->id,
                'materia' => $grupoMateria->materia?->nombre,
                'materia_codigo' => $grupoMateria->materia?->codigo,
                'grupo' => $grupoMateria->grupo?->codigo,
                'grupo_nombre' => $grupoMateria->grupo?->nombre,
                'gestion' => $grupoMateria->grupo?->gestion?->nombre,
                'aula' => $grupoMateria->grupo?->aula?->nombre ?? $grupoMateria->grupo?->aula?->codigo,
                'horarios' => $grupoMateria->horarios->map(fn ($horario) => [
                    'dia' => $this->diaSemana((int) $horario->dia_semana),
                    'dia_numero' => (int) $horario->dia_semana,
                    'hora_inicio' => substr((string) $horario->hora_inicio, 0, 5),
                    'hora_fin' => substr((string) $horario->hora_fin, 0, 5),
                    'aula' => $horario->aula?->codigo ?? $horario->aula?->nombre ?? $grupoMateria->grupo?->aula?->codigo,
                    'modalidad' => $horario->modalidad,
                ])->values(),
                'estado_grupo' => $grupoMateria->grupo?->estado,
            ])
            ->values();

        return response()->json([
            'ok' => true,
            'data' => [
                'docente' => $docente,
                'carga' => $carga,
                'resumen_asistencia' => app(AsistenciaDocenteService::class)->resumenDocente($request->user()),
            ],
        ]);
    }

    public function postulante(Request $request): JsonResponse
    {
        $user = $request->user();

        $postulante = Postulante::query()
            ->where('correo', $user->email)
            ->orWhere('ci', $user->numero_registro)
            ->with([
                'inscripciones' => fn ($query) => $query->latest('id'),
                'inscripciones.gestion',
                'inscripciones.validacionDocumental',
                'inscripciones.documentos',
                'inscripciones.grupos.aula',
                'inscripciones.evaluaciones.grupoMateria.materia',
                'inscripciones.evaluaciones.grupoMateria.horarios.aula',
                'inscripciones.resultadoCup',
                'inscripciones.asignacionCarrera.carrera',
            ])
            ->first();

        if ($postulante === null) {
            return response()->json([
                'ok' => true,
                'message' => 'No existe un registro de postulante vinculado a este usuario.',
                'data' => [
                    'postulante' => null,
                    'inscripcion' => null,
                    'grupo' => null,
                    'evaluaciones' => [],
                    'resultado' => null,
                    'asignacion_carrera' => null,
                ],
            ]);
        }

        $gestionVigente = $this->gestionVigenteService->actual();
        $inscripcion = $this->gestionVigenteService->inscripcionEnGestionVigente($postulante, $gestionVigente);

        if ($inscripcion === null) {
            return response()->json([
                'ok' => false,
                'message' => 'No pertenece a la gestion vigente. Debe realizar una repostulacion desde la pagina inicial.',
            ], 403);
        }

        if ($inscripcion->resultadoCup?->estado_final === 'reprobado') {
            return response()->json([
                'ok' => false,
                'message' => 'Su gestion fue reprobada. Debe realizar una repostulacion desde la pagina inicial.',
            ], 403);
        }

        $grupo = $inscripcion?->grupos->first();
        $resumenCup = app(CupExamenService::class)->resumen($inscripcion);

        return response()->json([
            'ok' => true,
            'data' => [
                'postulante' => $postulante->only(['id', 'ci', 'nombres', 'apellido_paterno', 'apellido_materno', 'correo']),
                'inscripcion' => $inscripcion ? [
                    'id' => $inscripcion->id,
                    'codigo' => $inscripcion->codigo,
                    'estado' => $inscripcion->estado,
                    'gestion' => $inscripcion->gestion?->nombre,
                    'validacion_documental' => $inscripcion->validacionDocumental ? [
                        'estado' => $inscripcion->validacionDocumental->estado,
                        'observacion' => $inscripcion->validacionDocumental->observacion,
                        'validado_en' => $inscripcion->validacionDocumental->validado_en?->toISOString(),
                    ] : null,
                    'documentos' => $inscripcion->documentos->map(fn ($doc) => [
                        'tipo' => $doc->tipo,
                        'estado' => $doc->estado,
                        'observacion' => $doc->observacion ?? null,
                    ])->values(),
                ] : null,
                'grupo' => $grupo ? [
                    'id' => $grupo->id,
                    'codigo' => $grupo->codigo,
                    'nombre' => $grupo->nombre,
                    'aula' => $grupo->aula?->nombre ?? $grupo->aula?->codigo,
                    'estado' => $grupo->estado,
                ] : null,
                'evaluaciones' => $inscripcion?->evaluaciones->map(fn (Evaluacion $evaluacion) => [
                    'id' => $evaluacion->id,
                    'materia' => $evaluacion->grupoMateria?->materia?->nombre,
                    'examen_1' => $evaluacion->examen_1,
                    'examen_2' => $evaluacion->examen_2,
                    'examen_3' => $evaluacion->examen_3,
                    'promedio' => $evaluacion->promedio,
                    'estado' => $evaluacion->estado,
                ])->values() ?? [],
                'examen_cup' => $resumenCup['examen_cup'],
                'materias_cup' => $resumenCup['materias_cup'],
                'resultado' => $inscripcion?->resultadoCup,
                'asignacion_carrera' => $inscripcion?->asignacionCarrera ? [
                    'estado' => $inscripcion->asignacionCarrera->estado,
                    'carrera' => $inscripcion->asignacionCarrera->carrera?->nombre,
                    'opcion_prioridad' => $inscripcion->asignacionCarrera->opcion_prioridad,
                    'promedio_usado' => $inscripcion->asignacionCarrera->promedio_usado,
                ] : null,
            ],
        ]);
    }

    private function diaSemana(int $dia): string
    {
        return match ($dia) {
            1 => 'Lun',
            2 => 'Mar',
            3 => 'Mie',
            4 => 'Jue',
            5 => 'Vie',
            6 => 'Sab',
            default => 'Dom',
        };
    }
}
