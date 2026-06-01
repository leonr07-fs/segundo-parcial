<?php

namespace App\Support;

final class TipoDocumento
{
    public const CI = 'ci';
    public const TITULO_BACHILLER = 'titulo_bachiller';
    public const CERTIFICADO_NACIMIENTO = 'certificado_nacimiento';
    public const FOTOGRAFIA = 'fotografia';

    /**
     * Documentos obligatorios para la inscripción.
     */
    public const OBLIGATORIOS = [
        self::CI,
        self::TITULO_BACHILLER,
        self::CERTIFICADO_NACIMIENTO,
        self::FOTOGRAFIA,
    ];
}
