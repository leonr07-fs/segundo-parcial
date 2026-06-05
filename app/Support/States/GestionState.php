<?php

namespace App\Support\States;

/**
 * Estados posibles de una gestión académica CUP.
 *
 * 'inscripcion' es el estado que habilita el registro de postulaciones.
 * 'inhabilitada' cierra nuevas postulaciones, pero permite asignacion,
 * horarios, evaluaciones y seguimiento academico.
 * 'cerrada' finaliza la gestion: solo queda disponible para consulta
 * administrativa y reportes historicos.
 */
final class GestionState
{
    public const PLANIFICADA = 'planificada';
    public const INSCRIPCION = 'inscripcion';
    public const INHABILITADA = 'inhabilitada';
    public const EN_CURSO = 'en_curso';
    public const CERRADA = 'cerrada';
    public const FINALIZADA = 'finalizada';

    public const ALL = [
        self::PLANIFICADA,
        self::INSCRIPCION,
        self::INHABILITADA,
        self::EN_CURSO,
        self::CERRADA,
        self::FINALIZADA,
    ];
}
