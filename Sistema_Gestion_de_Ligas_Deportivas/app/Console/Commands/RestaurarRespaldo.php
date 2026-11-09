<?php

namespace App\Console\Commands;

use App\Services\Respaldos\RespaldoException;
use App\Services\Respaldos\RestauradorRespaldo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

// Restaura un respaldo; por defecto en una base y una carpeta aparte, nunca sobre las de operación (HU-009)
class RestaurarRespaldo extends Command
{
    protected $signature = 'respaldo:restaurar
        {respaldo? : Nombre del respaldo (por ejemplo diario-20261109-020000); si se omite, el más reciente}
        {--operacion : Sobrescribe la base y los documentos de operación (pide confirmación)}';

    protected $description = 'Restaura un respaldo y el completo del que depende, en una base y una carpeta aparte salvo con --operacion';

    public function handle(RestauradorRespaldo $restaurador): int
    {
        $operacion = (bool) $this->option('operacion');
        $nombre = $this->argument('respaldo');
        $nombre = is_string($nombre) && $nombre !== '' ? $nombre : null;

        try {
            if ($operacion) {
                $nombre ??= $restaurador->ultimo();
                $base = $restaurador->baseDestino(true);
                $this->warn("Esto reemplaza la base «{$base}» y los documentos de operación con el respaldo {$nombre}.");

                // Sin terminal interactiva la respuesta es «no»
                if (! $this->confirm('¿Seguro que quieres sobrescribir los datos de operación?', false)) {
                    $this->info('No se restauró nada.');

                    return self::FAILURE;
                }

                Log::warning('Restauración sobre la base y los documentos de operación', ['respaldo' => $nombre, 'base' => $base]);
            }

            $resultado = $restaurador->restaurar($nombre, $operacion);
        } catch (\Throwable $error) {
            $mensaje = $error instanceof RespaldoException ? $error->getMessage() : 'Error inesperado: '.RespaldoException::sinSecretos($error->getMessage());
            $this->error('No se restauró el respaldo. '.$mensaje);
            Log::error('Restauración fallida', ['respaldo' => $nombre, 'operacion' => $operacion, 'motivo' => $mensaje]);

            return self::FAILURE;
        }

        $this->info("Respaldo {$resultado['nombre']} restaurado en la base «{$resultado['base']}» y {$resultado['documentos']} documentos en {$resultado['carpeta']}.");
        Log::info('Respaldo restaurado', ['respaldo' => $resultado['nombre'], 'base' => $resultado['base'], 'operacion' => $operacion, 'documentos' => $resultado['documentos']]);

        return self::SUCCESS;
    }
}
