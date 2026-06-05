<?php

namespace App\Http\Controllers\GruposDocentes;

use App\Http\Controllers\Controller;

use App\Http\Requests\StoreGrupoRequest;
use App\Services\GestionAcademica\ParametrizacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * [CU13] Gestionar grupos (cálculo CEIL y distribución)
 * Vinculación UML: Creación y listado de grupos de nivelación académica.
 */

class GrupoController extends Controller
{
    public function __construct(private readonly ParametrizacionService $parametrizacionService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $gestionId = $request->query('gestion_id');
        return response()->json([
            'ok' => true,
            'data' => [
                'grupos' => $this->parametrizacionService->listarGrupos($gestionId)
            ]
        ]);
    }

    public function store(StoreGrupoRequest $request): JsonResponse
    {
        $grupo = $this->parametrizacionService->crearGrupo($request->validated());

        return response()->json([
            'ok' => true,
            'message' => 'Grupo creado correctamente.',
            'data' => [
                'grupo' => $grupo
            ]
        ], 201);
    }
}
