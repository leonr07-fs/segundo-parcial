<?php

namespace App\Support;

final class FicctAulas
{
    public const AUDITORIO = 'AULA-00';

    public const TEORICAS = [
        'AULA-11', 'AULA-12', 'AULA-13', 'AULA-14', 'AULA-15', 'AULA-16',
        'AULA-21', 'AULA-22', 'AULA-23', 'AULA-24', 'AULA-25', 'AULA-26',
        'AULA-31', 'AULA-32', 'AULA-33', 'AULA-34', 'AULA-35', 'AULA-36',
    ];

    public const LABORATORIOS_COMPUTACION = [
        'AULA-41', 'AULA-42', 'AULA-43', 'AULA-44', 'AULA-45', 'AULA-46',
    ];

    public const TODAS = [
        self::AUDITORIO,
        ...self::TEORICAS,
        ...self::LABORATORIOS_COMPUTACION,
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function catalogo(): array
    {
        return [
            ['codigo' => 'AULA-00', 'nombre' => 'Aula 00 - Auditorio', 'capacidad' => 180, 'ubicacion' => 'Modulo 236 - Cuarto piso - Auditorio'],
            ...self::aulasPorPiso(1, 6, 70, 'Primer piso - Aula teorica'),
            ...self::aulasPorPiso(2, 6, 70, 'Segundo piso - Aula teorica'),
            ...self::aulasPorPiso(3, 6, 70, 'Tercer piso - Aula teorica'),
            ...self::aulasPorPiso(4, 6, 35, 'Cuarto piso - Laboratorio de computacion'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function codigosParaMateria(string $materia): array
    {
        return self::esComputacion($materia)
            ? self::LABORATORIOS_COMPUTACION
            : [...self::TEORICAS, self::AUDITORIO];
    }

    public static function esAulaFicct(string $codigo): bool
    {
        return in_array($codigo, self::TODAS, true);
    }

    public static function esComputacion(string $materia): bool
    {
        $normalizada = strtolower($materia);
        $normalizada = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $normalizada);

        return str_contains($normalizada, 'computacion') || strtoupper($materia) === 'COM';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function aulasPorPiso(int $piso, int $cantidad, int $capacidad, string $ubicacion): array
    {
        $aulas = [];

        for ($numero = 1; $numero <= $cantidad; $numero++) {
            $codigoSimple = "{$piso}{$numero}";
            $codigo = "AULA-{$codigoSimple}";
            $nombre = $piso === 4 ? "Aula/Lab {$codigoSimple}" : "Aula {$codigoSimple}";

            $aulas[] = [
                'codigo' => $codigo,
                'nombre' => $nombre,
                'capacidad' => $capacidad,
                'ubicacion' => "Modulo 236 - {$ubicacion}",
            ];
        }

        return $aulas;
    }
}
