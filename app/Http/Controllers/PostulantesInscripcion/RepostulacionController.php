<?php

namespace App\Http\Controllers\PostulantesInscripcion;

use App\Http\Controllers\Controller;

use App\Http\Requests\StoreRepostulacionRequest;
use App\Services\PortalPostulante\RepostulacionService;
use Illuminate\Http\JsonResponse;

class RepostulacionController extends Controller
{
    public function __construct(private readonly RepostulacionService $repostulacionService)
    {
    }

    /**
     * Habilita una repostulación para una nueva gestión.
     */
    public function store(StoreRepostulacionRequest $request): JsonResponse
    {
        try {
            $inscripcion = $this->repostulacionService->repostular($request->validated());

            return response()->json([
                'ok' => true,
                'message' => 'Repostulación registrada correctamente.',
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
