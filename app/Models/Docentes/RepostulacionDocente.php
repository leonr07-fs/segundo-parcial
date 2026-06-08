<?php

namespace App\Models\Docentes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepostulacionDocente extends Model
{
    protected $table = 'repostulaciones_docentes';

    protected $fillable = [
        'docente_id',
        'gestion_id',
        'estado',
        'observacion',
        'revisado_por',
        'revisado_en',
    ];

    protected function casts(): array
    {
        return [
            'revisado_en' => 'datetime',
        ];
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(\App\Models\GestionAcademica\Docente::class);
    }

    public function gestion(): BelongsTo
    {
        return $this->belongsTo(\App\Models\GestionAcademica\Gestion::class);
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Seguridad\User::class, 'revisado_por');
    }
}
