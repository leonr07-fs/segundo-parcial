<?php

namespace App\Support\States;

final class ValidacionDocumentalState
{
    public const PENDIENTE = 'pendiente';
    public const APROBADA = 'aprobada';
    public const OBSERVADA = 'observada';
    public const RECHAZADA = 'rechazada';

    public const ALL = [
        self::PENDIENTE,
        self::APROBADA,
        self::OBSERVADA,
        self::RECHAZADA,
    ];
}
