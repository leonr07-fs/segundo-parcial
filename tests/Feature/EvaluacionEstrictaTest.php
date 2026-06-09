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

    public function test_estudiante_no_reprueba_inmediatamente_si_examen_1_es_menor_a_60()
    {
        $eval = new Evaluacion([
            'examen_1' => 50,
            'estado' => EvaluacionState::INCOMPLETO
        ]);

        $service = new ValidacionAcademicaService();
        $service->validar($eval, false);

        $this->assertEquals(EvaluacionState::INCOMPLETO, $eval->estado);
    }

    public function test_estudiante_con_examen_1_menor_a_60_no_lanza_excepcion_si_tiene_examen_2()
    {
        $eval = new Evaluacion([
            'examen_1' => 40,
            'examen_2' => 80,
            'estado' => EvaluacionState::INCOMPLETO
        ]);

        $service = new ValidacionAcademicaService();
        $service->validar($eval, false);

        $this->assertEquals(EvaluacionState::INCOMPLETO, $eval->estado);
        $this->assertEquals('Falta el Examen 3.', $eval->observacion);
    }
}
