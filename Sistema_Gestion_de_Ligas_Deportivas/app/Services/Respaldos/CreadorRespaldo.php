<?php

namespace App\Services\Respaldos;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

// Respaldo completo (semanal) o diario: la base siempre completa y comprimida; del disco local, todo o solo lo que cambió
class CreadorRespaldo
{
    public function __construct(
        private readonly AlmacenRespaldos $almacen,
        private readonly CifradoRespaldo $cifrado,
        private readonly ClienteMariaDb $mariadb,
        private readonly PaqueteDocumentos $paquete,
    ) {
    }

    /**
     * @return array{nombre: string, tipo: string, depende_de: string|null, documentos: int, aviso: string|null}
     */
    public function crear(bool $completo): array
    {
        $trabajo = new DirectorioTrabajo();
        $nombre = null;
        $subiendo = false;

        try {
            $aviso = null;
            $dependeDe = $completo ? null : $this->ultimoCompleto();

            // Un diario necesita un completo del cual partir
            if (! $completo && $dependeDe === null) {
                $completo = true;
                $aviso = 'No había ningún respaldo completo: se creó uno completo en lugar del diario.';
            }

            $tipo = $completo ? 'completo' : 'diario';
            $nombre = $this->almacen->nombreNuevo($tipo);

            if ($this->almacen->disco()->exists($nombre)) {
                throw new RespaldoException("Ya existe el respaldo {$nombre}.");
            }

            $this->mariadb->volcar($trabajo->ruta('base.sql.gz'));

            $local = Storage::disk('local');
            $documentos = $this->huellas($local);
            $anteriores = $dependeDe === null ? [] : (array) ($this->almacen->manifiesto($dependeDe, $trabajo)['documentos'] ?? []);

            // El diario lleva solo lo creado o modificado desde el completo del que depende
            foreach ($documentos as $ruta => $huella) {
                $documentos[$ruta]['incluido'] = $completo || ($anteriores[$ruta]['sha256'] ?? null) !== $huella['sha256'];
            }

            $incluidos = array_keys(array_filter($documentos, fn (array $huella): bool => $huella['incluido']));
            $this->paquete->empaquetar($local, array_map('strval', $incluidos), $trabajo->ruta('documentos.gz'));

            $partes = [];

            foreach ([AlmacenRespaldos::BASE => 'base.sql.gz', AlmacenRespaldos::DOCUMENTOS => 'documentos.gz'] as $parte => $archivo) {
                $this->cifrado->cifrar($trabajo->ruta($archivo), $trabajo->ruta($parte));
                $partes[$parte] = ['sha256' => hash_file('sha256', $trabajo->ruta($parte)), 'bytes' => filesize($trabajo->ruta($parte))];
            }

            $manifiesto = [
                'version' => 1,
                'nombre' => $nombre,
                'tipo' => $tipo,
                'fecha' => $this->almacen->fecha($nombre)->toIso8601String(),
                'depende_de' => $dependeDe,
                'base_de_datos' => $this->mariadb->baseDeOperacion(),
                'partes' => $partes,
                'documentos' => $documentos,
            ];
            file_put_contents($trabajo->ruta('manifiesto.json'), json_encode($manifiesto, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $this->cifrado->cifrar($trabajo->ruta('manifiesto.json'), $trabajo->ruta(AlmacenRespaldos::MANIFIESTO));

            // El manifiesto va al final: si algo falla antes, el respaldo no se considera terminado y se borra
            $subiendo = true;

            foreach ([AlmacenRespaldos::BASE, AlmacenRespaldos::DOCUMENTOS, AlmacenRespaldos::MANIFIESTO] as $parte) {
                $this->almacen->subir($nombre, $parte, $trabajo->ruta($parte));
            }

            return ['nombre' => $nombre, 'tipo' => $tipo, 'depende_de' => $dependeDe, 'documentos' => count($incluidos), 'aviso' => $aviso];
        } catch (\Throwable $error) {
            if ($subiendo && $nombre !== null) {
                $this->borrarSinFallar($nombre);
            }

            throw $error;
        } finally {
            $trabajo->limpiar();
        }
    }

    private function ultimoCompleto(): ?string
    {
        $completos = array_filter($this->almacen->listar(), fn (string $nombre): bool => $this->almacen->tipo($nombre) === 'completo');

        return $completos === [] ? null : end($completos);
    }

    /**
     * SHA-256 y tamaño de cada documento del disco local.
     *
     * @return array<string, array{sha256: string, bytes: int, incluido?: bool}>
     */
    private function huellas(Filesystem $disco): array
    {
        $huellas = [];

        foreach ($disco->allFiles() as $ruta) {
            // Sin archivos ocultos como .gitignore
            if (str_starts_with(basename($ruta), '.')) {
                continue;
            }

            $entrada = $disco->readStream($ruta);

            if (! is_resource($entrada)) {
                throw new RespaldoException("No se pudo leer el documento {$ruta}.");
            }

            $contexto = hash_init('sha256');
            hash_update_stream($contexto, $entrada);
            fclose($entrada);

            $huellas[$ruta] = ['sha256' => hash_final($contexto), 'bytes' => $disco->size($ruta)];
        }

        ksort($huellas);

        return $huellas;
    }

    private function borrarSinFallar(string $nombre): void
    {
        try {
            $this->almacen->borrar($nombre);
        } catch (\Throwable) {
            // respaldo:depurar borra después las carpetas sin manifiesto
        }
    }
}
