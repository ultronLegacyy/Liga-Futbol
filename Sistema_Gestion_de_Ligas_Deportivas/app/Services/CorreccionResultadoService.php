<?php

namespace App\Services;

use App\Models\AuditoriaLog;
use App\Models\CedulaArbitral;
use App\Models\Jornada;
use App\Models\TablaPosicionJornada;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * HU-039 (RF-08.3, RNF-33): corrige el marcador de un resultado ya validado. En una sola transacción:
 * guarda el marcador nuevo con su motivo en la bitácora inmutable (CA1), recalcula la tabla del grupo (CA2)
 * y rehace las copias por jornada desde la jornada corregida, porque dependían del marcador que cambió (HU-043).
 * Quién puede corregir lo decide CedulaArbitralPolicy::corregirResultado (CA3).
 */
class CorreccionResultadoService
{
    public function __construct(
        private readonly AuditService $auditoria,
        private readonly TablaPosicionesService $tablas,
        private readonly EstadisticasGrupoService $estadisticas,
    ) {
    }

    public function corregir(CedulaArbitral $cedula, int $golesLocal, int $golesVisitante, string $motivo): CedulaArbitral
    {
        $motivo = trim($motivo);

        return $this->auditoria->conMotivo(
            'Corrección del resultado del partido '.$cedula->partido_id.': '.$motivo,
            function () use ($cedula, $golesLocal, $golesVisitante): CedulaArbitral {
                $bloqueada = CedulaArbitral::query()->whereKey($cedula->getKey())->lockForUpdate()->firstOrFail();

                if (! $bloqueada->esOficial()) {
                    throw ValidationException::withMessages([
                        'goles_local' => 'Solo se corrige un resultado ya validado. Si todavía no es oficial, se revisa antes de validarlo.',
                    ]);
                }

                $jornada = $bloqueada->partido?->jornada;
                $grupo = $jornada?->grupo;

                if ($grupo === null || $grupo->estaCerrado()) {
                    throw ValidationException::withMessages([
                        'goles_local' => 'La temporada de este partido está cerrada: sus resultados solo se consultan.',
                    ]);
                }

                if ($bloqueada->goles_local === $golesLocal && $bloqueada->goles_visitante === $golesVisitante) {
                    throw ValidationException::withMessages([
                        'goles_local' => 'El marcador es el mismo que el oficial: no hay nada que corregir.',
                    ]);
                }

                // Auditable deja el marcador anterior y el nuevo con el motivo de conMotivo (CA1, RNF-33)
                $bloqueada->forceFill(['goles_local' => $golesLocal, 'goles_visitante' => $golesVisitante])->save();

                // CA2: la tabla del grupo se recalcula con el marcador corregido
                $this->tablas->recalcular($grupo);

                // Las copias por jornada desde la corregida usaban el marcador anterior: se rehacen
                TablaPosicionJornada::query()
                    ->where('grupo_id', $grupo->id)
                    ->whereIn('jornada_id', Jornada::query()
                        ->where('grupo_id', $grupo->id)
                        ->where('numero_jornada', '>=', $jornada->numero_jornada)
                        ->select('id'))
                    ->delete();
                $this->estadisticas->conservarHistoricos($grupo);

                return $bloqueada;
            }
        );
    }

    /**
     * Correcciones anteriores de la cédula, de la más reciente a la más antigua, para mostrarlas al corregir.
     *
     * @return Collection<int, AuditoriaLog>
     */
    public function historial(CedulaArbitral $cedula): Collection
    {
        return AuditoriaLog::query()
            ->where('tabla_afectada', 'cedulas_arbitrales')
            ->where('registro_id', $cedula->getKey())
            ->where('motivo', 'like', 'Corrección del resultado%')
            ->with('usuario:id,name')
            ->latest()
            ->latest('id')
            ->get();
    }
}
