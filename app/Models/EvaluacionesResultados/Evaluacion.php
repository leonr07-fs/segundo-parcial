<?php

namespace App\Models\EvaluacionesResultados;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluacion extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\EvaluacionFactory
    {
        return \Database\Factories\EvaluacionFactory::new();
    }

    protected $table = 'evaluaciones';

    protected $fillable = [
        'inscripcion_id',
        'grupo_materia_id',
        'examen_1',
        'examen_2',
        'examen_3',
        'promedio',
        'estado',
        'observacion',
        'registrado_por',
        'registrado_en',
    ];

    protected function casts(): array
    {
        return [
            'examen_1' => 'decimal:2',
            'examen_2' => 'decimal:2',
            'examen_3' => 'decimal:2',
            'promedio' => 'decimal:2',
            'registrado_en' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relaciones                                                        */
    /* ------------------------------------------------------------------ */

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(\App\Models\InscripcionPagos\Inscripcion::class);
    }

    public function grupoMateria(): BelongsTo
    {
        // Asumiendo que el modelo GrupoMateria se llamará así
        return $this->belongsTo(\App\Models\GestionAcademica\GrupoMateria::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Seguridad\User::class, 'registrado_por');
    }
}
