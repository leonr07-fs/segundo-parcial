<?php

namespace Database\Factories;

use App\Models\GestionAcademica\GrupoMateria;
use Illuminate\Database\Eloquent\Factories\Factory;

class GrupoMateriaFactory extends Factory
{
    protected $model = GrupoMateria::class;

    public function definition(): array
    {
        // Solo necesitamos que la BD lo cree o que genere IDs falsos.
        // Como no tenemos factorías de Materia ni Grupo todavía (por simplicidad),
        // en los test usaremos GrupoMateria::forceCreate() si no hay factories listos.
        // Pero intentemos crear uno básico.
        return [
            'grupo_id' => 1, // Se sobrescribirá en tests
            'materia_id' => 1, // Se sobrescribirá en tests
            'docente_id' => null,
        ];
    }
}
