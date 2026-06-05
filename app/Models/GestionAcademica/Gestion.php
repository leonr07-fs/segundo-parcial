<?php

namespace App\Models\GestionAcademica;

use App\Support\States\GestionState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gestion extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\GestionFactory
    {
        return \Database\Factories\GestionFactory::new();
    }

    protected $table = 'gestiones';

    protected $fillable = [
        'nombre',
        'anio',
        'periodo',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relaciones                                                        */
    /* ------------------------------------------------------------------ */

    public function inscripciones(): HasMany
    {
        return $this->hasMany(\App\Models\InscripcionPagos\Inscripcion::class, 'gestion_id');
    }

    /* ------------------------------------------------------------------ */
    /*  Scopes                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Filtra gestiones que están habilitadas para recibir inscripciones.
     *
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     */
    public function scopeHabilitadaParaInscripcion($query): void
    {
        $query->where('estado', GestionState::INSCRIPCION);
    }

    /**
     * Filtra gestiones con postulaciones cerradas, pero aun operativas.
     *
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     */
    public function scopeOperativaPostInscripcion($query): void
    {
        $query->whereIn('estado', [GestionState::INHABILITADA, GestionState::EN_CURSO]);
    }
}
