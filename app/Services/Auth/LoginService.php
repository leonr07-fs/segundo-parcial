<?php

namespace App\Services\Auth;

use App\Models\GestionAcademica\Docente;
use App\Models\InscripcionPagos\Postulante;
use App\Models\Seguridad\User;
use App\Services\GestionAcademica\GestionVigenteService;
use App\Services\SeguridadUsuarios\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * CU01: Autenticación de Usuario
 * Servicio encargado de procesar la lógica de negocio para el inicio y cierre de sesión.
 */
class LoginService
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly GestionVigenteService $gestionVigenteService,
    ) {
    }

    /**
     * @param array{numero_registro: string, password: string} $credentials
     *
     * @return array<string, mixed>
     */
    public function login(array $credentials, Request $request): array
    {
        /** @var User|null $user */
        $user = User::query()->where('numero_registro', $credentials['numero_registro'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            $this->registerFailedAttempt($user, $request);

            throw ValidationException::withMessages([
                'numero_registro' => ['Las credenciales no son validas.'],
            ]);
        }

        if ($user->isLocked()) {
            $this->auditLogService->record('auth.login.locked', $user, $request);

            throw new HttpException(423, 'La cuenta esta bloqueada temporalmente.');
        }

        if (! $user->is_active) {
            $this->auditLogService->record('auth.login.inactive', $user, $request);

            throw new HttpException(403, 'La cuenta no esta habilitada.');
        }

        $this->ensurePostulanteGestionAccesible($user, $request);
        $this->ensureDocenteGestionAccesible($user, $request);

        Auth::login($user);
        $request->session()->regenerate();

        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
        ])->save();

        $this->auditLogService->record('auth.login.success', $user, $request, [
            'role' => $user->role,
        ]);

        return [
            'user' => $this->userContext($user),
            'redirect_url' => $user->dashboardPath(),
        ];
    }

    public function logout(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user !== null) {
            $this->auditLogService->record('auth.logout', $user, $request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /**
     * @return array<string, mixed>
     */
    public function userContext(User $user): array
    {
        $context = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'numero_registro' => $user->numero_registro,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'dashboard_path' => $user->dashboardPath(),
        ];

        $postulante = null;
        if ($user->role === User::ROLE_POSTULANTE) {
            $postulante = Postulante::query()
                ->where('correo', $user->email)
                ->orWhere('ci', $user->numero_registro)
                ->first();
        }

        if ($postulante) {
            $postulante->load(['inscripciones' => function ($query) {
                $query->latest('created_at')->limit(1);
            }]);
            
            $context['postulante'] = [
                'id' => $postulante->id,
                'ci' => $postulante->ci,
                'inscripcion_estado' => $postulante->inscripciones->first()?->estado,
            ];
        }

        return $context;
    }

    private function registerFailedAttempt(?User $user, Request $request): void
    {
        if ($user === null) {
            $this->auditLogService->record('auth.login.failed', null, $request, [
                'numero_registro' => $request->input('numero_registro'),
            ]);

            return;
        }

        $failedAttempts = $user->failed_login_attempts + 1;
        $lockedUntil = $failedAttempts >= self::MAX_FAILED_ATTEMPTS
            ? now()->addMinutes(self::LOCK_MINUTES)
            : null;

        $user->forceFill([
            'failed_login_attempts' => $failedAttempts,
            'locked_until' => $lockedUntil,
        ])->save();

        $this->auditLogService->record('auth.login.failed', $user, $request, [
            'failed_login_attempts' => $failedAttempts,
            'locked_until' => $lockedUntil?->toISOString(),
        ]);
    }

    private function ensurePostulanteGestionAccesible(User $user, Request $request): void
    {
        if ($user->role !== User::ROLE_POSTULANTE) {
            return;
        }

        $postulante = Postulante::query()
            ->where('correo', $user->email)
            ->orWhere('ci', $user->numero_registro)
            ->first();

        if ($postulante === null) {
            $this->auditLogService->record('auth.login.no_postulante', $user, $request);

            throw new HttpException(
                403,
                'No pertenece a la gestion vigente. Debe realizar una repostulacion desde la pagina inicial.'
            );
        }

        $gestionVigente = $this->gestionVigenteService->actual();
        $inscripcionVigente = $this->gestionVigenteService->inscripcionEnGestionVigente($postulante, $gestionVigente);

        if ($inscripcionVigente === null) {
            $this->auditLogService->record('auth.login.sin_inscripcion_vigente', $user, $request, [
                'gestion_vigente_id' => $gestionVigente?->id,
                'gestion_vigente' => $gestionVigente?->nombre,
            ]);

            throw new HttpException(
                403,
                'No pertenece a la gestion vigente. Debe realizar una repostulacion desde la pagina inicial.'
            );
        }

        if ($inscripcionVigente->resultadoCup?->estado_final === 'reprobado') {
            $this->auditLogService->record('auth.login.reprobado', $user, $request, [
                'inscripcion_id' => $inscripcionVigente->id,
            ]);

            throw new HttpException(
                403,
                'Su gestion fue reprobada. Debe realizar una repostulacion desde la pagina inicial.'
            );
        }
    }

    private function ensureDocenteGestionAccesible(User $user, Request $request): void
    {
        if ($user->role !== User::ROLE_DOCENTE) {
            return;
        }

        $docente = Docente::query()
            ->where('correo', $user->email)
            ->orWhere('ci', $user->numero_registro)
            ->first();

        if ($docente === null) {
            $this->auditLogService->record('auth.login.no_docente', $user, $request);

            throw new HttpException(
                403,
                'No pertenece a la gestion vigente. Debe realizar una repostulacion docente desde la pagina inicial.'
            );
        }

        if (! $this->gestionVigenteService->docentePuedeAcceder($docente)) {
            $gestionVigente = $this->gestionVigenteService->actual();

            $this->auditLogService->record('auth.login.docente_sin_gestion_vigente', $user, $request, [
                'docente_id' => $docente->id,
                'gestion_vigente_id' => $gestionVigente?->id,
            ]);

            throw new HttpException(
                403,
                'No pertenece a la gestion vigente. Debe realizar una repostulacion docente desde la pagina inicial.'
            );
        }
    }
}
