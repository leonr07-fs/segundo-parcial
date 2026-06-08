<?php

namespace App\Http\Controllers\PortalPostulante;

use App\Http\Controllers\Controller;
use App\Models\InscripcionPagos\Inscripcion;
use App\Models\InscripcionPagos\Postulante;
use App\Services\PortalPostulante\PayPalPagoProcessor;
use App\Services\PortalPostulante\PayPalService;
use App\Support\States\InscripcionState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * CU04 - Registrar/verificar pago CUP y confirmar inscripción
 * Gestiona la creación y captura de órdenes PayPal para confirmar pagos de inscripciones y repostulaciones.
 */
class PayPalController extends Controller
{
    public function __construct(
        private readonly PayPalService $payPalService,
        private readonly PayPalPagoProcessor $payPalPagoProcessor,
    ) {
    }

    /**
     * Frontend solicita crear una orden de PayPal (postulante autenticado).
     */
    public function createOrder(Request $request)
    {
        $user = $request->user();
        if (! $user || $user->role !== 'postulante') {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $postulante = Postulante::query()
            ->where('correo', $user->email)
            ->orWhere('ci', $user->numero_registro)
            ->first();

        if ($postulante === null) {
            return response()->json(['message' => 'No se encontró el registro de postulante.'], 400);
        }

        $inscripcion = Inscripcion::where('postulante_id', $postulante->id)
            ->where('estado', InscripcionState::DOCUMENTOS_APROBADOS)
            ->latest('fecha_inscripcion')
            ->first();

        if (! $inscripcion) {
            return response()->json(['message' => 'No tiene una inscripción pendiente de pago.'], 400);
        }

        try {
            $order = $this->payPalService->createOrder(
                PayPalPagoProcessor::MONTO_CUP,
                'USD',
                (string) $inscripcion->id,
            );

            return response()->json([
                'id' => $order['id'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Frontend notifica que el usuario aprobó la orden. El backend captura.
     */
    public function captureOrder(Request $request)
    {
        $orderId = $request->input('orderID');
        if (! $orderId) {
            return response()->json(['message' => 'Falta OrderID'], 400);
        }

        try {
            $captureResult = $this->payPalService->captureOrder($orderId);

            $referenceId = $captureResult['purchase_units'][0]['reference_id'] ?? null;
            $status = $captureResult['status'] ?? null;

            if ($status === 'COMPLETED' && $referenceId) {
                $this->payPalPagoProcessor->procesarPagoAprobado(
                    (int) $referenceId,
                    $orderId,
                    PayPalPagoProcessor::MONTO_CUP,
                    false,
                    $request,
                );
            }

            return response()->json($captureResult);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint oficial de Webhook de PayPal. No requiere Auth.
     */
    public function webhook(Request $request)
    {
        $headers = $request->headers->all();
        $payload = $request->all();

        try {
            $isValid = $this->payPalService->verifyWebhookSignature($headers, $payload);
            if (! $isValid) {
                Log::warning('PayPal Webhook firma inválida.');

                return response()->json(['message' => 'Firma inválida'], 400);
            }
        } catch (\Exception $e) {
            Log::error('Excepción al verificar webhook', ['error' => $e->getMessage()]);
            if (app()->environment('production')) {
                return response()->json(['message' => 'Error de validación'], 400);
            }
        }

        $eventType = $payload['event_type'] ?? '';

        if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
            $resource = $payload['resource'] ?? [];
            $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;
            $amount = $resource['amount']['value'] ?? 0;
            $referenceId = $resource['custom_id'] ?? null;

            if ($referenceId) {
                $esRepostulacion = $this->esPagoRepostulacion((int) $referenceId);
                $this->payPalPagoProcessor->procesarPagoAprobado(
                    (int) $referenceId,
                    $orderId,
                    (float) $amount,
                    $esRepostulacion,
                    $request,
                );
            } else {
                Log::error('PayPal Webhook: No reference/custom_id found', ['resource' => $resource]);
            }
        }

        return response()->json(['status' => 'success']);
    }

    private function esPagoRepostulacion(int $inscripcionId): bool
    {
        $inscripcion = Inscripcion::with('gestion')->find($inscripcionId);

        if ($inscripcion === null) {
            return false;
        }

        $tieneInscripcionReprobadaAnterior = Inscripcion::where('postulante_id', $inscripcion->postulante_id)
            ->where('id', '!=', $inscripcion->id)
            ->whereHas('resultadoCup', fn ($q) => $q->where('estado_final', 'reprobado'))
            ->exists();

        return $tieneInscripcionReprobadaAnterior;
    }
}
