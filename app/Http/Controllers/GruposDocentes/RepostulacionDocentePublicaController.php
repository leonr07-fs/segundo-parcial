<?php

namespace App\Http\Controllers\GruposDocentes;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidarRepostulacionDocenteRequest;
use App\Services\GruposDocentes\RepostulacionDocenteService;
use Illuminate\Http\JsonResponse;

class RepostulacionDocentePublicaController extends Controller
{
    public function __construct(private readonly RepostulacionDocenteService $service)
    {
    }

    public function store(ValidarRepostulacionDocenteRequest $request): JsonResponse
    {
        try {
            $data = $this->service->registrarSolicitud(
                $request->validated('ci'),
                $request->validated('correo'),
            );

            return response()->json([
                'ok' => true,
                'message' => 'Solicitud de repostulación registrada. Queda pendiente de aprobación por administración.',
                'data' => $data,
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
