<?php

namespace App\Models\Seguridad;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * CU01: Autenticación de Usuario
 * Modelo principal para manejar el acceso al sistema utilizando el numero_registro.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function newFactory(): \Database\Factories\UserFactory
    {
        return \Database\Factories\UserFactory::new();
    }

    public const ROLE_ADMIN = 'admin';
    public const ROLE_DOCENTE = 'docente';
    public const ROLE_POSTULANTE = 'postulante';
    public const ROLE_AUTORIDAD = 'autoridad';
    public const ROLE_COORDINADOR = 'coordinador';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_DOCENTE,
        self::ROLE_POSTULANTE,
        self::ROLE_AUTORIDAD,
        self::ROLE_COORDINADOR,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'numero_registro',
        'password',
        'role',
        'is_active',
        'failed_login_attempts',
        'locked_until',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function postulante(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\InscripcionPagos\Postulante::class, 'ci', 'numero_registro');
    }

    public function dashboardPath(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN, self::ROLE_AUTORIDAD, self::ROLE_COORDINADOR => '/admin/dashboard',
            self::ROLE_DOCENTE => '/docente/dashboard',
            default => '/postulante/dashboard',
        };
    }
}
