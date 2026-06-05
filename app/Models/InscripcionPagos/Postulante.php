<?php

namespace App\Models\InscripcionPagos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Postulante extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\PostulanteFactory
    {
        return \Database\Factories\PostulanteFactory::new();
    }

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
        return $this->hasMany(\App\Models\InscripcionPagos\Inscripcion::class, 'postulante_id');
    }

    public function usuario(): HasOne
    {
        return $this->hasOne(\App\Models\Seguridad\User::class, 'email', 'correo');
    }
}
