<?php

namespace App\Services\GestionAcademica;

use App\Models\Docentes\RepostulacionDocente;
use App\Models\GestionAcademica\Docente;
use App\Models\GestionAcademica\Gestion;
use App\Models\GestionAcademica\GrupoMateria;
use App\Models\InscripcionPagos\Inscripcion;
use App\Models\InscripcionPagos\Postulante;
use App\Support\States\GestionState;
use App\Support\States\InscripcionState;
use App\Support\States\RepostulacionDocenteState;

/**
 * Resuelve la gestión académica vigente y la inscripción activa del postulante en ella.
 */
class GestionVigenteService
{
    /**
     * Gestión operativa actual: admite inscripciones activas o curso en marcha.
     */
    public function actual(): ?Gestion
    {
        return Gestion::query()
            ->whereIn('estado', [
                GestionState::INSCRIPCION,
                GestionState::INHABILITADA,
                GestionState::EN_CURSO,
            ])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Gestión habilitada para nuevas repostulaciones (misma regla que postulación nueva).
     */
    public function paraRepostulacion(): ?Gestion
    {
        return Gestion::habilitadaParaInscripcion()->orderByDesc('id')->first();
    }

    public function inscripcionEnGestionVigente(Postulante $postulante, ?Gestion $gestionVigente = null): ?Inscripcion
    {
        $gestionVigente ??= $this->actual();

        if ($gestionVigente === null) {
            return null;
        }

        return Inscripcion::query()
            ->with('resultadoCup')
            ->where('postulante_id', $postulante->id)
            ->where('gestion_id', $gestionVigente->id)
            ->where('estado', '!=', InscripcionState::CANCELADO)
            ->latest('fecha_inscripcion')
            ->first();
    }

    public function postulantePuedeAcceder(Postulante $postulante): bool
    {
        $inscripcion = $this->inscripcionEnGestionVigente($postulante);

        if ($inscripcion === null) {
            return false;
        }

        // Validación de estado_final removida: postulantes con inscripción vigente pueden acceder
        // sin importar el resultado del examen (aprobado, reprobado, pendiente, etc.)
        return true;
    }

    public function docenteTieneCargaEnGestionVigente(Docente $docente, ?Gestion $gestionVigente = null): bool
    {
        $gestionVigente ??= $this->actual();

        if ($gestionVigente === null) {
            return false;
        }

        return GrupoMateria::query()
            ->where('docente_id', $docente->id)
            ->whereHas('grupo', fn ($query) => $query->where('gestion_id', $gestionVigente->id))
            ->exists();
    }

    public function repostulacionAprobadaEnGestionVigente(Docente $docente, ?Gestion $gestionVigente = null): ?RepostulacionDocente
    {
        $gestionVigente ??= $this->actual();

        if ($gestionVigente === null) {
            return null;
        }

        return RepostulacionDocente::query()
            ->where('docente_id', $docente->id)
            ->where('gestion_id', $gestionVigente->id)
            ->where('estado', RepostulacionDocenteState::APROBADA)
            ->first();
    }

    public function docentePuedeAcceder(Docente $docente): bool
    {
        if (! $docente->activo) {
            return false;
        }

        return $this->docenteTieneCargaEnGestionVigente($docente)
            || $this->repostulacionAprobadaEnGestionVigente($docente) !== null;
    }
}
