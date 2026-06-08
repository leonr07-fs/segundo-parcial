<?php

namespace App\Services\PortalPostulante;

use App\Models\InscripcionPagos\Inscripcion;
use App\Models\InscripcionPagos\Pago;
use App\Models\InscripcionPagos\Recibo;
use App\Models\Seguridad\User;
use App\Services\GestionAcademica\CredentialService;
use App\Services\SeguridadUsuarios\AuditLogService;
use App\Support\States\InscripcionState;
use App\Support\States\PagoState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Procesamiento idempotente de pagos PayPal aprobados.
 * Centraliza la lógica compartida entre flujos autenticados, públicos y de repostulación.
 */
class PayPalPagoProcessor
{
    public const MONTO_CUP = 200.00;

    public function __construct(
        private readonly CredentialService $credentialService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function procesarPagoAprobado(
        int $inscripcionId,
        string $orderId,
        float $monto,
        bool $esRepostulacion = false,
        ?Request $request = null,
    ): void {
        DB::transaction(function () use ($inscripcionId, $orderId, $monto, $esRepostulacion, $request) {
            $pagoExistente = Pago::where('referencia', $orderId)->first();
            if ($pagoExistente) {
                return;
            }

            $inscripcion = Inscripcion::lockForUpdate()->find($inscripcionId);
            if (! $inscripcion || $inscripcion->estado !== InscripcionState::DOCUMENTOS_APROBADOS) {
                return;
            }

            $pago = Pago::create([
                'inscripcion_id' => $inscripcion->id,
                'monto' => $monto,
                'moneda' => 'BOB',
                'metodo' => 'paypal',
                'referencia' => $orderId,
                'estado' => PagoState::APROBADO,
                'pagado_en' => now(),
            ]);

            $recibo = Recibo::create([
                'pago_id' => $pago->id,
                'numero' => 'REC-CUP-' . strtoupper(uniqid()),
                'emitido_en' => now(),
            ]);

            $inscripcion->update([
                'estado' => InscripcionState::PAGADO,
            ]);

            $postulante = $inscripcion->postulante;
            $usuarioExistente = User::where('email', $postulante->correo)->first();

            if ($usuarioExistente && $usuarioExistente->role !== User::ROLE_POSTULANTE) {
                throw new \DomainException('El correo del postulante pertenece a un usuario con un rol diferente.');
            }

            if ($esRepostulacion && $usuarioExistente) {
                $usuarioExistente->forceFill([
                    'name' => trim($postulante->nombres . ' ' . ($postulante->apellido_paterno ?? '') . ' ' . ($postulante->apellido_materno ?? '')),
                    'password' => $postulante->ci,
                    'is_active' => true,
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                ])->save();

                $numeroRegistro = $usuarioExistente->numero_registro;
                $passwordTemporal = $postulante->ci;
            } else {
                $numeroRegistro = $this->credentialService->generarRegistroEstudianteUAGRM();
                $passwordTemporal = $postulante->ci;

                User::updateOrCreate([
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
            }

            $correoEnviado = $this->enviarCorreoConfirmacion(
                $postulante,
                $recibo,
                $pago,
                $numeroRegistro,
                $passwordTemporal,
                $esRepostulacion,
            );

            try {
                $this->auditLogService->record(
                    $esRepostulacion ? 'paypal.repostulacion.procesada' : 'paypal.pago.procesado',
                    null,
                    $request ?? request(),
                    [
                        'inscripcion_id' => $inscripcion->id,
                        'pago_id' => $pago->id,
                        'recibo_numero' => $recibo->numero,
                        'referencia' => $pago->referencia,
                        'credenciales_emitidas' => ! $esRepostulacion || $usuarioExistente === null,
                        'correo_enviado' => $correoEnviado,
                        'es_repostulacion' => $esRepostulacion,
                    ]
                );
            } catch (\Throwable $t) {
                Log::error('Error al registrar auditoria PayPal: ' . $t->getMessage());
            }
        });
    }

    private function enviarCorreoConfirmacion(
        $postulante,
        Recibo $recibo,
        Pago $pago,
        string $numeroRegistro,
        string $passwordTemporal,
        bool $esRepostulacion,
    ): bool {
        $nombre = trim($postulante->nombres . ' ' . ($postulante->apellido_paterno ?? ''));

        if ($esRepostulacion) {
            $cuerpo = "Estimado(a) {$nombre},\n\n"
                . "Confirmamos la recepción de su pago de repostulación mediante PayPal para el Curso Preuniversitario (CUP).\n\n"
                . "--- DETALLES DEL RECIBO ---\n"
                . "Nro. Recibo: {$recibo->numero}\n"
                . "Monto Pagado: {$pago->monto} {$pago->moneda}\n"
                . "Método de Pago: PayPal\n"
                . "Referencia / Orden ID: {$pago->referencia}\n"
                . "Fecha de Pago: {$pago->pagado_en}\n\n"
                . "--- ACCESO AL SISTEMA ---\n"
                . "Su cuenta ha sido reactivada para la gestión vigente.\n"
                . "Usuario (Nro. Registro): {$numeroRegistro}\n"
                . "Contraseña: {$passwordTemporal}\n\n"
                . "Ingrese al sistema en: " . url('/login') . "\n";
            $asunto = 'Confirmación de Repostulación y Pago - CUP FICCT';
        } else {
            $cuerpo = "Estimado(a) {$nombre},\n\n"
                . "Confirmamos la recepción de su pago mediante PayPal para el Curso Preuniversitario (CUP).\n\n"
                . "--- DETALLES DEL RECIBO ---\n"
                . "Nro. Recibo: {$recibo->numero}\n"
                . "Monto Pagado: {$pago->monto} {$pago->moneda}\n"
                . "Método de Pago: PayPal\n"
                . "Referencia / Orden ID: {$pago->referencia}\n"
                . "Fecha de Pago: {$pago->pagado_en}\n\n"
                . "--- CREDENCIALES DE ACCESO ---\n"
                . "Correo Registrado: {$postulante->correo}\n"
                . "Usuario (Nro. Registro): {$numeroRegistro}\n"
                . "Contraseña Temporal: {$passwordTemporal}\n\n"
                . "Puede ingresar al sistema utilizando sus credenciales en el siguiente enlace:\n"
                . url('/login') . "\n\n"
                . "Conserve estos datos de manera segura.";
            $asunto = 'Recibo de Pago y Credenciales de Acceso - CUP FICCT';
        }

        try {
            Mail::raw($cuerpo, function ($message) use ($postulante, $asunto) {
                $message->to($postulante->correo)->subject($asunto);
            });

            return true;
        } catch (\Throwable $t) {
            Log::error('Error al enviar correo de pago PayPal: ' . $t->getMessage());

            return false;
        }
    }
}
