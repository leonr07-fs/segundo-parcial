<?php

namespace App\Services\PortalPostulante;

use App\Services\SeguridadUsuarios\AuditLogService;

use App\Models\AsignacionCarrera\Carrera;
use App\Models\GestionAcademica\Gestion;
use App\Models\InscripcionPagos\Inscripcion;
use App\Models\AsignacionCarrera\OpcionCarrera;
use App\Models\InscripcionPagos\Postulante;
use App\Models\InscripcionPagos\Documento;
use App\Models\Seguridad\User;
use App\Support\States\GestionState;
use App\Support\States\InscripcionState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * CU02 - Registrar postulación CUP
 * Servicio que procesa la creación de postulantes, opciones de carrera y la inscripción en la gestión vigente.
 *
 * Participantes del CU02 (Diagrama de Secuencia):
 * - Control: PostulacionController
 * - Control: PostulacionService (Actual)
 * - Entity: Postulante, Inscripcion, OpcionCarrera, Documento
 * - Control: AuditLogService
 */
class PostulacionService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly PrevalidacionDocumentalService $prevalidacionDocumentalService,
    )
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

        if (User::where('email', $data['correo'])->exists()) {
            throw ValidationException::withMessages([
                'correo' => ['Este correo electronico ya esta registrado en el sistema.'],
            ]);
        }

        if (User::where('numero_registro', $data['ci'])->exists()) {
            throw ValidationException::withMessages([
                'ci' => ['Este carnet de identidad ya esta registrado en el sistema.'],
            ]);
        }

        $postulanteConCorreo = Postulante::where('correo', $data['correo'])
            ->where('ci', '<>', $data['ci'])
            ->exists();

        if ($postulanteConCorreo) {
            throw ValidationException::withMessages([
                'correo' => ['Este correo electronico ya fue registrado por otro postulante.'],
            ]);
        }

        $postulanteConCi = Postulante::where('ci', $data['ci'])
            ->where('correo', '<>', $data['correo'])
            ->exists();

        if ($postulanteConCi) {
            throw ValidationException::withMessages([
                'ci' => ['Este carnet de identidad ya fue registrado con otro correo electronico.'],
            ]);
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

            /* 6.5. Guardar documentos (Archivos) */
            if (isset($data['foto_ci']) && $data['foto_ci'] instanceof \Illuminate\Http\UploadedFile) {
                $pathCi = $data['foto_ci']->store('documentos/' . $gestion->anio . '/' . $inscripcion->codigo, 'public');
                Documento::create([
                    'inscripcion_id' => $inscripcion->id,
                    'tipo' => 'carnet_identidad',
                    'archivo_path' => $pathCi,
                    'estado' => 'pendiente',
                ]);
            }

            if (isset($data['foto_libreta']) && $data['foto_libreta'] instanceof \Illuminate\Http\UploadedFile) {
                $pathLibreta = $data['foto_libreta']->store('documentos/' . $gestion->anio . '/' . $inscripcion->codigo, 'public');
                Documento::create([
                    'inscripcion_id' => $inscripcion->id,
                    'tipo' => 'libreta_digitalizada',
                    'archivo_path' => $pathLibreta,
                    'estado' => 'pendiente',
                ]);
            }

            $resumenPrevalidacion = $this->prevalidacionDocumentalService->prevalidarInscripcion($inscripcion->fresh(['postulante', 'documentos']));

            $this->notificarAdministradores($inscripcion);

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
                    'prevalidacion_documental' => $resumenPrevalidacion,
                ]
            );

            /* Cargar relaciones para la respuesta */
            $inscripcion->load(['postulante', 'gestion', 'opcionesCarrera.carrera']);

            return $inscripcion;
        });
    }

    private function notificarAdministradores(Inscripcion $inscripcion): void
    {
        $admins = User::where('role', User::ROLE_ADMIN)->where('is_active', true)->get();

        foreach ($admins as $admin) {
            try {
                Mail::raw(
                    "Nueva solicitud de postulante recibida.\n\n" .
                    "Codigo: {$inscripcion->codigo}\n" .
                    "Postulante: {$inscripcion->postulante->nombres} {$inscripcion->postulante->apellido_paterno}\n" .
                    "CI: {$inscripcion->postulante->ci}\n" .
                    "Correo: {$inscripcion->postulante->correo}\n\n" .
                    "Ingrese al sistema para revisar los documentos.",
                    function ($message) use ($admin) {
                        $message->to($admin->email)->subject('Nueva solicitud de postulante CUP');
                    }
                );
            } catch (\Throwable) {
                // La solicitud no debe fallar si el correo no esta configurado.
            }
        }
    }
}
