<?php

namespace Database\Seeders;

use App\Models\AsignacionCarrera\Carrera;
use App\Models\GestionAcademica\Materia;
use App\Models\Seguridad\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate([
            'email' => 'admin@cup.test',
        ], [
            'name' => 'Administrador CUP',
            'numero_registro' => 'admin123',
            'password' => Hash::make('12345678'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        User::query()->updateOrCreate([
            'email' => 'administrador@cup.test',
        ], [
            'name' => 'Administrador del Sistema',
            'numero_registro' => 'admin',
            'password' => Hash::make('12345678'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);



        collect([
            ['codigo' => 'INF', 'nombre' => 'Ingenieria Informatica', 'activa' => true],
            ['codigo' => 'SIS', 'nombre' => 'Ingenieria de Sistemas', 'activa' => true],
            ['codigo' => 'RED', 'nombre' => 'Ingenieria en Redes y Telecomunicaciones', 'activa' => true],
            ['codigo' => 'ROB', 'nombre' => 'Ingenieria en Robotica', 'activa' => true],
        ])->each(fn (array $carrera) => Carrera::query()->updateOrCreate(
            ['codigo' => $carrera['codigo']],
            $carrera
        ));

        collect([
            ['codigo' => 'ING-100', 'nombre' => 'Ingles', 'activa' => true],
            ['codigo' => 'MAT-100', 'nombre' => 'Matematicas', 'activa' => true],
            ['codigo' => 'COM-100', 'nombre' => 'Computacion', 'activa' => true],
            ['codigo' => 'FIS-100', 'nombre' => 'Fisica', 'activa' => true],
        ])->each(fn (array $materia) => Materia::query()->updateOrCreate(
            ['codigo' => $materia['codigo']],
            $materia
        ));
    }
}
