<?php

namespace App\Services\Respaldos;

use Illuminate\Contracts\Filesystem\Filesystem;

// Documentos en un solo archivo gzip: por cada uno, una línea JSON con su ruta y tamaño, y después sus bytes
class PaqueteDocumentos
{
    private const BLOQUE = 65536;

    /**
     * @param  list<string>  $rutas
     */
    public function empaquetar(Filesystem $disco, array $rutas, string $destinoGz): void
    {
        $gz = $this->abrirGz($destinoGz, 'wb6');

        try {
            foreach ($rutas as $ruta) {
                $entrada = $disco->readStream($ruta);

                if (! is_resource($entrada)) {
                    throw new RespaldoException("No se pudo leer el documento {$ruta}.");
                }

                try {
                    $this->escribir($gz, json_encode(['ruta' => $ruta, 'bytes' => $disco->size($ruta)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");

                    while (! feof($entrada)) {
                        $this->escribir($gz, (string) fread($entrada, self::BLOQUE));
                    }
                } finally {
                    fclose($entrada);
                }
            }
        } finally {
            gzclose($gz);
        }
    }

    /**
     * Extrae en $carpeta los documentos del paquete; con $solo, únicamente esas rutas.
     *
     * @param  array<string, true>|null  $solo
     * @return int documentos extraídos
     */
    public function extraer(string $origenGz, string $carpeta, ?array $solo = null): int
    {
        $gz = $this->abrirGz($origenGz, 'rb');
        $extraidos = 0;

        try {
            while (($linea = gzgets($gz)) !== false && $linea !== '') {
                $cabecera = json_decode($linea, true);

                if (! is_array($cabecera) || ! is_string($cabecera['ruta'] ?? null) || ! is_int($cabecera['bytes'] ?? null) || $cabecera['bytes'] < 0) {
                    throw new RespaldoException('El paquete de documentos está dañado.');
                }

                $ruta = $this->rutaSegura($cabecera['ruta']);
                $guardar = $solo === null || isset($solo[$ruta]);
                $salida = $guardar ? $this->abrirDestino($carpeta, $ruta) : null;

                try {
                    $faltan = $cabecera['bytes'];

                    while ($faltan > 0) {
                        $datos = gzread($gz, min(self::BLOQUE, $faltan));

                        if ($datos === false || $datos === '') {
                            throw new RespaldoException("El paquete de documentos está truncado en {$ruta}.");
                        }

                        if ($salida !== null && fwrite($salida, $datos) !== strlen($datos)) {
                            throw new RespaldoException("No se pudo escribir el documento {$ruta}; revisa el espacio en disco.");
                        }

                        $faltan -= strlen($datos);
                    }
                } finally {
                    if ($salida !== null) {
                        fclose($salida);
                    }
                }

                $extraidos += $guardar ? 1 : 0;
            }
        } finally {
            gzclose($gz);
        }

        return $extraidos;
    }

    // Un paquete alterado no puede escribir fuera de la carpeta de destino
    private function rutaSegura(string $ruta): string
    {
        $partes = explode('/', str_replace('\\', '/', $ruta));

        if ($ruta === '' || str_starts_with($ruta, '/') || in_array('..', $partes, true) || in_array('', $partes, true) || str_contains($ruta, "\0")) {
            throw new RespaldoException('El paquete de documentos tiene una ruta no válida.');
        }

        return implode('/', $partes);
    }

    /**
     * @return resource
     */
    private function abrirDestino(string $carpeta, string $ruta)
    {
        $destino = rtrim($carpeta, '/').'/'.$ruta;
        $directorio = dirname($destino);

        if (! is_dir($directorio) && ! @mkdir($directorio, 0750, true) && ! is_dir($directorio)) {
            throw new RespaldoException("No se pudo crear la carpeta para {$ruta}.");
        }

        $salida = @fopen($destino, 'wb');

        if ($salida === false) {
            throw new RespaldoException("No se pudo escribir el documento {$ruta}.");
        }

        return $salida;
    }

    /**
     * @return resource
     */
    private function abrirGz(string $archivo, string $modo)
    {
        $gz = @gzopen($archivo, $modo);

        if ($gz === false) {
            throw new RespaldoException('No se pudo abrir el paquete de documentos.');
        }

        return $gz;
    }

    /**
     * @param  resource  $gz
     */
    private function escribir($gz, string $datos): void
    {
        if ($datos !== '' && gzwrite($gz, $datos) === false) {
            throw new RespaldoException('Falló la compresión de los documentos; revisa el espacio en disco.');
        }
    }
}
