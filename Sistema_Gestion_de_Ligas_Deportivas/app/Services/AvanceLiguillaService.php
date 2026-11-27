<?php

namespace App\Services;

use App\Models\CedulaArbitral;
use App\Models\Equipo;
use App\Models\FaseTorneo;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\LlaveLiguilla;
use App\Models\Partido;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * HU-056 (RF-11.2): avances, eliminaciones, campeón y subcampeón de la liguilla.
 * - Cada llave se juega en un solo partido. Avanza el ganador del resultado oficial (cédula cerrada y validada, HU-040);
 *   si empatan, el mejor ubicado de la tabla general de la fase regular. No hay penales (decisión 3 de Daniel).
 * - La llave se resuelve al validar el resultado (oyente de ResultadoValidado, en la misma transacción).
 * - Cada ronda la crea el Presidente o el Administrador General (decisión 4): una jornada de liguilla con un partido
 *   en borrador por llave, que el Programador de Partidos programa como cualquier otro.
 * - Desde la segunda ronda se re-siembra: el mejor ubicado de los que avanzaron contra el peor (decisión de Daniel, 25/11).
 * HU-060: la liguilla de consolación y el Campeón de Campeones avanzan igual, cada uno con las llaves de su fase.
 */
class AvanceLiguillaService
{
    private const NOMBRES_RONDA = [2 => 'Final', 4 => 'Semifinales', 8 => 'Cuartos de final', 16 => 'Octavos de final'];

    public function __construct(private readonly AuditService $auditoria)
    {
    }

    /**
     * Criterio 1: resuelve la llave del partido cuyo resultado se acaba de validar. Corre dentro de la transacción de
     * la validación, que ya bloqueó la cédula; se bloquea el grupo y luego la llave, el mismo orden de la tabla.
     */
    public function resolverLlave(CedulaArbitral $cedula): void
    {
        $llave = LlaveLiguilla::query()->where('partido_id', $cedula->partido_id)->first(['id', 'grupo_id']);
        $partido = $cedula->partido;

        if ($llave === null || $partido === null) {
            return;
        }

        Grupo::query()->whereKey($llave->grupo_id)->lockForUpdate()->firstOrFail();
        $bloqueada = LlaveLiguilla::query()->whereKey($llave->id)->lockForUpdate()->firstOrFail();

        // Una llave resuelta no avanza a nadie otra vez
        if ($bloqueada->estaResuelta()) {
            return;
        }

        $goles = [
            $partido->equipo_local_id => $cedula->goles_local,
            $partido->equipo_visitante_id => $cedula->goles_visitante,
        ];
        $local = $goles[$bloqueada->equipo_local_id] ?? 0;
        $visitante = $goles[(int) $bloqueada->equipo_visitante_id] ?? 0;

        if ($local !== $visitante) {
            $avanza = $local > $visitante ? $bloqueada->equipo_local_id : $bloqueada->equipo_visitante_id;
        } else {
            $avanza = ($bloqueada->lugar_local ?? PHP_INT_MAX) <= ($bloqueada->lugar_visitante ?? PHP_INT_MAX)
                ? $bloqueada->equipo_local_id
                : $bloqueada->equipo_visitante_id;
        }

        // Auditable lo deja en la bitácora con el motivo de la validación
        $bloqueada->update([
            'marcador_global_local' => $local,
            'marcador_global_visitante' => $visitante,
            'avanza_equipo_id' => $avanza,
        ]);
    }

    /**
     * La ronda que se puede crear ahora o, si no se puede, por qué.
     *
     * @param  Collection<int, LlaveLiguilla>  $llaves  todas las llaves del grupo en la fase
     * @return array{ronda: int|null, aviso: string|null}
     */
    public function proximaRonda(Collection $llaves, string $tipo = FaseTorneo::LIGUILLA): array
    {
        if ($llaves->isEmpty()) {
            return ['ronda' => null, 'aviso' => 'Primero se generan las llaves de '.FaseTorneo::EN_TEXTO[$tipo].' del grupo.'];
        }

        $ultima = (int) $llaves->max('ronda');
        $deLaRonda = $llaves->where('ronda', $ultima);

        if ($deLaRonda->contains(fn (LlaveLiguilla $llave): bool => ! $llave->esPaseDirecto() && $llave->partido_id === null)) {
            return ['ronda' => $ultima, 'aviso' => null];
        }

        $pendientes = $deLaRonda->reject(fn (LlaveLiguilla $llave): bool => $llave->estaResuelta())->count();
        if ($pendientes > 0) {
            return ['ronda' => null, 'aviso' => "La ronda {$ultima} tiene {$pendientes} llave(s) sin resultado oficial. La siguiente ronda se crea cuando todas estén resueltas."];
        }

        if ($deLaRonda->count() === 1) {
            return ['ronda' => null, 'aviso' => match ($tipo) {
                FaseTorneo::CAMPEON_DE_CAMPEONES => 'El Campeón de Campeones ya está definido.',
                FaseTorneo::CONSOLACION => 'La liguilla de consolación terminó: la final ya tiene resultado oficial.',
                default => 'La liguilla terminó: la final ya tiene resultado oficial.',
            }];
        }

        return ['ronda' => $ultima + 1, 'aviso' => null];
    }

    /**
     * Crea la jornada de la ronda con un partido en borrador por llave. Desde la segunda ronda, antes crea las llaves
     * con los que avanzaron. Todo en una transacción con el grupo bloqueado: dos envíos a la vez crean una sola ronda.
     */
    public function crearRonda(Grupo $grupo, string $fechaInicio, string $fechaFin, User $usuario, string $tipo = FaseTorneo::LIGUILLA): int
    {
        try {
            return $this->auditoria->conMotivo(
                'Creación de una ronda de '.FaseTorneo::EN_TEXTO[$tipo].' del grupo '.$grupo->id,
                function () use ($grupo, $fechaInicio, $fechaFin, $usuario, $tipo): int {
                    $bloqueado = Grupo::query()->whereKey($grupo->getKey())->lockForUpdate()->firstOrFail();
                    $llaves = LlaveLiguilla::query()
                        ->where('grupo_id', $bloqueado->id)
                        ->whereHas('fase', fn ($fase) => $fase->where('tipo', $tipo))
                        ->orderBy('orden')
                        ->lockForUpdate()
                        ->get();

                    ['ronda' => $ronda, 'aviso' => $aviso] = $this->proximaRonda($llaves, $tipo);
                    if ($ronda === null) {
                        throw ValidationException::withMessages(['ronda' => $aviso]);
                    }

                    $deLaRonda = $ronda > (int) $llaves->max('ronda')
                        ? $this->armarRonda($llaves, $ronda, $usuario)
                        : $llaves->where('ronda', $ronda);

                    $jornada = Jornada::create([
                        'grupo_id' => $bloqueado->id,
                        'fase_id' => $llaves->first()?->fase_id,
                        'numero_jornada' => (int) Jornada::query()->where('grupo_id', $bloqueado->id)->max('numero_jornada') + 1,
                        'fecha_inicio' => $fechaInicio,
                        'fecha_fin' => $fechaFin,
                    ]);

                    foreach ($deLaRonda as $llave) {
                        if ($llave->esPaseDirecto()) {
                            continue;
                        }

                        $partido = Partido::create([
                            'jornada_id' => $jornada->id,
                            'equipo_local_id' => $llave->equipo_local_id,
                            'equipo_visitante_id' => $llave->equipo_visitante_id,
                            'estado' => Partido::ESTADO_BORRADOR,
                        ]);
                        $llave->update(['partido_id' => $partido->id]);
                    }

                    return $ronda;
                }
            );
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['ronda' => 'Esta ronda de '.FaseTorneo::EN_TEXTO[$tipo].' ya se creó. Recarga la página para verla.']);
        }
    }

    /**
     * Llaves de la ronda siguiente: los que avanzaron, ordenados por su lugar en la tabla general, el mejor contra el
     * peor; el mejor ubicado es local. Conservan las reglas y los criterios de la primera ronda (RNF-36).
     *
     * @param  Collection<int, LlaveLiguilla>  $llaves
     * @return Collection<int, LlaveLiguilla>
     */
    private function armarRonda(Collection $llaves, int $ronda, User $usuario): Collection
    {
        $avanzan = $llaves->where('ronda', $ronda - 1)
            ->map(fn (LlaveLiguilla $llave): array => ['equipo_id' => (int) $llave->avanza_equipo_id, 'lugar' => $llave->lugarDelQueAvanza() ?? PHP_INT_MAX])
            ->sortBy('lugar')
            ->values();

        $total = $avanzan->count();
        if ($total % 2 !== 0) {
            throw ValidationException::withMessages(['ronda' => "No se puede armar la ronda {$ronda}: avanzaron {$total} equipos y deben ser pares."]);
        }

        $base = $llaves->first();
        $orden = (int) $llaves->max('orden');
        $nuevas = new Collection();

        for ($i = 0; $i < intdiv($total, 2); $i++) {
            $local = $avanzan[$i];
            $visitante = $avanzan[$total - 1 - $i];
            $nuevas->push(LlaveLiguilla::create([
                'fase_id' => $base?->fase_id,
                'grupo_id' => $base?->grupo_id,
                'orden' => $orden + $i + 1,
                'ronda' => $ronda,
                'equipo_local_id' => $local['equipo_id'],
                'lugar_local' => $local['lugar'],
                'equipo_visitante_id' => $visitante['equipo_id'],
                'lugar_visitante' => $visitante['lugar'],
                'regla_temporada_id' => $base?->regla_temporada_id,
                'regla_version' => $base?->regla_version,
                'criterios_clasificacion' => $base?->criterios_clasificacion,
                'generada_por_id' => $usuario->id,
            ]));
        }

        return $nuevas;
    }

    /**
     * Criterio 2: campeón y subcampeón cuando la final tiene resultado oficial.
     *
     * @param  Collection<int, LlaveLiguilla>  $llaves  todas las llaves del grupo, con sus equipos
     * @return array{campeon: Equipo, subcampeon: Equipo}|null
     */
    public function campeones(Collection $llaves): ?array
    {
        $final = $llaves->where('ronda', (int) $llaves->max('ronda'));
        $llave = $final->first();

        if ($final->count() !== 1 || $llave === null || $llave->esPaseDirecto() || ! $llave->estaResuelta()) {
            return null;
        }

        $campeon = $llave->avanza_equipo_id === $llave->equipo_local_id ? $llave->local : $llave->visitante;
        $subcampeon = $llave->avanza_equipo_id === $llave->equipo_local_id ? $llave->visitante : $llave->local;

        return $campeon !== null && $subcampeon !== null ? ['campeon' => $campeon, 'subcampeon' => $subcampeon] : null;
    }

    /**
     * Nombre de la ronda por los equipos que la juegan: Final, Semifinales, Cuartos u Octavos de final.
     *
     * @param  Collection<int, LlaveLiguilla>  $deLaRonda
     */
    public function nombreRonda(Collection $deLaRonda, int $ronda, string $tipo = FaseTorneo::LIGUILLA): string
    {
        if ($tipo === FaseTorneo::CAMPEON_DE_CAMPEONES) {
            return FaseTorneo::NOMBRES[$tipo];
        }

        $equipos = $deLaRonda->sum(fn (LlaveLiguilla $llave): int => $llave->esPaseDirecto() ? 1 : 2);
        $tienePaseDirecto = $deLaRonda->contains(fn (LlaveLiguilla $llave): bool => $llave->esPaseDirecto());

        return $tienePaseDirecto ? "Ronda {$ronda}" : (self::NOMBRES_RONDA[$equipos] ?? "Ronda {$ronda}");
    }
}
