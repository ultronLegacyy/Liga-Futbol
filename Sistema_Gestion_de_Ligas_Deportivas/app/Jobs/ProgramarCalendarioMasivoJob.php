<?php

namespace App\Jobs;

use App\Models\Temporada;
use App\Services\ProgramacionAutomaticaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Ejecuta en segundo plano la programación automática masiva de toda una temporada/jornada general
 * (HU-074 CA2 / RNF-07: ~400 partidos / 40 canchas <= 60 segundos).
 */
class ProgramarCalendarioMasivoJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    /**
     * @param  array<string, mixed>  $opciones
     */
    public function __construct(
        public readonly int $temporadaId,
        public readonly array $opciones = [],
    ) {
    }

    public function handle(ProgramacionAutomaticaService $programacionService): void
    {
        $temporada = Temporada::query()->find($this->temporadaId);
        if (! $temporada instanceof Temporada || $temporada->estado === 'cerrada') {
            return;
        }

        $resumen = $programacionService->asignarHorariosMasivoTemporada($temporada, $this->opciones);

        Cache::put(
            "programacion_masiva_temporada_{$this->temporadaId}",
            array_merge($resumen, ['ejecutado_en' => now()->toIso8601String()]),
            now()->addHours(6)
        );

        Log::info('Programación automática masiva de temporada completada.', [
            'temporada_id' => $this->temporadaId,
            'total_partidos' => $resumen['total_partidos'] ?? 0,
            'asignados' => $resumen['asignados'] ?? 0,
            'sin_asignar' => $resumen['sin_asignar'] ?? 0,
            'duracion_ms' => $resumen['duracion_ms'] ?? 0.0,
        ]);
    }
}
