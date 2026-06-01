<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultadoCup extends Model
{
    use HasFactory;

    protected $table = 'resultados_cup';

    protected $fillable = [
        'inscripcion_id',
        'promedio_final',
        'estado_final',
        'cerrado_en',
    ];

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class);
    }
}
