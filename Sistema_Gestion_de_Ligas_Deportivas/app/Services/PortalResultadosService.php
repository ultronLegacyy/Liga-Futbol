<?php

namespace App\Services;

use App\Models\EventoPartido;
use App\Models\Grupo;
use App\Models\Partido;
use App\Models\TablaPosicion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as ColeccionBase;

/**
 * Resultados, tablas y estadísticas del portal público (HU-077, RF-12.3). Todo sale de resultados oficiales:
 * cédula cerrada y validada (HU-040). De los jugadores solo se expone nombre y equipo (RNF-31).
 */
class PortalResultadosService
{
    public function __construct(private readonly TablaPosicionesService $tablas)
    {
    }

    /**
     * Grupos con tabla calculada, es decir, con al menos un resultado oficial.
     *
     * @return Collection<int, Grupo>
     */
    public function grupos(): Collection
    {
        return Grupo::query()
            ->whereIn('id', TablaPosicion::query()->select('grupo_id'))
            ->with('categoria:id,nombre,rama', 'temporada:id,nombre')
            ->orderByDesc('temporada_id')
            ->orderBy('categoria_id')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'categoria_id', 'temporada_id']);
    }

    /**
     * @return Collection<int, TablaPosicion>
     */
    public function tabla(Grupo $grupo): Collection
    {
        return $this->tablas->tabla($grupo);
    }

    /**
     * Últimos resultados oficiales del grupo, del más reciente al más antiguo.
     *
     * @return Collection<int, Partido>
     */
    public function resultados(Grupo $grupo, int $limite = 30): Collection
    {
        return Partido::query()
            ->whereHas('jornada', fn ($jornada) => $jornada->where('grupo_id', $grupo->getKey()))
            ->whereHas('cedula', fn ($cedula) => $cedula->oficiales())
            ->with([
                'local:id,nombre',
                'visitante:id,nombre',
                'cancha:id,nombre',
                'jornada:id,numero_jornada',
                'cedula:id,partido_id,goles_local,goles_visitante',
            ])
            ->orderByDesc('fecha_partido')
            ->orderByDesc('hora_partido')
            ->orderByDesc('id')
            ->limit($limite)
            ->get();
    }

    /**
     * Goleo y tarjetas por jugador con los eventos de las cédulas oficiales del grupo. Los autogoles no suman.
     *
     * @return array{goleo: ColeccionBase<int, array{nombre: string, equipo: string, total: int}>, tarjetas: ColeccionBase<int, array{nombre: string, equipo: string, amarillas: int, rojas: int}>}
     */
    public function estadisticas(Grupo $grupo, int $limite = 10): array
    {
        $eventos = EventoPartido::query()
            ->whereNotNull('jugador_id')
            ->whereIn('tipo', ['Gol', 'Amarilla', 'Roja'])
            ->whereHas('cedula', fn ($cedula) => $cedula
                ->oficiales()
                ->whereHas('partido.jornada', fn ($jornada) => $jornada->where('grupo_id', $grupo->getKey())))
            ->with('jugador:id,persona_id,equipo_id', 'jugador.persona:id,nombre,apellidos', 'jugador.equipo:id,nombre')
            ->get(['id', 'cedula_id', 'jugador_id', 'tipo', 'autogol']);

        $porJugador = $eventos->groupBy('jugador_id')->map(function ($suyos): array {
            /** @var EventoPartido $primero */
            $primero = $suyos->first();
            $persona = $primero->jugador?->persona;

            return [
                'nombre' => $persona !== null ? trim($persona->nombre.' '.$persona->apellidos) : 'Jugador',
                'equipo' => $primero->jugador?->equipo?->nombre ?? 'Sin equipo',
                'goles' => $suyos->where('tipo', 'Gol')->where('autogol', false)->count(),
                'amarillas' => $suyos->where('tipo', 'Amarilla')->count(),
                'rojas' => $suyos->where('tipo', 'Roja')->count(),
            ];
        })->values();

        return [
            'goleo' => $porJugador
                ->filter(fn (array $fila): bool => $fila['goles'] > 0)
                ->sortBy([['goles', 'desc'], ['nombre', 'asc']])
                ->take($limite)
                ->map(fn (array $fila): array => ['nombre' => $fila['nombre'], 'equipo' => $fila['equipo'], 'total' => $fila['goles']])
                ->values(),
            'tarjetas' => $porJugador
                ->filter(fn (array $fila): bool => $fila['amarillas'] + $fila['rojas'] > 0)
                ->sortBy([['rojas', 'desc'], ['amarillas', 'desc'], ['nombre', 'asc']])
                ->take($limite)
                ->map(fn (array $fila): array => ['nombre' => $fila['nombre'], 'equipo' => $fila['equipo'], 'amarillas' => $fila['amarillas'], 'rojas' => $fila['rojas']])
                ->values(),
        ];
    }
}
