<?php

namespace Tests\Feature;

use App\Models\Seguridad\User;
use App\Models\GestionAcademica\Gestion;
use App\Models\GestionAcademica\Aula;
use App\Support\States\GestionState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParametrosTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_puede_crear_materia(): void
    {
        $payload = [
            'codigo' => 'MAT101',
            'nombre' => 'Matemáticas I',
            'activa' => true
        ];

        $response = $this->actingAs($this->adminUser)->postJson('/api/materias', $payload);

        $response->assertStatus(201)->assertJsonPath('ok', true);
        $this->assertDatabaseHas('materias', ['codigo' => 'MAT101']);
    }

    public function test_admin_puede_inhabilitar_y_habilitar_materia(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson('/api/materias', [
                'codigo' => 'COM',
                'nombre' => 'Computacion',
                'activa' => true,
            ])
            ->assertStatus(201);

        $materiaId = \App\Models\GestionAcademica\Materia::where('codigo', 'COM')->value('id');

        $this->actingAs($this->adminUser)
            ->putJson("/api/materias/{$materiaId}/estado", ['activa' => false])
            ->assertOk()
            ->assertJsonPath('data.materia.activa', false);

        $this->assertDatabaseHas('materias', ['codigo' => 'COM', 'activa' => false]);

        $this->actingAs($this->adminUser)
            ->putJson("/api/materias/{$materiaId}/estado", ['activa' => true])
            ->assertOk()
            ->assertJsonPath('data.materia.activa', true);

        $this->assertDatabaseHas('materias', ['codigo' => 'COM', 'activa' => true]);
    }

    public function test_admin_puede_crear_gestion_planificada(): void
    {
        $payload = [
            'nombre' => 'Semestre 2-2027',
            'anio' => 2027,
            'periodo' => '2',
        ];

        $response = $this->actingAs($this->adminUser)->postJson('/api/gestiones', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.gestion.estado', GestionState::PLANIFICADA);

        $this->assertDatabaseHas('gestiones', [
            'nombre' => 'Semestre 2-2027',
            'anio' => 2027,
            'periodo' => '2',
            'estado' => GestionState::PLANIFICADA,
        ]);
    }

    public function test_admin_puede_habilitar_gestion_para_inscripciones(): void
    {
        $anterior = Gestion::factory()->inscripcion()->create();
        $nueva = Gestion::factory()->planificada()->create();

        $response = $this->actingAs($this->adminUser)->putJson("/api/gestiones/{$nueva->id}/habilitar");

        $response->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('gestiones', [
            'id' => $nueva->id,
            'estado' => GestionState::INSCRIPCION,
        ]);
        $this->assertDatabaseHas('gestiones', [
            'id' => $anterior->id,
            'estado' => GestionState::INHABILITADA,
        ]);
    }

    public function test_admin_puede_cerrar_inscripciones_sin_cerrar_la_gestion_final(): void
    {
        $gestion = Gestion::factory()->inscripcion()->create();

        $response = $this->actingAs($this->adminUser)->putJson("/api/gestiones/{$gestion->id}/cerrar");

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.gestion.estado', GestionState::INHABILITADA);

        $this->assertDatabaseHas('gestiones', [
            'id' => $gestion->id,
            'estado' => GestionState::INHABILITADA,
        ]);
    }

    public function test_admin_puede_cerrar_gestion_definitivamente(): void
    {
        $gestion = Gestion::factory()->inhabilitada()->create();

        $response = $this->actingAs($this->adminUser)->putJson("/api/gestiones/{$gestion->id}/cerrar-final");

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.gestion.estado', GestionState::CERRADA);

        $this->assertDatabaseHas('gestiones', [
            'id' => $gestion->id,
            'estado' => GestionState::CERRADA,
        ]);
    }

    public function test_admin_puede_crear_aula(): void
    {
        $payload = [
            'codigo' => 'AULA-11',
            'nombre' => 'Aula 11',
            'capacidad' => 70
        ];

        $response = $this->actingAs($this->adminUser)->postJson('/api/aulas', $payload);

        $response->assertStatus(201)->assertJsonPath('ok', true);
        $this->assertDatabaseHas('aulas', ['codigo' => 'AULA-11']);
    }

    public function test_admin_puede_actualizar_capacidad_de_aula_ficct(): void
    {
        $aula = Aula::create([
            'codigo' => 'AULA-11',
            'nombre' => 'Aula 11',
            'capacidad' => 70,
            'ubicacion' => 'Modulo 236 - Primer piso - Aula teorica',
            'activa' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->putJson("/api/aulas/{$aula->id}/capacidad", [
            'capacidad' => 64,
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.aula.capacidad', 64);

        $this->assertDatabaseHas('aulas', [
            'id' => $aula->id,
            'capacidad' => 64,
        ]);
    }

    public function test_admin_puede_crear_grupo(): void
    {
        $gestion = Gestion::factory()->create();

        $payload = [
            'gestion_id' => $gestion->id,
            'codigo' => 'G1',
            'cupo_maximo' => 60
        ];

        $response = $this->actingAs($this->adminUser)->postJson('/api/grupos', $payload);

        $response->assertStatus(201)->assertJsonPath('ok', true);
        $this->assertDatabaseHas('grupos', ['codigo' => 'G1', 'gestion_id' => $gestion->id]);
    }

    public function test_no_permite_crear_grupo_con_mas_de_setenta_estudiantes(): void
    {
        $gestion = Gestion::factory()->create();

        $response = $this->actingAs($this->adminUser)->postJson('/api/grupos', [
            'gestion_id' => $gestion->id,
            'codigo' => 'G-MAYOR',
            'cupo_maximo' => 71,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('cupo_maximo')
            ->assertJsonPath('errors.cupo_maximo.0', 'Cada grupo puede tener como maximo 70 estudiantes.');

        $this->assertDatabaseMissing('grupos', ['codigo' => 'G-MAYOR']);
    }
}
