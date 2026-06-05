<?php

namespace Tests\Feature;

use App\Models\Seguridad\User;
use App\Models\GestionAcademica\Gestion;
use App\Models\GestionAcademica\Grupo;
use App\Models\GestionAcademica\Materia;
use App\Models\GestionAcademica\Docente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AsignacionMateriaTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_puede_crear_docente(): void
    {
        $payload = [
            'ci' => '123456',
            'nombres' => 'Juan',
            'apellidos' => 'Perez',
            'correo' => 'juan.perez@cup.test',
        ];

        $response = $this->actingAs($this->adminUser)->postJson('/api/docentes', $payload);

        $response->assertStatus(201)->assertJsonPath('ok', true);
        $this->assertDatabaseHas('docentes', ['nombres' => 'Juan']);
        $this->assertDatabaseHas('users', [
            'numero_registro' => '123456',
            'email' => 'juan.perez@cup.test',
            'role' => User::ROLE_DOCENTE,
        ]);
    }

    public function test_admin_puede_asignar_materia_a_grupo(): void
    {
        $gestion = Gestion::factory()->create();
        $grupo = Grupo::create([
            'gestion_id' => $gestion->id,
            'codigo' => 'G1'
        ]);
        $materia = Materia::create([
            'codigo' => 'MAT101',
            'nombre' => 'Matemáticas'
        ]);
        $docente = Docente::create([
            'nombres' => 'Juan',
            'apellidos' => 'Perez'
        ]);

        $payload = [
            'materia_id' => $materia->id,
            'docente_id' => $docente->id,
            'dia_semana' => 1,
            'hora_inicio' => '08:00',
            'hora_fin' => '10:00',
        ];

        $response = $this->actingAs($this->adminUser)->postJson("/api/grupos/{$grupo->id}/materias", $payload);

        $response->assertStatus(201)->assertJsonPath('ok', true);
        $this->assertDatabaseHas('grupo_materias', [
            'grupo_id' => $grupo->id,
            'materia_id' => $materia->id,
            'docente_id' => $docente->id
        ]);
        $this->assertDatabaseHas('horarios', [
            'dia_semana' => 1,
            'hora_inicio' => '08:00',
            'hora_fin' => '10:00',
        ]);
    }
}
