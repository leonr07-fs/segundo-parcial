<?php

namespace App\Http\Controllers\GruposDocentes;

use App\Http\Controllers\Controller;

use App\Models\AsistenciaDocente\Asistencia;
use App\Services\GruposDocentes\AsistenciaDocenteService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AsistenciaDocenteController extends Controller
{
    public function __construct(private readonly AsistenciaDocenteService $asistencias)
    {
    }

    public function show(Request $request, int $grupoMateriaId): JsonResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'data' => $this->asistencias->obtenerPlanilla($request->user(), $grupoMateriaId, $data['fecha']),
            ]);
        } catch (AuthorizationException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 403);
        }
    }

    public function store(Request $request, int $grupoMateriaId): JsonResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'asistencias' => ['required', 'array', 'min:1'],
            'asistencias.*.inscripcion_id' => ['required', 'integer', 'exists:inscripciones,id'],
            'asistencias.*.estado' => ['required', 'string', Rule::in(Asistencia::ESTADOS)],
            'asistencias.*.observacion' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $resultado = $this->asistencias->registrar(
                $request->user(),
                $grupoMateriaId,
                $data['fecha'],
                $data['asistencias'],
            );
        } catch (AuthorizationException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 403);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Asistencia registrada correctamente.',
            'data' => $resultado,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'gestion_id' => ['nullable', 'integer', 'exists:gestiones,id'],
            'docente_id' => ['nullable', 'integer', 'exists:docentes,id'],
        ]);

        return response()->json([
            'ok' => true,
            'data' => [
                'asistencias' => $this->asistencias->reporte($filtros),
                'resumen_docentes' => $this->asistencias->resumenAdminPorDocente($filtros),
            ],
        ]);
    }
}
