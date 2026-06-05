<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Horario extends Model
{
    use HasFactory;

    protected $fillable = [
        'grupo_materia_id',
        'aula_id',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
        'modalidad',
    ];

    public function grupoMateria()
    {
        return $this->belongsTo(\App\Models\GestionAcademica\GrupoMateria::class);
    }

    public function aula()
    {
        return $this->belongsTo(\App\Models\GestionAcademica\Aula::class);
    }
}
