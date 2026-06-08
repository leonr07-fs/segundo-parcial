<?php

namespace Tests\Feature;

use App\Models\Docentes\RepostulacionDocente;
use App\Models\GestionAcademica\Docente;
use App\Models\GestionAcademica\Gestion;
use App\Models\GestionAcademica\Grupo;
use App\Models\GestionAcademica\GrupoMateria;
use App\Models\GestionAcademica\Materia;
use App\Models\Seguridad\User;
use App\Support\States\GestionState;
use App\Support\States\RepostulacionDocenteState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RepostulacionDocenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_docente_previo_puede_registrar_repostulacion_publica(): void
    {
        $gestionAnterior = Gestion::factory()->create(['estado' => GestionState::CERRADA]);
        Gestion::factory()->create(['estado' => GestionState::INSCRIPCION]);

        $docente = Docente::create([
            'ci' => '5555555',
            'nombres' => 'Maria',
            'apellidos' => 'Lopez',
            'correo' => 'maria.docente@cup.test',
            'activo' => true,
        ]);

        $this->crearCargaDocente($docente, $gestionAnterior);

        $this->postJson('/api/public/repostulacion-docente', [
            'ci' => '5555555',
            'correo' => 'maria.docente@cup.test',
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.repostulacion.estado', RepostulacionDocenteState::PENDIENTE);
    }

    public function test_docente_sin_registro_previo_no_puede_repostular(): void
    {
        Gestion::factory()->create(['estado' => GestionState::INSCRIPCION]);

        $this->postJson('/api/public/repostulacion-docente', [
            'ci' => '0000000',
            'correo' => 'nuevo@cup.test',
        ])->assertStatus(422);
    }

    public function test_docente_sin_gestion_vigente_no_puede_iniciar_sesion(): void
    {
        Gestion::factory()->create(['estado' => GestionState::INSCRIPCION]);
        $gestionAnterior = Gestion::factory()->create(['estado' => GestionState::CERRADA]);

        $docente = Docente::create([
            'ci' => '6666666',
            'nombres' => 'Juan',
            'apellidos' => 'Perez',
            'correo' => 'juan.docente@cup.test',
            'activo' => true,
        ]);

        $this->crearCargaDocente($docente, $gestionAnterior);

        User::factory()->create([
            'email' => 'juan.docente@cup.test',
            'numero_registro' => '6666666',
            'password' => Hash::make('6666666'),
            'role' => User::ROLE_DOCENTE,
            'is_active' => true,
        ]);

        $this->postJson('/login', [
            'numero_registro' => '6666666',
            'password' => '6666666',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'No pertenece a la gestion vigente. Debe realizar una repostulacion docente desde la pagina inicial.');
    }

    public function test_admin_puede_aprobar_repostulacion_y_habilitar_acceso(): void
    {
        $gestionVigente = Gestion::factory()->create(['estado' => GestionState::INSCRIPCION]);
        $gestionAnterior = Gestion::factory()->create(['estado' => GestionState::CERRADA]);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $docente = Docente::create([
            'ci' => '7777777',
            'nombres' => 'Ana',
            'apellidos' => 'Rios',
            'correo' => 'ana.docente@cup.test',
            'activo' => false,
        ]);

        $this->crearCargaDocente($docente, $gestionAnterior);

        $repostulacion = RepostulacionDocente::create([
            'docente_id' => $docente->id,
            'gestion_id' => $gestionVigente->id,
            'estado' => RepostulacionDocenteState::PENDIENTE,
        ]);

        User::factory()->create([
            'email' => 'ana.docente@cup.test',
            'numero_registro' => '7777777',
            'password' => Hash::make('7777777'),
            'role' => User::ROLE_DOCENTE,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->postJson("/api/admin/repostulaciones-docentes/{$repostulacion->id}/aprobar")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('repostulaciones_docentes', [
            'id' => $repostulacion->id,
            'estado' => RepostulacionDocenteState::APROBADA,
        ]);

        $this->postJson('/login', [
            'numero_registro' => '7777777',
            'password' => '7777777',
        ])->assertOk();
    }

    private function crearCargaDocente(Docente $docente, Gestion $gestion): void
    {
        $materia = Materia::create(['codigo' => 'MAT', 'nombre' => 'Matematica', 'activa' => true]);
        $grupo = Grupo::create([
            'gestion_id' => $gestion->id,
            'codigo' => 'G1',
            'nombre' => 'Grupo 1',
            'estado' => 'activo',
        ]);

        GrupoMateria::create([
            'grupo_id' => $grupo->id,
            'materia_id' => $materia->id,
            'docente_id' => $docente->id,
        ]);
    }
}
