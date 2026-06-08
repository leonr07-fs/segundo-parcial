<?php

namespace App\Services\GestionAcademica;

use App\Models\EvaluacionesResultados\Evaluacion;
use App\Support\States\EvaluacionState;

/**
 * CU10 - Validar reglas académicas
 * CU11 - Calcular promedio final y determinar estado
 * Evalúa notas de exámenes, calcula promedio parcial y determina estados de evaluación.
 */
class ValidacionAcademicaService
{
    /**
     * Valida una evaluacion segun las reglas academicas (CU10).
     * Devuelve true si cambio de estado, false si no hubo cambios.
     */
    public function validar(Evaluacion $evaluacion, bool $guardar = true): bool
    {
        $estadoAnterior = $evaluacion->estado;
        $observacionAnterior = $evaluacion->observacion;

        $evaluacion->observacion = null;
        $evaluacion->promedio = $this->promedioNotasRendidas($evaluacion);

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

        if ($evaluacion->examen_1 !== null && $evaluacion->examen_1 < 60) {
            $evaluacion->estado = EvaluacionState::REPROBADO;
            $this->guardarSiHuboCambios($evaluacion, $estadoAnterior, $observacionAnterior, $guardar);

            if ($evaluacion->examen_2 !== null || $evaluacion->examen_3 !== null) {
                throw new \Exception("El estudiante reprobó el Examen 1 con {$evaluacion->examen_1}. No está habilitado para registrar más notas.");
            }

            return true;
        }

        if ($evaluacion->examen_2 !== null && $evaluacion->examen_2 < 60) {
            $evaluacion->estado = EvaluacionState::REPROBADO;
            $this->guardarSiHuboCambios($evaluacion, $estadoAnterior, $observacionAnterior, $guardar);

            if ($evaluacion->examen_3 !== null) {
                throw new \Exception("El estudiante reprobó el Examen 2 con {$evaluacion->examen_2}. No está habilitado para registrar más notas.");
            }

            return true;
        }

        if ($evaluacion->examen_3 !== null && $evaluacion->examen_3 < 60) {
            $evaluacion->estado = EvaluacionState::REPROBADO;
            return $this->guardarSiHuboCambios($evaluacion, $estadoAnterior, $observacionAnterior, $guardar);
        }

        if ($evaluacion->examen_1 === null) {
            $evaluacion->estado = EvaluacionState::INCOMPLETO;
            $evaluacion->observacion = 'Falta el Examen 1.';
            return $this->guardarSiHuboCambios($evaluacion, $estadoAnterior, $observacionAnterior, $guardar);
        }

        if ($evaluacion->examen_1 >= 60 && $evaluacion->examen_2 === null) {
            $evaluacion->estado = EvaluacionState::INCOMPLETO;
            $evaluacion->observacion = 'El estudiante aprobo el Examen 1, falta el Examen 2.';
            return $this->guardarSiHuboCambios($evaluacion, $estadoAnterior, $observacionAnterior, $guardar);
        }

        if ($evaluacion->examen_2 >= 60 && $evaluacion->examen_3 === null) {
            $evaluacion->estado = EvaluacionState::INCOMPLETO;
            $evaluacion->observacion = 'El estudiante aprobo los primeros 2 examenes, falta el Examen 3.';
            return $this->guardarSiHuboCambios($evaluacion, $estadoAnterior, $observacionAnterior, $guardar);
        }

        $evaluacion->estado = EvaluacionState::APROBADO;
        $evaluacion->promedio = round(($evaluacion->examen_1 + $evaluacion->examen_2 + $evaluacion->examen_3) / 3, 2);

        return $this->guardarSiHuboCambios($evaluacion, $estadoAnterior, $observacionAnterior, $guardar);
    }

    private function promedioNotasRendidas(Evaluacion $evaluacion): ?float
    {
        $notas = array_filter([
            $evaluacion->examen_1,
            $evaluacion->examen_2,
            $evaluacion->examen_3,
        ], fn ($nota) => $nota !== null);

        if ($notas === []) {
            return null;
        }

        return round(array_sum($notas) / count($notas), 2);
    }

    private function guardarSiHuboCambios(Evaluacion $evaluacion, $estadoAnterior, $observacionAnterior, bool $guardar): bool
    {
        if ($evaluacion->estado !== $estadoAnterior || $evaluacion->observacion !== $observacionAnterior || $evaluacion->isDirty('promedio')) {
            if ($guardar) {
                $evaluacion->save();
            }

            return true;
        }

        return false;
    }
}
