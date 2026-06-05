<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cup:synthetic-export-files', function () {
    $outputDir = storage_path('app/datos_sinteticos/carga_separada');
    File::ensureDirectoryExists($outputDir);

    $materias = [
        ['codigo' => 'MAT', 'nombre' => 'Matematica'],
        ['codigo' => 'COM', 'nombre' => 'Computacion'],
        ['codigo' => 'ING', 'nombre' => 'Ingles'],
        ['codigo' => 'FIS', 'nombre' => 'Fisica'],
    ];

    $inscripciones = DB::table('inscripciones')
        ->join('postulantes', 'postulantes.id', '=', 'inscripciones.postulante_id')
        ->join('gestiones', 'gestiones.id', '=', 'inscripciones.gestion_id')
        ->leftJoin('inscripcion_grupo', 'inscripcion_grupo.inscripcion_id', '=', 'inscripciones.id')
        ->leftJoin('grupos', 'grupos.id', '=', 'inscripcion_grupo.grupo_id')
        ->where('inscripciones.codigo', 'like', 'CUP-2026-%')
        ->orderBy('inscripciones.codigo')
        ->get([
            'inscripciones.id',
            'inscripciones.codigo',
            'inscripciones.estado',
            'postulantes.ci',
            'postulantes.nombres',
            'postulantes.apellido_paterno',
            'postulantes.apellido_materno',
            'postulantes.correo',
            'postulantes.telefono',
            'postulantes.ciudad',
            'postulantes.colegio_procedencia',
            'gestiones.nombre as gestion',
            'grupos.id as grupo_id',
            'grupos.codigo as grupo_codigo',
            'grupos.nombre as grupo_nombre',
        ]);

    if ($inscripciones->isEmpty()) {
        $this->warn('No hay inscripciones sinteticas CUP-2026. Ejecuta primero: php artisan db:seed --class=SyntheticCupDataSeeder --force');
        return 1;
    }

    $grupoMaterias = DB::table('grupo_materias')
        ->join('grupos', 'grupos.id', '=', 'grupo_materias.grupo_id')
        ->join('materias', 'materias.id', '=', 'grupo_materias.materia_id')
        ->whereIn('grupos.id', $inscripciones->pluck('grupo_id')->filter()->unique()->values())
        ->get(['grupo_materias.id', 'grupo_materias.grupo_id', 'materias.codigo', 'materias.nombre'])
        ->mapWithKeys(fn ($row) => [$row->grupo_id . ':' . $row->codigo => $row])
        ->all();

    $writeCsv = function (string $filename, array $headers, iterable $rows) use ($outputDir): string {
        $path = $outputDir . DIRECTORY_SEPARATOR . $filename;
        $handle = fopen($path, 'w');
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $path;
    };

    $numero = fn (string $codigo): int => (int) Str::afterLast($codigo, '-');

    $perfil = function (int $i): array {
        return match ($i % 10) {
            0, 1 => ['tipo' => 'reprobado_ex1', 'materia' => $i % 4],
            2 => ['tipo' => 'reprobado_ex2', 'materia' => $i % 4],
            3 => ['tipo' => 'reprobado_ex3', 'materia' => $i % 4],
            4 => ['tipo' => 'habilitado_ex2', 'materia' => null],
            5 => ['tipo' => 'habilitado_ex3', 'materia' => null],
            default => ['tipo' => 'aprobado', 'materia' => null],
        };
    };

    $notas = function (int $i, int $materiaIndex, array $perfil): array {
        $base1 = 66 + (($i + $materiaIndex * 7) % 24);
        $base2 = 65 + (($i * 3 + $materiaIndex * 5) % 25);
        $base3 = 64 + (($i * 5 + $materiaIndex * 3) % 26);

        if ($perfil['materia'] === $materiaIndex) {
            return match ($perfil['tipo']) {
                'reprobado_ex1' => [45 + ($i % 6), null, null],
                'reprobado_ex2' => [$base1, 44 + ($i % 7), null],
                'reprobado_ex3' => [$base1, $base2, 43 + ($i % 8)],
                default => [$base1, $base2, $base3],
            };
        }

        return match ($perfil['tipo']) {
            'reprobado_ex1' => [$base1, null, null],
            'reprobado_ex2', 'habilitado_ex2' => [$base1, null, null],
            'reprobado_ex3', 'habilitado_ex3' => [$base1, $base2, null],
            default => [$base1, $base2, $base3],
        };
    };

    $files = [];

    $files[] = $writeCsv(
        'alumnos_registros.csv',
        ['numero_registro', 'password_inicial', 'codigo_cup', 'ci', 'nombres', 'apellido_paterno', 'apellido_materno', 'correo', 'telefono', 'ciudad', 'colegio_procedencia', 'gestion', 'grupo', 'estado_inscripcion'],
        $inscripciones->map(fn ($row) => [
            $row->ci,
            'CupFicct2026!',
            $row->codigo,
            $row->ci,
            $row->nombres,
            $row->apellido_paterno,
            $row->apellido_materno,
            $row->correo,
            $row->telefono,
            $row->ciudad,
            $row->colegio_procedencia,
            $row->gestion,
            $row->grupo_codigo,
            $row->estado,
        ]),
    );

    $docentes = DB::table('docentes')
        ->where('correo', 'like', '%@test.cup')
        ->orderBy('ci')
        ->get(['ci', 'nombres', 'apellidos', 'correo', 'telefono', 'activo']);

    $files[] = $writeCsv(
        'docentes_registros.csv',
        ['numero_registro', 'password_inicial', 'ci', 'nombres', 'apellidos', 'correo', 'telefono', 'activo'],
        $docentes->map(fn ($row) => [
            $row->ci,
            'CupFicct2026!',
            $row->ci,
            $row->nombres,
            $row->apellidos,
            $row->correo,
            $row->telefono,
            $row->activo ? 1 : 0,
        ]),
    );

    $grupos = DB::table('grupo_materias')
        ->join('grupos', 'grupos.id', '=', 'grupo_materias.grupo_id')
        ->join('gestiones', 'gestiones.id', '=', 'grupos.gestion_id')
        ->join('materias', 'materias.id', '=', 'grupo_materias.materia_id')
        ->leftJoin('docentes', 'docentes.id', '=', 'grupo_materias.docente_id')
        ->leftJoin('horarios', 'horarios.grupo_materia_id', '=', 'grupo_materias.id')
        ->leftJoin('aulas', 'aulas.id', '=', 'grupos.aula_id')
        ->where('gestiones.nombre', 'Semestre 1 2026')
        ->orderBy('grupos.codigo')
        ->orderBy('materias.codigo')
        ->get([
            'gestiones.nombre as gestion',
            'grupos.codigo as grupo',
            'grupos.nombre as grupo_nombre',
            'aulas.codigo as aula',
            'grupo_materias.id as grupo_materia_id',
            'materias.codigo as materia_codigo',
            'materias.nombre as materia',
            'docentes.ci as docente_ci',
            'docentes.nombres as docente_nombres',
            'docentes.apellidos as docente_apellidos',
            'horarios.dia_semana',
            'horarios.hora_inicio',
            'horarios.hora_fin',
            'horarios.modalidad',
        ]);

    $files[] = $writeCsv(
        'grupos_docentes_horarios.csv',
        ['gestion', 'grupo', 'grupo_nombre', 'aula', 'grupo_materia_id', 'materia_codigo', 'materia', 'docente_ci', 'docente', 'dia_semana', 'hora_inicio', 'hora_fin', 'modalidad'],
        $grupos->map(fn ($row) => [
            $row->gestion,
            $row->grupo,
            $row->grupo_nombre,
            $row->aula,
            $row->grupo_materia_id,
            $row->materia_codigo,
            $row->materia,
            $row->docente_ci,
            trim($row->docente_nombres . ' ' . $row->docente_apellidos),
            $row->dia_semana,
            $row->hora_inicio,
            $row->hora_fin,
            $row->modalidad,
        ]),
    );

    foreach ([1, 2, 3] as $examen) {
        $rows = [];

        foreach ($inscripciones as $inscripcion) {
            $i = $numero($inscripcion->codigo);
            $perfilActual = $perfil($i);

            foreach ($materias as $materiaIndex => $materia) {
                $notaMateria = $notas($i, $materiaIndex, $perfilActual);
                $grupoMateria = $grupoMaterias[$inscripcion->grupo_id . ':' . $materia['codigo']] ?? null;

                if ($grupoMateria === null) {
                    continue;
                }

                $notaExamen = $notaMateria[$examen - 1] ?? null;

                if ($notaExamen === null) {
                    continue;
                }

                $rows[] = [
                    $inscripcion->codigo,
                    $grupoMateria->id,
                    $notaExamen,
                ];
            }
        }

        $files[] = $writeCsv(
            "notas_examen_{$examen}.csv",
            ['inscripcion_codigo', 'grupo_materia_id', 'nota'],
            $rows,
        );
    }

    foreach ($files as $file) {
        $this->line($file);
    }

    $this->info('Archivos de carga separados generados correctamente.');
})->purpose('Genera archivos CSV separados para alumnos, docentes, grupos y examenes sinteticos CUP.');
