<?php

namespace App\Http\Controllers\PortalPostulante;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidarRepostulacionPublicaRequest;
use App\Models\InscripcionPagos\Inscripcion;
use App\Models\InscripcionPagos\Postulante;
use App\Services\PortalPostulante\PayPalPagoProcessor;
use App\Services\PortalPostulante\PayPalService;
use App\Services\PortalPostulante\RepostulacionService;
use App\Support\States\InscripcionState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CU06 - Habilitar repostulación en nueva gestión
 * Gestiona la validación de elegibilidad y el pago de repostulación para postulantes reprobados.
 */
class RepostulacionPublicaController extends Controller
{
    public function __construct(
        private readonly RepostulacionService $repostulacionService,
        private readonly PayPalService $payPalService,
        private readonly PayPalPagoProcessor $payPalPagoProcessor,
    ) {
    }

    public function validar(ValidarRepostulacionPublicaRequest $request): JsonResponse
    {
        try {
            $data = $this->repostulacionService->validarElegibilidad(
                $request->validated('ci'),
                $request->validated('correo'),
            );

            return response()->json([
                'ok' => true,
                'message' => 'Elegible para repostulación.',
                'data' => $data,
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'errors' => [],
            ], 422);
        }
    }

    public function preparar(ValidarRepostulacionPublicaRequest $request): JsonResponse
    {
        try {
            $inscripcion = $this->repostulacionService->prepararRepostulacion(
                $request->validated('ci'),
                $request->validated('correo'),
            );

            if ($inscripcion->estado !== InscripcionState::DOCUMENTOS_APROBADOS) {
                return response()->json([
                    'ok' => false,
                    'message' => 'La repostulación requiere validación documental previa. Contacte a administración.',
                    'errors' => [],
                ], 422);
            }

            return response()->json([
                'ok' => true,
                'message' => 'Repostulación preparada. Proceda con el pago.',
                'data' => [
                    'inscripcion' => [
                        'id' => $inscripcion->id,
                        'codigo' => $inscripcion->codigo,
                        'estado' => $inscripcion->estado,
                    ],
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

    public function createOrder(ValidarRepostulacionPublicaRequest $request): JsonResponse
    {
        try {
            $inscripcion = $this->resolverInscripcionPendientePago(
                $request->validated('ci'),
                $request->validated('correo'),
            );

            $order = $this->payPalService->createOrder(
                PayPalPagoProcessor::MONTO_CUP,
                'USD',
                (string) $inscripcion->id,
            );

            return response()->json(['id' => $order['id']]);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function captureOrder(Request $request): JsonResponse
    {
        $request->validate([
            'orderID' => ['required', 'string'],
            'ci' => ['required', 'string'],
            'correo' => ['required', 'email'],
        ]);

        $orderId = $request->input('orderID');

        try {
            $inscripcion = $this->resolverInscripcionPendientePago(
                $request->input('ci'),
                $request->input('correo'),
            );

            $captureResult = $this->payPalService->captureOrder($orderId);

            $referenceId = $captureResult['purchase_units'][0]['reference_id'] ?? null;
            $status = $captureResult['status'] ?? null;

            if ($status === 'COMPLETED' && $referenceId && (int) $referenceId === $inscripcion->id) {
                $this->payPalPagoProcessor->procesarPagoAprobado(
                    (int) $referenceId,
                    $orderId,
                    PayPalPagoProcessor::MONTO_CUP,
                    true,
                    $request,
                );
            }

            return response()->json($captureResult);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function resolverInscripcionPendientePago(string $ci, string $correo): Inscripcion
    {
        $this->repostulacionService->validarElegibilidad($ci, $correo);

        $postulante = Postulante::where('ci', trim($ci))->firstOrFail();

        $inscripcion = Inscripcion::where('postulante_id', $postulante->id)
            ->where('estado', InscripcionState::DOCUMENTOS_APROBADOS)
            ->latest('fecha_inscripcion')
            ->first();

        if ($inscripcion === null) {
            throw new \DomainException('No tiene una repostulación pendiente de pago.');
        }

        return $inscripcion;
    }
}
