<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportarResultadosRequest;
use App\Services\AuditLogService;
use App\Services\ImportacionResultadosService;
use Illuminate\Http\JsonResponse;

class EvaluacionController extends Controller
{
    public function __construct(
        private readonly ImportacionResultadosService $importService,
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function importar(ImportarResultadosRequest $request): JsonResponse
    {
        try {
            $archivo = $request->file('archivo');
            $user = $request->user();

            $resultado = $this->importService->procesarCsv($archivo, $user);

            // Registrar auditoría al terminar la importación (sólo si procesó al menos una)
            if ($resultado['total_procesadas'] > 0) {
                $this->auditLogService->record(
                    'importacion.resultados.completada',
                    $user,
                    $request,
                    [
                        'archivo_nombre' => $archivo->getClientOriginalName(),
                        'total_procesadas' => $resultado['total_procesadas'],
                        'exitosas' => $resultado['exitosas'],
                        'errores' => count($resultado['errores']),
                    ]
                );
            }

            return response()->json([
                'ok' => true,
                'message' => 'Proceso de importación finalizado.',
                'data' => $resultado,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Ocurrió un error al procesar el archivo CSV.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
