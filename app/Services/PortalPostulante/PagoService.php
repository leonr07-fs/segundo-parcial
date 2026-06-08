<?php

namespace App\Services\PortalPostulante;

use App\Services\SeguridadUsuarios\AuditLogService;

use App\Models\InscripcionPagos\Inscripcion;
use App\Models\InscripcionPagos\Pago;
use App\Models\InscripcionPagos\Recibo;
use App\Models\Seguridad\User;
use App\Services\GestionAcademica\CredentialService;
use App\Support\States\InscripcionState;
use App\Support\States\PagoState;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * CU04 - Registrar/verificar pago CUP y confirmar inscripción
 * Servicio que valida y registra pagos de inscripciones, genera recibos y emite credenciales de postulante.
 */
class PagoService
{
    /**
     * El arancel del CUP según reglamento (E3).
     */
    public const ARANCEL_CUP = 300.00;

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CredentialService $credentialService,
    ) {
    }

    /**
     * Lista inscripciones que están en estado `documentos_aprobados`, es decir, listas para pagar.
     *
     * @return Collection<int, Inscripcion>
     */
    public function listarPendientesPago(): Collection
    {
        return Inscripcion::with(['postulante', 'gestion'])
            ->where('estado', InscripcionState::DOCUMENTOS_APROBADOS)
            ->orderBy('fecha_inscripcion', 'asc')
            ->get();
    }

    /**
     * Procesa el pago de un postulante (CUP).
     *
     * @param int $inscripcionId
     * @param array{monto: float, metodo: string, referencia: string} $datos
     * @param Request $request
     * @return array{pago: Pago, recibo: Recibo}
     * @throws \DomainException
     */
    public function registrarPago(int $inscripcionId, array $datos, Request $request): array
    {
        $inscripcion = Inscripcion::findOrFail($inscripcionId);
        $user = $request->user();

        // Validar estado de la inscripción
        if ($inscripcion->estado !== InscripcionState::DOCUMENTOS_APROBADOS) {
            throw new \DomainException('La inscripción no está habilitada para registrar pagos.');
        }

        // E3: Validar que el monto coincida con el arancel vigente
        if ((float) $datos['monto'] !== self::ARANCEL_CUP) {
            throw new \DomainException(sprintf(
                'El monto ingresado (%.2f BOB) no coincide con el arancel del CUP (%.2f BOB).',
                $datos['monto'],
                self::ARANCEL_CUP
            ));
        }

        // E1: Referencia duplicada (doble check, aunque el Request lo valida)
        if (Pago::where('referencia', $datos['referencia'])->exists()) {
            throw new \DomainException('La referencia de pago ya se encuentra registrada en otro pago.');
        }

        return DB::transaction(function () use ($inscripcion, $datos, $user, $request) {
            // 1. Crear el Pago
            $pago = Pago::create([
                'inscripcion_id' => $inscripcion->id,
                'monto' => $datos['monto'],
                'moneda' => 'BOB',
                'metodo' => $datos['metodo'],
                'referencia' => $datos['referencia'],
                'estado' => PagoState::APROBADO, // Se asume aprobado inmediatamente al ser registro manual/verificado
                'pagado_en' => now(),
            ]);

            // 2. Generar el Recibo
            $recibo = Recibo::create([
                'pago_id' => $pago->id,
                'numero' => Recibo::generarNumero(),
                'emitido_por' => $user?->id,
                'emitido_en' => now(),
            ]);

            // 3. Actualizar inscripción
            // Dependiendo del flujo, puede pasar a pagado o inscrito. Usaremos PAGADO.
            $inscripcion->update(['estado' => InscripcionState::PAGADO]);

            // 4. Emitir credenciales del postulante
            $credenciales = $this->emitirCredencialesPostulante($inscripcion, $user, $request);

            // 5. Auditoría
            $this->auditLogService->record(
                'pago.registrado',
                $user,
                $request,
                [
                    'inscripcion_id' => $inscripcion->id,
                    'pago_id' => $pago->id,
                    'recibo_numero' => $recibo->numero,
                    'referencia' => $pago->referencia,
                    'credenciales_emitidas' => true,
                ]
            );

            return [
                'pago' => $pago,
                'recibo' => $recibo,
                'credenciales' => $credenciales,
            ];
        });
    }

    private function emitirCredencialesPostulante(Inscripcion $inscripcion, User $admin, Request $request): array
    {
        $postulante = $inscripcion->postulante;
        $numeroRegistro = $this->credentialService->generarRegistroEstudianteUAGRM();
        $passwordTemporal = $postulante->ci;

        $usuarioExistente = User::where('email', $postulante->correo)->first();
        if ($usuarioExistente && $usuarioExistente->role !== User::ROLE_POSTULANTE) {
            throw new \DomainException('El correo del postulante pertenece a un usuario con un rol diferente.');
        }

        $usuario = User::updateOrCreate([
            'email' => $postulante->correo,
        ], [
            'name' => trim($postulante->nombres . ' ' . ($postulante->apellido_paterno ?? '') . ' ' . ($postulante->apellido_materno ?? '')),
            'numero_registro' => $numeroRegistro,
            'password' => $passwordTemporal,
            'role' => User::ROLE_POSTULANTE,
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $correoEnviado = $this->enviarCredencialesPostulante($postulante->correo, $numeroRegistro, $passwordTemporal);

        $this->auditLogService->record(
            'postulante.credenciales.emitidas',
            $admin,
            $request,
            [
                'tabla' => 'users',
                'registro_id' => $usuario->id,
                'inscripcion_id' => $inscripcion->id,
                'numero_registro' => $numeroRegistro,
                'correo_enviado' => $correoEnviado,
            ]
        );

        return [
            'numero_registro' => $numeroRegistro,
            'password_temporal' => $passwordTemporal,
            'correo_enviado' => $correoEnviado,
        ];
    }

    private function enviarCredencialesPostulante(string $correo, string $numeroRegistro, string $passwordTemporal): bool
    {
        try {
            Mail::raw(
                "Su solicitud de postulante CUP FICCT fue aprobada.\n\n" .
                "Numero de registro: {$numeroRegistro}\n" .
                "Contrasena temporal: {$passwordTemporal}\n\n" .
                "Ingrese al sistema y conserve estos datos.",
                function ($message) use ($correo) {
                    $message->to($correo)->subject('Credenciales postulante CUP FICCT');
                }
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
