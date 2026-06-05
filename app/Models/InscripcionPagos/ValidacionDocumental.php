<?php

namespace App\Models\InscripcionPagos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValidacionDocumental extends Model
{
    protected $table = 'validaciones_documentales';

    protected $fillable = [
        'inscripcion_id',
        'estado',
        'observacion',
        'validado_por',
        'validado_en',
    ];

    protected function casts(): array
    {
        return [
            'validado_en' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relaciones                                                        */
    /* ------------------------------------------------------------------ */

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(\App\Models\InscripcionPagos\Inscripcion::class);
    }

    public function validadoPor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Seguridad\User::class, 'validado_por');
    }
}
