<?php

namespace App\Http\Controllers\GruposDocentes;

use App\Http\Controllers\Controller;

use App\Services\GestionAcademica\ParametrizacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * [CU15] Asignar docentes, horarios y aulas
 * Vinculación UML: Asignación manual de planificación académica (horarios presenciales y virtuales).
 */

/**
 * CU15 - Registrar asistencia docente (y asignar materias)
 *
 * Participantes del CU15 (Diagrama de Secuencia):
 * - Actor: Administrador
 * - Boundary: UI_AsignacionMateriaGrupo (Vue)
 * - Control: GrupoMateriaController (Actual)
 * - Control: ParametrizacionService
 * - Entity: GrupoMateria, Horario
 */
class GrupoMateriaController extends Controller
{
    public function __construct(private readonly ParametrizacionService $parametrizacionService)
    {
    }

    public function index(int $grupoId): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => [
                'materias' => $this->parametrizacionService->listarMateriasDeGrupo($grupoId)
            ]
        ]);
    }

    public function store(Request $request, int $grupoId): JsonResponse
    {
        $validated = $request->validate([
            'materia_id' => 'required|integer|exists:materias,id',
            'docente_id' => 'nullable|integer|exists:docentes,id',
            'dia_semana' => 'required|integer|between:1,7',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio',
        ]);

        try {
            $materiaAsignada = $this->parametrizacionService->asignarMateriaAGrupo($grupoId, $validated);

            return response()->json([
                'ok' => true,
                'message' => 'Materia asignada al grupo correctamente.',
                'data' => [
                    'materia' => $materiaAsignada
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
