<?php

namespace App\Support\States;

/**
 * Estados posibles de una gestión académica CUP.
 *
 * 'inscripcion' es el estado que habilita el registro de postulaciones.
 */
final class GestionState
{
    public const PLANIFICADA = 'planificada';
    public const INSCRIPCION = 'inscripcion';
    public const EN_CURSO = 'en_curso';
    public const CERRADA = 'cerrada';
    public const FINALIZADA = 'finalizada';

    public const ALL = [
        self::PLANIFICADA,
        self::INSCRIPCION,
        self::EN_CURSO,
        self::CERRADA,
        self::FINALIZADA,
    ];
}
