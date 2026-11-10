<?php

namespace App\Services\Respaldos;

// Cifrado por bloques con Sodium secretstream (XChaCha20-Poly1305), sin cargar el archivo completo en memoria (D-036)
class CifradoRespaldo
{
    // Identifica el formato del archivo cifrado
    private const FIRMA = "SGLDRESP1\n";

    private const BLOQUE = 65536;

    public function cifrar(string $origen, string $destino): void
    {
        $clave = $this->clave();
        [$entrada, $salida] = $this->abrir($origen, $destino);

        try {
            [$estado, $cabecera] = sodium_crypto_secretstream_xchacha20poly1305_init_push($clave);
            $this->escribir($salida, self::FIRMA.$cabecera);

            $actual = $this->leer($entrada, self::BLOQUE);

            // Se lee un bloque por adelantado para marcar el último con TAG_FINAL
            do {
                $siguiente = $this->leer($entrada, self::BLOQUE);
                $final = $siguiente === '';
                $etiqueta = $final ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;

                $this->escribir($salida, sodium_crypto_secretstream_xchacha20poly1305_push($estado, $actual, '', $etiqueta));
                $actual = $siguiente;
            } while (! $final);
        } catch (\Throwable $error) {
            $this->cerrar($entrada, $salida);
            @unlink($destino);

            throw $error;
        } finally {
            sodium_memzero($clave);
        }

        $this->cerrar($entrada, $salida);
    }

    /**
     * Rechaza un archivo alterado, truncado (sin la etiqueta final) o cifrado con otra clave.
     */
    public function descifrar(string $origen, string $destino): void
    {
        $clave = $this->clave();
        [$entrada, $salida] = $this->abrir($origen, $destino);

        try {
            $firma = $this->leer($entrada, strlen(self::FIRMA));
            $cabecera = $this->leer($entrada, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);

            if ($firma !== self::FIRMA || strlen($cabecera) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) {
                throw new RespaldoException('El archivo no es un respaldo cifrado del sistema o está truncado.');
            }

            $estado = sodium_crypto_secretstream_xchacha20poly1305_init_pull($cabecera, $clave);
            $terminado = false;

            while (($cifrado = $this->leer($entrada, self::BLOQUE + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES)) !== '') {
                if ($terminado) {
                    throw new RespaldoException('El respaldo tiene datos después del final: está alterado.');
                }

                $resultado = sodium_crypto_secretstream_xchacha20poly1305_pull($estado, $cifrado);

                if ($resultado === false) {
                    throw new RespaldoException('No se pudo descifrar el respaldo: está alterado o se cifró con otra clave.');
                }

                [$bloque, $etiqueta] = $resultado;
                $this->escribir($salida, $bloque);
                $terminado = $etiqueta === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
            }

            if (! $terminado) {
                throw new RespaldoException('El respaldo está truncado: le falta el bloque final.');
            }
        } catch (\Throwable $error) {
            $this->cerrar($entrada, $salida);
            // No se deja un archivo descifrado a medias
            @unlink($destino);

            throw $error;
        } finally {
            sodium_memzero($clave);
        }

        $this->cerrar($entrada, $salida);
    }

    // La clave nunca aparece en el mensaje de error
    private function clave(): string
    {
        $base64 = config('respaldos.clave');

        if (! is_string($base64) || trim($base64) === '') {
            throw new RespaldoException('Falta la clave de cifrado de los respaldos: define RESPALDO_CLAVE (32 bytes en base64).');
        }

        $clave = base64_decode(trim($base64), true);

        if ($clave === false || strlen($clave) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RespaldoException('La clave RESPALDO_CLAVE no es válida: debe ser base64 de exactamente 32 bytes.');
        }

        return $clave;
    }

    /**
     * @return array{resource, resource}
     */
    private function abrir(string $origen, string $destino): array
    {
        $entrada = @fopen($origen, 'rb');

        if ($entrada === false) {
            throw new RespaldoException('No se pudo leer el archivo '.basename($origen).'.');
        }

        $salida = @fopen($destino, 'wb');

        if ($salida === false) {
            fclose($entrada);

            throw new RespaldoException('No se pudo escribir el archivo '.basename($destino).'.');
        }

        return [$entrada, $salida];
    }

    /**
     * @param  resource  $archivo
     */
    private function leer($archivo, int $bytes): string
    {
        $datos = '';

        // fread puede devolver menos bytes de los pedidos aunque no haya terminado el archivo
        while (strlen($datos) < $bytes && ! feof($archivo)) {
            $parte = fread($archivo, $bytes - strlen($datos));

            if ($parte === false) {
                throw new RespaldoException('Falló la lectura de un archivo del respaldo.');
            }

            $datos .= $parte;
        }

        return $datos;
    }

    /**
     * @param  resource  $archivo
     */
    private function escribir($archivo, string $datos): void
    {
        if (fwrite($archivo, $datos) !== strlen($datos)) {
            throw new RespaldoException('Falló la escritura de un archivo del respaldo; revisa el espacio en disco.');
        }
    }

    /**
     * @param  resource  $entrada
     * @param  resource  $salida
     */
    private function cerrar($entrada, $salida): void
    {
        if (is_resource($entrada)) {
            fclose($entrada);
        }

        if (is_resource($salida)) {
            fclose($salida);
        }
    }
}
