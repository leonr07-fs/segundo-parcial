<?php

namespace App\Http\Controllers\PortalPostulante;

use App\Http\Controllers\Controller;

use App\Http\Requests\ValidarDocumentosRequest;
use App\Services\PortalPostulante\ValidacionDocumentalService;
use Illuminate\Http\JsonResponse;

class ValidacionDocumentalController extends Controller
{
    public function __construct(private readonly ValidacionDocumentalService $validacionService)
    {
    }

    /**
     * Retorna la lista de inscripciones pendientes de validacion documental.
     */
    public function index(): JsonResponse
    {
        $inscripciones = $this->validacionService->listarPendientes();

        return response()->json([
            'ok' => true,
            'data' => [
                'inscripciones' => $inscripciones,
            ],
        ]);
    }

    /**
     * Retorna el expediente documental de una inscripcion.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $expediente = $this->validacionService->obtenerExpediente($id);

            return response()->json([
                'ok' => true,
                'data' => $expediente,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'No se pudo cargar el expediente documental.',
            ], 404);
        }
    }

    /**
     * Registra la validacion documental por cada documento.
     */
    public function store(ValidarDocumentosRequest $request, int $id): JsonResponse
    {
        try {
            $resultado = $this->validacionService->validarDocumentos(
                $id,
                $request->validated('revisiones'),
                $request
            );

            $mensaje = $resultado['credenciales'] !== null
                ? 'Validacion aprobada. Se genero el numero de registro y la contrasena temporal del postulante.'
                : 'Validacion documental registrada correctamente.';

            return response()->json([
                'ok' => true,
                'message' => $mensaje,
                'data' => $resultado,
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Ocurrio un error al procesar la validacion.',
            ], 500);
        }
    }
}
