<?php

namespace App\Services;

use App\Models\Grupo;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\TablaPosicion;
use App\Models\Temporada;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as ColeccionBase;

/**
 * Resultados, tablas y estadísticas del portal público (HU-077, RF-12.3). Todo sale de resultados oficiales:
 * cédula cerrada y validada (HU-040). De los jugadores solo se expone nombre y equipo (RNF-31).
 */
class PortalResultadosService
{
    /** @var list<int>|null */
    private ?array $temporadasEnCurso = null;

    public function __construct(
        private readonly TablaPosicionesService $tablas,
        private readonly EstadisticasGrupoService $estadisticasGrupo,
    ) {
    }

    /**
     * Grupos con tabla calculada, es decir, con al menos un resultado oficial.
     *
     * @return Collection<int, Grupo>
     */
    public function grupos(bool $soloTemporadaEnCurso = false): Collection
    {
        return Grupo::query()
            ->whereIn('id', TablaPosicion::query()->select('grupo_id'))
            ->when($soloTemporadaEnCurso, fn ($consulta) => $consulta->whereIn('temporada_id', $this->temporadasEnCurso()))
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
     * Goleo y tarjetas del grupo con la regla de HU-043 (EstadisticasGrupoService, #101): sin autogoles, solo cédulas
     * oficiales, la doble amarilla en un partido cuenta como roja (decisión de Daniel, 23/11) y los empates se
     * ordenan por apellidos. Aquí solo se recorta a $limite y se deja nombre y equipo para la vista (RNF-31).
     *
     * @return array{goleo: ColeccionBase<int, array{nombre: string, equipo: string, total: int}>, tarjetas: ColeccionBase<int, array{nombre: string, equipo: string, amarillas: int, rojas: int}>}
     */
    public function estadisticas(Grupo $grupo, int $limite = 10): array
    {
        return [
            'goleo' => $this->estadisticasGrupo->goleadores($grupo)
                ->take($limite)
                ->map(fn (array $fila): array => [...$this->publico($fila['jugador']), 'total' => $fila['goles']])
                ->values(),
            'tarjetas' => $this->estadisticasGrupo->tarjetas($grupo)
                ->take($limite)
                ->map(fn (array $fila): array => [...$this->publico($fila['jugador']), 'amarillas' => $fila['amarillas'], 'rojas' => $fila['rojas']])
                ->values(),
        ];
    }

    /**
     * Solo nombre y equipo del jugador (RNF-31).
     *
     * @return array{nombre: string, equipo: string}
     */
    private function publico(Jugador $jugador): array
    {
        $persona = $jugador->persona;

        return [
            'nombre' => $persona !== null ? trim($persona->nombre.' '.$persona->apellidos) : 'Jugador',
            'equipo' => $jugador->equipo->nombre ?? 'Sin equipo',
        ];
    }

    /**
     * Temporadas en curso (estado «activa»). Las cerradas no entran en la portada.
     *
     * @return list<int>
     */
    public function temporadasEnCurso(): array
    {
        return $this->temporadasEnCurso ??= Temporada::query()
            ->where('estado', 'activa')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }
}
