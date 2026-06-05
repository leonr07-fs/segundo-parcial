<?php

namespace App\Http\Controllers\ReportesExportaciones;

use App\Http\Controllers\Controller;

use App\Services\ReportesExportaciones\ReporteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * [CU18] Consultar dashboard y generar reportes
 * Vinculación UML: Genera estadísticas y reportes oficiales dinámicos y estáticos en PDF y Excel.
 */

class ReporteController extends Controller
{
    public function __construct(private readonly ReporteService $reportes)
    {
    }

    public function catalogo(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $this->reportes->catalogo(),
        ]);
    }

    public function estatico(Request $request, string $tipo): JsonResponse
    {
        $data = $request->validate([
            'gestion_id' => ['nullable', 'integer', 'exists:gestiones,id'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
        ]);

        try {
            $reporte = $this->reportes->generarEstatico($tipo, $data);
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'data' => $reporte,
        ]);
    }

    public function dinamico(Request $request): JsonResponse
    {
        $data = $request->validate([
            'modulo' => ['required', 'string', Rule::in(array_keys($this->reportes->catalogo()['modulos_dinamicos']))],
            'columnas' => ['nullable', 'array'],
            'columnas.*' => ['string'],
            'filtros' => ['nullable', 'array'],
            'filtros.gestion_id' => ['nullable', 'integer', 'exists:gestiones,id'],
            'filtros.materia_id' => ['nullable', 'integer', 'exists:materias,id'],
            'filtros.fecha_desde' => ['nullable', 'date'],
            'filtros.fecha_hasta' => ['nullable', 'date', 'after_or_equal:filtros.fecha_desde'],
        ]);

        try {
            $reporte = $this->reportes->generarDinamico(
                $data['modulo'],
                $data['columnas'] ?? [],
                $data['filtros'] ?? [],
            );
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'data' => $reporte,
        ]);
    }
}
