<?php

namespace App\Models\Docentes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoDocente extends Model
{
    use HasFactory;

    protected $table = 'documentos_docentes';

    protected $fillable = [
        'solicitud_docente_id',
        'tipo',
        'archivo_path',
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

    public function solicitudDocente(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Docentes\SolicitudDocente::class, 'solicitud_docente_id');
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Seguridad\User::class, 'revisado_por');
    }
}
