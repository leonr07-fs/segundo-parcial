<?php

namespace App\Support\States;

final class DocumentoState
{
    public const PENDIENTE = 'pendiente';
    public const APROBADO = 'aprobado';
    public const OBSERVADO = 'observado';
    public const RECHAZADO = 'rechazado';

    public const ALL = [
        self::PENDIENTE,
        self::APROBADO,
        self::OBSERVADO,
        self::RECHAZADO,
    ];
}
