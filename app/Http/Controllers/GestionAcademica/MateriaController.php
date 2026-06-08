<?php

namespace App\Http\Controllers\GestionAcademica;

use App\Http\Controllers\Controller;

use App\Http\Requests\StoreMateriaRequest;
use App\Services\GestionAcademica\ParametrizacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CU08 - Parametrizar gestiones, materias, aulas y grupos
 * Gestiona la configuración de materias disponibles para el proceso académico.
 */
class MateriaController extends Controller
{
    public function __construct(private readonly ParametrizacionService $parametrizacionService)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => [
                'materias' => $this->parametrizacionService->listarMaterias()
            ]
        ]);
    }

    public function store(StoreMateriaRequest $request): JsonResponse
    {
        $materia = $this->parametrizacionService->crearMateria($request->validated());

        return response()->json([
            'ok' => true,
            'message' => 'Materia creada correctamente.',
            'data' => [
                'materia' => $materia
            ]
        ], 201);
    }

    public function actualizarEstado(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'activa' => ['required', 'boolean'],
        ]);

        $materia = $this->parametrizacionService->actualizarEstadoMateria($id, (bool) $validated['activa']);

        return response()->json([
            'ok' => true,
            'message' => $materia->activa ? 'Materia habilitada correctamente.' : 'Materia inhabilitada correctamente.',
            'data' => [
                'materia' => $materia,
            ],
        ]);
    }
}
