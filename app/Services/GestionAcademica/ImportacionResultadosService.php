<?php

namespace App\Services\GestionAcademica;

use App\Models\EvaluacionesResultados\Evaluacion;
use App\Models\EvaluacionesResultados\ResultadoCup;
use App\Models\GestionAcademica\GrupoMateria;
use App\Models\InscripcionPagos\Inscripcion;
use App\Support\States\EvaluacionState;
use App\Support\States\GestionState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * CU09 - Cargar/importar resultados académicos
 * CU11 - Calcular promedio final y determinar estado
 * Procesa archivos CSV de notas y actualiza evaluaciones, resultados CUP y estado académico.
 */
class ImportacionResultadosService
{
    /**
     * Procesa un CSV de un solo examen.
     * Cabecera esperada: inscripcion_codigo,grupo_materia_id,nota
     */
    public function procesarCsv(UploadedFile $archivo, $user, int $numeroExamen): array
    {
        @set_time_limit(300);

        $errores = [];
        $exitosas = 0;
        $totalProcesadas = 0;

        $fileHandle = fopen($archivo->getRealPath(), 'r');

        if ($fileHandle === false) {
            throw new \Exception('No se pudo leer el archivo cargado.');
        }

        $header = fgetcsv($fileHandle, 1000, ',') ?: [];
        $indiceNota = $this->indiceNota($header, $numeroExamen);

        $csvRows = [];
        $codigos = [];
        $fila = 2;
        while (($data = fgetcsv($fileHandle, 1000, ',')) !== false) {
            if (empty(array_filter($data))) {
                $fila++;
                continue;
            }
            $csvRows[] = [
                'fila' => $fila,
                'data' => $data
            ];
            if (isset($data[0])) {
                $codigos[] = trim($data[0]);
            }
            $fila++;
        }
        fclose($fileHandle);

        $codigos = array_unique($codigos);

        // Preload in memory to reduce database queries from 24,000 to 3!
        $inscripcionesMap = Inscripcion::with(['gestion', 'grupos.materias'])
            ->whereIn('codigo', $codigos)
            ->get()
            ->keyBy('codigo');

        $inscripcionIds = $inscripcionesMap->pluck('id')->all();
        $evaluacionesMap = Evaluacion::whereIn('inscripcion_id', $inscripcionIds)
            ->get()
            ->groupBy('inscripcion_id');

        $grupoMateriasMap = GrupoMateria::with('grupo.gestion')->get()->keyBy('id');

        $inscripcionesParaSincronizar = [];

        DB::beginTransaction();

        $evaluacionesParaUpsert = [];
        $inscripcionesParaSincronizar = [];

        try {
            foreach ($csvRows as $rowInfo) {
                $fila = $rowInfo['fila'];
                $data = $rowInfo['data'];

                $totalProcesadas++;

                if (count($data) < 3 || ! array_key_exists($indiceNota, $data)) {
                    $errores[] = [
                        'fila' => $fila,
                        'mensaje' => 'La fila debe contener inscripcion_codigo, grupo_materia_id y nota del examen seleccionado.',
                    ];
                    continue;
                }

                $codigoInscripcion = trim($data[0]);
                $grupoMateriaId = trim($data[1]);
                $nota = trim((string) $data[$indiceNota]) === '' ? null : (float) trim((string) $data[$indiceNota]);

                if ($nota === null) {
                    $errores[] = [
                        'fila' => $fila,
                        'mensaje' => "Debe registrar una nota para el Examen {$numeroExamen}.",
                    ];
                    continue;
                }

                if ($nota < 0 || $nota > 100) {
                    $errores[] = [
                        'fila' => $fila,
                        'mensaje' => 'La nota esta fuera del rango permitido (0-100).',
                    ];
                    continue;
                }

                $inscripcion = $inscripcionesMap->get($codigoInscripcion);
                if (!$inscripcion) {
                    $errores[] = [
                        'fila' => $fila,
                        'mensaje' => "Inscripcion no encontrada para el codigo: $codigoInscripcion",
                    ];
                    continue;
                }

                if ($inscripcion->gestion?->estado === GestionState::CERRADA) {
                    $errores[] = [
                        'fila' => $fila,
                        'mensaje' => 'La gestion esta cerrada definitivamente. No se pueden importar ni modificar notas.',
                    ];
                    continue;
                }

                // Resolucion de grupo_materia_id si es codigo/nombre de materia
                if (!is_numeric($grupoMateriaId) || empty($grupoMateriaId)) {
                    $materiaNombreOCodigo = strtolower(trim($grupoMateriaId));
                    $resolvedId = null;
                    
                    foreach ($inscripcion->grupos as $grupo) {
                        foreach ($grupo->materias as $materia) {
                            if (strtolower($materia->codigo) === $materiaNombreOCodigo || strtolower($materia->nombre) === $materiaNombreOCodigo) {
                                $gm = $grupoMateriasMap->first(function ($item) use ($grupo, $materia) {
                                    return (int) $item->grupo_id === (int) $grupo->id 
                                        && (int) $item->materia_id === (int) $materia->id;
                                });
                                if ($gm) {
                                    $resolvedId = $gm->id;
                                    break 2;
                                }
                            }
                        }
                    }
                    
                    if ($resolvedId) {
                        $grupoMateriaId = $resolvedId;
                    }
                }

                $grupoMateria = $grupoMateriasMap->get($grupoMateriaId);
                if (!$grupoMateria) {
                    $errores[] = [
                        'fila' => $fila,
                        'mensaje' => "Grupo-Materia no encontrado para el ID: $grupoMateriaId",
                    ];
                    continue;
                }

                if ($grupoMateria->grupo?->gestion?->estado === GestionState::CERRADA) {
                    $errores[] = [
                        'fila' => $fila,
                        'mensaje' => 'El grupo pertenece a una gestion cerrada definitivamente. No se pueden importar ni modificar notas.',
                    ];
                    continue;
                }

                if ((int) $grupoMateria->grupo?->gestion_id !== (int) $inscripcion->gestion_id) {
                    $errores[] = [
                        'fila' => $fila,
                        'mensaje' => 'El grupo-materia no pertenece a la misma gestion de la inscripcion.',
                    ];
                    continue;
                }

                // Obtener o instanciar evaluacion en memoria
                $evaluacionesEstudiante = $evaluacionesMap->get($inscripcion->id) ?: collect();
                $evaluacion = $evaluacionesEstudiante->firstWhere('grupo_materia_id', (int) $grupoMateriaId);
                if (!$evaluacion) {
                    $evaluacion = new Evaluacion([
                        'inscripcion_id' => $inscripcion->id,
                        'grupo_materia_id' => (int) $grupoMateriaId,
                    ]);
                }

                $mensajeSecuencia = $this->validarSecuencia($evaluacion, $numeroExamen);
                if ($mensajeSecuencia !== null) {
                    $errores[] = [
                        'fila' => $fila,
                        'mensaje' => $mensajeSecuencia,
                    ];
                    continue;
                }

                $columna = "examen_{$numeroExamen}";
                $evaluacion->{$columna} = $nota;
                $evaluacion->registrado_por = $user?->id;
                $evaluacion->registrado_en = now();

                try {
                    app(ValidacionAcademicaService::class)->validar($evaluacion, false);
                    
                    $evaluacionesParaUpsert[] = [
                        'inscripcion_id' => $evaluacion->inscripcion_id,
                        'grupo_materia_id' => $evaluacion->grupo_materia_id,
                        'examen_1' => $evaluacion->examen_1,
                        'examen_2' => $evaluacion->examen_2,
                        'examen_3' => $evaluacion->examen_3,
                        'promedio' => $evaluacion->promedio,
                        'estado' => $evaluacion->estado,
                        'observacion' => $evaluacion->observacion,
                        'registrado_por' => $evaluacion->registrado_por,
                        'registrado_en' => $evaluacion->registrado_en ? $evaluacion->registrado_en->toDateTimeString() : null,
                        'created_at' => $evaluacion->created_at ? $evaluacion->created_at->toDateTimeString() : now()->toDateTimeString(),
                        'updated_at' => now()->toDateTimeString(),
                    ];

                    $inscripcionesParaSincronizar[$inscripcion->id] = $inscripcion;
                    $exitosas++;
                } catch (\Exception $ex) {
                    $errores[] = [
                        'fila' => $fila,
                        'mensaje' => $ex->getMessage(),
                    ];
                }
            }

            if (!empty($evaluacionesParaUpsert)) {
                foreach (array_chunk($evaluacionesParaUpsert, 1000) as $chunk) {
                    Evaluacion::upsert(
                        $chunk,
                        ['inscripcion_id', 'grupo_materia_id'],
                        ['examen_1', 'examen_2', 'examen_3', 'promedio', 'estado', 'observacion', 'registrado_por', 'registrado_en', 'updated_at']
                    );
                }
            }

            if (!empty($inscripcionesParaSincronizar)) {
                $this->sincronizarResultadosCupBatch($inscripcionesParaSincronizar);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'numero_examen' => $numeroExamen,
            'total_procesadas' => $totalProcesadas,
            'exitosas' => $exitosas,
            'errores' => $errores,
        ];
    }

    private function indiceNota(array $header, int $numeroExamen): int
    {
        $normalizados = array_map(fn ($columna) => strtolower(trim((string) $columna)), $header);
        $columnaExamen = "examen_{$numeroExamen}";
        $indiceExamen = array_search($columnaExamen, $normalizados, true);

        if ($indiceExamen !== false) {
            return (int) $indiceExamen;
        }

        $indiceNota = array_search('nota', $normalizados, true);

        return $indiceNota === false ? 2 : (int) $indiceNota;
    }

    private function validarSecuencia(Evaluacion $evaluacion, int $numeroExamen): ?string
    {
        if ($numeroExamen === 1) {
            return null;
        }

        $anterior = "examen_" . ($numeroExamen - 1);

        if ($evaluacion->{$anterior} === null) {
            return "No se puede registrar el Examen {$numeroExamen} antes de registrar el Examen " . ($numeroExamen - 1) . '.';
        }

        if ((float) $evaluacion->{$anterior} < 60) {
            return "No se puede registrar el Examen {$numeroExamen} porque reprobo el Examen " . ($numeroExamen - 1) . '.';
        }

        return null;
    }

    private function sincronizarResultadosCupBatch(array $inscripciones): void
    {
        $inscripcionIds = array_keys($inscripciones);

        $evaluacionesPorInscripcion = Evaluacion::whereIn('inscripcion_id', $inscripcionIds)
            ->get()
            ->groupBy('inscripcion_id');

        $upsertData = [];

        foreach ($inscripciones as $inscripcion) {
            $evaluaciones = $evaluacionesPorInscripcion->get($inscripcion->id) ?: collect();

            if ($evaluaciones->isEmpty()) {
                continue;
            }

            $materiasEsperadas = $inscripcion->grupos
                ->flatMap(fn ($grupo) => $grupo->materias)
                ->pluck('pivot.grupo_materia_id')
                ->filter()
                ->unique()
                ->count();

            $faltanMaterias = $materiasEsperadas > 0 && $evaluaciones->count() < $materiasEsperadas;
            $hayPendientes = $evaluaciones->contains(fn (Evaluacion $evaluacion) => in_array($evaluacion->estado, [
                EvaluacionState::INCOMPLETO,
                EvaluacionState::OBSERVADO,
            ], true));

            $promedios = $evaluaciones->pluck('promedio')->filter(fn ($promedio) => $promedio !== null);
            $promedioFinal = $promedios->isEmpty() ? null : round((float) $promedios->avg(), 2);

            if ($faltanMaterias || $hayPendientes) {
                $estadoFinal = 'pendiente';
                $cerradoEn = null;
            } else {
                $estadoFinal = $evaluaciones->contains(fn (Evaluacion $evaluacion) => $evaluacion->estado === EvaluacionState::REPROBADO)
                    ? 'reprobado'
                    : 'aprobado';
                $cerradoEn = now()->toDateTimeString();
            }

            $upsertData[] = [
                'inscripcion_id' => $inscripcion->id,
                'promedio_final' => $promedioFinal,
                'estado_final' => $estadoFinal,
                'cerrado_en' => $cerradoEn,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];
        }

        if (!empty($upsertData)) {
            foreach (array_chunk($upsertData, 1000) as $chunk) {
                ResultadoCup::upsert(
                    $chunk,
                    ['inscripcion_id'],
                    ['promedio_final', 'estado_final', 'cerrado_en', 'updated_at']
                );
            }
        }
    }
}
