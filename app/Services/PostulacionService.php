<?php

namespace App\Services;

use App\Models\Carrera;
use App\Models\Gestion;
use App\Models\Inscripcion;
use App\Models\OpcionCarrera;
use App\Models\Postulante;
use App\Support\States\GestionState;
use App\Support\States\InscripcionState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PostulacionService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /* ------------------------------------------------------------------ */
    /*  Datos para el formulario                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Retorna los datos necesarios para armar el formulario de postulación:
     * gestión vigente y carreras activas.
     *
     * @return array{gestion: Gestion|null, carreras: \Illuminate\Database\Eloquent\Collection<int, Carrera>}
     */
    public function datosFormulario(): array
    {
        $gestion = Gestion::habilitadaParaInscripcion()->first();

        $carreras = Carrera::activa()
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);

        return [
            'gestion' => $gestion,
            'carreras' => $carreras,
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Registro de postulación                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Ejecuta el flujo completo de registro de postulación dentro de una
     * transacción de base de datos.
     *
     * Pasos:
     * 1. Valida que la gestión esté habilitada.
     * 2. Valida que las dos carreras sean distintas y activas.
     * 3. Verifica que no exista inscripción previa (E1).
     * 4. Crea o actualiza el postulante.
     * 5. Crea la inscripción con estado prepostulado.
     * 6. Inserta las dos opciones de carrera.
     * 7. Registra auditoría.
     *
     * @param array<string, mixed> $data Datos validados del FormRequest
     *
     * @throws \App\Exceptions\PostulacionException
     */
    public function registrar(array $data, Request $request): Inscripcion
    {
        /* 1. Gestión habilitada */
        $gestion = Gestion::find($data['gestion_id']);

        if ($gestion === null || $gestion->estado !== GestionState::INSCRIPCION) {
            throw new \DomainException('La gestión seleccionada no está habilitada para inscripción.');
        }

        /* 2. Carreras distintas y activas */
        $primeraId = $data['carrera_primera_opcion_id'];
        $segundaId = $data['carrera_segunda_opcion_id'];

        if ((int) $primeraId === (int) $segundaId) {
            throw new \DomainException('La primera y segunda opción de carrera no pueden ser iguales.');
        }

        $carrerasSeleccionadas = Carrera::activa()
            ->whereIn('id', [$primeraId, $segundaId])
            ->pluck('id');

        if ($carrerasSeleccionadas->count() !== 2) {
            throw new \DomainException('Una o ambas carreras seleccionadas no están activas o no existen.');
        }

        /* 3-7 Dentro de transacción */
        return DB::transaction(function () use ($data, $gestion, $primeraId, $segundaId, $request) {

            /* 3. Crear o actualizar postulante */
            $postulante = Postulante::updateOrCreate(
                ['ci' => $data['ci']],
                [
                    'complemento' => $data['complemento'] ?? null,
                    'nombres' => $data['nombres'],
                    'apellido_paterno' => $data['apellido_paterno'],
                    'apellido_materno' => $data['apellido_materno'] ?? null,
                    'fecha_nacimiento' => $data['fecha_nacimiento'],
                    'genero' => $data['genero'],
                    'correo' => $data['correo'],
                    'telefono' => $data['telefono'],
                    'direccion' => $data['direccion'] ?? null,
                    'colegio_procedencia' => $data['colegio_procedencia'],
                    'ciudad' => $data['ciudad'],
                ]
            );

            /* 4. Verificar duplicidad (E1) */
            $existeInscripcion = Inscripcion::where('postulante_id', $postulante->id)
                ->where('gestion_id', $gestion->id)
                ->exists();

            if ($existeInscripcion) {
                throw new \DomainException(
                    'Ya existe una inscripción del postulante en esta gestión académica.'
                );
            }

            /* 5. Crear inscripción */
            $inscripcion = Inscripcion::create([
                'postulante_id' => $postulante->id,
                'gestion_id' => $gestion->id,
                'codigo' => Inscripcion::generarCodigo($gestion->anio),
                'fecha_inscripcion' => now(),
                'estado' => InscripcionState::PREPOSTULADO,
            ]);

            /* 6. Opciones de carrera */
            OpcionCarrera::create([
                'inscripcion_id' => $inscripcion->id,
                'carrera_id' => $primeraId,
                'prioridad' => 1,
            ]);

            OpcionCarrera::create([
                'inscripcion_id' => $inscripcion->id,
                'carrera_id' => $segundaId,
                'prioridad' => 2,
            ]);

            /* 7. Auditoría */
            $this->auditLogService->record(
                'postulacion.registrada',
                $request->user(),
                $request,
                [
                    'inscripcion_id' => $inscripcion->id,
                    'codigo' => $inscripcion->codigo,
                    'postulante_ci' => $postulante->ci,
                    'gestion' => $gestion->nombre,
                    'primera_opcion_carrera_id' => $primeraId,
                    'segunda_opcion_carrera_id' => $segundaId,
                ]
            );

            /* Cargar relaciones para la respuesta */
            $inscripcion->load(['postulante', 'gestion', 'opcionesCarrera.carrera']);

            return $inscripcion;
        });
    }
}
