<?php

namespace App\Http\Controllers\GestionAcademica;

use App\Http\Controllers\Controller;

use App\Models\EvaluacionesResultados\Evaluacion;
use App\Services\GestionAcademica\GestionVigenteService;
use Illuminate\Http\Request;

/**
 * CU10 - Validar reglas académicas
 * Permite identificar evaluaciones que requieren supervisión académica y consulta de casos pendientes.
 */
/**
 * CU10, CU11 - Validar reglas académicas y Calcular promedio final
 *
 * Participantes (Diagrama de Secuencia):
 * - Actor: Administrador / Sistema (Background Job)
 * - Boundary: UI_CalculoFinal, Interfaz_BackgroundJob
 * - Control: ValidacionAcademicaController (Actual)
 * - Control: ValidacionAcademicaService
 * - Entity: Evaluacion, Inscripcion
 */
class ValidacionAcademicaController extends Controller
{
    public function __construct(private readonly GestionVigenteService $gestionVigenteService)
    {
    }

    /**
     * Devuelve las evaluaciones que requieren supervisión (INCOMPLETO u OBSERVADO).
     */
    public function index(Request $request)
    {
        $query = Evaluacion::with(['inscripcion.postulante', 'grupoMateria.materia'])
            ->whereHas('inscripcion', function ($query) {
                $gestionVigente = $this->gestionVigenteService->actual();

                $gestionVigente
                    ? $query->where('gestion_id', $gestionVigente->id)
                    : $query->whereRaw('1 = 0');
            })
            ->whereIn('estado', ['incompleto', 'observado']);

        // Opcional: Filtrar por gestión activa u otros parámetros

        $evaluaciones = $query->paginate(20);

        return response()->json([
            'ok' => true,
            'data' => $evaluaciones,
        ]);
    }
}
