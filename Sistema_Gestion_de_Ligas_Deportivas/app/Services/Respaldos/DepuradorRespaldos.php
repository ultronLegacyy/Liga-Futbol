<?php

namespace App\Services\Respaldos;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;

// Retención de 30 días sin romper la cadena completo → diario (criterio 3)
class DepuradorRespaldos
{
    // Una carpeta sin manifiesto de más de un día quedó a medias
    private const HORAS_INCOMPLETO = 24;

    public function __construct(private readonly AlmacenRespaldos $almacen)
    {
    }

    /**
     * @return array{borrados: list<string>, conservados: list<string>, temporales: int}
     */
    public function depurar(): array
    {
        $ahora = now(config('respaldos.zona_horaria'));
        $limite = $ahora->copy()->subDays((int) config('respaldos.retencion_dias'));
        $nombres = $this->almacen->listar();
        $trabajo = new DirectorioTrabajo();

        try {
            // Completos de los que depende algún diario vigente
            $necesarios = [];

            foreach ($nombres as $nombre) {
                if ($this->almacen->tipo($nombre) === 'diario' && $this->almacen->fecha($nombre)->greaterThanOrEqualTo($limite)) {
                    $necesarios[] = (string) ($this->almacen->manifiesto($nombre, $trabajo)['depende_de'] ?? '');
                }
            }
        } finally {
            $trabajo->limpiar();
        }

        $completos = array_values(array_filter($nombres, fn (string $nombre): bool => $this->almacen->tipo($nombre) === 'completo'));
        $ultimoCompleto = $completos === [] ? null : end($completos);
        $borrados = [];

        foreach ($nombres as $nombre) {
            if ($this->almacen->fecha($nombre)->lessThan($limite) && $nombre !== $ultimoCompleto && ! in_array($nombre, $necesarios, true)) {
                $this->almacen->borrar($nombre);
                $borrados[] = $nombre;
            }
        }

        foreach ($this->almacen->incompletos() as $nombre) {
            if ($this->almacen->fecha($nombre)->lessThan($ahora->copy()->subHours(self::HORAS_INCOMPLETO))) {
                $this->almacen->borrar($nombre);
                $borrados[] = $nombre;
            }
        }

        return ['borrados' => $borrados, 'conservados' => array_values(array_diff($nombres, $borrados)), 'temporales' => $this->limpiarTrabajo()];
    }

    /**
     * Si respaldo:crear muere a la mitad (kill -9), su carpeta de trabajo queda con el volcado sin cifrar.
     * Se borran las de más de un día; las recientes pueden ser de un respaldo en curso.
     */
    private function limpiarTrabajo(): int
    {
        $raiz = (string) config('respaldos.trabajo');
        $borradas = 0;

        if (! is_dir($raiz)) {
            return 0;
        }

        foreach (File::directories($raiz) as $carpeta) {
            // Nombre de DirectorioTrabajo: fecha AAAAMMDDHHMMSS y 8 caracteres
            if (preg_match('/^(\d{14})-[a-z0-9]{8}$/', basename($carpeta), $partes) !== 1) {
                continue;
            }

            $creada = Carbon::createFromFormat('YmdHis', $partes[1]);

            if ($creada instanceof Carbon && $creada->lessThan(now()->subHours(self::HORAS_INCOMPLETO))) {
                File::deleteDirectory($carpeta);
                $borradas++;
            }
        }

        return $borradas;
    }
}
