<?php

namespace App\Http\Controllers\GestionAcademica;

use App\Http\Controllers\Controller;

use App\Http\Requests\ImportarResultadosRequest;
use App\Services\SeguridadUsuarios\AuditLogService;
use App\Services\GestionAcademica\ImportacionResultadosService;
use App\Services\GestionAcademica\EvaluacionService;
use Illuminate\Http\JsonResponse;

/**
 * [CU09] Cargar/importar resultados académicos / [CU18] Reportes
 * Vinculación UML: Carga masiva de notas desde plantillas e interfaces de consulta de actas de notas por grupo y materia.
 */

/**
 * CU09 - Cargar/importar resultados académicos
 *
 * Participantes del CU09 (Diagrama de Secuencia):
 * - Actor: Administrador
 * - Boundary: UI_ImportacionNotas (Vue)
 * - Control: EvaluacionController (Actual)
 * - Control: ImportacionResultadosService
 * - Entity: Evaluacion
 */
class EvaluacionController extends Controller
{
    public function __construct(
        private readonly ImportacionResultadosService $importService,
        private readonly AuditLogService $auditLogService,
        private readonly EvaluacionService $evaluacionService
    ) {
    }

    public function importar(ImportarResultadosRequest $request): JsonResponse
    {
        try {
            $archivo = $request->file('archivo');
            $user = $request->user();
            $numeroExamen = (int) $request->integer('numero_examen');

            $resultado = $this->importService->procesarCsv($archivo, $user, $numeroExamen);

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
                        'numero_examen' => $numeroExamen,
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

    public function porGrupoMateria(int $id): JsonResponse
    {
        try {
            $data = $this->evaluacionService->obtenerEvaluacionesPorGrupoMateria($id);
            return response()->json([
                'ok' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Error al obtener evaluaciones: ' . $e->getMessage()
            ], 404);
        }
    }

    public function exportarActa(int $id)
    {
        try {
            $data = $this->evaluacionService->obtenerEvaluacionesPorGrupoMateria($id);
            $estudiantes = $data['estudiantes'];
            $gm = $data['grupo_materia'];

            $headers = array(
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=Acta_{$gm['materia']}_{$gm['grupo_codigo']}.csv",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            );

            $columns = array('CI', 'Nombre Completo', 'Examen 1', 'Examen 2', 'Examen 3', 'Promedio', 'Estado');

            $callback = function() use($estudiantes, $columns) {
                $file = fopen('php://output', 'w');
                // Añadir BOM para que Excel lea correctamente los acentos
                fputs($file, "\xEF\xBB\xBF");
                fputcsv($file, $columns);

                foreach ($estudiantes as $estudiante) {
                    $row = array(
                        $estudiante['postulante_ci'],
                        $estudiante['postulante_nombre'],
                        $estudiante['examen_1'] ?? '-',
                        $estudiante['examen_2'] ?? '-',
                        $estudiante['examen_3'] ?? '-',
                        $estudiante['promedio'] ?? '-',
                        strtoupper($estudiante['estado']),
                    );
                    fputcsv($file, $row);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Error al exportar acta: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * [CU09] Modificación / Subida Manual de Notas
     * 
     * Permite al administrador modificar o cargar manualmente las notas de un estudiante
     * de forma individual (fila por fila en la vista).
     * Esta funcionalidad se añadió para proveer una alternativa a la carga masiva por CSV,
     * permitiendo corregir errores específicos. Las notas enviadas se validan (0-100) 
     * y la modificación queda registrada en la bitácora de auditoría.
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function actualizarManual(\Illuminate\Http\Request $request)
    {
        try {
            $request->validate([
                'inscripcion_id' => 'required|integer',
                'grupo_materia_id' => 'required|integer',
                'examen_1' => 'nullable|numeric|min:0|max:100',
                'examen_2' => 'nullable|numeric|min:0|max:100',
                'examen_3' => 'nullable|numeric|min:0|max:100',
            ]);

            $notas = $request->only(['examen_1', 'examen_2', 'examen_3']);
            $inscripcionId = $request->input('inscripcion_id');
            $grupoMateriaId = $request->input('grupo_materia_id');

            $evaluacion = $this->evaluacionService->actualizarNotasManuales(
                $inscripcionId,
                $grupoMateriaId,
                $notas,
                $request->user()?->id
            );

            $this->auditLogService->record(
                'evaluacion.modificacion_manual',
                $request->user(),
                $request,
                [
                    'inscripcion_id' => $inscripcionId,
                    'grupo_materia_id' => $grupoMateriaId,
                    'notas_actualizadas' => $notas,
                    'nuevo_estado' => $evaluacion->estado,
                ]
            );

            return response()->json([
                'ok' => true,
                'message' => 'Notas actualizadas correctamente',
                'data' => $evaluacion
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Error al actualizar notas de forma manual: ' . $e->getMessage()
            ], 500);
        }
    }
}
