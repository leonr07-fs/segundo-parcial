<?php

namespace App\Http\Controllers\GruposDocentes;

use App\Http\Controllers\Controller;
use App\Services\GruposDocentes\RepostulacionDocenteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RepostulacionDocenteAdminController extends Controller
{
    public function __construct(private readonly RepostulacionDocenteService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => [
                'repostulaciones' => $this->service->listar($request->only('estado')),
            ],
        ]);
    }

    public function aprobar(Request $request, int $repostulacionId): JsonResponse
    {
        try {
            $resultado = $this->service->aprobar($repostulacionId, $request->user(), $request);
        } catch (\DomainException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => $resultado['credenciales']['correo_enviado']
                ? 'Repostulación aprobada. Se enviaron credenciales al correo del docente.'
                : 'Repostulación aprobada. No se pudo enviar el correo; comunique las credenciales manualmente.',
            'data' => $resultado,
        ]);
    }

    public function rechazar(Request $request, int $repostulacionId): JsonResponse
    {
        $validated = $request->validate([
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $repostulacion = $this->service->rechazar(
                $repostulacionId,
                $request->user(),
                $validated['observacion'] ?? null,
                $request,
            );
        } catch (\DomainException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Repostulación rechazada.',
            'data' => [
                'repostulacion' => $repostulacion,
            ],
        ]);
    }
}
