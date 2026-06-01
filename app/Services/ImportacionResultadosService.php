<?php

namespace App\Services;

use App\Models\Evaluacion;
use App\Models\GrupoMateria;
use App\Models\Inscripcion;
use App\Support\States\EvaluacionState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ImportacionResultadosService
{
    /**
     * Procesar archivo CSV de notas.
     * Cabecera esperada: inscripcion_codigo, grupo_materia_id, examen_1, examen_2, examen_3, promedio
     */
    public function procesarCsv(UploadedFile $archivo, $user): array
    {
        $errores = [];
        $exitosas = 0;
        $totalProcesadas = 0;

        // Abrir archivo CSV
        $fileHandle = fopen($archivo->getRealPath(), 'r');
        
        if ($fileHandle === false) {
            throw new \Exception("No se pudo leer el archivo cargado.");
        }

        // Leer cabecera (ignorar o validar, asumiremos formato estricto por ahora)
        $header = fgetcsv($fileHandle, 1000, ',');
        // Opcional: Validar que las columnas requeridas existan en $header. 
        // Para simplificar, asumiremos el orden estricto de las 6 columnas mencionadas.

        DB::beginTransaction();

        try {
            $fila = 2; // Comenzar en 2 por el header

            while (($data = fgetcsv($fileHandle, 1000, ',')) !== false) {
                // Saltar filas vacías
                if (empty(array_filter($data))) {
                    $fila++;
                    continue;
                }

                $totalProcesadas++;

                // Validar que tenga el número correcto de columnas (6)
                if (count($data) < 6) {
                    $errores[] = [
                        'fila' => $fila,
                        'mensaje' => 'La fila no contiene todas las columnas requeridas.',
                    ];
                    $fila++;
                    continue;
                }

                $codigoInscripcion = trim($data[0]);
                $grupoMateriaId = trim($data[1]);
                $ex1 = trim($data[2]) === '' ? null : (float) trim($data[2]);
                $ex2 = trim($data[3]) === '' ? null : (float) trim($data[3]);
                $ex3 = trim($data[4]) === '' ? null : (float) trim($data[4]);
                $promedio = trim($data[5]) === '' ? null : (float) trim($data[5]);

                // 1. Validar Inscripción (E2)
                $inscripcion = Inscripcion::where('codigo', $codigoInscripcion)->first();
                if (!$inscripcion) {
                    $errores[] = [
                        'fila' => $fila,
                        'mensaje' => "Inscripción no encontrada para el código: $codigoInscripcion",
                    ];
                    $fila++;
                    continue;
                }

                // 2. Validar GrupoMateria (E2)
                if (!GrupoMateria::where('id', $grupoMateriaId)->exists()) {
                    $errores[] = [
                        'fila' => $fila,
                        'mensaje' => "Grupo-Materia no encontrado para el ID: $grupoMateriaId",
                    ];
                    $fila++;
                    continue;
                }

                // 3. Validar Rango de Notas (E3)
                $notasAValidar = [$ex1, $ex2, $ex3, $promedio];
                $notaInvalida = false;
                foreach ($notasAValidar as $nota) {
                    if ($nota !== null && ($nota < 0 || $nota > 100)) {
                        $notaInvalida = true;
                        break;
                    }
                }

                if ($notaInvalida) {
                    $errores[] = [
                        'fila' => $fila,
                        'mensaje' => "Una o más notas están fuera del rango permitido (0-100).",
                    ];
                    $fila++;
                    continue;
                }

                // 4. Insertar o Actualizar Evaluación (Sin estado fijo, lo decidirá el servicio)
                $evaluacion = Evaluacion::updateOrCreate(
                    [
                        'inscripcion_id' => $inscripcion->id,
                        'grupo_materia_id' => $grupoMateriaId,
                    ],
                    [
                        'examen_1' => $ex1,
                        'examen_2' => $ex2,
                        'examen_3' => $ex3,
                        'promedio' => $promedio,
                        'registrado_por' => $user?->id,
                        'registrado_en' => now(),
                    ]
                );

                // 5. Validar Reglas Académicas (CU10)
                // Usamos app() para instanciar el servicio y validar
                app(\App\Services\ValidacionAcademicaService::class)->validar($evaluacion);

                $exitosas++;
                $fila++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            fclose($fileHandle);
            throw $e;
        }

        fclose($fileHandle);

        return [
            'total_procesadas' => $totalProcesadas,
            'exitosas' => $exitosas,
            'errores' => $errores,
        ];
    }
}
