<?php

namespace App\Models\InscripcionPagos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pago extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\PagoFactory
    {
        return \Database\Factories\PagoFactory::new();
    }

    protected $table = 'pagos';

    protected $fillable = [
        'inscripcion_id',
        'monto',
        'moneda',
        'metodo',
        'referencia',
        'estado',
        'pagado_en',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'pagado_en' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relaciones                                                        */
    /* ------------------------------------------------------------------ */

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(\App\Models\InscripcionPagos\Inscripcion::class);
    }

    public function recibo(): HasOne
    {
        return $this->hasOne(\App\Models\InscripcionPagos\Recibo::class);
    }
}
