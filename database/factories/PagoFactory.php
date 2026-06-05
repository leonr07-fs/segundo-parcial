<?php

namespace Database\Factories;

use App\Models\InscripcionPagos\Inscripcion;
use App\Models\InscripcionPagos\Pago;
use App\Support\States\PagoState;
use Illuminate\Database\Eloquent\Factories\Factory;

class PagoFactory extends Factory
{
    protected $model = Pago::class;

    public function definition(): array
    {
        return [
            'inscripcion_id' => Inscripcion::factory(),
            'monto' => 300.00,
            'moneda' => 'BOB',
            'metodo' => 'Transferencia Bancaria',
            'referencia' => fake()->unique()->bothify('TX-########'),
            'estado' => PagoState::APROBADO,
            'pagado_en' => now(),
        ];
    }
}
