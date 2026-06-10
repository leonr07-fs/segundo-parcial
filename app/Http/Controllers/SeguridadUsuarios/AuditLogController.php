<?php

namespace App\Http\Controllers\SeguridadUsuarios;

use App\Http\Controllers\Controller;

use App\Models\ReportesAuditoria\AuditLog;
use App\Models\EvaluacionesResultados\Evaluacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * [CU07] Gestionar usuarios y roles / Bitácora
 * Vinculación UML: Permite consultar el registro de auditoría de operaciones críticas para asegurar la trazabilidad del proceso.
 */

/**
 * CU07 - Gestionar usuarios, roles y bitácora
 * Permite gestionar la auditoría del sistema.
 *
 * Participantes del CU07 (Diagrama de Secuencia):
 * - Actor: Administrador
 * - Boundary: UI_UsuariosRoles (Vue)
 * - Control: AuditLogController (Actual)
 * - Control: AuditLogService
 * - Entity: AuditLog
 */
class AuditLogController extends Controller
{
    public function index(): JsonResponse
    {
        $logs = AuditLog::with('user:id,name,role,email')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->take(200)
            ->get();

        $logsNormalizados = $logs->map(fn (AuditLog $log) => $this->normalizarLog($log))->values();

        return response()->json([
            'ok' => true,
            'message' => 'Bitacora obtenida exitosamente.',
            'data' => [
                'resumen' => [
                    'total_movimientos' => $logs->count(),
                    'usuarios_activos' => $logs->pluck('user_id')->filter()->unique()->count(),
                    'tablas_intervenidas' => $logsNormalizados->pluck('tabla')->filter()->unique()->count(),
                    'ultima_accion' => $logsNormalizados->first(),
                ],
                'tablas' => $this->agrupar($logsNormalizados, 'tabla'),
                'acciones' => $this->agrupar($logsNormalizados, 'accion'),
                'notas' => $this->estadisticasNotas(),
                'logs' => $logsNormalizados,
            ],
        ]);
    }

    private function normalizarLog(AuditLog $log): array
    {
        $metadata = $log->metadata ?? [];

        return [
            'id' => $log->id,
            'fecha' => $log->created_at,
            'accion' => $log->event,
            'accion_legible' => $this->accionLegible($log->event),
            'tabla' => $metadata['tabla'] ?? $this->inferirTabla($log->event),
            'registro_id' => $metadata['registro_id'] ?? $log->auditable_id,
            'usuario' => $log->user ? [
                'id' => $log->user->id,
                'name' => $log->user->name,
                'role' => $log->user->role,
                'email' => $log->user->email,
            ] : null,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'detalles' => $metadata,
        ];
    }

    private function agrupar(Collection $logs, string $campo): array
    {
        $total = max(1, $logs->count());

        return $logs
            ->groupBy(fn (array $log) => $log[$campo] ?: 'sin_dato')
            ->map(fn (Collection $items, string $nombre) => [
                'nombre' => $nombre,
                'total' => $items->count(),
                'porcentaje' => round(($items->count() / $total) * 100, 1),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    private function estadisticasNotas(): array
    {
        $evaluaciones = Evaluacion::query()->get(['examen_1', 'examen_2', 'examen_3', 'promedio', 'estado']);
        $promedio = fn (string $campo) => round((float) $evaluaciones->whereNotNull($campo)->avg($campo), 2);

        return [
            'total_evaluaciones' => $evaluaciones->count(),
            'promedios' => [
                'examen_1' => $promedio('examen_1'),
                'examen_2' => $promedio('examen_2'),
                'examen_3' => $promedio('examen_3'),
                'general' => $promedio('promedio'),
            ],
            'estados' => $evaluaciones
                ->groupBy('estado')
                ->map(fn (Collection $items, string $estado) => [
                    'nombre' => $estado,
                    'total' => $items->count(),
                ])
                ->values()
                ->all(),
        ];
    }

    private function inferirTabla(string $event): ?string
    {
        return match (true) {
            str_contains($event, 'postulacion') => 'inscripciones',
            str_contains($event, 'document') || str_contains($event, 'validacion') => 'documentos',
            str_contains($event, 'pago') => 'pagos',
            str_contains($event, 'evaluacion') => 'evaluaciones',
            str_contains($event, 'auth') => 'users',
            str_contains($event, 'docente') => 'solicitudes_docentes',
            default => null,
        };
    }

    private function accionLegible(string $event): string
    {
        return ucfirst(str_replace(['.', '_'], ' ', $event));
    }
}
