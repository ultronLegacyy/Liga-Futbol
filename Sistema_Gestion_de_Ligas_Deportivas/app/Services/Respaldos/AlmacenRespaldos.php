<?php

namespace App\Services\Respaldos;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

// Respaldos en el disco configurado: una carpeta por respaldo con sus partes cifradas y el manifiesto
class AlmacenRespaldos
{
    // Se sube al final: un respaldo sin manifiesto quedó a medias y no se usa
    public const MANIFIESTO = 'manifiesto.json.cifrado';

    public const BASE = 'base.sql.gz.cifrado';

    public const DOCUMENTOS = 'documentos.gz.cifrado';

    private const NOMBRE = '/^(completo|diario)-(\d{8}-\d{6})$/';

    public function __construct(private readonly CifradoRespaldo $cifrado)
    {
    }

    public function nombreDisco(): string
    {
        return (string) config('respaldos.disco');
    }

    public function disco(): Filesystem
    {
        return Storage::disk($this->nombreDisco());
    }

    public function nombreNuevo(string $tipo): string
    {
        return $tipo.'-'.now(config('respaldos.zona_horaria'))->format('Ymd-His');
    }

    /**
     * Respaldos terminados (con manifiesto), del más viejo al más reciente.
     *
     * @return list<string>
     */
    public function listar(): array
    {
        return $this->ordenar(array_values(array_filter(
            $this->carpetas(),
            fn (string $nombre): bool => $this->disco()->exists($nombre.'/'.self::MANIFIESTO)
        )));
    }

    /**
     * Carpetas de respaldos que no llegaron a subir su manifiesto.
     *
     * @return list<string>
     */
    public function incompletos(): array
    {
        return array_values(array_diff($this->carpetas(), $this->listar()));
    }

    public function existe(string $nombre): bool
    {
        return preg_match(self::NOMBRE, $nombre) === 1 && $this->disco()->exists($nombre.'/'.self::MANIFIESTO);
    }

    public function tipo(string $nombre): string
    {
        return explode('-', $nombre, 2)[0];
    }

    public function fecha(string $nombre): CarbonImmutable
    {
        if (preg_match(self::NOMBRE, $nombre, $partes) !== 1) {
            throw new RespaldoException("«{$nombre}» no es el nombre de un respaldo.");
        }

        $fecha = CarbonImmutable::createFromFormat('Ymd-His', $partes[2], config('respaldos.zona_horaria'));

        if (! $fecha instanceof CarbonImmutable) {
            throw new RespaldoException("La fecha del respaldo «{$nombre}» no es válida.");
        }

        return $fecha;
    }

    public function subir(string $nombre, string $parte, string $archivoLocal): void
    {
        $entrada = fopen($archivoLocal, 'rb');

        if ($entrada === false) {
            throw new RespaldoException("No se pudo leer {$parte} para subirlo.");
        }

        try {
            $this->disco()->writeStream($nombre.'/'.$parte, $entrada);
        } catch (\Throwable $error) {
            throw new RespaldoException("No se pudo subir {$parte} al disco «{$this->nombreDisco()}»: ".RespaldoException::sinSecretos($error->getMessage()), 0, $error);
        } finally {
            if (is_resource($entrada)) {
                fclose($entrada);
            }
        }
    }

    public function descargar(string $nombre, string $parte, string $destino): void
    {
        $entrada = $this->disco()->readStream($nombre.'/'.$parte);
        $salida = fopen($destino, 'wb');

        if (! is_resource($entrada) || $salida === false) {
            throw new RespaldoException("No se pudo descargar {$parte} del respaldo {$nombre}.");
        }

        try {
            if (stream_copy_to_stream($entrada, $salida) === false) {
                throw new RespaldoException("Falló la descarga de {$parte} del respaldo {$nombre}.");
            }
        } finally {
            fclose($entrada);
            fclose($salida);
        }
    }

    public function borrar(string $nombre): void
    {
        $this->disco()->deleteDirectory($nombre);
    }

    /**
     * Descarga y descifra el manifiesto; el cifrado autenticado detecta si se alteró.
     *
     * @return array<string, mixed>
     */
    public function manifiesto(string $nombre, DirectorioTrabajo $trabajo): array
    {
        $cifrado = $trabajo->ruta($nombre.'-manifiesto.cifrado');
        $plano = $trabajo->ruta($nombre.'-manifiesto.json');

        $this->descargar($nombre, self::MANIFIESTO, $cifrado);
        $this->cifrado->descifrar($cifrado, $plano);
        $manifiesto = json_decode((string) file_get_contents($plano), true);
        @unlink($plano);

        if (! is_array($manifiesto) || ($manifiesto['nombre'] ?? null) !== $nombre) {
            throw new RespaldoException("El manifiesto del respaldo {$nombre} no es válido.");
        }

        return $manifiesto;
    }

    /**
     * @return list<string>
     */
    private function carpetas(): array
    {
        return array_values(array_filter(
            $this->disco()->directories(),
            fn (string $carpeta): bool => preg_match(self::NOMBRE, $carpeta) === 1
        ));
    }

    /**
     * @param  list<string>  $nombres
     * @return list<string>
     */
    private function ordenar(array $nombres): array
    {
        usort($nombres, fn (string $a, string $b): int => [$this->fecha($a), $a] <=> [$this->fecha($b), $b]);

        return $nombres;
    }
}
