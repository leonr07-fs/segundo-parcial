<?php

namespace App\Support\States;

final class PagoState
{
    public const PENDIENTE = 'pendiente';
    public const APROBADO = 'aprobado';
    public const RECHAZADO = 'rechazado';
    public const ANULADO = 'anulado';

    public const ALL = [
        self::PENDIENTE,
        self::APROBADO,
        self::RECHAZADO,
        self::ANULADO,
    ];
}
