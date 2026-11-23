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
 * Cada llave conserva la versión de las reglas y los criterios con que se generó (CA2, RNF-36).
 */
class LiguillaService
{
    public function __construct(
        private readonly AuditService $auditoria,
        private readonly TablaPosicionesService $tablas,
    ) {
    }

    /**
     * Llaves ya generadas del grupo, en orden.
     *
     * @return Collection<int, LlaveLiguilla>
     */
    public function llaves(Grupo $grupo): Collection
    {
        return LlaveLiguilla::query()
            ->where('grupo_id', $grupo->id)
            ->with(['local:id,nombre', 'visitante:id,nombre', 'regla:id,version', 'generadaPor:id,name'])
            ->orderBy('orden')
            ->get();
    }

    /**
     * Revisa si el grupo puede pasar a liguilla y, si puede, propone los cruces sin guardarlos.
     * La tabla se calcula con la versión vigente de las reglas, aunque se haya publicado después del último
     * resultado (decisión de Daniel, 23/11); en la vista previa no se guarda nada.
     *
     * @return array{cruces: list<array{orden: int, local: array{equipo_id: int, nombre: string, posicion: int}, visitante: array{equipo_id: int, nombre: string, posicion: int}}>, problemas: list<string>, clasifican: int, regla: ReglaTemporada|null}
     */
    public function revisar(Grupo $grupo): array
    {
        $criterios = $grupo->getAttribute('criterios_clasificacion');
        $clasifican = is_array($criterios) ? (int) ($criterios['clasifican_liguilla'] ?? 0) : 0;
        $problemas = [];

        if ($clasifican < 2) {
            $problemas[] = 'Configura en «Formato y clasificación» cuántos equipos del grupo clasifican a la liguilla (al menos 2).';
        } elseif ($clasifican % 2 !== 0) {
            $problemas[] = 'El número de equipos que clasifican debe ser par para formar los cruces (por ejemplo, 1° vs 8°).';
        }

        array_push($problemas, ...$this->problemasDeLaFaseRegular($grupo));

        $regla = $this->tablas->reglaVigenteOpcional($grupo);
        if ($regla === null) {
            $problemas[] = 'La temporada no tiene reglas de puntos configuradas para calcular la tabla final.';
        }

        $tabla = $regla === null ? [] : $this->tablas->calcular($grupo, $regla);

        if ($clasifican >= 2 && count($tabla) < $clasifican) {
            $problemas[] = "Clasifican {$clasifican} equipos, pero la tabla del grupo tiene ".count($tabla).'.';
        } elseif ($clasifican >= 2 && $clasifican % 2 === 0) {
            array_push($problemas, ...$this->problemasDeEmpates($tabla, $clasifican));
        }

        $cruces = [];
        if ($problemas === []) {
            for ($lugar = 1; $lugar <= intdiv($clasifican, 2); $lugar++) {
                $cruces[] = [
                    'orden' => $lugar,
                    'local' => $tabla[$lugar - 1],
                    'visitante' => $tabla[$clasifican - $lugar],
                ];
            }
        }

        return ['cruces' => $cruces, 'problemas' => $problemas, 'clasifican' => $clasifican, 'regla' => $regla];
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
            ->whereHas('jornada', fn ($jornada) => $jornada->where('grupo_id', $grupo->id))
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
    private function problemasDeEmpates(array $tabla, int $clasifican): array
    {
        $porPosicion = [];
        foreach (array_slice($tabla, 0, $clasifican + 1) as $renglon) {
            $porPosicion[$renglon['posicion']][] = $renglon['nombre'];
        }

        $problemas = [];
        foreach ($porPosicion as $posicion => $nombres) {
            if (count($nombres) > 1) {
                $equipos = implode(', ', $nombres);
                $problemas[] = $posicion + count($nombres) - 1 > $clasifican
                    ? "Empate en el corte de la liguilla: {$equipos} comparten el {$posicion}.° lugar y no caben todos. Resuelvan el desempate antes de generar las llaves."
                    : "Empate dentro de los clasificados: {$equipos} comparten el {$posicion}.° lugar y no se puede decidir quién enfrenta a quién ni quién es local. Resuelvan el desempate antes de generar las llaves.";
            }
        }

        return $problemas;
    }

    /**
     * Guarda las llaves en la fase Liguilla de la temporada, con su auditoría, en una sola transacción.
     *
     * @return Collection<int, LlaveLiguilla>
     */
    public function generar(Grupo $grupo, User $usuario): Collection
    {
        $this->auditoria->conMotivo(
            'Generación de las llaves de liguilla del grupo '.$grupo->id,
            function () use ($grupo, $usuario): void {
                $bloqueado = Grupo::query()->whereKey($grupo->getKey())->lockForUpdate()->firstOrFail();

                if (LlaveLiguilla::query()->where('grupo_id', $bloqueado->id)->exists()) {
                    throw ValidationException::withMessages(['liguilla' => 'Las llaves de liguilla de este grupo ya se generaron.']);
                }

                // Antes de clasificar se recalcula la tabla con las reglas vigentes (decisión de Daniel, 23/11)
                if ($this->tablas->reglaVigenteOpcional($bloqueado) !== null) {
                    $this->tablas->recalcular($bloqueado);
                }

                $revision = $this->revisar($bloqueado);

                if ($revision['problemas'] !== [] || $revision['regla'] === null) {
                    throw ValidationException::withMessages(['liguilla' => $revision['problemas']]);
                }

                // Una sola fase «Liguilla» por temporada aunque dos grupos generen a la vez: se bloquea la temporada
                Temporada::query()->whereKey($bloqueado->temporada_id)->lockForUpdate()->firstOrFail();
                $fase = FaseTorneo::query()->firstOrCreate([
                    'temporada_id' => $bloqueado->temporada_id,
                    'tipo' => FaseTorneo::LIGUILLA,
                ]);

                $regla = $revision['regla'];
                foreach ($revision['cruces'] as $cruce) {
                    LlaveLiguilla::create([
                        'fase_id' => $fase->id,
                        'grupo_id' => $bloqueado->id,
                        'orden' => $cruce['orden'],
                        'equipo_local_id' => $cruce['local']['equipo_id'],
                        'lugar_local' => $cruce['local']['posicion'],
                        'equipo_visitante_id' => $cruce['visitante']['equipo_id'],
                        'lugar_visitante' => $cruce['visitante']['posicion'],
                        'regla_temporada_id' => $regla->id,
                        'regla_version' => $regla->version,
                        'criterios_clasificacion' => $bloqueado->getAttribute('criterios_clasificacion'),
                        'generada_por_id' => $usuario->id,
                    ]);
                }
            }
        );

        return $this->llaves($grupo);
    }
}
