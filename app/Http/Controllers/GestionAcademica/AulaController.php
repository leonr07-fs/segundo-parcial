<?php

namespace App\Http\Controllers\GestionAcademica;

use App\Http\Controllers\Controller;

use App\Http\Requests\StoreAulaRequest;
use App\Services\GestionAcademica\ParametrizacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AulaController extends Controller
{
    public function __construct(private readonly ParametrizacionService $parametrizacionService)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => [
                'aulas' => $this->parametrizacionService->listarAulas()
            ]
        ]);
    }

    public function store(StoreAulaRequest $request): JsonResponse
    {
        $aula = $this->parametrizacionService->crearAula($request->validated());

        return response()->json([
            'ok' => true,
            'message' => 'Aula creada correctamente.',
            'data' => [
                'aula' => $aula
            ]
        ], 201);
    }

    public function actualizarCapacidad(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'capacidad' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        $aula = $this->parametrizacionService->actualizarCapacidadAula($id, (int) $validated['capacidad']);

        return response()->json([
            'ok' => true,
            'message' => 'Capacidad del aula actualizada correctamente.',
            'data' => [
                'aula' => $aula,
            ],
        ]);
    }
}
