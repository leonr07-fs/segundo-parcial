<?php

namespace Tests\Feature;

use App\Models\EvaluacionesResultados\Evaluacion;
use App\Models\Seguridad\User;
use App\Support\States\EvaluacionState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Services\GestionAcademica\ValidacionAcademicaService;

class EvaluacionEstrictaTest extends TestCase
{
    use RefreshDatabase;

    public function test_estudiante_reprueba_si_nota_es_menor_a_51()
    {
        $eval = new Evaluacion([
            'examen_1' => 50,
            'estado' => EvaluacionState::INCOMPLETO
        ]);

        $service = new ValidacionAcademicaService();
        $service->validar($eval, false);

        $this->assertEquals(EvaluacionState::REPROBADO, $eval->estado);
    }

    public function test_estudiante_reprobado_lanza_excepcion_si_intenta_subir_mas_notas()
    {
        $eval = new Evaluacion([
            'examen_1' => 40,
            'examen_2' => 80,
            'estado' => EvaluacionState::INCOMPLETO
        ]);

        $service = new ValidacionAcademicaService();
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('El estudiante reprobó el Examen 1 con 40.00. No está habilitado para registrar más notas.');
        
        $service->validar($eval, false);
    }
}
