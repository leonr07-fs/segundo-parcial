<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Materia extends Model
{
    use HasFactory;

    protected $fillable = [
        'codigo',
        'nombre',
        'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    public function grupos()
    {
        return $this->belongsToMany(\App\Models\GestionAcademica\Grupo::class, 'grupo_materias', 'materia_id', 'grupo_id')
                    ->withPivot('docente_id')
                    ->withTimestamps();
    }
}
