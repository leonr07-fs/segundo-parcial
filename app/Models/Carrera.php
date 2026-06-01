<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Carrera extends Model
{
    use HasFactory;

    protected $table = 'carreras';

    protected $fillable = [
        'codigo',
        'nombre',
        'activa',
    ];

    public function opciones(): HasMany
    {
        return $this->hasMany(OpcionCarrera::class);
    }

    public function cupos(): HasMany
    {
        return $this->hasMany(CupoCarrera::class);
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
