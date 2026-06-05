<?php

namespace Database\Factories;

use App\Models\GestionAcademica\Gestion;
use App\Support\States\GestionState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gestion>
 */
class GestionFactory extends Factory
{
    protected $model = Gestion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $anio = (int) date('Y');

        return [
            'nombre' => "CUP {$anio}-" . fake()->unique()->numberBetween(1, 99),
            'anio' => $anio,
            'periodo' => '1',
            'fecha_inicio' => now()->subMonth(),
            'fecha_fin' => now()->addMonths(3),
            'estado' => GestionState::INSCRIPCION,
        ];
    }

    /**
     * Gestión habilitada para inscripción.
     */
    public function inscripcion(): static
    {
        return $this->state(fn () => [
            'estado' => GestionState::INSCRIPCION,
        ]);
    }

    /**
     * Gestión en estado planificada (no habilitada).
     */
    public function planificada(): static
    {
        return $this->state(fn () => [
            'estado' => GestionState::PLANIFICADA,
        ]);
    }

    /**
     * Gestion con inscripciones cerradas, pero operativa para asignacion y evaluaciones.
     */
    public function inhabilitada(): static
    {
        return $this->state(fn () => [
            'estado' => GestionState::INHABILITADA,
        ]);
    }
}
