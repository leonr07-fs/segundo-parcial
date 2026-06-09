<?php

namespace App\Services\GruposDocentes;

use App\Models\Docentes\RepostulacionDocente;
use App\Models\GestionAcademica\Docente;
use App\Models\Seguridad\User;
use App\Services\GestionAcademica\GestionVigenteService;
use App\Services\SeguridadUsuarios\AuditLogService;
use App\Support\States\RepostulacionDocenteState;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class RepostulacionDocenteService
{
    public function __construct(
        private readonly GestionVigenteService $gestionVigenteService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @return array{docente: array<string, mixed>, gestion: array<string, mixed>|null, repostulacion: array<string, mixed>|null}
     */
    public function registrarSolicitud(string $ci, string $correo): array
    {
        $ci = trim($ci);
        $correo = strtolower(trim($correo));

        $docente = Docente::where('ci', $ci)->first();

        if ($docente === null) {
            throw new DomainException('No se encontró un registro docente previo con el CI proporcionado.');
        }

        if (strtolower(trim($docente->correo)) !== $correo) {
            throw new DomainException('El correo electrónico no coincide con el registrado para este CI.');
        }

        $gestionVigente = $this->gestionVigenteService->actual();

        if ($gestionVigente === null) {
            throw new DomainException('No hay una gestión vigente habilitada para repostulaciones docentes.');
        }

        if ($this->gestionVigenteService->docentePuedeAcceder($docente)) {
            throw new DomainException('Ya tiene acceso habilitado para la gestión vigente.');
        }

        $repostulacionExistente = RepostulacionDocente::where('docente_id', $docente->id)
            ->where('gestion_id', $gestionVigente->id)
            ->first();

        if ($repostulacionExistente !== null) {
            if ($repostulacionExistente->estado === RepostulacionDocenteState::PENDIENTE) {
                return $this->resumenSolicitud($docente, $gestionVigente, $repostulacionExistente);
            }

            if ($repostulacionExistente->estado === RepostulacionDocenteState::RECHAZADA) {
                throw new DomainException('Su repostulación fue rechazada para esta gestión. Contacte a administración.');
            }

            throw new DomainException('Ya existe una solicitud de repostulación registrada para la gestión vigente.');
        }

        $repostulacion = RepostulacionDocente::create([
            'docente_id' => $docente->id,
            'gestion_id' => $gestionVigente->id,
            'estado' => RepostulacionDocenteState::PENDIENTE,
        ]);

        $this->notificarAdministradores($docente, $gestionVigente);

        return $this->resumenSolicitud($docente, $gestionVigente, $repostulacion);
    }

    public function listar(array $filtros = [])
    {
        $gestionVigente = $this->gestionVigenteService->actual();

        return RepostulacionDocente::with(['docente', 'gestion', 'revisadoPor'])
            ->when($gestionVigente, fn ($query) => $query->where('gestion_id', $gestionVigente->id))
            ->when(!$gestionVigente, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($filtros['estado'] ?? null, fn ($query, $estado) => $query->where('estado', $estado))
            ->latest('id')
            ->paginate(25);
    }

    public function aprobar(int $repostulacionId, User $admin, ?Request $request = null): array
    {
        return DB::transaction(function () use ($repostulacionId, $admin, $request) {
            $repostulacion = RepostulacionDocente::with(['docente', 'gestion'])->findOrFail($repostulacionId);

            if ($repostulacion->estado !== RepostulacionDocenteState::PENDIENTE) {
                throw new DomainException('Solo se pueden aprobar repostulaciones pendientes.');
            }

            $docente = $repostulacion->docente;
            $codigoDocente = $docente->ci;
            $passwordTemporal = $docente->ci;

            $docente->update(['activo' => true]);

            $usuarioExistente = User::where('email', $docente->correo)->first();
            if ($usuarioExistente && $usuarioExistente->role !== User::ROLE_DOCENTE) {
                throw new DomainException('El correo del docente pertenece a un usuario con un rol diferente.');
            }

            $user = User::updateOrCreate([
                'email' => $docente->correo,
            ], [
                'name' => trim($docente->nombres . ' ' . ($docente->apellidos ?? '')),
                'numero_registro' => $codigoDocente,
                'password' => $passwordTemporal,
                'role' => User::ROLE_DOCENTE,
                'is_active' => true,
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ]);

            $repostulacion->update([
                'estado' => RepostulacionDocenteState::APROBADA,
                'observacion' => null,
                'revisado_por' => $admin->id,
                'revisado_en' => now(),
            ]);

            $correoEnviado = $this->enviarCredencialesRepostulacion($docente, $repostulacion, $codigoDocente, $passwordTemporal);

            $this->auditLogService->record(
                'repostulacion.docente.aprobada',
                $admin,
                $request ?? request(),
                [
                    'repostulacion_id' => $repostulacion->id,
                    'docente_id' => $docente->id,
                    'gestion_id' => $repostulacion->gestion_id,
                    'correo_enviado' => $correoEnviado,
                ]
            );

            return [
                'repostulacion' => $repostulacion->fresh(['docente', 'gestion']),
                'docente' => $docente->fresh(),
                'usuario' => $user,
                'credenciales' => [
                    'codigo_docente' => $codigoDocente,
                    'password_temporal' => $passwordTemporal,
                    'correo_enviado' => $correoEnviado,
                ],
            ];
        });
    }

    public function rechazar(int $repostulacionId, User $admin, ?string $observacion, ?Request $request = null): RepostulacionDocente
    {
        return DB::transaction(function () use ($repostulacionId, $admin, $observacion, $request) {
            $repostulacion = RepostulacionDocente::with('docente')->findOrFail($repostulacionId);

            if ($repostulacion->estado !== RepostulacionDocenteState::PENDIENTE) {
                throw new DomainException('Solo se pueden rechazar repostulaciones pendientes.');
            }

            $repostulacion->update([
                'estado' => RepostulacionDocenteState::RECHAZADA,
                'observacion' => $observacion,
                'revisado_por' => $admin->id,
                'revisado_en' => now(),
            ]);

            User::where('email', $repostulacion->docente->correo)
                ->where('role', User::ROLE_DOCENTE)
                ->update(['is_active' => false]);

            $this->auditLogService->record(
                'repostulacion.docente.rechazada',
                $admin,
                $request ?? request(),
                [
                    'repostulacion_id' => $repostulacion->id,
                    'docente_id' => $repostulacion->docente_id,
                    'observacion' => $observacion,
                ]
            );

            return $repostulacion->fresh(['docente', 'gestion', 'revisadoPor']);
        });
    }

    /**
     * @return array{docente: array<string, mixed>, gestion: array<string, mixed>, repostulacion: array<string, mixed>}
     */
    private function resumenSolicitud(Docente $docente, $gestion, RepostulacionDocente $repostulacion): array
    {
        return [
            'docente' => [
                'id' => $docente->id,
                'ci' => $docente->ci,
                'nombres' => $docente->nombres,
                'apellidos' => $docente->apellidos,
                'correo' => $docente->correo,
            ],
            'gestion' => [
                'id' => $gestion->id,
                'nombre' => $gestion->nombre,
            ],
            'repostulacion' => [
                'id' => $repostulacion->id,
                'estado' => $repostulacion->estado,
            ],
        ];
    }

    private function enviarCredencialesRepostulacion(
        Docente $docente,
        RepostulacionDocente $repostulacion,
        string $codigoDocente,
        string $passwordTemporal,
    ): bool {
        try {
            Mail::raw(
                "Estimado(a) " . trim($docente->nombres . ' ' . ($docente->apellidos ?? '')) . ",\n\n" .
                "Su repostulación docente para la gestión {$repostulacion->gestion?->nombre} fue APROBADA.\n\n" .
                "--- CREDENCIALES DE ACCESO ---\n" .
                "Código docente: {$codigoDocente}\n" .
                "Contraseña temporal: {$passwordTemporal}\n\n" .
                "Ingrese al sistema en: " . url('/login') . "\n\n" .
                "Se recomienda cambiar su contraseña al ingresar.",
                function ($message) use ($docente) {
                    $message->to($docente->correo)->subject('Repostulación docente aprobada - CUP FICCT');
                }
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function notificarAdministradores(Docente $docente, $gestion): void
    {
        $admins = User::where('role', User::ROLE_ADMIN)->where('is_active', true)->get();

        foreach ($admins as $admin) {
            try {
                Mail::raw(
                    "Nueva repostulación docente recibida.\n\n" .
                    "Nombre: {$docente->nombres} {$docente->apellidos}\n" .
                    "CI: {$docente->ci}\n" .
                    "Correo: {$docente->correo}\n" .
                    "Gestión: {$gestion->nombre}\n\n" .
                    "Ingrese al módulo de repostulaciones docentes para revisarla.",
                    function ($message) use ($admin) {
                        $message->to($admin->email)->subject('Nueva repostulación docente CUP');
                    }
                );
            } catch (\Throwable) {
                // No interrumpir el registro si el correo falla.
            }
        }
    }
}
