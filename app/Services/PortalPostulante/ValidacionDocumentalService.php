<?php

namespace App\Services\PortalPostulante;

use App\Services\GestionAcademica\CredentialService;

use App\Services\GestionAcademica\GestionVigenteService;
use App\Services\SeguridadUsuarios\AuditLogService;

use App\Models\InscripcionPagos\Documento;
use App\Models\InscripcionPagos\Inscripcion;
use App\Models\Seguridad\User;
use App\Models\InscripcionPagos\ValidacionDocumental;
use App\Support\States\DocumentoState;
use App\Support\States\InscripcionState;
use App\Support\States\ValidacionDocumentalState;
use App\Support\TipoDocumento;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ValidacionDocumentalService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly PrevalidacionDocumentalService $prevalidacionDocumentalService,
        private readonly CredentialService $credentialService,
        private readonly GestionVigenteService $gestionVigenteService,
    )
    {
    }

    /**
     * Lista de inscripciones pendientes de validación documental.
     * Solo retorna inscripciones en estado 'prepostulado' o 'documentos_pendientes'.
     *
     * @return Collection<int, Inscripcion>
     */
    public function listarPendientes(): Collection
    {
        return $this->listarConFiltro('pendientes');
    }

    /**
     * Lista de inscripciones de validación documental filtradas por estado.
     *
     * @param string|null $estado
     * @return Collection<int, Inscripcion>
     */
    public function listarConFiltro(?string $estado = 'pendientes'): Collection
    {
        $gestionVigente = $this->gestionVigenteService->actual();

        $query = Inscripcion::with(['postulante', 'gestion', 'documentos', 'validacionDocumental'])
            ->when($gestionVigente, fn ($query) => $query->where('gestion_id', $gestionVigente->id))
            ->when(! $gestionVigente, fn ($query) => $query->whereRaw('1 = 0'));

        if ($estado === 'pendientes') {
            $query->whereIn('estado', [
                InscripcionState::PREPOSTULADO,
                InscripcionState::DOCUMENTOS_PENDIENTES,
            ]);
        } elseif ($estado === 'aprobados') {
            $query->where(function ($q) {
                $q->whereIn('estado', [
                    InscripcionState::DOCUMENTOS_APROBADOS,
                    InscripcionState::PAGADO,
                    InscripcionState::INSCRITO,
                    InscripcionState::EN_CURSO,
                    InscripcionState::FINALIZADO,
                ])->orWhereHas('validacionDocumental', fn ($vq) => $vq->where('estado', 'aprobada'));
            });
        } elseif ($estado === 'rechazados') {
            $query->where(function ($q) {
                $q->where('estado', InscripcionState::DOCUMENTOS_RECHAZADOS)
                  ->orWhereHas('validacionDocumental', fn ($vq) => $vq->where('estado', 'rechazada'));
            });
        }

        return $query->orderBy('fecha_inscripcion', 'asc')->get();
    }

    /**
     * Obtiene el expediente documental de una inscripción.
     * Si no tiene documentos cargados, genera placeholders para los obligatorios.
     *
     * @return array{inscripcion: Inscripcion, documentos: Collection<int, Documento>, validacion: ValidacionDocumental|null}
     */
    public function obtenerExpediente(int $inscripcionId): array
    {
        /** @var Inscripcion $inscripcion */
        $inscripcion = Inscripcion::with(['postulante', 'gestion', 'validacionDocumental', 'documentos'])
            ->findOrFail($inscripcionId);

        // Si no tiene documentos, generamos "placeholders" para los obligatorios en la UI
        if ($inscripcion->documentos->isEmpty()) {
            foreach (TipoDocumento::OBLIGATORIOS as $tipo) {
                Documento::create([
                    'inscripcion_id' => $inscripcion->id,
                    'tipo' => $tipo,
                    'estado' => DocumentoState::PENDIENTE,
                ]);
            }
            $inscripcion->load('documentos');
        }

        $sinPrevalidacion = $inscripcion->documentos
            ->contains(fn (Documento $documento) => $documento->prevalidacion_estado === null);

        if ($sinPrevalidacion) {
            $this->prevalidacionDocumentalService->prevalidarInscripcion($inscripcion);
            $inscripcion->load('documentos');
        }

        return [
            'inscripcion' => $inscripcion,
            'documentos' => $inscripcion->documentos,
            'validacion' => $inscripcion->validacionDocumental,
        ];
    }

    /**
     * Procesa la validación documental de una inscripción.
     *
     * @param int $inscripcionId
     * @param array<int, array{id: int, estado: string, observacion: string|null}> $revisiones
     * @param Request $request
     * @return array{validacion: ValidacionDocumental, credenciales: array{numero_registro: string, password_temporal: string, correo_enviado: bool}|null}
     * @throws \DomainException
     */
    public function validarDocumentos(int $inscripcionId, array $revisiones, Request $request): array
    {
        $inscripcion = Inscripcion::findOrFail($inscripcionId);
        $user = $request->user();

        if ($inscripcion->gestion_id !== $this->gestionVigenteService->actual()?->id) {
            throw new \DomainException('La inscripcion no pertenece a la gestion vigente.');
        }

        if ($user === null || $user->role !== 'admin') {
            throw new \DomainException('No tiene permisos para realizar esta acción.');
        }

        return DB::transaction(function () use ($inscripcion, $revisiones, $user, $request) {
            $todosAprobados = true;
            $algunoRechazado = false;
            $algunoObservado = false;

            // 1. Actualizar cada documento
            foreach ($revisiones as $rev) {
                $documento = Documento::where('inscripcion_id', $inscripcion->id)
                    ->where('id', $rev['id'])
                    ->firstOrFail();

                $documento->update([
                    'estado' => $rev['estado'],
                    'observacion' => $rev['observacion'],
                    'revisado_por' => $user->id,
                    'revisado_en' => now(),
                ]);

                if ($rev['estado'] === DocumentoState::RECHAZADO) {
                    $algunoRechazado = true;
                    $todosAprobados = false;
                } elseif ($rev['estado'] === DocumentoState::OBSERVADO) {
                    $algunoObservado = true;
                    $todosAprobados = false;
                } elseif ($rev['estado'] === DocumentoState::PENDIENTE) {
                    $todosAprobados = false;
                }
            }

            // 2. Determinar estado global de la validación
            $estadoValidacion = ValidacionDocumentalState::PENDIENTE;
            if ($todosAprobados) {
                $estadoValidacion = ValidacionDocumentalState::APROBADA;
            } elseif ($algunoRechazado) {
                $estadoValidacion = ValidacionDocumentalState::RECHAZADA;
            } elseif ($algunoObservado) {
                $estadoValidacion = ValidacionDocumentalState::OBSERVADA;
            }

            // 3. Crear o actualizar la validación documental global
            $validacion = ValidacionDocumental::updateOrCreate(
                ['inscripcion_id' => $inscripcion->id],
                [
                    'estado' => $estadoValidacion,
                    'validado_por' => $user->id,
                    'validado_en' => now(),
                ]
            );

            // 4. Sincronizar estado de la inscripción
            $nuevoEstadoInscripcion = $inscripcion->estado;
            if ($estadoValidacion === ValidacionDocumentalState::APROBADA) {
                $nuevoEstadoInscripcion = InscripcionState::DOCUMENTOS_APROBADOS;
            } elseif ($estadoValidacion === ValidacionDocumentalState::OBSERVADA) {
                $nuevoEstadoInscripcion = InscripcionState::DOCUMENTOS_PENDIENTES;
            } elseif ($estadoValidacion === ValidacionDocumentalState::RECHAZADA) {
                $nuevoEstadoInscripcion = InscripcionState::DOCUMENTOS_RECHAZADOS;
            }

            if ($nuevoEstadoInscripcion !== $inscripcion->estado) {
                $inscripcion->update(['estado' => $nuevoEstadoInscripcion]);
            }

            $credenciales = null;

            // 5. Registrar auditoría
            $this->auditLogService->record(
                'validacion.documental.completada',
                $user,
                $request,
                [
                    'inscripcion_id' => $inscripcion->id,
                    'estado_validacion' => $estadoValidacion,
                    'nuevo_estado_inscripcion' => $nuevoEstadoInscripcion,
                    'credenciales_emitidas' => false,
                    'revisiones' => $revisiones,
                ]
            );

            return [
                'validacion' => $validacion,
                'credenciales' => null,
            ];
        });
    }
}
