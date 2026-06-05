<?php

namespace App\Services\GestionAcademica;

use App\Models\Seguridad\User;

class CredentialService
{
    public function generarNumeroRegistroNumerico(): string
    {
        $anio = now()->format('y');
        $ultimo = User::query()
            ->where('numero_registro', 'like', "{$anio}%")
            ->pluck('numero_registro')
            ->filter(fn (?string $registro) => $registro !== null && ctype_digit($registro))
            ->sortDesc()
            ->first();

        if ($ultimo === null) {
            return "{$anio}4050001";
        }

        return (string) (((int) $ultimo) + 1);
    }

    public function generarPasswordTemporal(): string
    {
        $chars = [
            'upper' => 'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'lower' => 'abcdefghijkmnopqrstuvwxyz',
            'number' => '23456789',
            'symbol' => '#@$%*?',
        ];

        return $this->pick($chars['upper'])
            . $this->pick($chars['lower'])
            . $this->pick($chars['number'])
            . $this->pick($chars['symbol'])
            . $this->pick($chars['upper'] . $chars['lower'] . $chars['number'])
            . $this->pick($chars['upper'] . $chars['lower'] . $chars['number'])
            . $this->pick($chars['upper'] . $chars['lower'] . $chars['number'])
            . $this->pick($chars['upper'] . $chars['lower'] . $chars['number']);
    }

    private function pick(string $characters): string
    {
        return $characters[random_int(0, strlen($characters) - 1)];
    }

    public function generarRegistroUAGRM(int $año, int $correlativo): string
    {
        // 1. Generar los primeros 4 dígitos según el año
        $siglo = "2";
        $dosDigitosAño = substr((string) $año, -2);
        $prefijo = "{$siglo}{$dosDigitosAño}0";

        // 2. Generar el correlativo de 4 dígitos rellenado con ceros a la izquierda
        $secuencia = sprintf('%04d', $correlativo);

        // Combinar los primeros 8 dígitos
        $primeros8 = $prefijo . $secuencia;

        // 3. Calcular el Dígito Verificador (Algoritmo estándar Módulo 10 / Luhn)
        $suma = 0;
        $porDos = false;

        // Recorremos de derecha a izquierda los 8 dígitos
        for ($i = strlen($primeros8) - 1; $i >= 0; $i--) {
            $n = (int) $primeros8[$i];
            if ($porDos) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $suma += $n;
            $porDos = ! $porDos;
        }

        $digitoVerificador = (10 - ($suma % 10)) % 10;

        return "{$primeros8}{$digitoVerificador}";
    }

    public function generarRegistroEstudianteUAGRM(): string
    {
        $añoActual = (int) now()->format('Y');
        $siglo = "2";
        $dosDigitosAño = substr((string) $añoActual, -2);
        $prefijo = "{$siglo}{$dosDigitosAño}0"; // ej: 2260 para 2026

        // Buscamos el mayor correlativo para este prefijo en la base de datos
        // El numero_registro de estudiantes tiene 9 dígitos.
        $ultimoRegistro = User::query()
            ->where('numero_registro', 'like', "{$prefijo}%")
            ->whereRaw('LENGTH(numero_registro) = 9')
            ->orderByDesc('numero_registro')
            ->value('numero_registro');

        $correlativo = 1;
        if ($ultimoRegistro !== null) {
            // Los dígitos del 5 al 8 (0-indexed: del 4 al 7) son la secuencia
            $secuenciaStr = substr($ultimoRegistro, 4, 4);
            $correlativo = ((int) $secuenciaStr) + 1;
        }

        return $this->generarRegistroUAGRM($añoActual, $correlativo);
    }
}
