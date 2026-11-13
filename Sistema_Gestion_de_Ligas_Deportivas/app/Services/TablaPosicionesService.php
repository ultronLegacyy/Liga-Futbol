<?php

namespace App\Services;

use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Partido;
use App\Models\ReglaTemporada;
use App\Models\TablaPosicion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * HU-041 (RF-09.1): tabla de posiciones de un grupo calculada solo con resultados oficiales (HU-040)
 * y con los puntos y desempates de la versión vigente de las reglas de la temporada (HU-006).
 * El recálculo es atómico: se rehace la tabla completa o no cambia nada (RNF-22).
 */
class TablaPosicionesService
{
    // Criterios de desempate que admiten hoy las reglas de la temporada (StoreReglasTemporadaRequest)
    private const CAMPO_DESEMPATE = [
        'diferencia_goles' => 'dg',
        'goles_favor' => 'gf',
    ];

    /**
     * Rehace la tabla del grupo dentro de una transacción. Si ya hay una abierta (la validación de HU-040),
     * se anida en ella: una falla deshace también la validación.
     */
    public function recalcular(Grupo $grupo): void
    {
        DB::transaction(function () use ($grupo): void {
            // Un solo recálculo a la vez por grupo, para no mezclar dos validaciones simultáneas
            $bloqueado = Grupo::query()->whereKey($grupo->getKey())->lockForUpdate()->firstOrFail();
            $regla = $this->reglaVigente($bloqueado);

            TablaPosicion::query()->where('grupo_id', $bloqueado->id)->delete();

            foreach ($this->calcular($bloqueado, $regla) as $renglon) {
                TablaPosicion::create([
                    'grupo_id' => $bloqueado->id,
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
        });
    }

    /**
     * Calcula la tabla sin guardarla. Con $hastaJornada solo cuenta las jornadas con ese número o menor.
     *
     * @return list<array{equipo_id: int, nombre: string, posicion: int, pj: int, pg: int, pe: int, pp: int, gf: int, gc: int, dg: int, puntos: int}>
     */
    public function calcular(Grupo $grupo, ReglaTemporada $regla, ?int $hastaJornada = null): array
    {
        $puntos = $this->puntos($regla) ?? throw $this->sinReglas();

        /** @var array<int, array{equipo_id: int, nombre: string, posicion: int, pj: int, pg: int, pe: int, pp: int, gf: int, gc: int, dg: int, puntos: int}> $tabla */
        $tabla = [];
        foreach (Equipo::query()->where('grupo_id', $grupo->id)->orderBy('id')->get(['id', 'nombre']) as $equipo) {
            $tabla[$equipo->id] = [
                'equipo_id' => $equipo->id,
                'nombre' => (string) $equipo->nombre,
                'posicion' => 0,
                'pj' => 0, 'pg' => 0, 'pe' => 0, 'pp' => 0,
                'gf' => 0, 'gc' => 0, 'dg' => 0, 'puntos' => 0,
            ];
        }

        foreach ($this->partidosOficiales($grupo, $hastaJornada) as $partido) {
            $cedula = $partido->cedula;
            if ($cedula === null) {
                continue;
            }

            $local = $partido->equipo_local_id;
            $visitante = $partido->equipo_visitante_id;

            // Un partido contra un equipo de otro grupo no cuenta para esta tabla
            if (! isset($tabla[$local], $tabla[$visitante])) {
                continue;
            }

            $this->sumar($tabla[$local], $cedula->goles_local, $cedula->goles_visitante, $puntos);
            $this->sumar($tabla[$visitante], $cedula->goles_visitante, $cedula->goles_local, $puntos);
        }

        $criterios = $this->desempates($regla);
        $renglones = array_values($tabla);

        usort($renglones, function (array $a, array $b) use ($criterios): int {
            if ($a['puntos'] !== $b['puntos']) {
                return $b['puntos'] <=> $a['puntos'];
            }

            foreach ($criterios as $campo) {
                if ($a[$campo] !== $b[$campo]) {
                    return $b[$campo] <=> $a[$campo];
                }
            }

            // Sin más criterios, orden alfabético para que la tabla sea estable (HU-042 afina los desempates)
            return strcmp($a['nombre'], $b['nombre']) ?: $a['equipo_id'] <=> $b['equipo_id'];
        });

        foreach ($renglones as $indice => $renglon) {
            $renglones[$indice]['posicion'] = $indice + 1;
        }

        return $renglones;
    }

    /**
     * Tabla guardada del grupo, en orden de posición.
     *
     * @return Collection<int, TablaPosicion>
     */
    public function tabla(Grupo $grupo): Collection
    {
        return TablaPosicion::query()
            ->where('grupo_id', $grupo->id)
            ->with('equipo:id,nombre,club_id', 'equipo.club:id,nombre')
            ->orderBy('posicion')
            ->get();
    }

    // Versión más reciente de las reglas de la temporada del grupo; sin reglas no hay con qué dar puntos
    public function reglaVigente(Grupo $grupo): ReglaTemporada
    {
        $regla = ReglaTemporada::query()
            ->where('temporada_id', $grupo->temporada_id)
            ->orderByDesc('version')
            ->first();

        if ($regla === null || $this->puntos($regla) === null) {
            throw $this->sinReglas();
        }

        return $regla;
    }

    private function sinReglas(): ValidationException
    {
        return ValidationException::withMessages([
            'resultado' => 'La temporada no tiene reglas de puntos configuradas. Regístralas en «Reglas de la temporada» antes de validar resultados.',
        ]);
    }

    /**
     * Partidos del grupo con resultado oficial (cédula cerrada y validada).
     *
     * @return Collection<int, Partido>
     */
    private function partidosOficiales(Grupo $grupo, ?int $hastaJornada): Collection
    {
        return Partido::query()
            ->whereHas('jornada', function ($jornada) use ($grupo, $hastaJornada): void {
                $jornada->where('grupo_id', $grupo->id);

                if ($hastaJornada !== null) {
                    $jornada->where('numero_jornada', '<=', $hastaJornada);
                }
            })
            ->whereHas('cedula', fn ($cedula) => $cedula->oficiales())
            ->with('cedula:id,partido_id,goles_local,goles_visitante')
            ->get(['id', 'jornada_id', 'equipo_local_id', 'equipo_visitante_id']);
    }

    /**
     * @param  array{equipo_id: int, nombre: string, posicion: int, pj: int, pg: int, pe: int, pp: int, gf: int, gc: int, dg: int, puntos: int}  $renglon
     * @param  array{victoria: int, empate: int, derrota: int}  $puntos
     */
    private function sumar(array &$renglon, int $aFavor, int $enContra, array $puntos): void
    {
        $renglon['pj']++;
        $renglon['gf'] += $aFavor;
        $renglon['gc'] += $enContra;
        $renglon['dg'] = $renglon['gf'] - $renglon['gc'];

        if ($aFavor > $enContra) {
            $renglon['pg']++;
            $renglon['puntos'] += $puntos['victoria'];
        } elseif ($aFavor === $enContra) {
            $renglon['pe']++;
            $renglon['puntos'] += $puntos['empate'];
        } else {
            $renglon['pp']++;
            $renglon['puntos'] += $puntos['derrota'];
        }
    }

    /**
     * @return array{victoria: int, empate: int, derrota: int}|null
     */
    private function puntos(ReglaTemporada $regla): ?array
    {
        $clasificacion = $this->contenido($regla)['clasificacion'] ?? null;

        if (! is_array($clasificacion)) {
            return null;
        }

        foreach (['puntos_victoria', 'puntos_empate', 'puntos_derrota'] as $campo) {
            if (! is_int($clasificacion[$campo] ?? null)) {
                return null;
            }
        }

        return [
            'victoria' => $clasificacion['puntos_victoria'],
            'empate' => $clasificacion['puntos_empate'],
            'derrota' => $clasificacion['puntos_derrota'],
        ];
    }

    /**
     * Campos de la tabla en el orden de desempate configurado; se ignoran los que no se conocen.
     *
     * @return list<'dg'|'gf'>
     */
    private function desempates(ReglaTemporada $regla): array
    {
        $criterios = $this->contenido($regla)['desempates'] ?? [];
        $campos = [];

        foreach (is_array($criterios) ? $criterios : [] as $criterio) {
            if (is_string($criterio) && isset(self::CAMPO_DESEMPATE[$criterio])) {
                $campos[] = self::CAMPO_DESEMPATE[$criterio];
            }
        }

        return array_values(array_unique($campos));
    }

    /**
     * Contenido JSON de la versión de reglas (el cast lo entrega como arreglo).
     *
     * @return array<mixed>
     */
    private function contenido(ReglaTemporada $regla): array
    {
        $reglas = $regla->getAttribute('reglas');

        return is_array($reglas) ? $reglas : [];
    }
}
