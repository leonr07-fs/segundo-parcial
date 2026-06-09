<?php

namespace App\Http\Controllers\PortalPostulante;

use App\Http\Controllers\Controller;

use App\Http\Requests\StorePagoRequest;
use App\Services\PortalPostulante\PagoService;
use Illuminate\Http\JsonResponse;

/**
 * CU04 - Registrar/verificar pago CUP y confirmar inscripción
 * Permite registrar pagos de inscripciones y confirmar el estado de inscripción cuando el pago es aprobado.
 */
class PagoController extends Controller
{
    public function __construct(private readonly PagoService $pagoService)
    {
    }

    /**
     * Listar inscripciones de pago filtradas por estado.
     */
    public function index(\Illuminate\Http\Request $request): JsonResponse
    {
        $estado = $request->query('estado', 'pendientes');
        $inscripciones = $this->pagoService->listarPagosConFiltro($estado);

        return response()->json([
            'ok' => true,
            'data' => [
                'inscripciones' => $inscripciones,
            ],
        ]);
    }

    /**
     * Registra y aprueba un pago, confirmando la inscripción.
     */
    public function store(StorePagoRequest $request, int $id): JsonResponse
    {
        try {
            $resultado = $this->pagoService->registrarPago(
                $id,
                $request->validated(),
                $request
            );

            return response()->json([
                'ok' => true,
                'message' => 'Pago verificado e inscripción confirmada.',
                'data' => $resultado,
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422); // Unprocessable Entity por regla de negocio
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Ocurrió un error interno al registrar el pago.',
            ], 500);
        }
    }

    /**
     * Retorna los detalles de un pago registrado.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $resultado = $this->pagoService->obtenerDetallesPago($id);

            return response()->json([
                'ok' => true,
                'data' => $resultado,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'No se pudo cargar el detalle del pago.',
            ], 404);
        }
    }
}
