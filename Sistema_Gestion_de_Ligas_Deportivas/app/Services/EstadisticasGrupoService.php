<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\CedulaArbitral;
use App\Models\EventoPartido;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Sancion;
use App\Models\TablaPosicionJornada;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as Coleccion;

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
        $conteo = $this->golesOficiales([$grupo->id])
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
     * HU-060 CA3 (RF-11.6): campeón de goleo de cada categoría de la temporada (convocatoria, base VIII: «en el torneo de
     * liga»). Decisión 6 de Daniel: solo cuentan los goles de la fase regular, sin autogoles, de todos los grupos de la
     * categoría; si empatan, gana el jugador cuyo equipo quedó mejor en la tabla de su grupo, y si siguen empatados, el
     * título se comparte. Una categoría sin goles aparece sin campeón.
     *
     * @return list<array{categoria: Categoria, goles: int, jugadores: list<Jugador>}>
     */
    public function campeonesDeGoleo(Temporada $temporada): array
    {
        $grupos = Grupo::query()->where('temporada_id', $temporada->id)->with('categoria:id,nombre')->get()->keyBy('id');
        $conteo = $this->golesOficiales($grupos->modelKeys())
            ->whereNull('jornadas.fase_id')
            ->groupBy('eventos_partido.jugador_id', 'jornadas.grupo_id')
            ->selectRaw('eventos_partido.jugador_id, jornadas.grupo_id, COUNT(*) AS goles')
            ->toBase()
            ->get();

        /** @var array<int, list<array{jugador_id: int, grupo: Grupo, goles: int}>> $porCategoria */
        $porCategoria = [];
        foreach ($conteo as $fila) {
            $grupo = $grupos->get((int) $fila->grupo_id);
            if ($grupo !== null) {
                $porCategoria[$grupo->categoria_id][] = ['jugador_id' => (int) $fila->jugador_id, 'grupo' => $grupo, 'goles' => (int) $fila->goles];
            }
        }

        $campeones = [];
        $lugares = [];
        foreach ($grupos->pluck('categoria')->filter()->unique('id')->sortBy('nombre') as $categoria) {
            $filas = $porCategoria[$categoria->id] ?? [];
            $goles = $filas === [] ? 0 : max(array_column($filas, 'goles'));
            $empatados = array_values(array_filter($filas, fn (array $fila): bool => $fila['goles'] === $goles));
            $jugadores = $this->jugadores(array_column($empatados, 'jugador_id'));

            // Desempate: el lugar del equipo del jugador en la tabla de su grupo; sin reglas de puntos no hay lugar
            $candidatos = [];
            foreach ($empatados as $fila) {
                $jugador = $jugadores->get($fila['jugador_id']);
                if ($jugador !== null) {
                    $grupoId = $fila['grupo']->id;
                    $lugares[$grupoId] ??= $this->lugaresDelGrupo($fila['grupo']);
                    $candidatos[] = ['jugador' => $jugador, 'lugar' => $lugares[$grupoId][(int) $jugador->equipo_id] ?? PHP_INT_MAX];
                }
            }

            $mejor = $candidatos === [] ? null : min(array_column($candidatos, 'lugar'));
            $ganadores = array_values(array_filter($candidatos, fn (array $candidato): bool => $candidato['lugar'] === $mejor));
            usort($ganadores, fn (array $a, array $b): int => $this->nombre($a['jugador']) <=> $this->nombre($b['jugador']));

            $campeones[] = ['categoria' => $categoria, 'goles' => $goles, 'jugadores' => array_column($ganadores, 'jugador')];
        }

        return $campeones;
    }

    /**
     * Lugar de cada equipo en la tabla general del grupo, con las reglas vigentes.
     *
     * @return array<int, int>
     */
    private function lugaresDelGrupo(Grupo $grupo): array
    {
        $regla = $this->tablas->reglaVigenteOpcional($grupo);

        return $regla === null ? [] : array_column($this->tablas->calcular($grupo, $regla), 'posicion', 'equipo_id');
    }

    /**
     * Tarjetas acumuladas de cada jugador del grupo. Decisión de Daniel (23/11): la doble amarilla en un mismo
     * partido es una roja; la expulsión cuenta una sola vez y la segunda amarilla ya no suma como amarilla.
     *
     * @return Coleccion<int, array{jugador: Jugador, amarillas: int, rojas: int}>
     */
    public function tarjetas(Grupo $grupo): Coleccion
    {
        $porPartido = $this->eventosOficiales([$grupo->id])
            ->whereIn('eventos_partido.tipo', ['Amarilla', 'Roja'])
            ->whereNotNull('eventos_partido.jugador_id')
            ->groupBy('eventos_partido.jugador_id', 'eventos_partido.cedula_id')
            ->selectRaw("eventos_partido.jugador_id, SUM(eventos_partido.tipo = 'Amarilla') AS amarillas, SUM(eventos_partido.tipo = 'Roja') AS rojas")
            ->toBase()
            ->get();

        /** @var array<int, array{amarillas: int, rojas: int}> $totales */
        $totales = [];
        foreach ($porPartido as $fila) {
            $amarillas = (int) $fila->amarillas;
            $expulsado = (int) $fila->rojas > 0 || $amarillas >= 2;
            $id = (int) $fila->jugador_id;

            $totales[$id] ??= ['amarillas' => 0, 'rojas' => 0];
            $totales[$id]['amarillas'] += $amarillas >= 2 ? 1 : $amarillas;
            $totales[$id]['rojas'] += $expulsado ? 1 : 0;
        }

        $jugadores = $this->jugadores(array_keys($totales));
        $filas = [];

        foreach ($totales as $id => $conteo) {
            $jugador = $jugadores->get($id);

            if ($jugador !== null) {
                $filas[] = ['jugador' => $jugador, 'amarillas' => $conteo['amarillas'], 'rojas' => $conteo['rojas']];
            }
        }

        usort($filas, fn (array $a, array $b): int => [$b['rojas'], $b['amarillas'], $this->nombre($a['jugador'])] <=> [$a['rojas'], $a['amarillas'], $this->nombre($b['jugador'])]);

        return collect($filas);
    }

    /**
     * Sanciones del Tribunal de Penas cuyos expedientes vienen de partidos del grupo, con las mismas reglas de
     * visibilidad que el boletín (#95, respuesta 45 de Medina; decisión de Daniel, 23/11):
     * el Tribunal de Penas y el Administrador General ven todas; el Presidente y el Delegado, las validadas de su club;
     * el Jugador, las suyas validadas. A los demás no se les muestra la sección (SancionPolicy::verBoletin).
     *
     * @return Collection<int, Sancion>
     */
    public function sanciones(Grupo $grupo, User $usuario): Collection
    {
        $consulta = Sancion::query()
            ->whereHas('expediente.partido.jornada', fn ($jornada) => $jornada->where('grupo_id', $grupo->id))
            ->with(['persona:id,nombre,apellidos', 'equipo:id,nombre,club_id', 'expediente.partido.jornada:id,numero_jornada']);

        if (! $usuario->hasRole('Administrador General') && ! $usuario->can('disciplina.sancionar')) {
            if ($usuario->hasAnyRole(['Presidente del Club', 'Delegado del Club']) && $usuario->club_id !== null) {
                $clubId = (int) $usuario->club_id;
                $consulta->whereNotNull('validado_por_usuario_id')
                    ->where(fn ($deSuClub) => $deSuClub
                        ->whereHas('equipo', fn ($equipo) => $equipo->where('club_id', $clubId))
                        ->orWhereHas('persona.inscripciones', fn ($inscripcion) => $inscripcion
                            ->where('estado_elegibilidad', '!=', 'Baja')
                            ->whereHas('equipo', fn ($equipo) => $equipo->where('club_id', $clubId))));
            } elseif ($usuario->hasRole('Jugador') && $usuario->persona_id !== null) {
                $consulta->whereNotNull('validado_por_usuario_id')->where('persona_id', $usuario->persona_id);
            } else {
                return new Collection();
            }
        }

        return $consulta->orderByRaw("estado = 'Vigente' DESC")->orderByDesc('id')->get();
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
     * CA2: guarda la tabla de cada jornada cerrada que aún no tiene copia. Una jornada está cerrada cuando no le quedan
     * partidos pendientes (los cancelados no cuentan) y tiene al menos uno con resultado oficial. Solo se copia si las
     * anteriores también lo están, para que la copia refleje la tabla real de esa jornada; una jornada con todos sus
     * partidos cancelados se salta y no bloquea a las siguientes (decisión de Daniel, 23/11). Una copia ya guardada
     * no se reescribe.
     *
     * Bloquea primero la fila del grupo, igual que TablaPosicionesService::recalcular(): si dos validaciones cierran
     * la misma jornada a la vez, la segunda espera y ya ve la primera, así que una de las dos guarda la copia.
     */
    public function conservarHistoricos(Grupo $grupo): void
    {
        $bloqueado = Grupo::query()->whereKey($grupo->getKey())->lockForUpdate()->firstOrFail();
        $regla = null;

        // HU-056: la copia es de la tabla de la fase regular; las jornadas de liguilla no la cambian
        $jornadas = Jornada::query()
            ->where('grupo_id', $bloqueado->id)
            ->whereNull('fase_id')
            ->withCount([
                'partidos as jugables_count' => fn ($partido) => $partido->where('estado', '!=', Partido::ESTADO_CANCELADO),
                'partidos as oficiales_count' => fn ($partido) => $partido->where('estado', '!=', Partido::ESTADO_CANCELADO)
                    ->whereHas('cedula', fn ($cedula) => $cedula->oficiales()),
            ])
            ->orderBy('numero_jornada')
            ->get();

        foreach ($jornadas as $jornada) {
            $jugables = (int) $jornada->getAttribute('jugables_count');

            // Toda cancelada: la tabla no cambia, no se copia y no frena a las siguientes
            if ($jugables === 0) {
                continue;
            }

            if ((int) $jornada->getAttribute('oficiales_count') < $jugables) {
                return;
            }

            if (TablaPosicionJornada::query()->where('jornada_id', $jornada->id)->exists()) {
                continue;
            }

            // Sin reglas de puntos no hay tabla que copiar (al validar ya se exigen; al cancelar puede no haberlas)
            $regla ??= $this->tablas->reglaVigenteOpcional($bloqueado);
            if ($regla === null) {
                return;
            }

            foreach ($this->tablas->calcular($bloqueado, $regla, $jornada->numero_jornada) as $renglon) {
                TablaPosicionJornada::create([
                    'grupo_id' => $bloqueado->id,
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
     * Goles de jugadores en cédulas oficiales de los grupos, sin autogoles.
     *
     * @param  list<int>  $grupoIds
     * @return Builder<EventoPartido>
     */
    private function golesOficiales(array $grupoIds): Builder
    {
        return $this->eventosOficiales($grupoIds)
            ->where('eventos_partido.tipo', 'Gol')
            ->where('eventos_partido.autogol', false)
            ->whereNotNull('eventos_partido.jugador_id');
    }

    /**
     * Eventos de cédulas oficiales de partidos de los grupos.
     *
     * @param  list<int>  $grupoIds
     * @return Builder<EventoPartido>
     */
    private function eventosOficiales(array $grupoIds): Builder
    {
        return EventoPartido::query()
            ->join('cedulas_arbitrales', 'cedulas_arbitrales.id', '=', 'eventos_partido.cedula_id')
            ->join('partidos', 'partidos.id', '=', 'cedulas_arbitrales.partido_id')
            ->join('jornadas', 'jornadas.id', '=', 'partidos.jornada_id')
            ->whereIn('jornadas.grupo_id', $grupoIds)
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
