<?php

namespace App\Models\InscripcionPagos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Documento extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\DocumentoFactory
    {
        return \Database\Factories\DocumentoFactory::new();
    }

    protected $fillable = [
        'inscripcion_id',
        'tipo',
        'numero',
        'archivo_path',
        'estado',
        'prevalidacion_estado',
        'prevalidacion_puntaje',
        'prevalidacion_observaciones',
        'prevalidado_en',
        'observacion',
        'revisado_por',
        'revisado_en',
    ];

    protected function casts(): array
    {
        return [
            'prevalidacion_observaciones' => 'array',
            'prevalidado_en' => 'datetime',
            'revisado_en' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relaciones                                                        */
    /* ------------------------------------------------------------------ */

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(\App\Models\InscripcionPagos\Inscripcion::class);
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Seguridad\User::class, 'revisado_por');
    }
}
