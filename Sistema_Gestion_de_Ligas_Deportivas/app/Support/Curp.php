<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Lectura de la Clave Única de Registro de Población (CURP).
 *
 * Posiciones 5 a 10: fecha de nacimiento AAMMDD. Posición 17: diferenciador de siglo
 * asignado por RENAPO, un dígito para quien nació antes del 2000 y una letra desde el 2000.
 */
final class Curp
{
    /** Fecha de nacimiento que indica la CURP, o null si la CURP no tiene una fecha válida. */
    public static function fechaNacimiento(?string $curp): ?CarbonImmutable
    {
        $curp = strtoupper(trim((string) $curp));

        if (preg_match('/^[A-Z]{4}(\d{2})(\d{2})(\d{2})[HMX][A-Z]{5}([A-Z0-9])\d$/', $curp, $partes) !== 1) {
            return null;
        }

        [, $anio, $mes, $dia, $siglo] = $partes;
        $anioCompleto = (ctype_digit($siglo) ? 1900 : 2000) + (int) $anio;

        if (! checkdate((int) $mes, (int) $dia, $anioCompleto)) {
            return null;
        }

        return CarbonImmutable::create($anioCompleto, (int) $mes, (int) $dia)->startOfDay();
    }
}
