<?php

namespace App\Http\Controllers\GestionAcademica;

use App\Http\Controllers\Controller;

use App\Models\GestionAcademica\Gestion;
use App\Support\States\GestionState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CU08 - Parametrizar gestiones, materias, aulas y grupos
 * Permite gestionar el ciclo académico de gestiones y el estado de inscripciones.
 */
class GestionController extends Controller
{
    public function index()
    {
        return response()->json([
            'ok' => true,
            'data' => [
                'gestiones' => Gestion::orderBy('created_at', 'desc')->get()
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'anio' => 'required|integer',
            'periodo' => 'required|integer|in:1,2',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
        ]);

        $validated['nombre'] = Gestion::nombreDesdePeriodo($validated['periodo'], $validated['anio']);

        $gestion = Gestion::create(array_merge($validated, [
            'estado' => GestionState::PLANIFICADA, // Por defecto se crea pero no se habilita hasta que el admin le de al botón
        ]));

        return response()->json([
            'ok' => true,
            'message' => 'Gestión creada exitosamente.',
            'data' => ['gestion' => $gestion]
        ], 201);
    }

    public function habilitar(int $id)
    {
        DB::transaction(function () use ($id) {
            // Cerramos definitivamente cualquier otra gestion activa o intermedia.
            Gestion::whereIn('estado', [
                GestionState::INSCRIPCION,
                GestionState::INHABILITADA,
                GestionState::EN_CURSO,
            ])->update([
                'estado' => GestionState::CERRADA
            ]);

            // Habilitamos la gestión solicitada
            $gestion = Gestion::findOrFail($id);
            $gestion->update(['estado' => GestionState::INSCRIPCION]);
        });

        return response()->json([
            'ok' => true,
            'message' => 'La gestión fue habilitada para inscripciones.'
        ]);
    }
    public function cerrar(int $id)
    {
        $gestion = Gestion::findOrFail($id);
        $gestion->update(['estado' => GestionState::INHABILITADA]);

        return response()->json([
            'ok' => true,
            'message' => 'Las inscripciones fueron cerradas. La gestion queda operativa para asignacion, horarios y evaluaciones.',
            'data' => [
                'gestion' => $gestion->fresh(),
            ],
        ]);
    }

    public function cerrarFinal(int $id)
    {
        $gestion = Gestion::findOrFail($id);
        $gestion->update(['estado' => GestionState::CERRADA]);

        return response()->json([
            'ok' => true,
            'message' => 'La gestion fue cerrada definitivamente. Solo queda disponible para consulta administrativa y reportes.',
            'data' => [
                'gestion' => $gestion->fresh(),
            ],
        ]);
    }
}
