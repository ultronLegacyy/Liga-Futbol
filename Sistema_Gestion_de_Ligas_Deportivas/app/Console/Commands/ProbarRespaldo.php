<?php

namespace App\Console\Commands;

use App\Services\Respaldos\ClienteMariaDb;
use App\Services\Respaldos\RespaldoException;
use App\Services\Respaldos\RestauradorRespaldo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

// Prueba mensual: restaura el último respaldo aparte y comprueba RPO ≤ 24 h y RTO ≤ 4 h (HU-009, criterio 4)
class ProbarRespaldo extends Command
{
    protected $signature = 'respaldo:probar';

    protected $description = 'Restaura el último respaldo en una base aparte, mide el RPO y el RTO, revisa las tablas principales y lo borra al final';

    public function handle(RestauradorRespaldo $restaurador, ClienteMariaDb $mariadb): int
    {
        $base = $restaurador->baseDestino(false);
        $inicio = hrtime(true);

        try {
            $resultado = $restaurador->restaurar(null, false);
            $rtoHoras = (hrtime(true) - $inicio) / 1e9 / 3600;
            $rpoHoras = abs($resultado['fecha']->diffInSeconds(now())) / 3600;
            $filas = $mariadb->contarFilas($resultado['base'], array_values((array) config('respaldos.prueba.tablas')));
        } catch (\Throwable $error) {
            $mensaje = $error instanceof RespaldoException ? $error->getMessage() : 'Error inesperado: '.RespaldoException::sinSecretos($error->getMessage());
            $this->error('NO CUMPLE: la restauración de prueba falló. '.$mensaje);
            Log::error('Prueba de restauración fallida', ['motivo' => $mensaje]);

            return self::FAILURE;
        } finally {
            $this->limpiar($mariadb, $base);
        }

        $rpoMaximo = (float) config('respaldos.prueba.rpo_horas');
        $rtoMaximo = (float) config('respaldos.prueba.rto_horas');
        $vacias = array_keys(array_filter($filas, fn (int $total): bool => $total === 0));

        $this->line("Respaldo probado: {$resultado['nombre']} ({$resultado['documentos']} documentos).");
        $this->table(['Medida', 'Valor', 'Límite', 'Resultado'], [
            ['RPO (antigüedad del respaldo)', $this->horas($rpoHoras), '≤ '.$rpoMaximo.' h', $rpoHoras <= $rpoMaximo ? 'Cumple' : 'No cumple'],
            ['RTO (tiempo de restauración)', $this->horas($rtoHoras), '≤ '.$rtoMaximo.' h', $rtoHoras <= $rtoMaximo ? 'Cumple' : 'No cumple'],
        ]);
        $this->table(['Tabla', 'Filas'], array_map(fn (string $tabla, int $total): array => [$tabla, $total], array_keys($filas), $filas));

        $fallas = array_filter([
            $rpoHoras > $rpoMaximo ? "el respaldo tiene {$this->horas($rpoHoras)} (RPO > {$rpoMaximo} h)" : null,
            $rtoHoras > $rtoMaximo ? "la restauración tardó {$this->horas($rtoHoras)} (RTO > {$rtoMaximo} h)" : null,
            $vacias !== [] ? 'tablas sin filas: '.implode(', ', $vacias) : null,
        ]);

        $contexto = ['respaldo' => $resultado['nombre'], 'rpo_horas' => round($rpoHoras, 2), 'rto_horas' => round($rtoHoras, 4), 'filas' => $filas];

        if ($fallas !== []) {
            $this->error('NO CUMPLE: '.implode('; ', $fallas).'.');
            Log::warning('Prueba de restauración: no cumple', $contexto + ['fallas' => array_values($fallas)]);

            return self::FAILURE;
        }

        $this->info("CUMPLE: RPO ≤ {$rpoMaximo} h y RTO ≤ {$rtoMaximo} h, con datos en las tablas principales.");
        Log::info('Prueba de restauración: cumple', $contexto);

        return self::SUCCESS;
    }

    // Lo restaurado se borra siempre al final
    private function limpiar(ClienteMariaDb $mariadb, string $base): void
    {
        try {
            $mariadb->eliminarBase($base);
        } catch (\Throwable $error) {
            $this->warn('No se pudo borrar la base de prueba «'.$base.'»: '.RespaldoException::sinSecretos($error->getMessage()));
        }

        File::deleteDirectory((string) config('respaldos.restauracion.carpeta'));
    }

    private function horas(float $horas): string
    {
        return match (true) {
            $horas < 1 / 60 => round($horas * 3600, 1).' s',
            $horas < 1 => round($horas * 60, 1).' min',
            default => round($horas, 2).' h',
        };
    }
}
