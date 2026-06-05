<?php

namespace Database\Factories;

use App\Models\AsignacionCarrera\Carrera;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Carrera>
 */
class CarreraFactory extends Factory
{
    protected $model = Carrera::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => strtoupper(fake()->unique()->lexify('CAR-???')),
            'nombre' => fake()->unique()->randomElement([
                'Ingeniería Informática',
                'Ingeniería de Sistemas',
                'Ingeniería Industrial',
                'Ingeniería en Telecomunicaciones',
                'Ingeniería Civil',
                'Ingeniería Ambiental',
                'Ingeniería Electrónica',
                'Ingeniería Mecatrónica',
                'Ingeniería Comercial',
                'Ingeniería Petrolera',
            ]),
            'activa' => true,
        ];
    }

    /**
     * Carrera inactiva.
     */
    public function inactiva(): static
    {
        return $this->state(fn () => [
            'activa' => false,
        ]);
    }
}
