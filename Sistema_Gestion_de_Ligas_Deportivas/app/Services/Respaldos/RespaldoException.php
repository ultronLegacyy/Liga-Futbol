<?php

namespace App\Services\Respaldos;

use RuntimeException;

// Error de un respaldo con un mensaje en español que no lleva secretos (HU-009)
class RespaldoException extends RuntimeException
{
    /**
     * Quita del texto la clave de cifrado y las contraseñas configuradas, por si un programa externo las repite.
     */
    public static function sinSecretos(string $texto): string
    {
        $secretos = array_filter([
            config('respaldos.clave'),
            config('database.connections.'.config('database.default').'.password'),
            config('database.administracion.password'),
            config('filesystems.disks.r2.secret'),
            config('filesystems.disks.r2.key'),
        ], fn ($secreto): bool => is_string($secreto) && $secreto !== '');

        return trim(str_replace($secretos, '[oculto]', $texto));
    }
}
