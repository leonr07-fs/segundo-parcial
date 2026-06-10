<?php

namespace App\Services\GestionAcademica;

use App\Models\EvaluacionesResultados\Evaluacion;
use App\Models\GestionAcademica\GrupoMateria;
use App\Models\InscripcionPagos\Inscripcion;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * [CU17] Consultar información académica del postulante
 * Vinculación UML: Servicio de consolidación de resultados de exámenes y horarios vigentes para el portal de autoatención.
 */

/**
 * CU17 - Consultar información académica del postulante
 *
 * Participantes del CU17 (Diagrama de Secuencia):
 * - Control: DashboardController
 * - Control: CupExamenService (Actual)
 * - Entity: Inscripcion
 */
class CupExamenService
{
    private const MATERIAS_CUP = [
        'Matematica',
        'Computacion',
        'Ingles',
        'Fisica',
    ];

    private const EXAMENES = [
        1 => 'Primer examen CUP',
        2 => 'Segundo examen CUP',
        3 => 'Tercer examen CUP',
    ];

    public function resumen(?Inscripcion $inscripcion): array
    {
        if ($inscripcion === null) {
            return [
                'examen_cup' => $this->sinInscripcion(),
                'materias_cup' => $this->materiasBase(),
            ];
        }

        $evaluaciones = $this->evaluacionesCup($inscripcion->evaluaciones ?? collect());
        $grupoMaterias = $this->grupoMateriasCup($inscripcion);
        $materias = collect(self::MATERIAS_CUP)
            ->map(fn (string $materia) => $this->materiaCup(
                $materia,
                $evaluaciones->get($this->normalizar($materia)),
                $grupoMaterias->get($this->normalizar($materia)),
            ))
            ->values();

        $reprobada = $materias->first(fn (array $materia) => $materia['habilitacion'] === 'no_habilitado');

        if ($reprobada !== null) {
            return [
                'examen_cup' => [
                    'estado' => 'no_habilitado',
                    'siguiente_examen' => null,
                    'motivo' => "Reprobo {$reprobada['materia']} en el {$reprobada['examen_reprobado']}.",
                ],
                'materias_cup' => $materias->all(),
            ];
        }

        $siguienteNumero = $this->siguienteExamen($materias);

        return [
            'examen_cup' => [
                'estado' => $siguienteNumero === null ? 'finalizado' : 'habilitado',
                'siguiente_examen' => $siguienteNumero === null ? null : [
                    'numero' => $siguienteNumero,
                    'nombre' => self::EXAMENES[$siguienteNumero],
                    'fecha' => null,
                    'hora' => null,
                    'aula' => $this->aulaPrincipal($materias),
                    'estado_programacion' => 'pendiente',
                    'mensaje' => 'La fecha y hora seran publicadas por administracion.',
                ],
                'motivo' => $siguienteNumero === null
                    ? 'Completo los tres examenes CUP.'
                    : 'Habilitado para rendir el siguiente examen CUP.',
            ],
            'materias_cup' => $materias->all(),
        ];
    }

    private function evaluacionesCup(Collection $evaluaciones): Collection
    {
        return $evaluaciones
            ->filter(fn (Evaluacion $evaluacion) => $this->esMateriaCup($evaluacion->grupoMateria?->materia?->nombre))
            ->keyBy(fn (Evaluacion $evaluacion) => $this->normalizar($evaluacion->grupoMateria?->materia?->nombre ?? ''));
    }

    private function grupoMateriasCup(Inscripcion $inscripcion): Collection
    {
        $grupo = $inscripcion->grupos->first();

        if ($grupo === null) {
            return collect();
        }

        return GrupoMateria::query()
            ->with(['materia', 'horarios.aula'])
            ->where('grupo_id', $grupo->id)
            ->get()
            ->filter(fn (GrupoMateria $grupoMateria) => $this->esMateriaCup($grupoMateria->materia?->nombre))
            ->keyBy(fn (GrupoMateria $grupoMateria) => $this->normalizar($grupoMateria->materia?->nombre ?? ''));
    }

    private function materiaCup(string $materia, ?Evaluacion $evaluacion, ?GrupoMateria $grupoMateria = null): array
    {
        $grupoMateria = $evaluacion?->grupoMateria ?? $grupoMateria;
        $notas = [
            1 => $evaluacion?->examen_1 === null ? null : (float) $evaluacion->examen_1,
            2 => $evaluacion?->examen_2 === null ? null : (float) $evaluacion->examen_2,
            3 => $evaluacion?->examen_3 === null ? null : (float) $evaluacion->examen_3,
        ];
        $examenReprobado = $this->examenReprobado($notas, $evaluacion?->estado);
        $horarios = $grupoMateria?->horarios
            ? $grupoMateria->horarios->map(fn ($horario) => [
                'dia' => $this->diaSemana((int) $horario->dia_semana),
                'hora_inicio' => substr((string) $horario->hora_inicio, 0, 5),
                'hora_fin' => substr((string) $horario->hora_fin, 0, 5),
                'aula' => $horario->aula?->codigo ?? $horario->aula?->nombre,
                'modalidad' => $horario->modalidad,
            ])->values()->all()
            : [];

        return [
            'materia' => $materia,
            'preguntas' => 10,
            'examen_1' => $notas[1],
            'examen_2' => $notas[2],
            'examen_3' => $notas[3],
            'promedio' => $evaluacion?->promedio === null ? null : (float) $evaluacion->promedio,
            'estado' => $evaluacion?->estado ?? 'pendiente',
            'habilitacion' => $examenReprobado === null ? 'habilitado' : 'no_habilitado',
            'examen_reprobado' => $examenReprobado,
            'horarios' => $horarios,
        ];
    }

    private function examenReprobado(array $notas, ?string $estado): ?string
    {
        if ($estado === 'reprobado') {
            return 'promedio final';
        }

        return null;
    }

    private function siguienteExamen(Collection $materias): ?int
    {
        $ultimoRendido = 0;

        foreach ([1, 2, 3] as $numero) {
            $columna = "examen_{$numero}";
            if ($materias->contains(fn (array $materia) => $materia[$columna] !== null)) {
                $ultimoRendido = $numero;
            }
        }

        return $ultimoRendido >= 3 ? null : $ultimoRendido + 1;
    }

    private function aulaPrincipal(Collection $materias): ?string
    {
        foreach ($materias as $materia) {
            foreach ($materia['horarios'] as $horario) {
                if (! empty($horario['aula'])) {
                    return $horario['aula'];
                }
            }
        }

        return null;
    }

    private function materiasBase(): array
    {
        return collect(self::MATERIAS_CUP)
            ->map(fn (string $materia) => [
                'materia' => $materia,
                'preguntas' => 10,
                'examen_1' => null,
                'examen_2' => null,
                'examen_3' => null,
                'promedio' => null,
                'estado' => 'pendiente',
                'habilitacion' => 'pendiente',
                'examen_reprobado' => null,
                'horarios' => [],
            ])
            ->all();
    }

    private function sinInscripcion(): array
    {
        return [
            'estado' => 'pendiente',
            'siguiente_examen' => null,
            'motivo' => 'Aun no existe una inscripcion CUP vigente.',
        ];
    }

    private function esMateriaCup(?string $nombre): bool
    {
        return in_array($this->normalizar($nombre ?? ''), array_map(fn (string $materia) => $this->normalizar($materia), self::MATERIAS_CUP), true);
    }

    private function normalizar(string $valor): string
    {
        return Str::of($valor)->ascii()->lower()->replace(' ', '')->toString();
    }

    private function ordinalExamen(int $numero): string
    {
        return match ($numero) {
            1 => 'primer examen',
            2 => 'segundo examen',
            default => 'tercer examen',
        };
    }

    private function diaSemana(int $dia): string
    {
        return match ($dia) {
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miercoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sabado',
            default => 'Domingo',
        };
    }
}
