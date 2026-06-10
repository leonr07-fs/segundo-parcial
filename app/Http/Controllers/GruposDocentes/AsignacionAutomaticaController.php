<?php

namespace App\Http\Controllers\GruposDocentes;

use App\Http\Controllers\Controller;

use App\Services\GruposDocentes\AsignacionAutomaticaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * [CU13] y [CU15] Asignación automática de grupos y horarios
 * Vinculación UML: Generación automática de propuesta de grupos, aulas, docentes y horarios con distribución semanal.
 */

/**
 * CU13 - Asignar grupos, materias, docentes, aulas y horarios
 *
 * Participantes del CU13 (Diagrama de Secuencia):
 * - Actor: Administrador
 * - Boundary: UI_Grupos (Vue)
 * - Control: AsignacionAutomaticaController (Actual)
 * - Control: AsignacionAutomaticaService
 * - Entity: Grupo
 */
class AsignacionAutomaticaController extends Controller
{
    public function __construct(private readonly AsignacionAutomaticaService $service)
    {
    }

    public function generar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gestion_id' => ['required', 'integer', 'exists:gestiones,id'],
        ]);

        $propuesta = $this->service->generarPropuesta((int) $validated['gestion_id']);

        return response()->json([
            'ok' => $propuesta['errores'] === [],
            'message' => $propuesta['errores'] === []
                ? 'Propuesta generada correctamente.'
                : 'La propuesta tiene errores que deben corregirse.',
            'data' => [
                'propuesta' => $propuesta,
            ],
        ], $propuesta['errores'] === [] ? 200 : 422);
    }

    public function confirmar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gestion_id' => ['required', 'integer', 'exists:gestiones,id'],
        ]);

        try {
            $resultado = $this->service->confirmarPropuesta((int) $validated['gestion_id']);
        } catch (RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Asignacion automatica confirmada correctamente.',
            'data' => [
                'resultado' => $resultado,
            ],
        ]);
    }

    public function rellenarRezagados(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gestion_id' => ['required', 'integer', 'exists:gestiones,id'],
        ]);

        try {
            $resultado = $this->service->asignarRezagados((int) $validated['gestion_id']);
        } catch (RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Estudiantes rezagados asignados correctamente a los grupos con cupos disponibles.',
            'data' => [
                'resultado' => $resultado,
            ],
        ]);
    }
}
