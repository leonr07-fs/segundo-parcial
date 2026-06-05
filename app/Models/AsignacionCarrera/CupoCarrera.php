<?php

namespace App\Models\AsignacionCarrera;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CupoCarrera extends Model
{
    use HasFactory;

    protected $table = 'cupos_carrera';

    protected $fillable = [
        'gestion_id',
        'carrera_id',
        'cupo_total',
        'cupo_disponible',
    ];

    public function gestion(): BelongsTo
    {
        return $this->belongsTo(\App\Models\GestionAcademica\Gestion::class);
    }

    public function carrera(): BelongsTo
    {
        return $this->belongsTo(\App\Models\AsignacionCarrera\Carrera::class);
    }
}
