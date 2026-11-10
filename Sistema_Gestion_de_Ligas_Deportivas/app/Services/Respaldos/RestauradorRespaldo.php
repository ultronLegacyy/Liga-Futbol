<?php

namespace App\Services\Respaldos;

use App\Models\AuditoriaLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

// Restaura un respaldo y el completo del que depende. Por defecto en una base y una carpeta aparte
class RestauradorRespaldo
{
    public function __construct(
        private readonly AlmacenRespaldos $almacen,
        private readonly CifradoRespaldo $cifrado,
        private readonly ClienteMariaDb $mariadb,
        private readonly PaqueteDocumentos $paquete,
    ) {
    }

    // Respaldo más reciente terminado
    public function ultimo(): string
    {
        $nombres = $this->almacen->listar();

        if ($nombres === []) {
            throw new RespaldoException("No hay respaldos en el disco «{$this->almacen->nombreDisco()}».");
        }

        return end($nombres);
    }

    public function baseDestino(bool $operacion): string
    {
        return $operacion ? $this->mariadb->baseDeOperacion() : (string) config('respaldos.restauracion.base');
    }

    /**
     * @return array{nombre: string, fecha: CarbonImmutable, base: string, carpeta: string, documentos: int}
     */
    public function restaurar(?string $nombre, bool $operacion): array
    {
        $trabajo = new DirectorioTrabajo();

        try {
            $nombre ??= $this->ultimo();

            if (! $this->almacen->existe($nombre)) {
                throw new RespaldoException("No existe el respaldo «{$nombre}» o no está terminado.");
            }

            $manifiesto = $this->almacen->manifiesto($nombre, $trabajo);
            $completo = $manifiesto['tipo'] === 'diario' ? (string) $manifiesto['depende_de'] : null;

            if ($completo !== null && ! $this->almacen->existe($completo)) {
                throw new RespaldoException("Falta el respaldo completo {$completo}, del que depende {$nombre}.");
            }

            $base = $this->baseDestino($operacion);

            if (! $operacion && strcasecmp($base, $this->mariadb->baseDeOperacion()) === 0) {
                throw new RespaldoException('La base de restauración no puede ser la de operación; usa otra en RESPALDO_BASE_RESTAURACION.');
            }

            // Se verifica todo antes de tocar la base o los documentos
            $sql = $this->descomprimir($this->parte($nombre, $manifiesto, AlmacenRespaldos::BASE, $trabajo), $trabajo->ruta('base.sql'));
            $documentos = $this->parte($nombre, $manifiesto, AlmacenRespaldos::DOCUMENTOS, $trabajo);
            $documentosCompleto = $completo === null ? null
                : $this->parte($completo, $this->almacen->manifiesto($completo, $trabajo), AlmacenRespaldos::DOCUMENTOS, $trabajo);

            if (! $operacion) {
                $this->mariadb->prepararBase($base);
            }

            $this->mariadb->importar($base, $sql);

            $carpeta = $operacion ? Storage::disk('local')->path('') : (string) config('respaldos.restauracion.carpeta');

            if (! $operacion) {
                File::deleteDirectory($carpeta);
                File::ensureDirectoryExists($carpeta, 0750);
            }

            // Primero lo que viene del completo y después lo del diario; lo borrado después del completo no regresa
            $lista = (array) ($manifiesto['documentos'] ?? []);
            $delDiario = array_fill_keys(array_keys(array_filter($lista, fn ($huella): bool => (bool) ($huella['incluido'] ?? false))), true);
            $delCompleto = array_fill_keys(array_keys(array_diff_key($lista, $delDiario)), true);

            $extraidos = $documentosCompleto !== null && $delCompleto !== [] ? $this->paquete->extraer($documentosCompleto, $carpeta, $delCompleto) : 0;
            $extraidos += $this->paquete->extraer($documentos, $carpeta, $delDiario);

            // RNF-33: después de importar, para que la importación no lo borre; desde consola no hay usuario
            AuditoriaLog::create([
                'usuario_id' => null,
                'accion' => 'RESTAURACION_RESPALDO',
                'tabla_afectada' => 'sistema',
                'registro_id' => null,
                'valores_anteriores' => null,
                'valores_nuevos' => ['respaldo' => $nombre, 'base' => $base, 'documentos' => $extraidos, 'operacion' => $operacion],
                'motivo' => $operacion
                    ? 'Restauración del respaldo sobre la base de operación'
                    : 'Restauración del respaldo en la base aparte '.$base,
                'ip' => null,
            ]);

            return ['nombre' => $nombre, 'fecha' => $this->almacen->fecha($nombre), 'base' => $base, 'carpeta' => $carpeta, 'documentos' => $extraidos];
        } finally {
            $trabajo->limpiar();
        }
    }

    /**
     * Descarga una parte, comprueba su SHA-256 contra el manifiesto y la descifra.
     *
     * @param  array<string, mixed>  $manifiesto
     */
    private function parte(string $nombre, array $manifiesto, string $parte, DirectorioTrabajo $trabajo): string
    {
        $cifrado = $trabajo->ruta($nombre.'-'.$parte);
        $this->almacen->descargar($nombre, $parte, $cifrado);

        $esperado = $manifiesto['partes'][$parte]['sha256'] ?? null;

        if (! is_string($esperado) || ! hash_equals($esperado, (string) hash_file('sha256', $cifrado))) {
            throw new RespaldoException("El respaldo {$nombre} está alterado: el SHA-256 de {$parte} no coincide con el manifiesto.");
        }

        $plano = $trabajo->ruta($nombre.'-'.str_replace('.cifrado', '', $parte));
        $this->cifrado->descifrar($cifrado, $plano);
        @unlink($cifrado);

        return $plano;
    }

    private function descomprimir(string $origenGz, string $destino): string
    {
        $entrada = gzopen($origenGz, 'rb');
        $salida = fopen($destino, 'wb');

        if ($entrada === false || $salida === false) {
            throw new RespaldoException('No se pudo descomprimir el volcado de la base.');
        }

        try {
            while (! gzeof($entrada)) {
                $datos = gzread($entrada, 65536);

                if ($datos === false || fwrite($salida, $datos) !== strlen($datos)) {
                    throw new RespaldoException('Falló la descompresión del volcado de la base.');
                }
            }
        } finally {
            gzclose($entrada);
            fclose($salida);
            @unlink($origenGz);
        }

        return $destino;
    }
}
