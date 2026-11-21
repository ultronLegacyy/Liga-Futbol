<?php

namespace App\Services;

use App\Models\Jornada;
use App\Models\Partido;
use App\Models\ReprogramacionPartido;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Programación semanal del portal público (HU-062, RF-12.3, IU-01).
 *
 * Solo muestra partidos de jornadas publicadas (HU-030) y nunca los que siguen en borrador.
 * Del partido solo salen datos públicos: fecha, hora, cancha, equipos, categoría y estado.
 * Respuestas 52 y 53 del PO: avisa de las jornadas que se están actualizando y de los partidos reprogramados.
 */
class ProgramacionPublicaService
{
    // Rango de años que se aceptan en ?semana=; fuera de él se muestra la semana actual
    public const PRIMER_ANIO = 2000;

    public const ULTIMO_ANIO = 2100;

    /** Lunes de la semana que contiene la fecha, con la hora de México. */
    public function inicioDeSemana(?string $fecha = null): CarbonImmutable
    {
        $hoy = CarbonImmutable::parse(GestionJugadorService::hoy());
        $anio = $fecha !== null ? (int) substr($fecha, 0, 4) : 0;
        $dia = $fecha !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) === 1
            && $anio >= self::PRIMER_ANIO && $anio <= self::ULTIMO_ANIO
            && checkdate((int) substr($fecha, 5, 2), (int) substr($fecha, 8, 2), $anio)
            ? CarbonImmutable::parse($fecha)
            : $hoy;

        return $dia->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();
    }

    /**
     * Partidos de la semana, agrupados por día (Y-m-d) y en orden de hora.
     *
     * @return \Illuminate\Support\Collection<string, Collection<int, Partido>>
     */
    public function semana(CarbonImmutable $lunes): \Illuminate\Support\Collection
    {
        return $this->publicados()
            ->whereBetween('fecha_partido', [$lunes->toDateString(), $lunes->addDays(6)->toDateString()])
            ->with([
                'local:id,nombre',
                'visitante:id,nombre',
                'cancha:id,nombre,direccion_ubicacion',
                'jornada:id,grupo_id,numero_jornada',
                'jornada.grupo:id,categoria_id,nombre',
                'jornada.grupo.categoria:id,nombre,rama',
                // Respuesta 53: la fecha anterior de un partido reprogramado (la más reciente primero)
                'reprogramaciones' => fn (HasMany $consulta) => $this->conCambioDeFecha($consulta)
                    ->select(['id', 'partido_id', 'fecha_anterior', 'hora_anterior', 'fecha_nueva', 'hora_nueva', 'created_at'])
                    ->orderByDesc('id'),
            ])
            ->orderBy('fecha_partido')
            ->orderByRaw('hora_partido is null')
            ->orderBy('hora_partido')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Partido $partido): string => (string) $partido->fecha_partido?->toDateString());
    }

    /** Lunes de la siguiente semana con partidos publicados después de la semana dada, para orientar a quien no encuentra partidos. */
    public function siguienteSemanaConPartidos(CarbonImmutable $lunes): ?CarbonImmutable
    {
        $fecha = $this->publicados()->where('fecha_partido', '>', $lunes->addDays(6)->toDateString())->min('fecha_partido');

        return $fecha === null ? null : $this->inicioDeSemana(CarbonImmutable::parse($fecha)->toDateString());
    }

    /**
     * Respuesta 53: partidos publicados que estaban en esta semana y se reprogramaron a otra. Se avisa en la
     * semana original para que quien los busque ahí sepa a dónde se movieron.
     *
     * @return Collection<int, Partido>
     */
    public function reprogramadosAOtraSemana(CarbonImmutable $lunes): Collection
    {
        $desde = $lunes->toDateString();
        $hasta = $lunes->addDays(6)->toDateString();

        return $this->publicados()
            ->whereNotBetween('fecha_partido', [$desde, $hasta])
            ->whereHas('reprogramaciones', fn (Builder $consulta) => $consulta->whereBetween('fecha_anterior', [$desde, $hasta]))
            ->with(['local:id,nombre', 'visitante:id,nombre', 'cancha:id,nombre'])
            ->orderBy('fecha_partido')
            ->orderBy('hora_partido')
            ->get();
    }

    /**
     * Respuesta 52: jornadas con partidos en esta semana que ya se habían publicado (la auditoría lo registra) y
     * volvieron a revisión por un cambio. Sus partidos no se muestran hasta que se publiquen otra vez.
     *
     * @return Collection<int, Jornada>
     */
    public function jornadasEnActualizacion(CarbonImmutable $lunes): Collection
    {
        return Jornada::query()
            ->where('estado', '!=', Jornada::ESTADO_PUBLICADA)
            ->whereHas('partidos', fn (Builder $partido) => $partido
                ->whereBetween('fecha_partido', [$lunes->toDateString(), $lunes->addDays(6)->toDateString()]))
            ->whereExists(fn (QueryBuilder $auditoria) => $auditoria
                ->selectRaw('1')
                ->from('auditoria_logs')
                ->where('tabla_afectada', 'jornadas')
                ->whereColumn('auditoria_logs.registro_id', 'jornadas.id')
                ->where('valores_nuevos->estado', Jornada::ESTADO_PUBLICADA))
            ->with(['grupo:id,categoria_id,nombre', 'grupo.categoria:id,nombre,rama'])
            ->orderBy('numero_jornada')
            ->get(['id', 'grupo_id', 'numero_jornada']);
    }

    /**
     * Reprogramaciones que cambiaron la fecha o la hora del partido.
     *
     * @param  HasMany<ReprogramacionPartido, Partido>  $consulta
     * @return HasMany<ReprogramacionPartido, Partido>
     */
    private function conCambioDeFecha(HasMany $consulta): HasMany
    {
        return $consulta
            ->whereNotNull('fecha_anterior')
            ->where(fn (Builder $cambio) => $cambio
                ->whereColumn('fecha_anterior', '!=', 'fecha_nueva')
                ->orWhereColumn('hora_anterior', '!=', 'hora_nueva'));
    }

    /** @return Builder<Partido> */
    private function publicados(): Builder
    {
        return Partido::query()
            ->whereNotNull('fecha_partido')
            ->where('estado', '!=', Partido::ESTADO_BORRADOR)
            ->whereHas('jornada', fn (Builder $jornada) => $jornada->where('estado', Jornada::ESTADO_PUBLICADA));
    }
}
