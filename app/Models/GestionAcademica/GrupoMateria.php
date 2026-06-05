<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrupoMateria extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\GrupoMateriaFactory
    {
        return \Database\Factories\GrupoMateriaFactory::new();
    }

    protected $table = 'grupo_materias';

    protected $fillable = [
        'grupo_id',
        'materia_id',
        'docente_id',
    ];

    public function grupo()
    {
        return $this->belongsTo(\App\Models\GestionAcademica\Grupo::class);
    }

    public function materia()
    {
        return $this->belongsTo(\App\Models\GestionAcademica\Materia::class);
    }

    public function docente()
    {
        return $this->belongsTo(\App\Models\GestionAcademica\Docente::class);
    }

    public function horarios()
    {
        return $this->hasMany(\App\Models\GestionAcademica\Horario::class);
    }
}
