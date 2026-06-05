<?php

namespace App\Services\GestionAcademica;

use App\Services\SeguridadUsuarios\AuditLogService;

use App\Models\InscripcionPagos\Inscripcion;
use App\Models\InscripcionPagos\Postulante;
use App\Models\Seguridad\User;
use App\Support\States\InscripcionState;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AdminPostulanteService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * Buscar postulantes con filtros.
     */
    public function buscar(array $filtros): LengthAwarePaginator
    {
        $query = Postulante::query()->with([
            'usuario',
            'inscripciones' => fn ($q) => $q->with(['gestion', 'validacionDocumental'])
                ->orderByDesc('fecha_inscripcion')
                ->orderByDesc('id'),
        ]);

        if (!empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->where(function ($q) use ($search) {
                $q->where('ci', 'like', "%{$search}%")
                  ->orWhere('nombres', 'like', "%{$search}%")
                  ->orWhere('apellido_paterno', 'like', "%{$search}%")
                  ->orWhere('apellido_materno', 'like', "%{$search}%")
                  ->orWhere('correo', 'like', "%{$search}%")
                  ->orWhereHas('usuario', function ($usuario) use ($search) {
                      $usuario->where('numero_registro', 'like', "%{$search}%");
                  })
                  ->orWhereHas('inscripciones', function ($inscripcion) use ($search) {
                      $inscripcion->where('codigo', 'like', "%{$search}%");
                  });
            });
        }

        if (!empty($filtros['gestion_id'])) {
            $query->whereHas('inscripciones', function ($q) use ($filtros) {
                $q->where('gestion_id', $filtros['gestion_id']);
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate(15);
    }

    /**
     * Obtener el expediente completo de un postulante.
     */
    public function expedienteCompleto(int $postulanteId): Postulante
    {
        return Postulante::with([
            'inscripciones.gestion',
            'inscripciones.opcionesCarrera.carrera',
            'inscripciones.documentos',
            'inscripciones.validacionDocumental',
            'inscripciones.pagos',
            'inscripciones.evaluaciones.materia',
            'inscripciones.resultadoCup',
            'inscripciones.asignacionCarrera.carrera',
            'inscripciones.grupos',
            'usuario',
        ])->findOrFail($postulanteId);
    }

    /**
     * Actualizar los datos de un postulante (sólo campos permitidos).
     */
    public function actualizar(int $postulanteId, array $datos): Postulante
    {
        $postulante = Postulante::findOrFail($postulanteId);
        
        $postulante->update([
            'nombres' => $datos['nombres'] ?? $postulante->nombres,
            'apellido_paterno' => $datos['apellido_paterno'] ?? $postulante->apellido_paterno,
            'apellido_materno' => $datos['apellido_materno'] ?? $postulante->apellido_materno,
            'fecha_nacimiento' => $datos['fecha_nacimiento'] ?? $postulante->fecha_nacimiento,
            'telefono' => $datos['telefono'] ?? $postulante->telefono,
            'colegio_procedencia' => $datos['colegio_procedencia'] ?? $postulante->colegio_procedencia,
            'ciudad' => $datos['ciudad'] ?? $postulante->ciudad,
            'direccion' => $datos['direccion'] ?? $postulante->direccion,
        ]);

        return $postulante;
    }

    public function anularInscripcion(int $postulanteId, int $inscripcionId, string $motivo, User $admin, Request $request): Inscripcion
    {
        return DB::transaction(function () use ($postulanteId, $inscripcionId, $motivo, $admin, $request) {
            $inscripcion = Inscripcion::query()
                ->where('postulante_id', $postulanteId)
                ->findOrFail($inscripcionId);

            if ($inscripcion->estado === InscripcionState::CANCELADO) {
                throw new \DomainException('La postulacion ya se encuentra anulada.');
            }

            $estadoAnterior = $inscripcion->estado;

            $inscripcion->update([
                'estado' => InscripcionState::CANCELADO,
                'observacion' => $motivo,
            ]);

            $this->auditLogService->record('postulacion.anulada', $admin, $request, [
                'postulante_id' => $postulanteId,
                'inscripcion_id' => $inscripcion->id,
                'codigo' => $inscripcion->codigo,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => InscripcionState::CANCELADO,
                'motivo' => $motivo,
            ]);

            return $inscripcion->fresh(['gestion', 'postulante']);
        });
    }
}
