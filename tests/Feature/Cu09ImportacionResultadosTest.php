<?php

namespace Tests\Feature;

use App\Models\InscripcionPagos\Inscripcion;
use App\Models\Seguridad\User;
use App\Support\States\EvaluacionState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Cu09ImportacionResultadosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $docente;
    private int $gestionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->docente = User::factory()->create(['role' => 'docente']);

        $this->gestionId = DB::table('gestiones')->insertGetId([
            'nombre' => '1-2026',
            'anio' => 2026,
            'estado' => 'activa',
        ]);

        DB::table('grupos')->insert([
            'id' => 1,
            'gestion_id' => $this->gestionId,
            'codigo' => 'G-A',
            'nombre' => 'Grupo A',
            'cupo_maximo' => 50,
        ]);

        DB::table('materias')->insert([
            'id' => 1,
            'codigo' => 'MAT101',
            'nombre' => 'Calculo I',
        ]);
    }

    private function generarCsv(array $filas): UploadedFile
    {
        $contenido = "inscripcion_codigo,grupo_materia_id,nota\n";

        foreach ($filas as $fila) {
            $contenido .= implode(',', $fila) . "\n";
        }

        return UploadedFile::fake()->createWithContent('notas.csv', $contenido);
    }

    public function test_importacion_progresiva_conserva_notas_y_calcula_resultado_final(): void
    {
        $inscripcion = Inscripcion::factory()->create([
            'codigo' => 'CUP-2026-00001',
            'gestion_id' => $this->gestionId,
        ]);
        $gmId = DB::table('grupo_materias')->insertGetId(['grupo_id' => 1, 'materia_id' => 1]);
        DB::table('inscripcion_grupo')->insert(['inscripcion_id' => $inscripcion->id, 'grupo_id' => 1]);

        foreach ([[1, 60], [2, 60], [3, 70]] as [$numeroExamen, $nota]) {
            $response = $this->actingAs($this->admin)
                ->postJson('/api/evaluaciones/importar', [
                    'numero_examen' => $numeroExamen,
                    'archivo' => $this->generarCsv([
                        ['CUP-2026-00001', $gmId, (string) $nota],
                    ]),
                ]);

            $response->assertOk()
                ->assertJsonPath('data.total_procesadas', 1)
                ->assertJsonPath('data.exitosas', 1)
                ->assertJsonCount(0, 'data.errores');
        }

        $this->assertDatabaseHas('evaluaciones', [
            'inscripcion_id' => $inscripcion->id,
            'grupo_materia_id' => $gmId,
            'examen_1' => 60.00,
            'examen_2' => 60.00,
            'examen_3' => 70.00,
            'promedio' => 63.33,
            'estado' => EvaluacionState::APROBADO,
        ]);

        $this->assertDatabaseHas('resultados_cup', [
            'inscripcion_id' => $inscripcion->id,
            'promedio_final' => 63.33,
            'estado_final' => 'aprobado',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'importacion.resultados.completada',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_no_permite_importar_examen_2_sin_examen_1(): void
    {
        $inscripcion = Inscripcion::factory()->create([
            'codigo' => 'CUP-2026-00002',
            'gestion_id' => $this->gestionId,
        ]);
        $gmId = DB::table('grupo_materias')->insertGetId(['grupo_id' => 1, 'materia_id' => 1]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/evaluaciones/importar', [
                'numero_examen' => 2,
                'archivo' => $this->generarCsv([
                    ['CUP-2026-00002', $gmId, '80'],
                ]),
            ]);

        $response->assertOk()
            ->assertJsonPath('data.exitosas', 0)
            ->assertJsonCount(1, 'data.errores');

        $this->assertDatabaseMissing('evaluaciones', [
            'inscripcion_id' => $inscripcion->id,
            'grupo_materia_id' => $gmId,
        ]);
    }

    public function test_e1_archivo_no_csv_es_rechazado(): void
    {
        $archivoInvalido = UploadedFile::fake()->create('imagen.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($this->admin)
            ->postJson('/api/evaluaciones/importar', [
                'numero_examen' => 1,
                'archivo' => $archivoInvalido,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['archivo']);
    }

    public function test_e2_fila_con_inscripcion_inexistente_reporta_error(): void
    {
        $gmId = DB::table('grupo_materias')->insertGetId(['grupo_id' => 1, 'materia_id' => 1]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/evaluaciones/importar', [
                'numero_examen' => 1,
                'archivo' => $this->generarCsv([
                    ['CUP-INVENTADO', $gmId, '80'],
                ]),
            ]);

        $response->assertOk()
            ->assertJsonPath('data.total_procesadas', 1)
            ->assertJsonPath('data.exitosas', 0)
            ->assertJsonCount(1, 'data.errores');

        $errores = $response->json('data.errores');
        $this->assertStringContainsString('Inscripcion no encontrada', $errores[0]['mensaje']);
    }

    public function test_e3_nota_fuera_de_rango_se_reporta_como_error(): void
    {
        Inscripcion::factory()->create([
            'codigo' => 'CUP-100',
            'gestion_id' => $this->gestionId,
        ]);
        $gmId = DB::table('grupo_materias')->insertGetId(['grupo_id' => 1, 'materia_id' => 1]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/evaluaciones/importar', [
                'numero_examen' => 1,
                'archivo' => $this->generarCsv([
                    ['CUP-100', $gmId, '150'],
                    ['CUP-100', $gmId, '-10'],
                ]),
            ]);

        $response->assertOk()
            ->assertJsonPath('data.exitosas', 0)
            ->assertJsonCount(2, 'data.errores');

        $errores = $response->json('data.errores');
        $this->assertStringContainsString('fuera del rango', $errores[0]['mensaje']);
    }

    public function test_docente_no_puede_importar_resultados(): void
    {
        $response = $this->actingAs($this->docente)
            ->postJson('/api/evaluaciones/importar', [
                'numero_examen' => 1,
                'archivo' => $this->generarCsv([]),
            ]);

        $response->assertForbidden();
    }

    public function test_importacion_resuelve_grupo_materia_id_usando_materia_codigo_o_nombre(): void
    {
        $inscripcion = Inscripcion::factory()->create([
            'codigo' => 'CUP-2026-99999',
            'gestion_id' => $this->gestionId,
        ]);
        $gmId = DB::table('grupo_materias')->insertGetId(['grupo_id' => 1, 'materia_id' => 1]);
        DB::table('inscripcion_grupo')->insert(['inscripcion_id' => $inscripcion->id, 'grupo_id' => 1]);

        // Usando código de la materia "MAT101"
        $response = $this->actingAs($this->admin)
            ->postJson('/api/evaluaciones/importar', [
                'numero_examen' => 1,
                'archivo' => $this->generarCsv([
                    ['CUP-2026-99999', 'MAT101', '85'],
                ]),
            ]);

        $response->assertOk()
            ->assertJsonPath('data.total_procesadas', 1)
            ->assertJsonPath('data.exitosas', 1)
            ->assertJsonCount(0, 'data.errores');

        $this->assertDatabaseHas('evaluaciones', [
            'inscripcion_id' => $inscripcion->id,
            'grupo_materia_id' => $gmId,
            'examen_1' => 85.00,
        ]);
    }
}
