<?php

namespace App\Services;

use App\Models\Equipo;
use App\Models\FaseTorneo;
use App\Models\Grupo;
use App\Models\LlaveLiguilla;
use App\Models\Partido;
use App\Models\ReglaTemporada;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * HU-055 (RF-11.1): al terminar la fase regular, toma los primeros lugares de la tabla final (HU-041)
 * según los equipos que clasifican en el grupo (HU-008) y arma los cruces 1° vs N°, 2° vs (N-1)°...
 * Si los clasificados no son 2, 4, 8 o 16, los mejores ubicados pasan directo y los demás juegan la primera ronda
 * (decisión de Daniel, 25/11, como en la práctica del fútbol mexicano): con 6, 1° y 2° pasan; 3° vs 6° y 4° vs 5°.
 * Cada llave conserva la versión de las reglas y los criterios con que se generó (CA2, RNF-36).
 * HU-060 (RF-11.6): la liguilla de consolación juega los lugares que siguen a la liguilla, tantos como diga la
 * configuración del grupo (HU-008; decisión de Daniel, 27/11), con los mismos cruces y reglas. El Campeón de Campeones
 * es una llave del campeón de liga (1.° de la tabla general) contra el campeón de liguilla; si es el mismo equipo, lo
 * es sin jugar (decisión 6 de Daniel).
 */
class LiguillaService
{
    public function __construct(
        private readonly AuditService $auditoria,
        private readonly TablaPosicionesService $tablas,
        private readonly AvanceLiguillaService $avances,
    ) {
    }

    /**
     * Llaves ya generadas del grupo en una fase, en orden.
     *
     * @return Collection<int, LlaveLiguilla>
     */
    public function llaves(Grupo $grupo, string $tipo = FaseTorneo::LIGUILLA): Collection
    {
        return LlaveLiguilla::query()
            ->where('grupo_id', $grupo->id)
            ->whereHas('fase', fn ($fase) => $fase->where('tipo', $tipo))
            ->with(['local:id,nombre', 'visitante:id,nombre', 'avanza:id,nombre', 'regla:id,version', 'generadaPor:id,name', 'partido:id,jornada_id,estado', 'partido.jornada:id,numero_jornada'])
            ->orderBy('ronda')
            ->orderBy('orden')
            ->get();
    }

    /**
     * Revisa si el grupo puede pasar a liguilla y, si puede, propone los cruces sin guardarlos.
     * La tabla se calcula con la versión vigente de las reglas, aunque se haya publicado después del último
     * resultado (decisión de Daniel, 23/11); en la vista previa no se guarda nada.
     *
     * @return array{cruces: list<array{orden: int, local: array{equipo_id: int, nombre: string, posicion: int}, visitante: array{equipo_id: int, nombre: string, posicion: int}|null}>, problemas: list<string>, clasifican: int, desde: int, hasta: int, regla: ReglaTemporada|null}
     */
    public function revisar(Grupo $grupo, string $tipo = FaseTorneo::LIGUILLA): array
    {
        if ($tipo === FaseTorneo::CAMPEON_DE_CAMPEONES) {
            return $this->revisarCampeonDeCampeones($grupo);
        }

        $criterios = $grupo->getAttribute('criterios_clasificacion');
        $clasifican = is_array($criterios) ? (int) ($criterios['clasifican_liguilla'] ?? 0) : 0;
        $consolacion = is_array($criterios) ? (int) ($criterios['clasifican_consolacion'] ?? 0) : 0;
        $configurado = $clasifican >= 2 && $clasifican % 2 === 0;
        $problemas = [];

        if ($clasifican < 2) {
            $problemas[] = 'Configura en «Formato y clasificación» cuántos equipos del grupo clasifican a la liguilla (al menos 2).';
        } elseif ($clasifican % 2 !== 0) {
            $problemas[] = 'El número de equipos que clasifican debe ser par para formar los cruces (por ejemplo, 1° vs 8°).';
        }

        if ($tipo === FaseTorneo::CONSOLACION && $consolacion < 2) {
            $problemas[] = 'Configura en «Formato y clasificación» cuántos equipos del grupo clasifican a la liguilla de consolación (al menos 2).';
            $configurado = false;
        }

        array_push($problemas, ...$this->problemasDeLaFaseRegular($grupo));

        $regla = $this->tablas->reglaVigenteOpcional($grupo);
        if ($regla === null) {
            $problemas[] = 'La temporada no tiene reglas de puntos configuradas para calcular la tabla final.';
        }

        $tabla = $regla === null ? [] : $this->tablas->calcular($grupo, $regla);

        // La consolación toma los lugares siguientes a la liguilla; con menos equipos, juegan los que haya
        [$desde, $hasta] = $tipo === FaseTorneo::LIGUILLA
            ? [1, $clasifican]
            : [$clasifican + 1, min($clasifican + $consolacion, count($tabla))];
        $equipos = max(0, $hasta - $desde + 1);

        if ($clasifican >= 2 && $tipo === FaseTorneo::LIGUILLA && count($tabla) < $clasifican) {
            $problemas[] = "Clasifican {$clasifican} equipos, pero la tabla del grupo tiene ".count($tabla).'.';
        } elseif ($configurado && $regla !== null && $equipos < 2) {
            $problemas[] = "Después de los {$clasifican} lugares de la liguilla quedan ".max(0, count($tabla) - $clasifican).' equipo(s) en la tabla: la liguilla de consolación necesita al menos 2.';
        } elseif ($configurado) {
            array_push($problemas, ...$this->problemasDeEmpates($tabla, $desde, $hasta, $tipo));
        }

        $cruces = [];
        if ($problemas === []) {
            $directos = $this->pasesDirectos($equipos);
            for ($i = 0; $i < $directos; $i++) {
                $cruces[] = ['orden' => $i + 1, 'local' => $tabla[$desde - 1 + $i], 'visitante' => null];
            }

            // Los que no pasan directo se cruzan igual: el mejor ubicado contra el peor
            for ($i = 0; $i < intdiv($equipos - $directos, 2); $i++) {
                $cruces[] = [
                    'orden' => $directos + $i + 1,
                    'local' => $tabla[$desde - 1 + $directos + $i],
                    'visitante' => $tabla[$hasta - 1 - $i],
                ];
            }
        }

        return [
            'cruces' => $cruces,
            'problemas' => $problemas,
            'clasifican' => $tipo === FaseTorneo::LIGUILLA ? $clasifican : $equipos,
            'desde' => $desde,
            'hasta' => $hasta,
            'regla' => $regla,
        ];
    }

    /**
     * Campeón de Campeones (decisión 6 de Daniel): el campeón de liga es el 1.° de la tabla general, guardado en la
     * primera ronda de la liguilla; juega contra el campeón de liguilla. Si es el mismo equipo, no hay rival.
     *
     * @return array{cruces: list<array{orden: int, local: array{equipo_id: int, nombre: string, posicion: int}, visitante: array{equipo_id: int, nombre: string, posicion: int}|null}>, problemas: list<string>, clasifican: int, desde: int, hasta: int, regla: ReglaTemporada|null}
     */
    private function revisarCampeonDeCampeones(Grupo $grupo): array
    {
        $llaves = $this->llaves($grupo);
        $campeones = $this->avances->campeones($llaves);
        $liga = $llaves->first(fn (LlaveLiguilla $llave): bool => $llave->ronda === 1 && $llave->lugar_local === 1);
        $final = $llaves->where('ronda', (int) $llaves->max('ronda'))->first();
        $revision = ['cruces' => [], 'problemas' => [], 'clasifican' => 2, 'desde' => 1, 'hasta' => 1, 'regla' => $llaves->first()?->regla];

        if ($campeones === null || $liga === null || $final === null) {
            $revision['problemas'][] = 'La liguilla del grupo todavía no tiene campeón: el Campeón de Campeones se define después de la final.';

            return $revision;
        }

        $campeonLiguilla = $campeones['campeon'];
        $revision['cruces'][] = [
            'orden' => 1,
            'local' => ['equipo_id' => $liga->equipo_local_id, 'nombre' => (string) $liga->local?->nombre, 'posicion' => 1],
            'visitante' => $campeonLiguilla->id === $liga->equipo_local_id
                ? null
                : ['equipo_id' => $campeonLiguilla->id, 'nombre' => $campeonLiguilla->nombre, 'posicion' => $final->lugarDelQueAvanza() ?? PHP_INT_MAX],
        ];

        return $revision;
    }

    /**
     * Cuántos de los mejores ubicados pasan directo para que la segunda ronda tenga 2, 4, 8 o 16 equipos.
     * Con 6 clasificados la potencia es 4: pasan 2 y juegan 4; con 8 no pasa nadie.
     */
    private function pasesDirectos(int $clasifican): int
    {
        $potencia = 1;
        while ($potencia * 2 <= $clasifican) {
            $potencia *= 2;
        }

        return $potencia === $clasifican ? 0 : 2 * $potencia - $clasifican;
    }

    /**
     * Fin de la fase regular (decisión de Daniel, 23/11): no queda ningún partido pendiente en el grupo y cada par de
     * equipos tiene al menos un partido con resultado oficial o cancelado. Sirve igual para una o dos vueltas del
     * formato «Todos contra todos», que hoy es el único (Grupo::FORMATOS).
     *
     * @return list<string>
     */
    private function problemasDeLaFaseRegular(Grupo $grupo): array
    {
        $partidos = Partido::query()
            ->whereHas('jornada', fn ($jornada) => $jornada->where('grupo_id', $grupo->id)->whereNull('fase_id'))
            ->with('cedula:id,partido_id,estado,validada_en')
            ->get(['id', 'jornada_id', 'equipo_local_id', 'equipo_visitante_id', 'estado']);

        if ($partidos->isEmpty()) {
            return ['El grupo no tiene partidos de la fase regular.'];
        }

        $problemas = [];
        $pendientes = $partidos->filter(fn (Partido $partido): bool => $partido->estado !== Partido::ESTADO_CANCELADO && ! ($partido->cedula?->esOficial() ?? false));
        if ($pendientes->isNotEmpty()) {
            $problemas[] = "La fase regular no ha terminado: faltan {$pendientes->count()} partido(s) con resultado oficial.";
        }

        $cubiertos = [];
        foreach ($partidos as $partido) {
            if ($partido->estado === Partido::ESTADO_CANCELADO || ($partido->cedula?->esOficial() ?? false)) {
                $par = [$partido->equipo_local_id, $partido->equipo_visitante_id];
                sort($par);
                $cubiertos[implode('-', $par)] = true;
            }
        }

        $equipos = Equipo::query()->where('grupo_id', $grupo->id)->orderBy('id')->pluck('id')->all();
        $faltan = 0;
        foreach ($equipos as $indice => $equipo) {
            foreach (array_slice($equipos, $indice + 1) as $rival) {
                $faltan += isset($cubiertos[$equipo.'-'.$rival]) ? 0 : 1;
            }
        }

        if ($faltan > 0) {
            $problemas[] = "El rol de «Todos contra todos» no está completo: faltan {$faltan} enfrentamiento(s) entre equipos del grupo.";
        }

        return $problemas;
    }

    /**
     * Equipos que comparten posición (#100): si el empate cruza el corte o está dentro de los clasificados, no se
     * decide por el nombre quién entra, contra quién juega ni quién es local; se avisa para que lo resuelva la Liga.
     *
     * @param  list<array{equipo_id: int, nombre: string, posicion: int}>  $tabla
     * @return list<string>
     */
    private function problemasDeEmpates(array $tabla, int $desde, int $hasta, string $tipo): array
    {
        $porPosicion = [];
        foreach ($tabla as $renglon) {
            $porPosicion[$renglon['posicion']][] = $renglon['nombre'];
        }

        $problemas = [];
        foreach ($porPosicion as $posicion => $nombres) {
            $ultimo = $posicion + count($nombres) - 1;

            // Solo importan los empates que tocan los lugares de esta fase
            if (count($nombres) < 2 || $ultimo < $desde || $posicion > $hasta) {
                continue;
            }

            $equipos = implode(', ', $nombres);
            $problemas[] = $posicion < $desde || $ultimo > $hasta
                ? 'Empate en el corte de la '.FaseTorneo::EN_TEXTO[$tipo].": {$equipos} comparten el {$posicion}.° lugar y no caben todos. Resuelvan el desempate antes de generar las llaves."
                : "Empate dentro de los clasificados: {$equipos} comparten el {$posicion}.° lugar y no se puede decidir quién enfrenta a quién ni quién es local. Resuelvan el desempate antes de generar las llaves.";
        }

        return $problemas;
    }

    /**
     * Guarda las llaves en la fase de la temporada, con su auditoría, en una sola transacción.
     *
     * @return Collection<int, LlaveLiguilla>
     */
    public function generar(Grupo $grupo, User $usuario, string $tipo = FaseTorneo::LIGUILLA): Collection
    {
        $this->auditoria->conMotivo(
            'Generación de las llaves de '.FaseTorneo::EN_TEXTO[$tipo].' del grupo '.$grupo->id,
            function () use ($grupo, $usuario, $tipo): void {
                $bloqueado = Grupo::query()->whereKey($grupo->getKey())->lockForUpdate()->firstOrFail();

                if (LlaveLiguilla::query()->where('grupo_id', $bloqueado->id)->whereHas('fase', fn ($fase) => $fase->where('tipo', $tipo))->exists()) {
                    throw ValidationException::withMessages(['liguilla' => 'Las llaves de '.FaseTorneo::EN_TEXTO[$tipo].' de este grupo ya se generaron.']);
                }

                // Antes de clasificar se recalcula la tabla con las reglas vigentes (decisión de Daniel, 23/11)
                if ($tipo !== FaseTorneo::CAMPEON_DE_CAMPEONES && $this->tablas->reglaVigenteOpcional($bloqueado) !== null) {
                    $this->tablas->recalcular($bloqueado);
                }

                $revision = $this->revisar($bloqueado, $tipo);

                if ($revision['problemas'] !== [] || $revision['regla'] === null) {
                    throw ValidationException::withMessages(['liguilla' => $revision['problemas']]);
                }

                // Una sola fase de cada tipo por temporada aunque dos grupos generen a la vez: se bloquea la temporada
                Temporada::query()->whereKey($bloqueado->temporada_id)->lockForUpdate()->firstOrFail();
                $fase = FaseTorneo::query()->firstOrCreate([
                    'temporada_id' => $bloqueado->temporada_id,
                    'tipo' => $tipo,
                ]);

                $regla = $revision['regla'];
                foreach ($revision['cruces'] as $cruce) {
                    LlaveLiguilla::create([
                        'fase_id' => $fase->id,
                        'grupo_id' => $bloqueado->id,
                        'orden' => $cruce['orden'],
                        'equipo_local_id' => $cruce['local']['equipo_id'],
                        'lugar_local' => $cruce['local']['posicion'],
                        'equipo_visitante_id' => $cruce['visitante']['equipo_id'] ?? null,
                        'lugar_visitante' => $cruce['visitante']['posicion'] ?? null,
                        // HU-056: el pase directo avanza sin jugar; en el Campeón de Campeones, el que ganó liga y liguilla
                        'avanza_equipo_id' => $cruce['visitante'] === null ? $cruce['local']['equipo_id'] : null,
                        'regla_temporada_id' => $regla->id,
                        'regla_version' => $regla->version,
                        'criterios_clasificacion' => $bloqueado->getAttribute('criterios_clasificacion'),
                        'generada_por_id' => $usuario->id,
                    ]);
                }
            }
        );

        return $this->llaves($grupo, $tipo);
    }
}
