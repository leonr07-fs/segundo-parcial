<?php

namespace Database\Factories;

use App\Models\EvaluacionesResultados\Evaluacion;
use App\Models\GestionAcademica\GrupoMateria;
use App\Models\InscripcionPagos\Inscripcion;
use App\Models\Seguridad\User;
use App\Support\States\EvaluacionState;
use Illuminate\Database\Eloquent\Factories\Factory;

class EvaluacionFactory extends Factory
{
    protected $model = Evaluacion::class;

    public function definition(): array
    {
        return [
            'inscripcion_id' => Inscripcion::factory(),
            'grupo_materia_id' => GrupoMateria::factory(),
            'examen_1' => fake()->randomFloat(2, 0, 100),
            'examen_2' => fake()->randomFloat(2, 0, 100),
            'examen_3' => fake()->randomFloat(2, 0, 100),
            'promedio' => fake()->randomFloat(2, 0, 100),
            'estado' => EvaluacionState::PENDIENTE,
            'registrado_por' => User::factory(),
            'registrado_en' => now(),
        ];
    }
}
