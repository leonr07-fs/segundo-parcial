<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrupoMateria extends Model
{
    use HasFactory;

    protected $table = 'grupo_materias';

    protected $fillable = [
        'grupo_id',
        'materia_id',
        'docente_id',
    ];
}
