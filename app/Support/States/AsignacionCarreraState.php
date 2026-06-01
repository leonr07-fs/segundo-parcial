<?php

namespace App\Support\States;

final class AsignacionCarreraState
{
    public const ASIGNADO = 'asignado';
    public const SIN_CUPO = 'sin_cupo';

    public const ALL = [
        self::ASIGNADO,
        self::SIN_CUPO,
    ];
}
