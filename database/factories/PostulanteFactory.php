<?php

namespace Database\Factories;

use App\Models\InscripcionPagos\Postulante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Postulante>
 */
class PostulanteFactory extends Factory
{
    protected $model = Postulante::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ci' => fake()->unique()->numerify('#######'),
            'complemento' => null,
            'nombres' => fake()->firstName(),
            'apellido_paterno' => fake()->lastName(),
            'apellido_materno' => fake()->lastName(),
            'fecha_nacimiento' => fake()->date('Y-m-d', '-18 years'),
            'genero' => fake()->randomElement(['masculino', 'femenino']),
            'correo' => fake()->unique()->safeEmail(),
            'telefono' => fake()->numerify('7#######'),
            'direccion' => fake()->address(),
            'colegio_procedencia' => 'Colegio ' . fake()->company(),
            'ciudad' => fake()->city(),
        ];
    }
}
