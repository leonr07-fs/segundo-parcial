<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Docente extends Model
{
    use HasFactory;

    protected $fillable = [
        'ci',
        'nombres',
        'apellidos',
        'correo',
        'telefono',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function grupoMaterias(): HasMany
    {
        return $this->hasMany(GrupoMateria::class);
    }

    public function repostulaciones(): HasMany
    {
        return $this->hasMany(\App\Models\Docentes\RepostulacionDocente::class);
    }
}
