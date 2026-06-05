<?php

namespace Tests\Feature;

use App\Models\Seguridad\User;
use App\Models\GestionAcademica\Gestion;
use App\Models\GestionAcademica\Grupo;
use App\Models\GestionAcademica\Materia;
use App\Models\GestionAcademica\Docente;
use App\Models\InscripcionPagos\Postulante;
use App\Models\InscripcionPagos\Inscripcion;
use App\Models\EvaluacionesResultados\Evaluacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultaNotasTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_puede_consultar_notas_de_grupo_materia(): void
    {
        // 1. Setup Gestión y Grupo
        $gestion = Gestion::factory()->create();
        $grupo = Grupo::create(['gestion_id' => $gestion->id, 'codigo' => 'G1']);
        
        // 2. Setup Materia y Docente
        $materia = Materia::create(['codigo' => 'MAT101', 'nombre' => 'Matemáticas']);
        $docente = Docente::create(['nombres' => 'Juan']);
        
        $grupo->materias()->attach($materia->id, ['docente_id' => $docente->id]);
        $grupoMateria = \App\Models\GestionAcademica\GrupoMateria::where('grupo_id', $grupo->id)->where('materia_id', $materia->id)->first();
        $grupoMateriaId = $grupoMateria->id;

        // 3. Setup Postulante e Inscripción en Grupo
        $postulante = Postulante::create([
            'ci' => '123456',
            'nombres' => 'Estudiante',
            'correo' => 'est@test.com'
        ]);
        $inscripcion = Inscripcion::create([
            'postulante_id' => $postulante->id,
            'gestion_id' => $gestion->id,
            'codigo' => 'INS-123'
        ]);
        $grupo->inscripciones()->attach($inscripcion->id);

        // 4. Agregar Evaluación
        Evaluacion::create([
            'inscripcion_id' => $inscripcion->id,
            'grupo_materia_id' => $grupoMateriaId,
            'examen_1' => 80,
            'promedio' => 80,
            'estado' => 'aprobado'
        ]);

        // 5. Consultar API
        $response = $this->actingAs($this->adminUser)->getJson("/api/evaluaciones/grupo-materia/{$grupoMateriaId}");
        
        if ($response->status() !== 200) {
            dd($response->json());
        }

        $response->assertStatus(200)
                 ->assertJsonPath('ok', true)
                 ->assertJsonPath('data.estudiantes.0.postulante_ci', '123456')
                 ->assertJsonPath('data.estudiantes.0.examen_1', '80.00'); // the cast might return string '80.00'
    }
}
