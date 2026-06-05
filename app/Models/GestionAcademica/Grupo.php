<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    use HasFactory;

    public const CUPO_MAXIMO = 70;

    protected $fillable = [
        'gestion_id',
        'codigo',
        'nombre',
        'cupo_maximo',
        'aula_id',
        'estado',
    ];

    public function gestion()
    {
        return $this->belongsTo(\App\Models\GestionAcademica\Gestion::class);
    }

    public function aula()
    {
        return $this->belongsTo(\App\Models\GestionAcademica\Aula::class);
    }

    public function materias()
    {
        return $this->belongsToMany(\App\Models\GestionAcademica\Materia::class, 'grupo_materias', 'grupo_id', 'materia_id')
                    ->withPivot('docente_id', 'id as grupo_materia_id')
                    ->withTimestamps();
    }

    public function inscripciones()
    {
        return $this->belongsToMany(\App\Models\InscripcionPagos\Inscripcion::class, 'inscripcion_grupo', 'grupo_id', 'inscripcion_id')
                    ->withPivot('estado', 'asignado_en')
                    ->withTimestamps();
    }
}
