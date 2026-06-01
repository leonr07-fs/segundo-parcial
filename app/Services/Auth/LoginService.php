<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LoginService
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * @param array{email: string, password: string} $credentials
     *
     * @return array<string, mixed>
     */
    public function login(array $credentials, Request $request): array
    {
        /** @var User|null $user */
        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            $this->registerFailedAttempt($user, $request);

            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son validas.'],
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
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'dashboard_path' => $user->dashboardPath(),
        ];
    }

    private function registerFailedAttempt(?User $user, Request $request): void
    {
        if ($user === null) {
            $this->auditLogService->record('auth.login.failed', null, $request, [
                'email' => $request->input('email'),
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
}
