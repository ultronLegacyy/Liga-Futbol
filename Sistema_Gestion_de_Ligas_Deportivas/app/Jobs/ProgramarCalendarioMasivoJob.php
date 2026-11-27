<?php

namespace App\Jobs;

use App\Models\Temporada;
use App\Services\ProgramacionAutomaticaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Ejecuta en segundo plano la programación automática masiva de toda una temporada/jornada general
 * (HU-074 CA2 / RNF-07) atribuyendo la auditoría al usuario solicitante (RNF-30) y persistiendo el
 * reporte de conflictos para su consulta en pantalla (HU-033).
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
        public readonly ?int $usuarioId = null,
    ) {
    }

    public function handle(ProgramacionAutomaticaService $programacionService): void
    {
        $temporada = Temporada::query()->find($this->temporadaId);
        if (! $temporada instanceof Temporada || $temporada->estado === 'cerrada') {
            return;
        }

        $usuarioPrevioId = Auth::id();
        if ($this->usuarioId !== null) {
            Auth::onceUsingId($this->usuarioId);
        }

        try {
            $resumen = $programacionService->asignarHorariosMasivoTemporada($temporada, $this->opciones);

            Cache::put(
                "programacion_masiva_temporada_{$this->temporadaId}",
                array_merge($resumen, [
                    'estado' => 'completado',
                    'solicitado_por_id' => $this->usuarioId,
                    'ejecutado_en' => now()->toIso8601String(),
                ]),
                now()->addHours(6)
            );

            Log::info('Programación automática masiva de temporada completada.', [
                'temporada_id' => $this->temporadaId,
                'usuario_id' => $this->usuarioId,
                'total_partidos' => $resumen['total_partidos'] ?? 0,
                'asignados' => $resumen['asignados'] ?? 0,
                'sin_asignar' => $resumen['sin_asignar'] ?? 0,
                'duracion_ms' => $resumen['duracion_ms'] ?? 0.0,
            ]);
        } finally {
            if ($usuarioPrevioId === null) {
                Auth::forgetGuards();
            } elseif ($usuarioPrevioId !== $this->usuarioId) {
                Auth::onceUsingId($usuarioPrevioId);
            }
        }
    }
}
