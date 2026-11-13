<?php

namespace App\Services;

use App\Models\FaseTorneo;
use App\Models\Grupo;
use App\Models\LlaveLiguilla;
use App\Models\Partido;
use App\Models\ReglaTemporada;
use App\Models\TablaPosicion;
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
    public function __construct(private readonly AuditService $auditoria)
    {
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
     *
     * @return array{cruces: list<array{orden: int, local: TablaPosicion, visitante: TablaPosicion}>, problemas: list<string>, clasifican: int}
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

        $partidos = Partido::query()->whereHas('jornada', fn ($jornada) => $jornada->where('grupo_id', $grupo->id));
        $total = (clone $partidos)->count();
        $pendientes = $total - (clone $partidos)->whereHas('cedula', fn ($cedula) => $cedula->oficiales())->count();

        if ($total === 0) {
            $problemas[] = 'El grupo no tiene partidos de la fase regular.';
        } elseif ($pendientes > 0) {
            $problemas[] = "La fase regular no ha terminado: faltan {$pendientes} partido(s) con resultado oficial.";
        }

        $tabla = TablaPosicion::query()
            ->where('grupo_id', $grupo->id)
            ->with('equipo:id,nombre')
            ->orderBy('posicion')
            ->get();

        if ($clasifican >= 2 && $tabla->count() < $clasifican) {
            $problemas[] = "Clasifican {$clasifican} equipos, pero la tabla del grupo tiene {$tabla->count()}.";
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

        return ['cruces' => $cruces, 'problemas' => $problemas, 'clasifican' => $clasifican];
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

                $revision = $this->revisar($bloqueado);

                if ($revision['problemas'] !== []) {
                    throw ValidationException::withMessages(['liguilla' => $revision['problemas']]);
                }

                // La versión de las reglas es la que se usó para calcular la tabla final
                $regla = ReglaTemporada::query()
                    ->where('temporada_id', $bloqueado->temporada_id)
                    ->where('version', $revision['cruces'][0]['local']->regla_version)
                    ->first();

                $fase = FaseTorneo::query()->firstOrCreate([
                    'temporada_id' => $bloqueado->temporada_id,
                    'tipo' => FaseTorneo::LIGUILLA,
                ]);

                foreach ($revision['cruces'] as $cruce) {
                    LlaveLiguilla::create([
                        'fase_id' => $fase->id,
                        'grupo_id' => $bloqueado->id,
                        'orden' => $cruce['orden'],
                        'equipo_local_id' => $cruce['local']->equipo_id,
                        'lugar_local' => $cruce['local']->posicion,
                        'equipo_visitante_id' => $cruce['visitante']->equipo_id,
                        'lugar_visitante' => $cruce['visitante']->posicion,
                        'regla_temporada_id' => $regla?->id,
                        'regla_version' => $regla?->version,
                        'criterios_clasificacion' => $bloqueado->getAttribute('criterios_clasificacion'),
                        'generada_por_id' => $usuario->id,
                    ]);
                }
            }
        );

        return $this->llaves($grupo);
    }
}
