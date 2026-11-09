<?php

namespace App\Console\Commands;

use App\Services\Respaldos\DepuradorRespaldos;
use App\Services\Respaldos\RespaldoException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

// Borra los respaldos de más de 30 días sin dejar un diario sin su completo (HU-009, criterio 3)
class DepurarRespaldos extends Command
{
    protected $signature = 'respaldo:depurar';

    protected $description = 'Borra los respaldos de más de 30 días; conserva el último completo y los completos de los diarios vigentes';

    public function handle(DepuradorRespaldos $depurador): int
    {
        try {
            $resultado = $depurador->depurar();
        } catch (\Throwable $error) {
            $mensaje = $error instanceof RespaldoException ? $error->getMessage() : 'Error inesperado: '.RespaldoException::sinSecretos($error->getMessage());
            $this->error('No se depuraron los respaldos. '.$mensaje);
            Log::error('Depuración de respaldos fallida', ['motivo' => $mensaje]);

            return self::FAILURE;
        }

        foreach ($resultado['borrados'] as $nombre) {
            $this->line("Borrado: {$nombre}");
        }

        $this->info(count($resultado['borrados']).' respaldos borrados y '.count($resultado['conservados']).' conservados.');
        Log::info('Respaldos depurados', $resultado);

        return self::SUCCESS;
    }
}
