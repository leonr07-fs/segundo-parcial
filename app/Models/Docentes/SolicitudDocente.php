<?php

namespace App\Models\Docentes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SolicitudDocente extends Model
{
    use HasFactory;

    protected $table = 'solicitudes_docentes';

    protected $fillable = [
        'ci',
        'nombres',
        'apellidos',
        'correo',
        'telefono',
        'materia_id',
        'profesion',
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

    public function materia(): BelongsTo
    {
        return $this->belongsTo(\App\Models\GestionAcademica\Materia::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(\App\Models\Docentes\DocumentoDocente::class, 'solicitud_docente_id');
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Seguridad\User::class, 'revisado_por');
    }
}
