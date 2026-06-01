<?php

namespace App\Support\States;

/**
 * Estados posibles de una inscripción CUP.
 *
 * Centraliza los valores para evitar hardcodear strings en controllers,
 * services, tests y frontend.
 */
final class InscripcionState
{
    public const PREPOSTULADO = 'prepostulado';
    public const DOCUMENTOS_PENDIENTES = 'documentos_pendientes';
    public const DOCUMENTOS_APROBADOS = 'documentos_aprobados';
    public const PAGADO = 'pagado';
    public const INSCRITO = 'inscrito';
    public const EN_CURSO = 'en_curso';
    public const FINALIZADO = 'finalizado';
    public const CANCELADO = 'cancelado';

    public const ALL = [
        self::PREPOSTULADO,
        self::DOCUMENTOS_PENDIENTES,
        self::DOCUMENTOS_APROBADOS,
        self::PAGADO,
        self::INSCRITO,
        self::EN_CURSO,
        self::FINALIZADO,
        self::CANCELADO,
    ];
}
