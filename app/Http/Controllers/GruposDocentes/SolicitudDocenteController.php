<?php

namespace App\Http\Controllers\GruposDocentes;

use App\Http\Controllers\Controller;

use App\Http\Requests\StoreSolicitudDocenteRequest;
use App\Services\GruposDocentes\SolicitudDocenteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * CU14 - Gestionar solicitudes docentes
 * Permite que docentes presenten solicitudes y que administración revise, apruebe, observe o rechace documentos.
 */
class SolicitudDocenteController extends Controller
{
    public function __construct(private readonly SolicitudDocenteService $service)
    {
    }

    public function create(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'message' => 'Datos de postulacion docente cargados correctamente.',
            'data' => $this->service->datosFormulario(),
        ]);
    }

    public function store(StoreSolicitudDocenteRequest $request): JsonResponse
    {
        try {
            $solicitud = $this->service->registrar($request->validated());
        } catch (ValidationException $exception) {
            return response()->json([
                'ok' => false,
                'message' => 'Revise los datos ingresados.',
                'errors' => $exception->errors(),
            ], 422);
        } catch (\DomainException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
                'errors' => [],
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Solicitud docente enviada correctamente. Sera revisada por administracion. Si es aceptada, recibira su codigo docente y contrasena por correo electronico.',
            'data' => [
                'solicitud' => $solicitud,
            ],
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => [
                'solicitudes' => $this->service->listar($request->only('estado')),
            ],
        ]);
    }

    public function revisarDocumento(Request $request, int $documentoId): JsonResponse
    {
        $validated = $request->validate([
            'estado' => ['required', 'string', 'in:aprobado,observado,rechazado'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $documento = $this->service->revisarDocumento(
            $documentoId,
            $request->user(),
            $validated['estado'],
            $validated['observacion'] ?? null
        );

        return response()->json([
            'ok' => true,
            'message' => 'Documento revisado correctamente.',
            'data' => [
                'documento' => $documento,
            ],
        ]);
    }

    public function aprobar(Request $request, int $solicitudId): JsonResponse
    {
        try {
            $resultado = $this->service->aprobar($solicitudId, $request->user());
        } catch (\DomainException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => $resultado['credenciales']['correo_enviado']
                ? 'Solicitud aprobada. Se enviaron credenciales al correo del docente.'
                : 'Solicitud aprobada. No se pudo enviar el correo, muestre las credenciales generadas.',
            'data' => $resultado,
        ]);
    }

    public function observar(Request $request, int $solicitudId): JsonResponse
    {
        $validated = $request->validate([
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Solicitud marcada como observada.',
            'data' => [
                'solicitud' => $this->service->observar($solicitudId, $request->user(), $validated['observacion'] ?? null),
            ],
        ]);
    }

    public function rechazar(Request $request, int $solicitudId): JsonResponse
    {
        $validated = $request->validate([
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Solicitud rechazada.',
            'data' => [
                'solicitud' => $this->service->rechazar($solicitudId, $request->user(), $validated['observacion'] ?? null),
            ],
        ]);
    }
}
