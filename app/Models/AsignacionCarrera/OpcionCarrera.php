<?php

namespace App\Models\AsignacionCarrera;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpcionCarrera extends Model
{
    use HasFactory;

    protected $table = 'opciones_carrera';

    protected $fillable = [
        'inscripcion_id',
        'carrera_id',
        'prioridad',
    ];

    /* ------------------------------------------------------------------ */
    /*  Relaciones                                                        */
    /* ------------------------------------------------------------------ */

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(\App\Models\InscripcionPagos\Inscripcion::class, 'inscripcion_id');
    }

    public function carrera(): BelongsTo
    {
        return $this->belongsTo(\App\Models\AsignacionCarrera\Carrera::class, 'carrera_id');
    }
}
