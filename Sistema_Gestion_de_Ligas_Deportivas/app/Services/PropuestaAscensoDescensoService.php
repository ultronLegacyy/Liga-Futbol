<?php

namespace App\Services;

use App\Models\Grupo;
use App\Models\Partido;
use App\Models\PropuestaAscensoDescenso;
use App\Models\ReglaTemporada;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * HU-057 (RF-11.3): propuestas de ascenso y descenso de un grupo al cierre de su temporada.
 * CA1: cuántos equipos suben y bajan sale de la sección ascenso_descenso de las reglas vigentes (HU-006), y el orden
 * de la tabla final calculada con esas mismas reglas (HU-041/HU-042).
 * CA2: cada propuesta es una versión nueva que guarda la versión de las reglas y una copia de su sección; un cambio
 * posterior de las reglas no la toca. Aplicar el cambio de categoría queda para HU-058 y HU-059.
 */
class PropuestaAscensoDescensoService
{
    public function __construct(
        private readonly AuditService $auditoria,
        private readonly TablaPosicionesService $tablas,
    ) {
    }

    /**
     * Revisa si se puede proponer y arma la propuesta sin guardarla.
     *
     * @return array{asciende: list<array{equipo_id: int, nombre: string, posicion: int, puntos: int}>, desciende: list<array{equipo_id: int, nombre: string, posicion: int, puntos: int}>, problemas: list<string>, regla: ReglaTemporada|null}
     */
    public function revisar(Grupo $grupo): array
    {
        $problemas = [];
        $regla = $this->tablas->reglaVigenteOpcional($grupo);

        if ($regla === null) {
            return ['asciende' => [], 'desciende' => [], 'problemas' => ['La temporada no tiene reglas de puntos configuradas para calcular la tabla final.'], 'regla' => null];
        }

        $seccion = $this->seccion($regla);
        $ascienden = $seccion['equipos_ascienden'];
        $descienden = $seccion['equipos_descienden'];

        if ($ascienden === 0 && $descienden === 0) {
            $problemas[] = 'Las reglas de la temporada no tienen equipos que asciendan ni que desciendan.';
        }

        // Cierre del grupo: ningún partido pendiente (los cancelados no cuentan) y al menos un resultado oficial
        $partidos = Partido::query()
            ->whereHas('jornada', fn ($jornada) => $jornada->where('grupo_id', $grupo->id))
            ->where('estado', '!=', Partido::ESTADO_CANCELADO)
            ->with('cedula:id,partido_id,estado,validada_en')
            ->get(['id', 'jornada_id', 'estado']);
        $pendientes = $partidos->filter(fn (Partido $partido): bool => ! ($partido->cedula?->esOficial() ?? false))->count();

        if ($partidos->isEmpty()) {
            $problemas[] = 'El grupo no tiene partidos con resultado oficial.';
        } elseif ($pendientes > 0) {
            $problemas[] = "La temporada del grupo no ha terminado: faltan {$pendientes} partido(s) con resultado oficial.";
        }

        $tabla = $this->tablas->calcular($grupo, $regla);
        $total = count($tabla);

        if ($ascienden + $descienden > $total) {
            $problemas[] = "Las reglas piden {$ascienden} ascenso(s) y {$descienden} descenso(s), pero el grupo tiene {$total} equipo(s).";
        } else {
            array_push($problemas, ...$this->empatesEnElCorte($tabla, $ascienden, 'ascenso'));
            array_push($problemas, ...$this->empatesEnElCorte($tabla, $total - $descienden, 'descenso'));
        }

        $renglon = fn (array $fila): array => ['equipo_id' => $fila['equipo_id'], 'nombre' => $fila['nombre'], 'posicion' => $fila['posicion'], 'puntos' => $fila['puntos']];

        return [
            'asciende' => $problemas === [] ? array_map($renglon, array_slice($tabla, 0, $ascienden)) : [],
            'desciende' => $problemas === [] ? array_map($renglon, array_slice($tabla, $total - $descienden)) : [],
            'problemas' => $problemas,
            'regla' => $regla,
        ];
    }

    /**
     * Guarda una versión nueva de la propuesta, con su auditoría, en una sola transacción.
     */
    public function generar(Grupo $grupo, User $usuario): int
    {
        return $this->auditoria->conMotivo(
            'Propuesta de ascenso y descenso del grupo '.$grupo->id,
            function () use ($grupo, $usuario): int {
                $bloqueado = Grupo::query()->whereKey($grupo->getKey())->lockForUpdate()->firstOrFail();
                $revision = $this->revisar($bloqueado);
                $regla = $revision['regla'];

                if ($revision['problemas'] !== [] || $regla === null) {
                    throw ValidationException::withMessages(['propuesta' => $revision['problemas']]);
                }

                $version = (int) PropuestaAscensoDescenso::query()->where('grupo_id', $bloqueado->id)->max('version') + 1;
                $seccion = $this->seccion($regla);

                foreach ([PropuestaAscensoDescenso::ASCIENDE => $revision['asciende'], PropuestaAscensoDescenso::DESCIENDE => $revision['desciende']] as $movimiento => $equipos) {
                    foreach ($equipos as $equipo) {
                        PropuestaAscensoDescenso::create([
                            'grupo_id' => $bloqueado->id,
                            'version' => $version,
                            'equipo_id' => $equipo['equipo_id'],
                            'movimiento' => $movimiento,
                            'posicion' => $equipo['posicion'],
                            'puntos' => $equipo['puntos'],
                            'regla_temporada_id' => $regla->id,
                            'regla_version' => $regla->version,
                            'reglas_ascenso_descenso' => $seccion,
                            'generada_por_id' => $usuario->id,
                        ]);
                    }
                }

                return $version;
            }
        );
    }

    /**
     * Todas las versiones guardadas del grupo, la más reciente primero.
     *
     * @return \Illuminate\Support\Collection<int, Collection<int, PropuestaAscensoDescenso>>
     */
    public function versiones(Grupo $grupo): \Illuminate\Support\Collection
    {
        return PropuestaAscensoDescenso::query()
            ->where('grupo_id', $grupo->id)
            ->with(['equipo:id,nombre', 'generadaPor:id,name'])
            ->orderByDesc('version')
            ->orderBy('posicion')
            ->get()
            ->groupBy('version');
    }

    /**
     * @return array{equipos_ascienden: int, equipos_descienden: int}
     */
    private function seccion(ReglaTemporada $regla): array
    {
        $reglas = $regla->getAttribute('reglas');
        $seccion = is_array($reglas) && is_array($reglas['ascenso_descenso'] ?? null) ? $reglas['ascenso_descenso'] : [];

        return [
            'equipos_ascienden' => max(0, (int) ($seccion['equipos_ascienden'] ?? 0)),
            'equipos_descienden' => max(0, (int) ($seccion['equipos_descienden'] ?? 0)),
        ];
    }

    /**
     * Si dos equipos comparten posición justo en el corte, el nombre no decide quién sube o baja: se avisa.
     *
     * @param  list<array{equipo_id: int, nombre: string, posicion: int}>  $tabla
     * @return list<string>
     */
    private function empatesEnElCorte(array $tabla, int $corte, string $tipo): array
    {
        if ($corte <= 0 || $corte >= count($tabla) || $tabla[$corte - 1]['posicion'] !== $tabla[$corte]['posicion']) {
            return [];
        }

        $posicion = $tabla[$corte]['posicion'];
        $empatados = array_column(array_filter($tabla, fn (array $fila): bool => $fila['posicion'] === $posicion), 'nombre');

        return ["Empate en el corte de {$tipo}: ".implode(', ', $empatados)." comparten el {$posicion}.° lugar. Resuelvan el desempate antes de generar la propuesta."];
    }
}
