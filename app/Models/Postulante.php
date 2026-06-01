<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Postulante extends Model
{
    use HasFactory;

    protected $table = 'postulantes';

    protected $fillable = [
        'ci',
        'complemento',
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'fecha_nacimiento',
        'genero',
        'correo',
        'telefono',
        'direccion',
        'colegio_procedencia',
        'ciudad',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relaciones                                                        */
    /* ------------------------------------------------------------------ */

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'postulante_id');
    }
}
