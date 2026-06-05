<?php

namespace App\Models\AsignacionCarrera;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carrera extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\CarreraFactory
    {
        return \Database\Factories\CarreraFactory::new();
    }

    protected $table = 'carreras';

    protected $fillable = [
        'codigo',
        'nombre',
        'activa',
    ];

    public function opciones(): HasMany
    {
        return $this->hasMany(\App\Models\AsignacionCarrera\OpcionCarrera::class);
    }

    public function cupos(): HasMany
    {
        return $this->hasMany(\App\Models\AsignacionCarrera\CupoCarrera::class);
    }

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Scopes                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Filtra carreras activas disponibles para selección.
     *
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     */
    public function scopeActiva($query): void
    {
        $query->where('activa', true);
    }
}
