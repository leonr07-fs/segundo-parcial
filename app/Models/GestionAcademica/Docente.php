<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
