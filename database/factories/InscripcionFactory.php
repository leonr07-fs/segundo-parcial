<?php

namespace Database\Factories;

use App\Models\GestionAcademica\Gestion;
use App\Models\InscripcionPagos\Inscripcion;
use App\Models\InscripcionPagos\Postulante;
use App\Support\States\InscripcionState;
use Illuminate\Database\Eloquent\Factories\Factory;

class InscripcionFactory extends Factory
{
    protected $model = Inscripcion::class;

    public function definition(): array
    {
        $anio = (int) date('Y');

        return [
            'postulante_id' => Postulante::factory(),
            'gestion_id' => Gestion::factory(),
            'codigo' => fake()->unique()->bothify("CUP-{$anio}-#####"),
            'fecha_inscripcion' => now(),
            'estado' => InscripcionState::PREPOSTULADO,
        ];
    }
}
