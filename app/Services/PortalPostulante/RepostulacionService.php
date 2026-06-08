<?php

namespace App\Services\PortalPostulante;

use App\Models\GestionAcademica\Gestion;
use App\Models\InscripcionPagos\Documento;
use App\Models\InscripcionPagos\Inscripcion;
use App\Models\InscripcionPagos\Postulante;
use App\Models\InscripcionPagos\ValidacionDocumental;
use App\Models\AsignacionCarrera\OpcionCarrera;
use App\Services\GestionAcademica\GestionVigenteService;
use App\Support\States\GestionState;
use App\Support\States\InscripcionState;
use App\Support\States\ValidacionDocumentalState;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * CU06 - Habilitar repostulación en nueva gestión
 * Servicio para validar la elegibilidad de repostulación y preparar inscripciones de repostulación.
 */
class RepostulacionService
{
    public function __construct(private readonly GestionVigenteService $gestionVigenteService)
    {
    }

    /**
     * Valida identidad y elegibilidad académica para repostulación pública.
     *
     * @return array{postulante: array<string, mixed>, gestion: array<string, mixed>|null, inscripcion_pendiente: array<string, mixed>|null}
     */
    public function validarElegibilidad(string $ci, string $correo): array
    {
        $ci = trim($ci);
        $correo = strtolower(trim($correo));

        $postulante = Postulante::where('ci', $ci)->first();

        if ($postulante === null) {
            throw new DomainException('No se encontró un estudiante con el CI proporcionado.');
        }

        if (strtolower(trim($postulante->correo)) !== $correo) {
            throw new DomainException('El correo electrónico no coincide con el registrado para este CI.');
        }

        $gestionRepostulacion = $this->gestionVigenteService->paraRepostulacion();

        if ($gestionRepostulacion === null) {
            throw new DomainException('No hay una gestión abierta para repostulaciones en este momento.');
        }

        $inscripcionExistente = Inscripcion::where('postulante_id', $postulante->id)
            ->where('gestion_id', $gestionRepostulacion->id)
            ->where('estado', '!=', InscripcionState::CANCELADO)
            ->first();

        if ($inscripcionExistente !== null) {
            if ($inscripcionExistente->estado === InscripcionState::DOCUMENTOS_APROBADOS) {
                return [
                    'postulante' => $this->resumenPostulante($postulante),
                    'gestion' => $this->resumenGestion($gestionRepostulacion),
                    'inscripcion_pendiente' => [
                        'id' => $inscripcionExistente->id,
                        'codigo' => $inscripcionExistente->codigo,
                        'estado' => $inscripcionExistente->estado,
                    ],
                ];
            }

            if (in_array($inscripcionExistente->estado, [InscripcionState::PAGADO, InscripcionState::INSCRITO, InscripcionState::EN_CURSO, InscripcionState::FINALIZADO], true)) {
                throw new DomainException('Ya tiene una inscripción activa en la gestión vigente.');
            }

            throw new DomainException('Ya tiene una solicitud de repostulación en proceso para la gestión vigente.');
        }

        $inscripcionReprobada = Inscripcion::with('resultadoCup')
            ->where('postulante_id', $postulante->id)
            ->whereHas('resultadoCup', fn ($q) => $q->where('estado_final', 'reprobado'))
            ->latest('fecha_inscripcion')
            ->first();

        if ($inscripcionReprobada === null) {
            throw new DomainException(
                'Solo los estudiantes con estado reprobado pueden realizar una repostulación. '
                . 'Su estado académico actual no permite continuar con este proceso.'
            );
        }

        return [
            'postulante' => $this->resumenPostulante($postulante),
            'gestion' => $this->resumenGestion($gestionRepostulacion),
            'inscripcion_pendiente' => null,
        ];
    }

    /**
     * Crea la inscripción de repostulación lista para pago PayPal.
     */
    public function prepararRepostulacion(string $ci, string $correo): Inscripcion
    {
        $validacion = $this->validarElegibilidad($ci, $correo);

        if ($validacion['inscripcion_pendiente'] !== null) {
            return Inscripcion::findOrFail($validacion['inscripcion_pendiente']['id']);
        }

        $postulante = Postulante::where('ci', trim($ci))->firstOrFail();
        $gestion = $this->gestionVigenteService->paraRepostulacion();

        if ($gestion === null) {
            throw new DomainException('No hay una gestión abierta para repostulaciones en este momento.');
        }

        $inscripcionAnterior = Inscripcion::with(['validacionDocumental', 'opcionesCarrera'])
            ->where('postulante_id', $postulante->id)
            ->whereHas('resultadoCup', fn ($q) => $q->where('estado_final', 'reprobado'))
            ->latest('fecha_inscripcion')
            ->first();

        if ($inscripcionAnterior === null) {
            throw new DomainException('No se encontró una inscripción reprobada para repostular.');
        }

        $opcionesAnteriores = $inscripcionAnterior->opcionesCarrera->sortBy('prioridad')->values();
        if ($opcionesAnteriores->count() < 2) {
            throw new DomainException('La inscripción anterior no tiene opciones de carrera registradas.');
        }

        return DB::transaction(function () use ($postulante, $gestion, $inscripcionAnterior, $opcionesAnteriores) {
            $tieneDocumentosAprobados = $inscripcionAnterior->validacionDocumental?->estado === ValidacionDocumentalState::APROBADA;

            $inscripcion = Inscripcion::create([
                'postulante_id' => $postulante->id,
                'gestion_id' => $gestion->id,
                'codigo' => Inscripcion::generarCodigo((int) $gestion->anio),
                'fecha_inscripcion' => now(),
                'estado' => $tieneDocumentosAprobados
                    ? InscripcionState::DOCUMENTOS_APROBADOS
                    : InscripcionState::PREPOSTULADO,
            ]);

            foreach ($opcionesAnteriores as $opcion) {
                OpcionCarrera::create([
                    'inscripcion_id' => $inscripcion->id,
                    'carrera_id' => $opcion->carrera_id,
                    'prioridad' => $opcion->prioridad,
                ]);
            }

            if ($tieneDocumentosAprobados) {
                $this->clonarDocumentacion($inscripcionAnterior, $inscripcion);
            }

            return $inscripcion;
        });
    }

    /**
     * @deprecated Solo para compatibilidad interna; usar prepararRepostulacion() en flujo público.
     */
    public function repostular(array $datos): Inscripcion
    {
        $postulante = Postulante::findOrFail($datos['postulante_id']);
        $gestion = Gestion::findOrFail($datos['gestion_id']);

        if ($gestion->estado !== GestionState::INSCRIPCION) {
            throw new DomainException('La gestión seleccionada no está habilitada para repostulación.');
        }

        return DB::transaction(function () use ($datos, $postulante, $gestion) {
            $existeInscripcion = Inscripcion::where('postulante_id', $postulante->id)
                ->where('gestion_id', $gestion->id)
                ->where('estado', '!=', InscripcionState::CANCELADO)
                ->exists();

            if ($existeInscripcion) {
                throw new DomainException('El postulante ya tiene una inscripción activa en la gestión seleccionada.');
            }

            $inscripcionAnterior = Inscripcion::where('postulante_id', $postulante->id)
                ->whereHas('validacionDocumental', function ($q) {
                    $q->where('estado', ValidacionDocumentalState::APROBADA);
                })
                ->latest('fecha_inscripcion')
                ->first();

            $estadoInicial = $inscripcionAnterior
                ? InscripcionState::DOCUMENTOS_APROBADOS
                : InscripcionState::PREPOSTULADO;

            $inscripcion = Inscripcion::create([
                'postulante_id' => $postulante->id,
                'gestion_id' => $gestion->id,
                'codigo' => Inscripcion::generarCodigo((int) $gestion->anio),
                'fecha_inscripcion' => now(),
                'estado' => $estadoInicial,
            ]);

            OpcionCarrera::create([
                'inscripcion_id' => $inscripcion->id,
                'carrera_id' => $datos['opcion1_carrera_id'],
                'prioridad' => 1,
            ]);

            OpcionCarrera::create([
                'inscripcion_id' => $inscripcion->id,
                'carrera_id' => $datos['opcion2_carrera_id'],
                'prioridad' => 2,
            ]);

            if ($inscripcionAnterior) {
                $this->clonarDocumentacion($inscripcionAnterior, $inscripcion);
            }

            return $inscripcion;
        });
    }

    private function clonarDocumentacion(Inscripcion $inscripcionAnterior, Inscripcion $inscripcion): void
    {
        $documentosAnteriores = Documento::where('inscripcion_id', $inscripcionAnterior->id)->get();
        foreach ($documentosAnteriores as $doc) {
            Documento::create([
                'inscripcion_id' => $inscripcion->id,
                'tipo' => $doc->tipo,
                'numero' => $doc->numero,
                'archivo_path' => $doc->archivo_path,
                'estado' => $doc->estado,
                'observacion' => 'Documento heredado de gestión anterior.',
                'revisado_por' => $doc->revisado_por,
                'revisado_en' => $doc->revisado_en,
            ]);
        }

        $validacionAnterior = $inscripcionAnterior->validacionDocumental;
        if ($validacionAnterior) {
            ValidacionDocumental::create([
                'inscripcion_id' => $inscripcion->id,
                'estado' => $validacionAnterior->estado,
                'observacion' => 'Validación heredada de gestión anterior.',
                'validado_por' => $validacionAnterior->validado_por,
                'validado_en' => now(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resumenPostulante(Postulante $postulante): array
    {
        return [
            'id' => $postulante->id,
            'ci' => $postulante->ci,
            'nombres' => $postulante->nombres,
            'apellido_paterno' => $postulante->apellido_paterno,
            'correo' => $postulante->correo,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resumenGestion(Gestion $gestion): array
    {
        return [
            'id' => $gestion->id,
            'nombre' => $gestion->nombre,
            'anio' => $gestion->anio,
        ];
    }
}
