<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostulacionRequest;
use App\Services\PostulacionService;
use Illuminate\Http\JsonResponse;

class PostulacionController extends Controller
{
    public function __construct(private readonly PostulacionService $postulacionService)
    {
    }

    /**
     * Retorna los datos necesarios para armar el formulario de postulación:
     * gestión vigente habilitada y lista de carreras activas.
     */
    public function create(): JsonResponse
    {
        $datos = $this->postulacionService->datosFormulario();

        if ($datos['gestion'] === null) {
            return response()->json([
                'ok' => false,
                'message' => 'No hay una gestión habilitada para inscripción en este momento.',
                'data' => [],
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Datos del formulario cargados correctamente.',
            'data' => $datos,
        ]);
    }

    /**
     * Registra una nueva postulación CUP.
     *
     * Delega toda la lógica de negocio al PostulacionService.
     */
    public function store(StorePostulacionRequest $request): JsonResponse
    {
        try {
            $inscripcion = $this->postulacionService->registrar(
                $request->validated(),
                $request
            );

            return response()->json([
                'ok' => true,
                'message' => 'Postulación registrada correctamente.',
                'data' => [
                    'inscripcion' => $inscripcion,
                ],
            ], 201);
        } catch (\DomainException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'errors' => [],
            ], 422);
        }
    }
}
