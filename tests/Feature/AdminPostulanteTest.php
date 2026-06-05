<?php

namespace Tests\Feature;

use App\Models\InscripcionPagos\Postulante;
use App\Models\Seguridad\User;
use App\Models\InscripcionPagos\Inscripcion;
use App\Support\States\InscripcionState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPostulanteTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::factory()->create(['role' => 'admin']);
    }

    public function test_index_retorna_lista_de_postulantes(): void
    {
        $postulante = Postulante::factory()->create(['correo' => 'habilitado@example.com']);
        Inscripcion::factory()->create([
            'postulante_id' => $postulante->id,
            'estado' => InscripcionState::DOCUMENTOS_APROBADOS,
            'codigo' => 'CUP-2026-00010',
        ]);
        User::factory()->create([
            'email' => 'habilitado@example.com',
            'numero_registro' => 'EST-2026-0010',
            'role' => User::ROLE_POSTULANTE,
        ]);

        $response = $this->actingAs($this->adminUser)->getJson('/api/admin/postulantes?search=EST-2026-0010');

        $response->assertStatus(200)
                 ->assertJsonPath('ok', true)
                 ->assertJsonPath('data.postulantes.data.0.ci', $postulante->ci)
                 ->assertJsonPath('data.postulantes.data.0.usuario.numero_registro', 'EST-2026-0010')
                 ->assertJsonPath('data.postulantes.data.0.inscripciones.0.estado', InscripcionState::DOCUMENTOS_APROBADOS)
                 ->assertJsonStructure([
                     'data' => [
                         'postulantes' => [
                             'data', 'current_page', 'last_page'
                         ]
                     ]
                 ]);
    }

    public function test_show_retorna_expediente_completo(): void
    {
        $postulante = Postulante::factory()->create();

        $response = $this->actingAs($this->adminUser)->getJson("/api/admin/postulantes/{$postulante->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('ok', true)
                 ->assertJsonPath('data.postulante.id', $postulante->id);
    }

    public function test_update_modifica_datos_permitidos(): void
    {
        $postulante = Postulante::factory()->create([
            'nombres' => 'Juan',
            'apellido_paterno' => 'Perez',
            'apellido_materno' => 'Lopez',
        ]);

        $payload = [
            'nombres' => 'Juan Modificado',
            'apellido_paterno' => 'Perez Modificado',
            'apellido_materno' => 'Lopez Modificado',
            'colegio_procedencia' => 'Colegio Actualizado',
        ];

        $response = $this->actingAs($this->adminUser)->putJson("/api/admin/postulantes/{$postulante->id}", $payload);

        $response->assertStatus(200)
                 ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('postulantes', [
            'id' => $postulante->id,
            'nombres' => 'Juan Modificado',
            'apellido_paterno' => 'Perez Modificado',
            'apellido_materno' => 'Lopez Modificado',
            'colegio_procedencia' => 'Colegio Actualizado',
        ]);
    }

    public function test_admin_puede_anular_una_postulacion_con_motivo_y_auditoria(): void
    {
        $postulante = Postulante::factory()->create();
        $inscripcion = Inscripcion::factory()->create([
            'postulante_id' => $postulante->id,
            'estado' => InscripcionState::DOCUMENTOS_APROBADOS,
            'codigo' => 'CUP-2026-09001',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/postulantes/{$postulante->id}/inscripciones/{$inscripcion->id}/anular", [
                'motivo' => 'Documentacion presentada fuera de plazo.',
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.inscripcion.estado', InscripcionState::CANCELADO)
            ->assertJsonPath('data.inscripcion.observacion', 'Documentacion presentada fuera de plazo.');

        $this->assertDatabaseHas('inscripciones', [
            'id' => $inscripcion->id,
            'estado' => InscripcionState::CANCELADO,
            'observacion' => 'Documentacion presentada fuera de plazo.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'postulacion.anulada',
            'user_id' => $this->adminUser->id,
        ]);

        $this->assertDatabaseHas('postulantes', [
            'id' => $postulante->id,
        ]);
    }

    public function test_no_permite_anular_una_postulacion_sin_motivo(): void
    {
        $postulante = Postulante::factory()->create();
        $inscripcion = Inscripcion::factory()->create([
            'postulante_id' => $postulante->id,
            'estado' => InscripcionState::DOCUMENTOS_APROBADOS,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/postulantes/{$postulante->id}/inscripciones/{$inscripcion->id}/anular", [
                'motivo' => '',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('motivo');
    }
}
