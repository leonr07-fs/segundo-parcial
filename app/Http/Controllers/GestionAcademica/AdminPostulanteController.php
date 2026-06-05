<?php

namespace App\Http\Controllers\GestionAcademica;

use App\Http\Controllers\Controller;

use App\Http\Requests\AnularInscripcionRequest;
use App\Http\Requests\UpdatePostulanteRequest;
use App\Services\GestionAcademica\AdminPostulanteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPostulanteController extends Controller
{
    public function __construct(private readonly AdminPostulanteService $adminPostulanteService)
    {
    }

    /**
     * Listar y buscar postulantes.
     */
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->only(['search', 'gestion_id']);
        $postulantes = $this->adminPostulanteService->buscar($filtros);

        return response()->json([
            'ok' => true,
            'message' => 'Postulantes obtenidos correctamente.',
            'data' => [
                'postulantes' => $postulantes
            ],
        ]);
    }

    /**
     * Ver expediente completo.
     */
    public function show(int $id): JsonResponse
    {
        $postulante = $this->adminPostulanteService->expedienteCompleto($id);

        return response()->json([
            'ok' => true,
            'message' => 'Expediente obtenido correctamente.',
            'data' => [
                'postulante' => $postulante
            ],
        ]);
    }

    /**
     * Actualizar datos permitidos del postulante.
     */
    public function update(UpdatePostulanteRequest $request, int $id): JsonResponse
    {
        $postulante = $this->adminPostulanteService->actualizar($id, $request->validated());

        return response()->json([
            'ok' => true,
            'message' => 'Postulante actualizado correctamente.',
            'data' => [
                'postulante' => $postulante
            ],
        ]);
    }

    public function anularInscripcion(AnularInscripcionRequest $request, int $postulanteId, int $inscripcionId): JsonResponse
    {
        try {
            $inscripcion = $this->adminPostulanteService->anularInscripcion(
                $postulanteId,
                $inscripcionId,
                $request->validated('motivo'),
                $request->user(),
                $request
            );
        } catch (\DomainException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
                'errors' => [],
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Postulacion anulada correctamente.',
            'data' => [
                'inscripcion' => $inscripcion,
            ],
        ]);
    }
}
