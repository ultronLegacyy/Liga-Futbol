<?php

namespace App\Services;

use App\Models\CedulaArbitral;
use App\Models\EventoPartido;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\Jugador;
use App\Models\TablaPosicionJornada;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as Coleccion;
use Illuminate\Support\Facades\DB;

/**
 * HU-043 (RF-09.3, RF-09.4): estadísticas de goleo, tarjetas y sanciones de un grupo y copia histórica
 * de la tabla al cierre de cada jornada. Goles y tarjetas salen solo de resultados oficiales (HU-040).
 */
class EstadisticasGrupoService
{
    public function __construct(private readonly TablaPosicionesService $tablas)
    {
    }

    /**
     * Goleadores del grupo: goles de cada jugador, sin contar autogoles.
     *
     * @return Coleccion<int, array{jugador: Jugador, goles: int}>
     */
    public function goleadores(Grupo $grupo): Coleccion
    {
        $conteo = $this->eventosOficiales($grupo)
            ->where('eventos_partido.tipo', 'Gol')
            ->where('eventos_partido.autogol', false)
            ->whereNotNull('eventos_partido.jugador_id')
            ->groupBy('eventos_partido.jugador_id')
            ->selectRaw('eventos_partido.jugador_id, COUNT(*) AS goles')
            ->toBase()
            ->get();

        $jugadores = $this->jugadores($conteo->pluck('jugador_id')->map(fn ($id) => (int) $id)->all());
        $filas = [];

        foreach ($conteo as $fila) {
            $jugador = $jugadores->get((int) $fila->jugador_id);

            if ($jugador !== null) {
                $filas[] = ['jugador' => $jugador, 'goles' => (int) $fila->goles];
            }
        }

        usort($filas, fn (array $a, array $b): int => [$b['goles'], $this->nombre($a['jugador'])] <=> [$a['goles'], $this->nombre($b['jugador'])]);

        return collect($filas);
    }

    /**
     * Tarjetas acumuladas de cada jugador del grupo.
     *
     * @return Coleccion<int, array{jugador: Jugador, amarillas: int, rojas: int}>
     */
    public function tarjetas(Grupo $grupo): Coleccion
    {
        $conteo = $this->eventosOficiales($grupo)
            ->whereIn('eventos_partido.tipo', ['Amarilla', 'Roja'])
            ->whereNotNull('eventos_partido.jugador_id')
            ->groupBy('eventos_partido.jugador_id')
            ->selectRaw("eventos_partido.jugador_id, SUM(eventos_partido.tipo = 'Amarilla') AS amarillas, SUM(eventos_partido.tipo = 'Roja') AS rojas")
            ->toBase()
            ->get();

        $jugadores = $this->jugadores($conteo->pluck('jugador_id')->map(fn ($id) => (int) $id)->all());
        $filas = [];

        foreach ($conteo as $fila) {
            $jugador = $jugadores->get((int) $fila->jugador_id);

            if ($jugador !== null) {
                $filas[] = ['jugador' => $jugador, 'amarillas' => (int) $fila->amarillas, 'rojas' => (int) $fila->rojas];
            }
        }

        usort($filas, fn (array $a, array $b): int => [$b['rojas'], $b['amarillas'], $this->nombre($a['jugador'])] <=> [$a['rojas'], $a['amarillas'], $this->nombre($b['jugador'])]);

        return collect($filas);
    }

    /**
     * Sanciones del Tribunal de Penas (HU-045) cuyos expedientes vienen de partidos del grupo.
     *
     * @return Coleccion<int, \stdClass>
     */
    public function sanciones(Grupo $grupo): Coleccion
    {
        return DB::table('sanciones')
            ->join('expedientes_disciplinarios', 'expedientes_disciplinarios.id', '=', 'sanciones.expediente_id')
            ->join('partidos', 'partidos.id', '=', 'expedientes_disciplinarios.partido_id')
            ->join('jornadas', 'jornadas.id', '=', 'partidos.jornada_id')
            ->join('personas', 'personas.id', '=', 'sanciones.persona_id')
            ->where('jornadas.grupo_id', $grupo->id)
            ->orderByRaw("sanciones.estado = 'Vigente' DESC")
            ->orderBy('personas.apellidos')
            ->orderBy('personas.nombre')
            ->get([
                'sanciones.id',
                'personas.nombre',
                'personas.apellidos',
                'jornadas.numero_jornada',
                'sanciones.descripcion_sancion',
                'sanciones.partidos_suspendidos',
                'sanciones.partidos_cumplidos',
                'sanciones.estado',
            ]);
    }

    /**
     * Resultados oficiales del grupo (dato oficial, RF-09.4), por jornada.
     *
     * @return Collection<int, CedulaArbitral>
     */
    public function resultadosOficiales(Grupo $grupo): Collection
    {
        return CedulaArbitral::query()
            ->oficiales()
            ->whereHas('partido.jornada', fn (Builder $jornada) => $jornada->where('grupo_id', $grupo->id))
            ->with(['partido.local:id,nombre', 'partido.visitante:id,nombre', 'partido.jornada:id,numero_jornada', 'validadaPor:id,name'])
            ->get()
            ->sortBy(fn (CedulaArbitral $cedula) => [$cedula->partido?->jornada?->numero_jornada, $cedula->partido_id])
            ->values();
    }

    /**
     * Jornadas del grupo que ya tienen copia histórica de la tabla.
     *
     * @return Collection<int, Jornada>
     */
    public function jornadasConHistorico(Grupo $grupo): Collection
    {
        return Jornada::query()
            ->where('grupo_id', $grupo->id)
            ->whereIn('id', TablaPosicionJornada::query()->select('jornada_id'))
            ->orderBy('numero_jornada')
            ->get(['id', 'numero_jornada']);
    }

    /**
     * Copia histórica de la tabla en una jornada.
     *
     * @return Collection<int, TablaPosicionJornada>
     */
    public function historico(Jornada $jornada): Collection
    {
        return TablaPosicionJornada::query()
            ->where('jornada_id', $jornada->id)
            ->with('equipo:id,nombre')
            ->orderBy('posicion')
            ->get();
    }

    /**
     * CA2: guarda la tabla de cada jornada cerrada que aún no tiene copia. Una jornada está cerrada cuando
     * todos sus partidos tienen resultado oficial; solo se copia si las anteriores también lo están,
     * para que la copia refleje la tabla real de esa jornada. Una copia ya guardada no se reescribe.
     */
    public function conservarHistoricos(Grupo $grupo): void
    {
        $regla = null;

        $jornadas = Jornada::query()
            ->where('grupo_id', $grupo->id)
            ->withCount([
                'partidos',
                'partidos as oficiales_count' => fn ($partido) => $partido->whereHas('cedula', fn ($cedula) => $cedula->oficiales()),
            ])
            ->orderBy('numero_jornada')
            ->get();

        foreach ($jornadas as $jornada) {
            $total = (int) $jornada->getAttribute('partidos_count');

            if ($total === 0 || (int) $jornada->getAttribute('oficiales_count') < $total) {
                return;
            }

            if (TablaPosicionJornada::query()->where('jornada_id', $jornada->id)->exists()) {
                continue;
            }

            $regla ??= $this->tablas->reglaVigente($grupo);

            foreach ($this->tablas->calcular($grupo, $regla, $jornada->numero_jornada) as $renglon) {
                TablaPosicionJornada::create([
                    'grupo_id' => $grupo->id,
                    'jornada_id' => $jornada->id,
                    'equipo_id' => $renglon['equipo_id'],
                    'posicion' => $renglon['posicion'],
                    'pj' => $renglon['pj'],
                    'pg' => $renglon['pg'],
                    'pe' => $renglon['pe'],
                    'pp' => $renglon['pp'],
                    'gf' => $renglon['gf'],
                    'gc' => $renglon['gc'],
                    'dg' => $renglon['dg'],
                    'puntos' => $renglon['puntos'],
                    'regla_version' => $regla->version,
                ]);
            }
        }
    }

    /**
     * Eventos de cédulas oficiales de partidos del grupo.
     *
     * @return Builder<EventoPartido>
     */
    private function eventosOficiales(Grupo $grupo): Builder
    {
        return EventoPartido::query()
            ->join('cedulas_arbitrales', 'cedulas_arbitrales.id', '=', 'eventos_partido.cedula_id')
            ->join('partidos', 'partidos.id', '=', 'cedulas_arbitrales.partido_id')
            ->join('jornadas', 'jornadas.id', '=', 'partidos.jornada_id')
            ->where('jornadas.grupo_id', $grupo->id)
            ->where('cedulas_arbitrales.estado', CedulaArbitral::CERRADA)
            ->whereNotNull('cedulas_arbitrales.validada_en');
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Jugador>
     */
    private function jugadores(array $ids): Collection
    {
        return Jugador::query()
            ->whereIn('id', $ids)
            ->with('persona:id,nombre,apellidos', 'equipo:id,nombre')
            ->get()
            ->keyBy('id');
    }

    private function nombre(Jugador $jugador): string
    {
        $persona = $jugador->persona;

        return $persona === null ? '' : trim($persona->apellidos.' '.$persona->nombre);
    }
}
