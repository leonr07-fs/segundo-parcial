<?php

namespace Database\Seeders;

use App\Models\Seguridad\User;
use App\Support\FicctAulas;
use App\Support\States\InscripcionState;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SyntheticCupDataSeeder extends Seeder
{
    private const TOTAL_POSTULANTES = 3000;
    private const TOTAL_DOCENTES = 60;
    private const ANIO = 2026;
    private const GESTION = 'Semestre 1 2026';
    private const PASSWORD = 'CupFicct2026!';
    private const CUPO_GRUPO = 70;

    private const MATERIAS = [
        ['codigo' => 'MAT', 'nombre' => 'Matematica'],
        ['codigo' => 'COM', 'nombre' => 'Computacion'],
        ['codigo' => 'ING', 'nombre' => 'Ingles'],
        ['codigo' => 'FIS', 'nombre' => 'Fisica'],
    ];

    private const CARRERAS = [
        ['codigo' => 'SIS', 'nombre' => 'Ingenieria en Sistemas', 'cupos' => 300],
        ['codigo' => 'INF', 'nombre' => 'Informatica', 'cupos' => 500],
        ['codigo' => 'RED', 'nombre' => 'Redes', 'cupos' => 100],
        ['codigo' => 'ROB', 'nombre' => 'Robotica', 'cupos' => 50],
    ];

    private array $ciudades = ['Santa Cruz', 'Warnes', 'Montero', 'La Guardia', 'Cotoca'];
    private array $colegios = ['Carlos Laborde Pulido', 'Nacional Florida', 'Uboldi', 'La Salle', 'Don Bosco', 'San Agustin'];
    private array $nombresH = ['Carlos', 'Juan', 'Miguel', 'Luis', 'Jose', 'Andres', 'Diego', 'Fernando', 'Raul', 'Marco'];
    private array $nombresM = ['Marilyn', 'Ana', 'Maria', 'Lucia', 'Gabriela', 'Paola', 'Daniela', 'Carla', 'Sofia', 'Roxana'];
    private array $apellidos = ['Condori', 'Diaz', 'Rojas', 'Vargas', 'Mamani', 'Flores', 'Suarez', 'Gomez', 'Arce', 'Mendez', 'Lopez', 'Quiroga'];

    public function run(): void
    {
        // Remove single long-running transaction to avoid connection timeouts
        // and long-lived locks when inserting very large synthetic datasets.
        $now = now();
        $adminId = $this->adminId($now);
        $gestionId = $this->gestionId($now);
        $carreras = $this->carreras($gestionId, $now);
        $materias = $this->materias($now);
        $docentes = $this->docentes($now);
        $grupos = $this->grupos($gestionId, $now);
        $grupoMaterias = $this->grupoMaterias($grupos, $materias, $docentes, $now);

        // Each step performs its own batched upserts; run sequentially.
        $this->horarios($grupos, $grupoMaterias, $now);
        $postulantes = $this->postulantes($now);
        $this->usuariosPostulantes($postulantes, $now);
        $inscripciones = $this->inscripciones($postulantes, $gestionId, $now);

        $this->opcionesCarrera($inscripciones, $carreras, $now);

        // En modo rápido, omitimos inserciones auxiliares pesadas.
        if (! $this->isFast()) {
            $this->documentos($inscripciones, $adminId, $now);
            $this->pagos($inscripciones, $adminId, $now);
            $this->evaluaciones($inscripciones, $grupos, $materias, $grupoMaterias, $adminId, $now);
        }

        $this->asignarGrupos($inscripciones, $grupos, $now);
    }

    private function isFast(): bool
    {
        return filter_var(env('SYNTHETIC_FAST', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function totalPostulantes(): int
    {
        return (int) env('SYNTHETIC_TOTAL_POSTULANTES', self::TOTAL_POSTULANTES);
    }

    private function totalDocentes(): int
    {
        return (int) env('SYNTHETIC_TOTAL_DOCENTES', self::TOTAL_DOCENTES);
    }

    private function adminId($now): int
    {
        DB::table('users')->upsert(
            [[
                'email' => 'synthetic.admin@cup.test',
                'name' => 'Administrador Datos Sinteticos',
                'numero_registro' => 'SYN-ADMIN',
                'password' => Hash::make(self::PASSWORD),
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
                'email_verified_at' => $now,
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'updated_at' => $now,
                'created_at' => $now,
            ]],
            ['email'],
            ['name', 'numero_registro', 'password', 'role', 'is_active', 'email_verified_at', 'failed_login_attempts', 'locked_until', 'updated_at'],
        );

        return (int) DB::table('users')->where('email', 'synthetic.admin@cup.test')->value('id');
    }

    private function gestionId($now): int
    {
        DB::table('gestiones')->updateOrInsert(
            ['nombre' => self::GESTION],
            [
                'anio' => self::ANIO,
                'periodo' => '1-2026',
                'fecha_inicio' => '2026-01-15',
                'fecha_fin' => '2026-06-30',
                'estado' => 'inhabilitada',
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        return (int) DB::table('gestiones')->where('nombre', self::GESTION)->value('id');
    }

    private function carreras(int $gestionId, $now): array
    {
        $rows = [];

        foreach (self::CARRERAS as $carrera) {
            $rows[] = [
                'codigo' => $carrera['codigo'],
                'nombre' => $carrera['nombre'],
                'activa' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('carreras')->upsert($rows, ['codigo'], ['nombre', 'activa', 'updated_at']);
        $carreras = DB::table('carreras')->whereIn('codigo', array_column(self::CARRERAS, 'codigo'))->pluck('id', 'codigo')->all();

        $cupos = [];
        foreach (self::CARRERAS as $carrera) {
            $cupos[] = [
                'gestion_id' => $gestionId,
                'carrera_id' => $carreras[$carrera['codigo']],
                'cupo_total' => $carrera['cupos'],
                'cupo_disponible' => $carrera['cupos'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('cupos_carrera')->upsert(
            $cupos,
            ['gestion_id', 'carrera_id'],
            ['cupo_total', 'cupo_disponible', 'updated_at'],
        );

        return $carreras;
    }

    private function materias($now): array
    {
        $rows = array_map(fn (array $materia) => [
            'codigo' => $materia['codigo'],
            'nombre' => $materia['nombre'],
            'activa' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], self::MATERIAS);

        DB::table('materias')->upsert($rows, ['codigo'], ['nombre', 'activa', 'updated_at']);
        DB::table('materias')
            ->whereIn('codigo', ['MAT-100', 'COM-100', 'ING-100', 'FIS-100'])
            ->update(['activa' => false, 'updated_at' => $now]);

        return DB::table('materias')->whereIn('codigo', array_column(self::MATERIAS, 'codigo'))->pluck('id', 'codigo')->all();
    }

    private function docentes($now): array
    {
        $docentes = [];
        $usuarios = [];

        $totalDocentes = $this->totalDocentes();
        for ($i = 1; $i <= $totalDocentes; $i++) {
            $nombre = $this->pick(array_merge($this->nombresM, $this->nombresH), $i);
            $apellido = $this->pick($this->apellidos, $i) . ' ' . $this->pick($this->apellidos, $i + 4);
            $ci = 'DOC' . $this->pad($i, 5);
            $correo = $this->slug($nombre) . '.' . $this->slug($apellido) . '.' . strtolower($ci) . '@test.cup';

            $docentes[] = [
                'ci' => $ci,
                'nombres' => $nombre,
                'apellidos' => $apellido,
                'correo' => $correo,
                'telefono' => '7' . $this->pad(1000000 + $i, 7),
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $usuarios[] = [
                'name' => "{$nombre} {$apellido}",
                'email' => $correo,
                'numero_registro' => $ci,
                'password' => Hash::make(self::PASSWORD),
                'role' => User::ROLE_DOCENTE,
                'is_active' => true,
                'email_verified_at' => $now,
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('docentes')->upsert($docentes, ['ci'], ['nombres', 'apellidos', 'correo', 'telefono', 'activo', 'updated_at']);
        DB::table('users')->upsert($usuarios, ['email'], ['name', 'numero_registro', 'password', 'role', 'is_active', 'email_verified_at', 'failed_login_attempts', 'locked_until', 'updated_at']);

        return DB::table('docentes')->whereIn('ci', array_column($docentes, 'ci'))->pluck('id')->values()->all();
    }

    private function grupos(int $gestionId, $now): array
    {
        $totalGrupos = (int) ceil($this->totalPostulantes() / self::CUPO_GRUPO);
        $aulas = [];
        $grupos = [];

        foreach (FicctAulas::catalogo() as $aula) {
            $aulas[] = [
                'codigo' => $aula['codigo'],
                'nombre' => $aula['nombre'],
                'capacidad' => $aula['capacidad'],
                'ubicacion' => $aula['ubicacion'],
                'activa' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('aulas')->upsert($aulas, ['codigo'], ['nombre', 'capacidad', 'ubicacion', 'activa', 'updated_at']);
        DB::table('aulas')->whereNotIn('codigo', FicctAulas::TODAS)->update(['activa' => false, 'updated_at' => $now]);

        $aulaIds = DB::table('aulas')->whereIn('codigo', array_column($aulas, 'codigo'))->pluck('id', 'codigo')->all();
        $aulasBase = [...FicctAulas::TEORICAS, FicctAulas::AUDITORIO];

        for ($i = 1; $i <= $totalGrupos; $i++) {
            $codigo = 'G-' . $this->pad($i, 3);
            $codigoAulaBase = $aulasBase[($i - 1) % count($aulasBase)];
            $grupos[] = [
                'gestion_id' => $gestionId,
                'codigo' => $codigo,
                'nombre' => 'Grupo ' . $this->pad($i, 3),
                'cupo_maximo' => self::CUPO_GRUPO,
                'aula_id' => $aulaIds[$codigoAulaBase],
                'estado' => 'activo',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('grupos')->upsert($grupos, ['gestion_id', 'codigo'], ['nombre', 'cupo_maximo', 'aula_id', 'estado', 'updated_at']);

        return DB::table('grupos')->where('gestion_id', $gestionId)->whereIn('codigo', array_column($grupos, 'codigo'))->orderBy('codigo')->pluck('id', 'codigo')->all();
    }

    private function grupoMaterias(array $grupos, array $materias, array $docentes, $now): array
    {
        $rows = [];
        $docenteIndex = 0;

        foreach ($grupos as $grupoId) {
            foreach (self::MATERIAS as $materia) {
                $rows[] = [
                    'grupo_id' => $grupoId,
                    'materia_id' => $materias[$materia['codigo']],
                    'docente_id' => $docentes[$docenteIndex % count($docentes)],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $docenteIndex++;
            }
        }

        DB::table('grupo_materias')->upsert($rows, ['grupo_id', 'materia_id'], ['docente_id', 'updated_at']);

        return DB::table('grupo_materias')
            ->join('materias', 'materias.id', '=', 'grupo_materias.materia_id')
            ->whereIn('grupo_materias.grupo_id', array_values($grupos))
            ->get(['grupo_materias.id', 'grupo_materias.grupo_id', 'materias.codigo'])
            ->mapWithKeys(fn ($row) => [$row->grupo_id . ':' . $row->codigo => $row->id])
            ->all();
    }

    private function horarios(array $grupos, array $grupoMaterias, $now): void
    {
        $bloques = [
            'MAT' => [1, '08:00', '09:30'],
            'COM' => [2, '09:45', '11:15'],
            'ING' => [3, '14:00', '15:30'],
            'FIS' => [4, '15:45', '17:15'],
        ];
        $grupoAulas = DB::table('grupos')->whereIn('id', array_values($grupos))->pluck('aula_id', 'id')->all();
        $ordenGrupos = array_flip(array_values($grupos));
        $aulaIds = DB::table('aulas')->whereIn('codigo', FicctAulas::TODAS)->pluck('id', 'codigo')->all();

        $rows = [];
        foreach ($grupoMaterias as $key => $grupoMateriaId) {
            [$grupoId, $materiaCodigo] = explode(':', $key);
            [$dia, $inicio, $fin] = $bloques[$materiaCodigo];
            $aulaId = $materiaCodigo === 'COM'
                ? $aulaIds[FicctAulas::LABORATORIOS_COMPUTACION[($ordenGrupos[(int) $grupoId] ?? 0) % count(FicctAulas::LABORATORIOS_COMPUTACION)]]
                : ($grupoAulas[(int) $grupoId] ?? null);

            $rows[] = [
                'grupo_materia_id' => $grupoMateriaId,
                'aula_id' => $aulaId,
                'dia_semana' => $dia,
                'hora_inicio' => $inicio,
                'hora_fin' => $fin,
                'modalidad' => 'presencial',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('horarios')->upsert(
                $chunk,
                ['grupo_materia_id', 'dia_semana', 'hora_inicio'],
                ['hora_fin', 'modalidad', 'updated_at'],
            );
        }
    }

    private function postulantes($now): array
    {
        $rows = [];

        $totalPostulantes = $this->totalPostulantes();
        for ($i = 1; $i <= $totalPostulantes; $i++) {
            $genero = $i % 2 === 0 ? 'masculino' : 'femenino';
            $nombre = $genero === 'masculino' ? $this->pick($this->nombresH, $i) : $this->pick($this->nombresM, $i);
            $paterno = $this->pick($this->apellidos, $i + 1);
            $materno = $this->pick($this->apellidos, $i + 5);
            $ci = (string) (9000000 + $i);

            $rows[] = [
                'ci' => $ci,
                'complemento' => null,
                'nombres' => $nombre,
                'apellido_paterno' => $paterno,
                'apellido_materno' => $materno,
                'fecha_nacimiento' => '2005-' . $this->pad(($i % 12) + 1, 2) . '-' . $this->pad(($i % 27) + 1, 2),
                'genero' => $genero,
                'correo' => $this->slug($nombre) . '.' . $this->slug($paterno) . '.' . $ci . '@test.cup',
                'telefono' => '6' . $this->pad(2000000 + $i, 7),
                'direccion' => 'Barrio ' . $this->pick(['Plan 3000', 'Hamacas', 'Equipetrol', 'Los Tusequis', 'Villa Primero de Mayo'], $i) . ' #' . (100 + $i),
                'colegio_procedencia' => $this->pick($this->colegios, $i),
                'ciudad' => $this->pick($this->ciudades, $i),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('postulantes')->upsert(
                $chunk,
                ['ci'],
                ['complemento', 'nombres', 'apellido_paterno', 'apellido_materno', 'fecha_nacimiento', 'genero', 'correo', 'telefono', 'direccion', 'colegio_procedencia', 'ciudad', 'updated_at'],
            );
        }

        return DB::table('postulantes')->whereIn('ci', array_column($rows, 'ci'))->orderBy('ci')->get()->keyBy('ci')->all();
    }

    private function usuariosPostulantes(array $postulantes, $now): void
    {
        $rows = [];

        foreach ($postulantes as $postulante) {
            $rows[] = [
                'name' => trim($postulante->nombres . ' ' . $postulante->apellido_paterno . ' ' . $postulante->apellido_materno),
                'email' => $postulante->correo,
                'numero_registro' => $postulante->ci,
                'password' => Hash::make(self::PASSWORD),
                'role' => User::ROLE_POSTULANTE,
                'is_active' => true,
                'email_verified_at' => $now,
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('users')->upsert(
                $chunk,
                ['email'],
                ['name', 'numero_registro', 'password', 'role', 'is_active', 'email_verified_at', 'failed_login_attempts', 'locked_until', 'updated_at'],
            );
        }
    }

    private function inscripciones(array $postulantes, int $gestionId, $now): array
    {
        $rows = [];
        $index = 1;

        foreach ($postulantes as $postulante) {
            $rows[] = [
                'postulante_id' => $postulante->id,
                'gestion_id' => $gestionId,
                'codigo' => 'CUP-' . self::ANIO . '-' . $this->pad($index, 5),
                'fecha_inscripcion' => $now,
                'estado' => InscripcionState::PAGADO,
                'observacion' => 'Carga sintetica para pruebas masivas CUP.',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $index++;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('inscripciones')->upsert(
                $chunk,
                ['codigo'],
                ['postulante_id', 'gestion_id', 'fecha_inscripcion', 'estado', 'observacion', 'updated_at'],
            );
        }

        return DB::table('inscripciones')->whereIn('codigo', array_column($rows, 'codigo'))->orderBy('codigo')->get()->keyBy('codigo')->all();
    }

    private function opcionesCarrera(array $inscripciones, array $carreras, $now): void
    {
        $codigos = array_column(self::CARRERAS, 'codigo');
        $rows = [];
        $index = 1;

        foreach ($inscripciones as $inscripcion) {
            $primera = $codigos[$index % count($codigos)];
            $segunda = $codigos[($index + 1) % count($codigos)];

            $rows[] = ['inscripcion_id' => $inscripcion->id, 'carrera_id' => $carreras[$primera], 'prioridad' => 1, 'created_at' => $now, 'updated_at' => $now];
            $rows[] = ['inscripcion_id' => $inscripcion->id, 'carrera_id' => $carreras[$segunda], 'prioridad' => 2, 'created_at' => $now, 'updated_at' => $now];
            $index++;
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('opciones_carrera')->upsert($chunk, ['inscripcion_id', 'prioridad'], ['carrera_id', 'updated_at']);
        }
    }

    private function documentos(array $inscripciones, int $adminId, $now): void
    {
        $rows = [];
        $tipos = ['ci', 'libreta', 'titulo_bachiller', 'fotografia'];

        foreach ($inscripciones as $inscripcion) {
            foreach ($tipos as $tipo) {
                $rows[] = [
                    'inscripcion_id' => $inscripcion->id,
                    'tipo' => $tipo,
                    'numero' => $inscripcion->codigo . '-' . strtoupper($tipo),
                    'archivo_path' => 'sinteticos/documentos/' . $inscripcion->codigo . '-' . $tipo . '.pdf',
                    'estado' => 'aprobado',
                    'observacion' => 'Documento sintetico aprobado.',
                    'revisado_por' => $adminId,
                    'revisado_en' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('documentos')->upsert(
                $chunk,
                ['inscripcion_id', 'tipo'],
                ['numero', 'archivo_path', 'estado', 'observacion', 'revisado_por', 'revisado_en', 'updated_at'],
            );
        }

        $validaciones = array_map(fn ($inscripcion) => [
            'inscripcion_id' => $inscripcion->id,
            'estado' => 'aprobado',
            'observacion' => 'Validacion documental sintetica aprobada.',
            'validado_por' => $adminId,
            'validado_en' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], $inscripciones);

        foreach (array_chunk($validaciones, 1000) as $chunk) {
            DB::table('validaciones_documentales')->upsert($chunk, ['inscripcion_id'], ['estado', 'observacion', 'validado_por', 'validado_en', 'updated_at']);
        }
    }

    private function pagos(array $inscripciones, int $adminId, $now): void
    {
        $pagos = [];
        foreach ($inscripciones as $inscripcion) {
            $pagos[] = [
                'inscripcion_id' => $inscripcion->id,
                'monto' => 200,
                'moneda' => 'BOB',
                'metodo' => 'sintetico',
                'referencia' => 'SYN-PAGO-' . $inscripcion->codigo,
                'estado' => 'aprobado',
                'pagado_en' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($pagos, 1000) as $chunk) {
            DB::table('pagos')->upsert($chunk, ['referencia'], ['inscripcion_id', 'monto', 'moneda', 'metodo', 'estado', 'pagado_en', 'updated_at']);
        }

        $pagoIds = DB::table('pagos')->whereIn('referencia', array_column($pagos, 'referencia'))->pluck('id', 'referencia')->all();
        $recibos = [];

        foreach ($inscripciones as $inscripcion) {
            $referencia = 'SYN-PAGO-' . $inscripcion->codigo;
            $recibos[] = [
                'pago_id' => $pagoIds[$referencia],
                'numero' => 'SYN-REC-' . $inscripcion->codigo,
                'archivo_path' => 'sinteticos/recibos/' . $inscripcion->codigo . '.pdf',
                'emitido_por' => $adminId,
                'emitido_en' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($recibos, 1000) as $chunk) {
            DB::table('recibos')->upsert($chunk, ['numero'], ['pago_id', 'archivo_path', 'emitido_por', 'emitido_en', 'updated_at']);
        }
    }

    private function asignarGrupos(array $inscripciones, array $grupos, $now): void
    {
        $grupoIds = array_values($grupos);
        $rows = [];
        $index = 0;

        foreach ($inscripciones as $inscripcion) {
            $grupoId = $grupoIds[(int) floor($index / self::CUPO_GRUPO)];
            $rows[] = [
                'inscripcion_id' => $inscripcion->id,
                'grupo_id' => $grupoId,
                'estado' => 'asignado',
                'asignado_en' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $index++;
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('inscripcion_grupo')->upsert($chunk, ['inscripcion_id'], ['grupo_id', 'estado', 'asignado_en', 'updated_at']);
        }
    }

    private function evaluaciones(array $inscripciones, array $grupos, array $materias, array $grupoMaterias, int $adminId, $now): void
    {
        $grupoIds = array_values($grupos);
        $evaluaciones = [];
        $resultados = [];
        $index = 0;

        foreach ($inscripciones as $inscripcion) {
            $numero = $index + 1;
            $grupoId = $grupoIds[(int) floor($index / self::CUPO_GRUPO)];
            $perfil = $this->perfil($numero);
            $promedios = [];

            foreach (self::MATERIAS as $materiaIndex => $materia) {
                $notas = $this->notas($numero, $materiaIndex, $perfil);
                $promedio = $this->promedio($notas);
                $estado = $this->estadoEvaluacion($notas);
                $promedios[] = $promedio;

                $evaluaciones[] = [
                    'inscripcion_id' => $inscripcion->id,
                    'grupo_materia_id' => $grupoMaterias[$grupoId . ':' . $materia['codigo']],
                    'examen_1' => $notas[0],
                    'examen_2' => $notas[1],
                    'examen_3' => $notas[2],
                    'promedio' => $promedio,
                    'estado' => $estado,
                    'observacion' => $estado === 'reprobado' ? 'Reprobado por nota menor a 51 en la materia.' : null,
                    'registrado_por' => $adminId,
                    'registrado_en' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $resultados[] = [
                'inscripcion_id' => $inscripcion->id,
                'promedio_final' => round(array_sum($promedios) / count($promedios), 2),
                'estado_final' => $perfil['resultado_estado'],
                'cerrado_en' => $perfil['resultado_estado'] === 'pendiente' ? null : $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $index++;
        }

        foreach (array_chunk($evaluaciones, 1000) as $chunk) {
            DB::table('evaluaciones')->upsert(
                $chunk,
                ['inscripcion_id', 'grupo_materia_id'],
                ['examen_1', 'examen_2', 'examen_3', 'promedio', 'estado', 'observacion', 'registrado_por', 'registrado_en', 'updated_at'],
            );
        }

        foreach (array_chunk($resultados, 1000) as $chunk) {
            DB::table('resultados_cup')->upsert($chunk, ['inscripcion_id'], ['promedio_final', 'estado_final', 'cerrado_en', 'updated_at']);
        }
    }

    /**
     * Perfiles:
     * - 0/1: reprobado en examen 1.
     * - 2: reprobado en examen 2.
     * - 3: reprobado en examen 3.
     * - 4: solo examen 1 cargado, habilitado para examen 2.
     * - 5: examen 1 y 2 cargados, habilitado para examen 3.
     * - 6-9: aprobado con tres examenes completos.
     */
    private function perfil(int $i): array
    {
        return match ($i % 10) {
            0, 1 => ['tipo' => 'reprobado_ex1', 'materia' => $i % 4, 'inscripcion_estado' => InscripcionState::FINALIZADO, 'resultado_estado' => 'reprobado'],
            2 => ['tipo' => 'reprobado_ex2', 'materia' => $i % 4, 'inscripcion_estado' => InscripcionState::FINALIZADO, 'resultado_estado' => 'reprobado'],
            3 => ['tipo' => 'reprobado_ex3', 'materia' => $i % 4, 'inscripcion_estado' => InscripcionState::FINALIZADO, 'resultado_estado' => 'reprobado'],
            4 => ['tipo' => 'habilitado_ex2', 'materia' => null, 'inscripcion_estado' => InscripcionState::EN_CURSO, 'resultado_estado' => 'pendiente'],
            5 => ['tipo' => 'habilitado_ex3', 'materia' => null, 'inscripcion_estado' => InscripcionState::EN_CURSO, 'resultado_estado' => 'pendiente'],
            default => ['tipo' => 'aprobado', 'materia' => null, 'inscripcion_estado' => InscripcionState::FINALIZADO, 'resultado_estado' => 'aprobado'],
        };
    }

    private function notas(int $i, int $materiaIndex, array $perfil): array
    {
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
    }

    private function estadoEvaluacion(array $notas): string
    {
        foreach ($notas as $nota) {
            if ($nota !== null && $nota < 51) {
                return 'reprobado';
            }
        }

        return in_array(null, $notas, true) ? 'incompleto' : 'aprobado';
    }

    private function promedio(array $notas): float
    {
        $rendidas = array_values(array_filter($notas, fn ($nota) => $nota !== null));

        return round(array_sum($rendidas) / count($rendidas), 2);
    }

    private function pick(array $items, int $index): string
    {
        return $items[$index % count($items)];
    }

    private function pad(int $number, int $size): string
    {
        return str_pad((string) $number, $size, '0', STR_PAD_LEFT);
    }

    private function slug(string $value): string
    {
        return Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '.')->trim('.')->toString();
    }
}
