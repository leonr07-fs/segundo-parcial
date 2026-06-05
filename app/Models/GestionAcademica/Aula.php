<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Aula extends Model
{
    use HasFactory;

    protected $fillable = [
        'codigo',
        'nombre',
        'capacidad',
        'ubicacion',
        'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    public function grupos()
    {
        return $this->hasMany(\App\Models\GestionAcademica\Grupo::class);
    }
}
