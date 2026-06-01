<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsignacionCarrera extends Model
{
    use HasFactory;

    protected $table = 'asignaciones_carrera';

    protected $fillable = [
        'inscripcion_id',
        'carrera_id',
        'opcion_prioridad',
        'promedio_usado',
        'estado',
        'asignado_en',
    ];

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class);
    }

    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class);
    }
}
