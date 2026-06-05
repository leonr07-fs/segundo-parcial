<?php

namespace Database\Factories;

use App\Models\InscripcionPagos\Pago;
use App\Models\InscripcionPagos\Recibo;
use App\Models\Seguridad\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReciboFactory extends Factory
{
    protected $model = Recibo::class;

    public function definition(): array
    {
        return [
            'pago_id' => Pago::factory(),
            'numero' => fake()->unique()->bothify('REC-2026-#####'),
            'archivo_path' => null,
            'emitido_por' => User::factory(),
            'emitido_en' => now(),
        ];
    }
}
