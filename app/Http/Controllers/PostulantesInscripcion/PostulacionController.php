<?php

namespace App\Http\Controllers\PostulantesInscripcion;

use App\Http\Controllers\Controller;

use App\Http\Requests\StorePostulacionRequest;
use App\Services\PortalPostulante\PostulacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * CU02 - Registrar postulación CUP
 * Permite registrar una nueva postulación para la gestión vigente, validando datos, creando postulante e inscripción.
 *
 * Participantes del CU02 (Diagrama de Secuencia):
 * - Actor: Postulante
 * - Boundary: UI_FormPostulacion (Vue)
 * - Control: PostulacionController (Actual)
 * - Control: PostulacionService
 * - Entity: Postulante, Inscripcion, OpcionCarrera, Documento
 * - Control: AuditLogService
 */
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
                'message' => 'Solicitud enviada correctamente. Sera revisada por administracion. Si es aceptada, recibira su numero de registro y contrasena por correo electronico.',
                'data' => [
                    'inscripcion' => $inscripcion,
                ],
            ], 201);
        } catch (ValidationException $exception) {
            return response()->json([
                'ok' => false,
                'message' => 'Revise los datos ingresados.',
                'errors' => $exception->errors(),
            ], 422);
        } catch (\DomainException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'errors' => [],
            ], 422);
        }
    }

    /**
     * Endpoint para que el postulante subsane/corrija un documento observado o rechazado.
     */
    public function subsanarDocumento(\Illuminate\Http\Request $request, string $codigoInscripcion, int $documentoId): JsonResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048']
        ]);

        $inscripcion = \App\Models\InscripcionPagos\Inscripcion::where('codigo', $codigoInscripcion)->firstOrFail();
        
        $documento = \App\Models\InscripcionPagos\Documento::where('inscripcion_id', $inscripcion->id)
            ->where('id', $documentoId)
            ->firstOrFail();

        if ($documento->estado !== 'observado' && $documento->estado !== 'rechazado') {
            return response()->json(['ok' => false, 'message' => 'Este documento no requiere corrección.'], 422);
        }

        // Subir nuevo archivo
        $path = $request->file('archivo')->store('documentos/' . $inscripcion->codigo, 'local');

        // Actualizar estado a pendiente
        $documento->update([
            'archivo_path' => $path,
            'estado' => 'pendiente',
            'observacion' => null
        ]);

        // Cambiar el estado global de la validación documental para que vuelva a ser revisado
        if ($inscripcion->validacionDocumental) {
            $inscripcion->validacionDocumental->update([
                'estado' => \App\Support\States\ValidacionDocumentalState::PENDIENTE
            ]);
        }
        
        $inscripcion->update([
            'estado' => \App\Support\States\InscripcionState::DOCUMENTOS_PENDIENTES
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Documento subsanado correctamente. Queda a la espera de una nueva revisión.',
            'data' => $documento
        ]);
    }

    /**
     * Endpoint para obtener la lista de documentos que requieren corrección
     */
    public function obtenerDocumentosObservados(string $codigoInscripcion): JsonResponse
    {
        $inscripcion = \App\Models\InscripcionPagos\Inscripcion::with('documentos')
            ->where('codigo', $codigoInscripcion)
            ->firstOrFail();

        $documentosMalos = $inscripcion->documentos->filter(function ($doc) {
            return $doc->estado === 'observado' || $doc->estado === 'rechazado';
        })->values();

        return response()->json([
            'ok' => true,
            'data' => [
                'inscripcion' => [
                    'codigo' => $inscripcion->codigo,
                    'estado' => $inscripcion->estado,
                ],
                'documentos_a_subsanar' => $documentosMalos
            ]
        ]);
    }
}
