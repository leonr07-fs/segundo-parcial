<?php

namespace Tests\Feature;

use App\Models\GrupoMateria;
use App\Models\Inscripcion;
use App\Models\User;
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->docente = User::factory()->create(['role' => 'docente']);

        // Crear tablas relacionadas estáticas para evitar fallos de llaves foráneas en el test
        $gestionId = DB::table('gestiones')->insertGetId(['nombre' => '1-2026', 'anio' => 2026, 'estado' => 'activa']);
        DB::table('grupos')->insert(['id' => 1, 'gestion_id' => $gestionId, 'codigo' => 'G-A', 'nombre' => 'Grupo A', 'cupo_maximo' => 50]);
        DB::table('materias')->insert(['id' => 1, 'codigo' => 'MAT101', 'nombre' => 'Cálculo I']);
    }

    private function generarCsv(array $filas): UploadedFile
    {
        $header = "inscripcion_codigo,grupo_materia_id,examen_1,examen_2,examen_3,promedio\n";
        $contenido = $header;
        foreach ($filas as $fila) {
            $contenido .= implode(',', $fila) . "\n";
        }

        return UploadedFile::fake()->createWithContent('notas.csv', $contenido);
    }

    /* ================================================================== */
    /*  TEST 1: Importación Exitosa y Cálculo de Estados                  */
    /* ================================================================== */

    public function test_importacion_exitosa_crea_registros_y_calcula_estado(): void
    {
        $inscripcion1 = Inscripcion::factory()->create(['codigo' => 'CUP-2026-00001']);
        $inscripcion2 = Inscripcion::factory()->create(['codigo' => 'CUP-2026-00002']);

        $gmId = DB::table('grupo_materias')->insertGetId(['grupo_id' => 1, 'materia_id' => 1]);

        $csv = $this->generarCsv([
            ['CUP-2026-00001', $gmId, '60', '60', '70', '63'], // Aprobado
            ['CUP-2026-00002', $gmId, '40', '50', '45', '45'], // Reprobado
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/evaluaciones/importar', [
                'archivo' => $csv
            ]);

        $response->assertOk()
            ->assertJsonPath('data.total_procesadas', 2)
            ->assertJsonPath('data.exitosas', 2)
            ->assertJsonCount(0, 'data.errores');

        // Verificar BD para Aprobado
        $this->assertDatabaseHas('evaluaciones', [
            'inscripcion_id' => $inscripcion1->id,
            'grupo_materia_id' => $gmId,
            'promedio' => 63.33,
            'estado' => EvaluacionState::APROBADO,
        ]);

        // Verificar BD para Reprobado
        $this->assertDatabaseHas('evaluaciones', [
            'inscripcion_id' => $inscripcion2->id,
            'grupo_materia_id' => $gmId,
            'promedio' => 45.00,
            'estado' => EvaluacionState::REPROBADO,
        ]);

        // Verificar Auditoría
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'importacion.resultados.completada',
            'user_id' => $this->admin->id,
        ]);
    }

    /* ================================================================== */
    /*  TEST 2: Falla Formato de Archivo (E1)                             */
    /* ================================================================== */

    public function test_e1_archivo_no_csv_es_rechazado(): void
    {
        $archivoInvalido = UploadedFile::fake()->create('imagen.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($this->admin)
            ->postJson('/api/evaluaciones/importar', [
                'archivo' => $archivoInvalido
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['archivo']);
    }

    /* ================================================================== */
    /*  TEST 3: Inscripción/Grupo inexistente se salta y reporta (E2)     */
    /* ================================================================== */

    public function test_e2_fila_con_inscripcion_inexistente_reporta_error(): void
    {
        $gmId = DB::table('grupo_materias')->insertGetId(['grupo_id' => 1, 'materia_id' => 1]);

        $csv = $this->generarCsv([
            ['CUP-INVENTADO', $gmId, '80', '80', '80', '80'], // Error
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/evaluaciones/importar', [
                'archivo' => $csv
            ]);

        $response->assertOk()
            ->assertJsonPath('data.total_procesadas', 1)
            ->assertJsonPath('data.exitosas', 0)
            ->assertJsonCount(1, 'data.errores');

        $errores = $response->json('data.errores');
        $this->assertStringContainsString('Inscripción no encontrada', $errores[0]['mensaje']);
    }

    /* ================================================================== */
    /*  TEST 4: Nota fuera de rango (E3)                                  */
    /* ================================================================== */

    public function test_e3_nota_fuera_de_rango_se_reporta_como_error(): void
    {
        $inscripcion = Inscripcion::factory()->create(['codigo' => 'CUP-100']);
        $gmId = DB::table('grupo_materias')->insertGetId(['grupo_id' => 1, 'materia_id' => 1]);

        $csv = $this->generarCsv([
            ['CUP-100', $gmId, '150', '80', '80', '80'], // Error (150 > 100)
            ['CUP-100', $gmId, '80', '-10', '80', '80'], // Error (-10 < 0)
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/evaluaciones/importar', [
                'archivo' => $csv
            ]);

        $response->assertOk()
            ->assertJsonPath('data.exitosas', 0)
            ->assertJsonCount(2, 'data.errores');
            
        $errores = $response->json('data.errores');
        $this->assertStringContainsString('fuera del rango', $errores[0]['mensaje']);
    }

    /* ================================================================== */
    /*  TEST 5: Seguridad roles                                           */
    /* ================================================================== */

    public function test_docente_no_puede_importar_resultados(): void
    {
        $csv = $this->generarCsv([]);

        $response = $this->actingAs($this->docente)
            ->postJson('/api/evaluaciones/importar', [
                'archivo' => $csv
            ]);

        $response->assertForbidden();
    }
}
