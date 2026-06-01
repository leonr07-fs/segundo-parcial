<?php

namespace App\Services;

use App\Models\AsignacionCarrera;
use App\Models\CupoCarrera;
use App\Models\ResultadoCup;
use App\Support\States\AsignacionCarreraState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AsignacionCarreraService
{
    /**
     * Ejecuta la asignación de carreras para una gestión dada.
     * Solo evalúa a los postulantes aprobados que aún no tienen asignación.
     */
    public function ejecutarAsignacion(int $gestionId): array
    {
        // Obtener a todos los aprobados de esta gestión, ordenados por estricto mérito
        $resultados = ResultadoCup::with(['inscripcion.opcionesCarrera' => function ($q) {
                $q->orderBy('prioridad', 'asc');
            }])
            ->whereHas('inscripcion', function ($q) use ($gestionId) {
                $q->where('gestion_id', $gestionId);
            })
            ->where('estado_final', 'aprobado')
            ->whereDoesntHave('inscripcion.asignacionCarrera')
            ->orderBy('promedio_final', 'desc')
            ->get();

        $stats = [
            'procesados' => 0,
            'asignados_1ra' => 0,
            'asignados_2da' => 0,
            'sin_cupo' => 0,
        ];

        foreach ($resultados as $resultado) {
            $inscripcion = $resultado->inscripcion;
            $opciones = $inscripcion->opcionesCarrera;

            if ($opciones->isEmpty()) {
                Log::warning("Inscripción ID {$inscripcion->id} no tiene opciones de carrera.");
                continue;
            }

            DB::transaction(function () use ($inscripcion, $resultado, $opciones, &$stats) {
                $asignado = false;

                foreach ($opciones as $opcion) {
                    // Pessimistic locking para evitar sobreventa concurrente
                    $cupo = CupoCarrera::where('carrera_id', $opcion->carrera_id)
                        ->where('gestion_id', $inscripcion->gestion_id)
                        ->lockForUpdate()
                        ->first();

                    if ($cupo && $cupo->cupo_disponible > 0) {
                        // ¡Hay cupo! Decrementamos y asignamos
                        $cupo->decrement('cupo_disponible');

                        AsignacionCarrera::create([
                            'inscripcion_id' => $inscripcion->id,
                            'carrera_id' => $opcion->carrera_id,
                            'opcion_prioridad' => $opcion->prioridad,
                            'promedio_usado' => $resultado->promedio_final,
                            'estado' => AsignacionCarreraState::ASIGNADO,
                            'asignado_en' => now(),
                        ]);

                        $asignado = true;
                        
                        if ($opcion->prioridad === 1) {
                            $stats['asignados_1ra']++;
                        } else {
                            $stats['asignados_2da']++;
                        }
                        
                        break; // Sale del loop de opciones, ya fue asignado
                    }
                }

                if (!$asignado) {
                    // Si iteró ambas opciones y ninguna tenía cupo
                    AsignacionCarrera::create([
                        'inscripcion_id' => $inscripcion->id,
                        'carrera_id' => null, // No se asigna carrera
                        'opcion_prioridad' => null,
                        'promedio_usado' => $resultado->promedio_final,
                        'estado' => AsignacionCarreraState::SIN_CUPO,
                        'asignado_en' => now(),
                    ]);
                    $stats['sin_cupo']++;
                }

                $stats['procesados']++;
            });
        }

        return $stats;
    }
}
