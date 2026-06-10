<?php

namespace App\Http\Controllers\PostulantesInscripcion;

use App\Http\Controllers\Controller;

use App\Http\Requests\ValidarDocumentosRequest;
use App\Services\PortalPostulante\ValidacionDocumentalService;
use Illuminate\Http\JsonResponse;

/**
 * CU03 - Validar requisitos documentales
 * Permite revisar y aprobar u observar documentos presentados en la inscripción CUP.
 *
 * Participantes del CU03 (Diagrama de Secuencia):
 * - Actor: Administrador
 * - Boundary: UI_ValidacionDocs (Vue)
 * - Control: ValidacionDocumentalController (Actual)
 * - Control: ValidacionDocumentalService
 * - Entity: Documento, ValidacionDocumental, Inscripcion
 * - Control: AuditLogService
 */
class ValidacionDocumentalController extends Controller
{
    public function __construct(private readonly ValidacionDocumentalService $validacionService)
    {
    }

    /**
     * Retorna la lista de inscripciones de validacion documental filtradas por estado.
     */
    public function index(\Illuminate\Http\Request $request): JsonResponse
    {
        $estado = $request->query('estado', 'pendientes');
        $inscripciones = $this->validacionService->listarConFiltro($estado);

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
