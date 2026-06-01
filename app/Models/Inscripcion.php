<?php

namespace App\Models;

use App\Support\States\InscripcionState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Inscripcion extends Model
{
    use HasFactory;

    protected $table = 'inscripciones';

    protected $fillable = [
        'postulante_id',
        'gestion_id',
        'codigo',
        'fecha_inscripcion',
        'estado',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inscripcion' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relaciones                                                        */
    /* ------------------------------------------------------------------ */

    public function postulante(): BelongsTo
    {
        return $this->belongsTo(Postulante::class, 'postulante_id');
    }

    public function gestion(): BelongsTo
    {
        return $this->belongsTo(Gestion::class, 'gestion_id');
    }

    public function opcionesCarrera(): HasMany
    {
        return $this->hasMany(OpcionCarrera::class, 'inscripcion_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class, 'inscripcion_id');
    }

    public function validacionDocumental()
    {
        return $this->hasOne(ValidacionDocumental::class, 'inscripcion_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'inscripcion_id');
    }

    public function evaluaciones(): HasMany
    {
        return $this->hasMany(Evaluacion::class, 'inscripcion_id');
    }

    public function resultadoCup(): HasOne
    {
        return $this->hasOne(ResultadoCup::class, 'inscripcion_id');
    }

    public function asignacionCarrera(): HasOne
    {
        return $this->hasOne(AsignacionCarrera::class, 'inscripcion_id');
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Genera un código de inscripción único basado en el año de la gestión
     * y un secuencial.
     */
    public static function generarCodigo(int $anio): string
    {
        $ultimo = static::where('codigo', 'like', "CUP-{$anio}-%")
            ->orderByDesc('codigo')
            ->value('codigo');

        $secuencial = 1;

        if ($ultimo !== null) {
            $partes = explode('-', $ultimo);
            $secuencial = ((int) end($partes)) + 1;
        }

        return sprintf('CUP-%d-%05d', $anio, $secuencial);
    }
}
