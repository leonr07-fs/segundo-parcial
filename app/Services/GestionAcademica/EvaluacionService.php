<?php

namespace App\Services\GestionAcademica;

use App\Models\EvaluacionesResultados\Evaluacion;
use App\Models\GestionAcademica\GrupoMateria;

class EvaluacionService
{
    public function obtenerEvaluacionesPorGrupoMateria(int $grupoMateriaId)
    {
        $grupoMateria = GrupoMateria::with([
            'materia',
            'docente',
            'grupo.gestion',
            'grupo.inscripciones.postulante'
        ])->findOrFail($grupoMateriaId);

        // Obtenemos todas las evaluaciones registradas para este grupo materia
        $evaluaciones = Evaluacion::where('grupo_materia_id', $grupoMateriaId)->get()->keyBy('inscripcion_id');

        // Cruzamos las inscripciones con sus evaluaciones (si no tienen, se devuelven en null/pendiente)
        $estudiantes = $grupoMateria->grupo->inscripciones->map(function ($inscripcion) use ($evaluaciones) {
            $eval = $evaluaciones->get($inscripcion->id);

            return [
                'inscripcion_id' => $inscripcion->id,
                'postulante_ci' => $inscripcion->postulante->ci,
                'postulante_nombre' => $inscripcion->postulante->nombres . ' ' . $inscripcion->postulante->apellido_paterno . ' ' . $inscripcion->postulante->apellido_materno,
                'examen_1' => $eval ? $eval->examen_1 : null,
                'examen_2' => $eval ? $eval->examen_2 : null,
                'examen_3' => $eval ? $eval->examen_3 : null,
                'promedio' => $eval ? $eval->promedio : null,
                'estado' => $eval ? $eval->estado : 'pendiente',
            ];
        });

        return [
            'grupo_materia' => [
                'id' => $grupoMateria->id,
                'materia' => $grupoMateria->materia->nombre,
                'docente' => $grupoMateria->docente ? $grupoMateria->docente->nombres . ' ' . $grupoMateria->docente->apellidos : 'Sin asignar',
                'grupo_codigo' => $grupoMateria->grupo->codigo,
                'gestion' => $grupoMateria->grupo->gestion->nombre,
            ],
            'estudiantes' => $estudiantes->values(),
        ];
    }
}
