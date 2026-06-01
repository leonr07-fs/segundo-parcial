<?php

namespace App\Http\Controllers;

use App\Models\AsignacionCarrera;
use App\Models\CupoCarrera;
use App\Models\Gestion;
use App\Services\AsignacionCarreraService;
use Illuminate\Http\Request;

class AsignacionCarreraController extends Controller
{
    private AsignacionCarreraService $service;

    public function __construct(AsignacionCarreraService $service)
    {
        $this->service = $service;
    }

    public function ejecutar(Request $request)
    {
        // En una app real, el gestion_id vendría del contexto o del request
        $gestion = Gestion::where('estado', 'activa')->firstOrFail();

        $stats = $this->service->ejecutarAsignacion($gestion->id);

        return response()->json([
            'ok' => true,
            'message' => 'Proceso de asignación ejecutado con éxito',
            'stats' => $stats
        ]);
    }

    public function index(Request $request)
    {
        $gestion = Gestion::where('estado', 'activa')->firstOrFail();

        // Obtener el estado actual de los cupos
        $cupos = CupoCarrera::with('carrera')
            ->where('gestion_id', $gestion->id)
            ->get();

        // Obtener el listado paginado de asignaciones
        $asignaciones = AsignacionCarrera::with(['inscripcion.postulante', 'carrera'])
            ->whereHas('inscripcion', function($q) use ($gestion) {
                $q->where('gestion_id', $gestion->id);
            })
            ->orderBy('promedio_usado', 'desc')
            ->paginate(50);

        return response()->json([
            'ok' => true,
            'data' => [
                'cupos' => $cupos,
                'asignaciones' => $asignaciones
            ]
        ]);
    }
}
