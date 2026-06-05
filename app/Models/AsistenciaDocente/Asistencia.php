<?php

namespace App\Models\AsistenciaDocente;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asistencia extends Model
{
    use HasFactory;

    public const ESTADOS = ['presente', 'ausente', 'tardanza', 'justificado'];

    protected $fillable = [
        'grupo_materia_id',
        'inscripcion_id',
        'docente_id',
        'fecha',
        'estado',
        'observacion',
        'registrado_por',
        'registrado_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'registrado_en' => 'datetime',
        ];
    }

    public function grupoMateria(): BelongsTo
    {
        return $this->belongsTo(\App\Models\GestionAcademica\GrupoMateria::class);
    }

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(\App\Models\InscripcionPagos\Inscripcion::class);
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(\App\Models\GestionAcademica\Docente::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Seguridad\User::class, 'registrado_por');
    }
}
