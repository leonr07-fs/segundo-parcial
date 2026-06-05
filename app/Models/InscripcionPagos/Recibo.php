<?php

namespace App\Models\InscripcionPagos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recibo extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\ReciboFactory
    {
        return \Database\Factories\ReciboFactory::new();
    }

    protected $table = 'recibos';

    protected $fillable = [
        'pago_id',
        'numero',
        'archivo_path',
        'emitido_por',
        'emitido_en',
    ];

    protected function casts(): array
    {
        return [
            'emitido_en' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relaciones                                                        */
    /* ------------------------------------------------------------------ */

    public function pago(): BelongsTo
    {
        return $this->belongsTo(\App\Models\InscripcionPagos\Pago::class);
    }

    public function emitidoPor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Seguridad\User::class, 'emitido_por');
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Genera un número de recibo único para el comprobante de pago.
     */
    public static function generarNumero(): string
    {
        $anio = date('Y');
        
        $ultimo = static::where('numero', 'like', "REC-{$anio}-%")
            ->orderByDesc('numero')
            ->value('numero');

        $secuencial = 1;

        if ($ultimo !== null) {
            $partes = explode('-', $ultimo);
            $secuencial = ((int) end($partes)) + 1;
        }

        return sprintf('REC-%d-%05d', $anio, $secuencial);
    }
}
