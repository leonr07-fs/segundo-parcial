<?php

namespace App\Support\States;

final class EvaluacionState
{
    public const INCOMPLETO = 'incompleto';
    public const OBSERVADO = 'observado';
    public const APROBADO = 'aprobado';
    public const REPROBADO = 'reprobado';

    public const ALL = [
        self::INCOMPLETO,
        self::OBSERVADO,
        self::APROBADO,
        self::REPROBADO,
    ];
}
