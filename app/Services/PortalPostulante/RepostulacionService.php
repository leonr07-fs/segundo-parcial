<?php

namespace App\Services\PortalPostulante;

use App\Models\InscripcionPagos\Inscripcion;
use App\Models\GestionAcademica\Gestion;
use App\Models\AsignacionCarrera\OpcionCarrera;
use Illuminate\Support\Facades\DB;
use DomainException;

class RepostulacionService
{
    /**
     * Habilitar repostulación de un postulante en una nueva gestión.
     * Mantiene el historial intacto y crea una nueva inscripción.
     */
    public function repostular(array $datos): Inscripcion
    {
        return DB::transaction(function () use ($datos) {
            $postulanteId = $datos['postulante_id'];
            $gestionId = $datos['gestion_id'];
            $gestion = Gestion::findOrFail($gestionId);

            // Regla: No puede tener ya una inscripción en esta gestión
            $existeInscripcion = Inscripcion::where('postulante_id', $postulanteId)
                ->where('gestion_id', $gestionId)
                ->exists();

            if ($existeInscripcion) {
                throw new DomainException('El postulante ya tiene una inscripción activa en la gestión seleccionada.');
            }

            // Crear nueva inscripción
            $inscripcion = Inscripcion::create([
                'postulante_id' => $postulanteId,
                'gestion_id' => $gestionId,
                'codigo' => Inscripcion::generarCodigo((int) $gestion->anio),
                'fecha_inscripcion' => now(),
                'estado' => 'prepostulado'
            ]);

            // Crear opciones de carrera
            OpcionCarrera::create([
                'inscripcion_id' => $inscripcion->id,
                'carrera_id' => $datos['opcion1_carrera_id'],
                'prioridad' => 1
            ]);

            OpcionCarrera::create([
                'inscripcion_id' => $inscripcion->id,
                'carrera_id' => $datos['opcion2_carrera_id'],
                'prioridad' => 2
            ]);

            return $inscripcion;
        });
    }
}
