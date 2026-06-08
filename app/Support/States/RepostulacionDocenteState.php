<?php

namespace App\Support\States;

final class RepostulacionDocenteState
{
    public const PENDIENTE = 'pendiente';
    public const APROBADA = 'aprobada';
    public const RECHAZADA = 'rechazada';

    public const ALL = [
        self::PENDIENTE,
        self::APROBADA,
        self::RECHAZADA,
    ];
}
