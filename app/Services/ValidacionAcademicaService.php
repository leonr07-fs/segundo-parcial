<?php

namespace App\Services;

use App\Models\Evaluacion;
use App\Support\States\EvaluacionState;

class ValidacionAcademicaService
{
    /**
     * Valida una evaluación según las reglas académicas (CU10).
     * Devuelve true si cambió de estado, false si no hubo cambios.
     */
    public function validar(Evaluacion $evaluacion, bool $guardar = true): bool
    {
        $estadoAnterior = $evaluacion->estado;
        $observacionAnterior = $evaluacion->observacion;

        // Limpiamos la observación inicial
        $evaluacion->observacion = null;

        // 1. Validar que ninguna nota exceda los límites 0-100 (E2)
        // (La BD también lo protege, pero lo controlamos aquí a nivel de dominio)
        $notas = [
            'Examen 1' => $evaluacion->examen_1,
            'Examen 2' => $evaluacion->examen_2,
            'Examen 3' => $evaluacion->examen_3,
        ];

        foreach ($notas as $nombre => $nota) {
            if ($nota !== null && ($nota < 0 || $nota > 100)) {
                $evaluacion->estado = EvaluacionState::OBSERVADO;
                $evaluacion->observacion = "El $nombre tiene un valor fuera del rango permitido (0-100). Valor actual: $nota";
                return $this->guardarSiHuboCambios($evaluacion, $estadoAnterior, $observacionAnterior, $guardar);
            }
        }

        // 2. Regla de Reprobación Directa (En cascada)
        // Si algún examen es < 60, el estudiante reprueba y no se exige los demás.
        if ($evaluacion->examen_1 !== null && $evaluacion->examen_1 < 60) {
            $evaluacion->estado = EvaluacionState::REPROBADO;
            return $this->guardarSiHuboCambios($evaluacion, $estadoAnterior, $observacionAnterior, $guardar);
        }

        if ($evaluacion->examen_2 !== null && $evaluacion->examen_2 < 60) {
            $evaluacion->estado = EvaluacionState::REPROBADO;
            return $this->guardarSiHuboCambios($evaluacion, $estadoAnterior, $observacionAnterior, $guardar);
        }

        if ($evaluacion->examen_3 !== null && $evaluacion->examen_3 < 60) {
            $evaluacion->estado = EvaluacionState::REPROBADO;
            return $this->guardarSiHuboCambios($evaluacion, $estadoAnterior, $observacionAnterior, $guardar);
        }

        // Llegados a este punto: todos los exámenes presentes son >= 60

        // 3. Regla E1 (Faltante / Incompleto)
        // Si examen_1 es >= 60, debe existir examen_2.
        // Si examen_2 es >= 60, debe existir examen_3.
        
        // Si falta el examen 1 (no debería pasar, pero por si acaso)
        if ($evaluacion->examen_1 === null) {
            $evaluacion->estado = EvaluacionState::INCOMPLETO;
            $evaluacion->observacion = "Falta el Examen 1.";
            return $this->guardarSiHuboCambios($evaluacion, $estadoAnterior, $observacionAnterior, $guardar);
        }

        if ($evaluacion->examen_1 >= 60 && $evaluacion->examen_2 === null) {
            $evaluacion->estado = EvaluacionState::INCOMPLETO;
            $evaluacion->observacion = "El estudiante aprobó el Examen 1, falta el Examen 2.";
            return $this->guardarSiHuboCambios($evaluacion, $estadoAnterior, $observacionAnterior, $guardar);
        }

        if ($evaluacion->examen_2 >= 60 && $evaluacion->examen_3 === null) {
            $evaluacion->estado = EvaluacionState::INCOMPLETO;
            $evaluacion->observacion = "El estudiante aprobó los primeros 2 exámenes, falta el Examen 3.";
            return $this->guardarSiHuboCambios($evaluacion, $estadoAnterior, $observacionAnterior, $guardar);
        }

        // 4. Aprobación Total
        // Tiene los 3 exámenes y todos son >= 60
        $evaluacion->estado = EvaluacionState::APROBADO;
        
        // Calcular promedio aquí (opcionalmente) por si no venía en la importación
        $evaluacion->promedio = round(($evaluacion->examen_1 + $evaluacion->examen_2 + $evaluacion->examen_3) / 3, 2);

        return $this->guardarSiHuboCambios($evaluacion, $estadoAnterior, $observacionAnterior, $guardar);
    }

    private function guardarSiHuboCambios(Evaluacion $evaluacion, $estadoAnterior, $observacionAnterior, bool $guardar): bool
    {
        if ($evaluacion->estado !== $estadoAnterior || $evaluacion->observacion !== $observacionAnterior) {
            if ($guardar) {
                $evaluacion->save();
            }
            return true;
        }
        return false;
    }
}
