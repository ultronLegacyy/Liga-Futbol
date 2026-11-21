<?php

namespace App\Services;

use App\Models\Jornada;
use App\Models\Partido;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Programación semanal del portal público (HU-062, RF-12.3, IU-01).
 *
 * Solo muestra partidos de jornadas publicadas (HU-030) y nunca los que siguen en borrador.
 * Del partido solo salen datos públicos: fecha, hora, cancha, equipos, categoría y estado.
 */
class ProgramacionPublicaService
{
    /** Lunes de la semana que contiene la fecha, con la hora de México. */
    public function inicioDeSemana(?string $fecha = null): CarbonImmutable
    {
        $hoy = CarbonImmutable::parse(GestionJugadorService::hoy());
        $dia = $fecha !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) === 1 && checkdate((int) substr($fecha, 5, 2), (int) substr($fecha, 8, 2), (int) substr($fecha, 0, 4))
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

    /** @return Builder<Partido> */
    private function publicados(): Builder
    {
        return Partido::query()
            ->whereNotNull('fecha_partido')
            ->where('estado', '!=', Partido::ESTADO_BORRADOR)
            ->whereHas('jornada', fn (Builder $jornada) => $jornada->where('estado', Jornada::ESTADO_PUBLICADA));
    }
}
