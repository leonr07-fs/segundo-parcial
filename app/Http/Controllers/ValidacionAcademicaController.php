<?php

namespace App\Http\Controllers;

use App\Models\Evaluacion;
use Illuminate\Http\Request;

class ValidacionAcademicaController extends Controller
{
    /**
     * Devuelve las evaluaciones que requieren supervisión (INCOMPLETO u OBSERVADO).
     */
    public function index(Request $request)
    {
        $query = Evaluacion::with(['inscripcion.postulante', 'grupoMateria.materia'])
            ->whereIn('estado', ['incompleto', 'observado']);

        // Opcional: Filtrar por gestión activa u otros parámetros

        $evaluaciones = $query->paginate(20);

        return response()->json([
            'ok' => true,
            'data' => $evaluaciones,
        ]);
    }
}
