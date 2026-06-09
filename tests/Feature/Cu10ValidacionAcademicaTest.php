<?php

namespace Tests\Feature;

use App\Models\EvaluacionesResultados\Evaluacion;
use App\Models\InscripcionPagos\Inscripcion;
use App\Services\GestionAcademica\ValidacionAcademicaService;
use App\Support\States\EvaluacionState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Cu10ValidacionAcademicaTest extends TestCase
{
    use RefreshDatabase;

    private ValidacionAcademicaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ValidacionAcademicaService();
    }

    public function test_evaluacion_fuera_de_rango_es_observada(): void
    {
        $evaluacion = new Evaluacion();
        $evaluacion->examen_1 = 150; // Fuera de rango

        $this->service->validar($evaluacion, false);

        $this->assertEquals(EvaluacionState::OBSERVADO, $evaluacion->estado);
        $this->assertStringContainsString('fuera del rango', $evaluacion->observacion);
    }

    public function test_reprobacion_directa_por_examen_1_menor_a_60_es_incompleto(): void
    {
        $evaluacion = new Evaluacion();
        $evaluacion->examen_1 = 59; 
        
        $this->service->validar($evaluacion, false);

        $this->assertEquals(EvaluacionState::INCOMPLETO, $evaluacion->estado);
        $this->assertStringContainsString('Falta el Examen 2', $evaluacion->observacion);
    }

    public function test_reprobacion_directa_por_examen_2_menor_a_60_es_incompleto(): void
    {
        $evaluacion = new Evaluacion();
        $evaluacion->examen_1 = 80; 
        $evaluacion->examen_2 = 40; 
        
        $this->service->validar($evaluacion, false);

        $this->assertEquals(EvaluacionState::INCOMPLETO, $evaluacion->estado);
        $this->assertStringContainsString('Falta el Examen 3', $evaluacion->observacion);
    }

    public function test_evaluacion_aprobada_pero_falta_examen_2_es_incompleta(): void
    {
        $evaluacion = new Evaluacion();
        $evaluacion->examen_1 = 80; 
        $evaluacion->examen_2 = null; 
        
        $this->service->validar($evaluacion, false);

        $this->assertEquals(EvaluacionState::INCOMPLETO, $evaluacion->estado);
        $this->assertStringContainsString('Falta el Examen 2', $evaluacion->observacion);
    }

    public function test_evaluacion_con_3_examenes_mayores_a_60_es_aprobada(): void
    {
        $evaluacion = new Evaluacion();
        $evaluacion->examen_1 = 80; 
        $evaluacion->examen_2 = 70; 
        $evaluacion->examen_3 = 90; 
        
        $this->service->validar($evaluacion, false);

        $this->assertEquals(EvaluacionState::APROBADO, $evaluacion->estado);
        $this->assertNull($evaluacion->observacion);
        $this->assertEquals(80.00, $evaluacion->promedio); // (80+70+90)/3 = 80
    }

    public function test_aprobacion_con_algun_examen_menor_a_60_por_promedio_mayor_a_60(): void
    {
        $evaluacion = new Evaluacion();
        $evaluacion->examen_1 = 50; 
        $evaluacion->examen_2 = 60; 
        $evaluacion->examen_3 = 70; 
        
        $this->service->validar($evaluacion, false);

        $this->assertEquals(EvaluacionState::APROBADO, $evaluacion->estado);
        $this->assertEquals(60.00, $evaluacion->promedio);
    }

    public function test_reprobacion_por_promedio_menor_a_60(): void
    {
        $evaluacion = new Evaluacion();
        $evaluacion->examen_1 = 50; 
        $evaluacion->examen_2 = 50; 
        $evaluacion->examen_3 = 70; 
        
        $this->service->validar($evaluacion, false);

        $this->assertEquals(EvaluacionState::REPROBADO, $evaluacion->estado);
        $this->assertEquals(56.67, $evaluacion->promedio);
    }
}
