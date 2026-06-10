<?php

namespace App\Http\Controllers\PortalPostulante;

use App\Http\Controllers\Controller;
use App\Models\InscripcionPagos\Inscripcion;
use App\Models\InscripcionPagos\Postulante;
use App\Services\PortalPostulante\PayPalPagoProcessor;
use App\Services\PortalPostulante\PayPalService;
use App\Support\States\InscripcionState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Consulta pública del estado de postulación.
 * No requiere autenticación. El postulante consulta con su CI.
 */
class ConsultaPostulacionController extends Controller
{
    public function __construct(
        private readonly PayPalService $payPalService,
        private readonly PayPalPagoProcessor $payPalPagoProcessor,
    ) {
    }

    /**
     * Consultar el estado de la postulación por CI.
     */
    public function consultar(string $ci): JsonResponse
    {
        $ci = trim($ci);

        $postulante = Postulante::where('ci', $ci)->first();

        if (! $postulante) {
            return response()->json([
                'ok' => false,
                'message' => 'No se encontró ninguna postulación con el CI proporcionado.',
            ], 404);
        }

        $inscripcion = Inscripcion::with(['gestion', 'validacionDocumental', 'documentos'])
            ->where('postulante_id', $postulante->id)
            ->latest('fecha_inscripcion')
            ->first();

        if (! $inscripcion) {
            return response()->json([
                'ok' => true,
                'data' => [
                    'postulante' => [
                        'nombres' => $postulante->nombres,
                        'apellido_paterno' => $postulante->apellido_paterno,
                    ],
                    'inscripcion' => null,
                ],
            ]);
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'postulante' => [
                    'nombres' => $postulante->nombres,
                    'apellido_paterno' => $postulante->apellido_paterno,
                ],
                'inscripcion' => [
                    'id' => $inscripcion->id,
                    'codigo' => $inscripcion->codigo,
                    'estado' => $inscripcion->estado,
                    'gestion' => $inscripcion->gestion?->nombre,
                    'validacion_documental' => $inscripcion->validacionDocumental ? [
                        'estado' => $inscripcion->validacionDocumental->estado,
                        'observacion' => $inscripcion->validacionDocumental->observacion,
                        'validado_en' => $inscripcion->validacionDocumental->validado_en?->toISOString(),
                    ] : null,
                    'documentos' => $inscripcion->documentos->map(fn ($doc) => [
                        'tipo' => $doc->tipo,
                        'estado' => $doc->estado,
                        'observacion' => $doc->observacion ?? null,
                    ])->values(),
                ],
            ],
        ]);
    }

    /**
     * Crear una orden PayPal para pago público (sin auth), identificado por CI.
     */
    public function createOrder(Request $request): JsonResponse
    {
        $ci = trim($request->input('ci', ''));

        if (! $ci) {
            return response()->json(['message' => 'CI requerido.'], 400);
        }

        $postulante = Postulante::where('ci', $ci)->first();

        if (! $postulante) {
            return response()->json(['message' => 'Postulante no encontrado.'], 404);
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

            return response()->json(['id' => $order['id']]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener los libros de estudio disponibles para un postulante (sin grupo aún).
     */
    public function obtenerLibros(string $ci): JsonResponse
    {
        $ci = trim($ci);

        // Verificar que el postulante existe
        $postulante = Postulante::where('ci', $ci)->first();

        if (! $postulante) {
            return response()->json([
                'ok' => false,
                'message' => 'Postulante no encontrado.',
            ], 404);
        }

        // Devolver todos los libros disponibles, agrupados por materia
        $libros = \App\Models\GestionAcademica\Libro::with('materia')
            ->get()
            ->map(fn ($libro) => [
                'id' => $libro->id,
                'titulo' => $libro->titulo,
                'materia' => $libro->materia?->nombre,
                'url' => asset('storage/' . $libro->archivo_path),
            ])
            ->values();

        return response()->json([
            'ok' => true,
            'data' => [
                'libros' => $libros,
            ],
        ]);
    }

    /**
     * Capturar una orden PayPal para pago público (sin auth).
     */
    public function captureOrder(Request $request): JsonResponse
    {
        $orderId = $request->input('orderID');

        if (! $orderId) {
            return response()->json(['message' => 'Falta OrderID.'], 400);
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
}
