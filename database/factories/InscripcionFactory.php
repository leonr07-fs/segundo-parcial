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
        $gestion = Gestion::factory()->create();

        return [
            'postulante_id' => Postulante::factory(),
            'gestion_id' => $gestion->id,
            'codigo' => fake()->unique()->bothify("CUP-{$gestion->anio}-#####"),
            'fecha_inscripcion' => now(),
            'estado' => InscripcionState::PREPOSTULADO,
        ];
    }
}
