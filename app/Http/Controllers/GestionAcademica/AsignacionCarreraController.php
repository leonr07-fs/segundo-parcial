<?php

namespace App\Http\Controllers\GestionAcademica;

use App\Http\Controllers\Controller;
use App\Models\AsignacionCarrera\AsignacionCarrera;
use App\Models\AsignacionCarrera\Carrera;
use App\Models\AsignacionCarrera\CupoCarrera;
use App\Models\GestionAcademica\Gestion;
use App\Services\GestionAcademica\AsignacionCarreraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Services\GestionAcademica\GestionVigenteService;

/**
 * [CU12] Asignar carrera por cupos (1ra y 2da opción)
 * Vinculación UML: Permite definir cupos por carrera y ejecutar el algoritmo de asignación de carrera a estudiantes aprobados.
 */

/**
 * CU12 - Asignar carrera por cupos
 *
 * Participantes del CU12 (Diagrama de Secuencia):
 * - Actor: Administrador
 * - Boundary: UI_AsignacionCarreras (Vue)
 * - Control: AsignacionCarreraController (Actual)
 * - Control: AsignacionCarreraService
 * - Entity: PostulanteCarrera
 */
class AsignacionCarreraController extends Controller
{
    public function __construct(
        private readonly AsignacionCarreraService $service,
        private readonly GestionVigenteService $gestionVigenteService,
    ) {
    }

    public function ejecutar(Request $request): JsonResponse
    {
        $gestion = $this->resolverGestion($request);
        $stats = $this->service->ejecutarAsignacion($gestion->id);

        return response()->json([
            'ok' => true,
            'message' => 'Proceso de asignacion ejecutado con exito',
            'stats' => $stats,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $gestion = $this->resolverGestion($request);

        $carreras = Carrera::query()
            ->where('activa', true)
            ->orderBy('nombre')
            ->get();

        $cupos = CupoCarrera::with('carrera')
            ->where('gestion_id', $gestion->id)
            ->orderBy('carrera_id')
            ->get();

        $asignaciones = AsignacionCarrera::with(['inscripcion.postulante', 'carrera'])
            ->whereHas('inscripcion', fn ($query) => $query->where('gestion_id', $gestion->id))
            ->orderBy('promedio_usado', 'desc')
            ->paginate(50);

        return response()->json([
            'ok' => true,
            'data' => [
                'gestion' => $gestion,
                'gestiones' => Gestion::orderByDesc('created_at')->get(),
                'carreras' => $carreras,
                'cupos' => $cupos,
                'asignaciones' => $asignaciones,
            ],
        ]);
    }

    public function guardarCupos(Request $request): JsonResponse
    {
        $gestion = $this->resolverGestion($request);

        $datos = $request->validate([
            'gestion_id' => ['nullable', 'integer', 'exists:gestiones,id'],
            'cupos' => ['required', 'array', 'min:1'],
            'cupos.*.carrera_id' => ['required', 'integer', 'exists:carreras,id'],
            'cupos.*.cupo_total' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($datos['cupos'] as $item) {
            $ocupados = AsignacionCarrera::query()
                ->where('carrera_id', $item['carrera_id'])
                ->whereHas('inscripcion', fn ($query) => $query->where('gestion_id', $gestion->id))
                ->count();

            CupoCarrera::updateOrCreate(
                [
                    'gestion_id' => $gestion->id,
                    'carrera_id' => $item['carrera_id'],
                ],
                [
                    'cupo_total' => $item['cupo_total'],
                    'cupo_disponible' => max($item['cupo_total'] - $ocupados, 0),
                ]
            );
        }

        $cupos = CupoCarrera::with('carrera')
            ->where('gestion_id', $gestion->id)
            ->orderBy('carrera_id')
            ->get();

        return response()->json([
            'ok' => true,
            'message' => 'Cupos por carrera guardados correctamente.',
            'data' => [
                'cupos' => $cupos,
            ],
        ]);
    }

    private function resolverGestion(Request $request): Gestion
    {
        $gestionId = $request->input('gestion_id', $request->query('gestion_id'));
        $gestionVigente = $this->gestionVigenteService->actual();

        if ($gestionId !== null) {
            $gestion = Gestion::findOrFail((int) $gestionId);
            if (!$gestionVigente || $gestion->id !== $gestionVigente->id) {
                throw new \DomainException('La gestion seleccionada esta deshabilitada/cerrada.');
            }
            return $gestion;
        }

        if (!$gestionVigente) {
            throw new \DomainException('No hay ninguna gestion activa o habilitada.');
        }

        return $gestionVigente;
    }
}
