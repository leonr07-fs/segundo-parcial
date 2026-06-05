<?php

namespace Tests\Feature;

use App\Models\AsignacionCarrera\Carrera;
use App\Models\GestionAcademica\Gestion;
use App\Models\InscripcionPagos\Postulante;
use App\Models\Seguridad\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepostulacionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'postulante']);
    }

    public function test_postulante_puede_repostular_a_nueva_gestion(): void
    {
        $postulante = Postulante::factory()->create();
        $gestion = Gestion::factory()->create();
        $carrera1 = Carrera::factory()->create();
        $carrera2 = Carrera::factory()->create();

        $payload = [
            'postulante_id' => $postulante->id,
            'gestion_id' => $gestion->id,
            'opcion1_carrera_id' => $carrera1->id,
            'opcion2_carrera_id' => $carrera2->id,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/postulantes/repostular', $payload);

        $response->assertStatus(201)
                 ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('inscripciones', [
            'postulante_id' => $postulante->id,
            'gestion_id' => $gestion->id,
            'estado' => 'prepostulado'
        ]);
    }
}
