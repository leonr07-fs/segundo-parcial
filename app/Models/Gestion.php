<?php

namespace App\Models;

use App\Support\States\GestionState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gestion extends Model
{
    use HasFactory;

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
        return $this->hasMany(Inscripcion::class, 'gestion_id');
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
}
