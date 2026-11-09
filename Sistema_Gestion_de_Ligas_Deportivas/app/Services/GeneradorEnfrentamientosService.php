<?php

namespace App\Services;

use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\Partido;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Motor algorítmico Round-Robin / Sistema Berger para generación automática de enfrentamientos (HU-032, RF-07.1)
class GeneradorEnfrentamientosService
{
    public function __construct(
        private readonly AuditService $auditService
    ) {
    }

    /**
     * Valida que el grupo y su temporada cumplan las condiciones para calendarización.
     *
     * @throws ValidationException
     */
    public function validarGrupoParaGeneracion(Grupo $grupo): void
    {
        $grupo->loadMissing(['categoria.temporada', 'equipos']);

        if ($grupo->estaCerrado()) {
            throw ValidationException::withMessages([
                'grupo' => 'Una temporada cerrada no admite la generación de enfrentamientos.',
            ]);
        }

        if ($grupo->equipos->count() < 2) {
            throw ValidationException::withMessages([
                'grupo' => 'El grupo debe contar con al menos 2 equipos inscritos para generar enfrentamientos.',
            ]);
        }
    }

    /**
     * Calcula la matriz de enfrentamientos empleando el algoritmo Round-Robin (Sistema Berger).
     *
     * En grupos con número impar de equipos, se agrega un comodín nulo para determinar
     * de forma rotativa y equitativa el equipo en descanso por jornada (HU-028, RF-06.2).
     *
     * @param  array<int, int|string>  $equiposIds
     * @return array<int, array{numero_jornada: int, equipo_descanso_id: int|null, partidos: array<int, array{equipo_local_id: int, equipo_visitante_id: int}>}>
     */
    public function calcularRolBerger(array $equiposIds, int $vueltas = 1, bool $aleatorizar = false): array
    {
        $n = count($equiposIds);
        if ($n < 2) {
            return [];
        }

        $lista = array_values($equiposIds);
        if ($aleatorizar) {
            shuffle($lista);
        }

        $esImpar = ($n % 2 !== 0);
        if ($esImpar) {
            $lista[] = null;
        }

        /** @var array<int, int|null> $actual */
        $actual = $lista;
        $totalEquipos = count($actual);
        $numRondasPorVuelta = $totalEquipos - 1;
        $mitad = (int) ($totalEquipos / 2);

        $rolPrimeraVuelta = [];

        for ($r = 0; $r < $numRondasPorVuelta; $r++) {
            $jornadaNum = $r + 1;
            $partidos = [];
            $descanso = null;

            for ($i = 0; $i < $mitad; $i++) {
                $t1 = $actual[$i];
                $t2 = $actual[$totalEquipos - 1 - $i];

                // Alternancia de localías para balancear partidos casa/fuera (Sistema Berger)
                if (($i + $r) % 2 === 0) {
                    $local = $t1;
                    $visitante = $t2;
                } else {
                    $local = $t2;
                    $visitante = $t1;
                }

                if ($local === null) {
                    $descanso = $visitante !== null ? (int) $visitante : null;
                } elseif ($visitante === null) {
                    $descanso = (int) $local;
                } else {
                    $partidos[] = [
                        'equipo_local_id' => (int) $local,
                        'equipo_visitante_id' => (int) $visitante,
                    ];
                }
            }

            $rolPrimeraVuelta[] = [
                'numero_jornada' => $jornadaNum,
                'equipo_descanso_id' => $descanso,
                'partidos' => $partidos,
            ];

            // Rotación fija de Berger: elemento 0 constante, el resto rota en sentido horario
            $fijo = $actual[0];
            $rotables = array_slice($actual, 1);
            $ultimo = array_pop($rotables);
            array_unshift($rotables, $ultimo);
            $actual = array_merge([$fijo], $rotables);
        }

        $resultado = $rolPrimeraVuelta;

        // Segunda vuelta (ida y vuelta / revanchas): se invierten las localías
        if ($vueltas === 2) {
            foreach ($rolPrimeraVuelta as $ronda) {
                $jornadaNum = $ronda['numero_jornada'] + $numRondasPorVuelta;
                $partidosSegunda = [];
                foreach ($ronda['partidos'] as $p) {
                    $partidosSegunda[] = [
                        'equipo_local_id' => $p['equipo_visitante_id'],
                        'equipo_visitante_id' => $p['equipo_local_id'],
                    ];
                }

                $resultado[] = [
                    'numero_jornada' => $jornadaNum,
                    'equipo_descanso_id' => $ronda['equipo_descanso_id'],
                    'partidos' => $partidosSegunda,
                ];
            }
        }

        return $resultado;
    }

    /**
     * Genera la propuesta completa del rol regular para previsualización.
     *
     * @param  array{fecha_inicio?: string|null, intervalo_dias?: int|string|null, dias_duracion?: int|string|null, vueltas?: int|string|null, aleatorizar?: bool|int|string|null}  $opciones
     * @return array{grupo_id: int, grupo_nombre: string, categoria_nombre: string, temporada_nombre: string, total_equipos: int, total_jornadas: int, vueltas: int, jornadas: array<int, array{numero_jornada: int, fecha_inicio: string, fecha_fin: string, equipo_descanso_id: int|null, equipo_descanso_nombre: string|null, partidos: array<int, array{equipo_local_id: int, equipo_local_nombre: string, equipo_visitante_id: int, equipo_visitante_nombre: string}>}>}
     *
     * @throws ValidationException
     */
    public function generarPropuestaRol(Grupo $grupo, array $opciones = []): array
    {
        $this->validarGrupoParaGeneracion($grupo);

        /** @var Collection<int, Equipo> $equipos */
        $equipos = $grupo->equipos()->orderBy('nombre')->get();
        $equiposMap = $equipos->keyBy('id');
        $equiposIds = $equipos->pluck('id')->all();

        $vueltas = isset($opciones['vueltas']) ? (int) $opciones['vueltas'] : 1;
        if (! in_array($vueltas, [1, 2], true)) {
            $vueltas = 1;
        }

        $aleatorizar = ! empty($opciones['aleatorizar']);
        $rolCalculado = $this->calcularRolBerger($equiposIds, $vueltas, $aleatorizar);

        $fechaBase = ! empty($opciones['fecha_inicio'])
            ? Carbon::parse($opciones['fecha_inicio'])
            : Carbon::now()->startOfWeek();

        $intervalo = isset($opciones['intervalo_dias']) ? max(1, (int) $opciones['intervalo_dias']) : 7;
        $duracion = isset($opciones['dias_duracion']) ? max(0, (int) $opciones['dias_duracion']) : 2;

        $jornadasFormateadas = [];

        foreach ($rolCalculado as $indice => $ronda) {
            $inicioJornada = $fechaBase->copy()->addDays($indice * $intervalo);
            $finJornada = $inicioJornada->copy()->addDays($duracion);

            $descansoId = $ronda['equipo_descanso_id'];
            $descansoNombre = ($descansoId !== null && isset($equiposMap[$descansoId]))
                ? $equiposMap[$descansoId]->nombre
                : null;

            $partidosDetalle = [];
            foreach ($ronda['partidos'] as $p) {
                $localId = $p['equipo_local_id'];
                $visitanteId = $p['equipo_visitante_id'];

                $partidosDetalle[] = [
                    'equipo_local_id' => $localId,
                    'equipo_local_nombre' => $equiposMap[$localId]->nombre ?? "Equipo #{$localId}",
                    'equipo_visitante_id' => $visitanteId,
                    'equipo_visitante_nombre' => $equiposMap[$visitanteId]->nombre ?? "Equipo #{$visitanteId}",
                ];
            }

            $jornadasFormateadas[] = [
                'numero_jornada' => $ronda['numero_jornada'],
                'fecha_inicio' => $inicioJornada->toDateString(),
                'fecha_fin' => $finJornada->toDateString(),
                'equipo_descanso_id' => $descansoId,
                'equipo_descanso_nombre' => $descansoNombre,
                'partidos' => $partidosDetalle,
            ];
        }

        return [
            'grupo_id' => $grupo->id,
            'grupo_nombre' => $grupo->nombre,
            'categoria_nombre' => $grupo->categoria->nombre,
            'temporada_nombre' => $grupo->temporada->nombre,
            'total_equipos' => $equipos->count(),
            'total_jornadas' => count($jornadasFormateadas),
            'vueltas' => $vueltas,
            'jornadas' => $jornadasFormateadas,
        ];
    }

    /**
     * Calcula los enfrentamientos para una única jornada específica de un grupo.
     *
     * @return array{equipo_descanso_id: int|null, partidos: array<int, array{equipo_local_id: int, equipo_visitante_id: int}>}
     *
     * @throws ValidationException
     */
    public function calcularEnfrentamientosParaJornada(Jornada $jornada): array
    {
        $grupo = $jornada->grupo;
        $this->validarGrupoParaGeneracion($grupo);

        /** @var Collection<int, Equipo> $equipos */
        $equipos = $grupo->equipos()->orderBy('nombre')->get();
        $equiposIds = $equipos->pluck('id')->all();

        $numEquipos = count($equiposIds);
        $totalItems = ($numEquipos % 2 !== 0) ? ($numEquipos + 1) : $numEquipos;
        $rondasPorVuelta = $totalItems - 1;

        $numeroJornada = $jornada->numero_jornada;
        $vueltas = ($numeroJornada > $rondasPorVuelta) ? 2 : 1;

        $rol = $this->calcularRolBerger($equiposIds, $vueltas, false);

        // Buscar la ronda cuyo número coincida con la jornada actual
        $rondaEncontrada = null;
        foreach ($rol as $ronda) {
            if ($ronda['numero_jornada'] === $numeroJornada) {
                $rondaEncontrada = $ronda;
                break;
            }
        }

        if ($rondaEncontrada === null) {
            // Mapeo cíclico si excede las vueltas calculadas
            $indiceModulo = ($numeroJornada - 1) % count($rol);
            $rondaEncontrada = $rol[$indiceModulo];
        }

        return [
            'equipo_descanso_id' => $rondaEncontrada['equipo_descanso_id'],
            'partidos' => $rondaEncontrada['partidos'],
        ];
    }

    /**
     * Persiste la propuesta del rol regular en la base de datos bajo transacción atómica.
     *
     * @param  array{jornadas: array<int, array{numero_jornada: int, fecha_inicio: string, fecha_fin: string, equipo_descanso_id: int|null, partidos: array<int, array{equipo_local_id: int, equipo_visitante_id: int}>}>}  $propuesta
     * @return array{jornadas_creadas: int, partidos_creados: int}
     *
     * @throws ValidationException
     */
    public function guardarRolGenerado(Grupo $grupo, array $propuesta, bool $sobrescribir = false): array
    {
        $this->validarGrupoParaGeneracion($grupo);

        if (empty($propuesta['jornadas'])) {
            throw ValidationException::withMessages([
                'jornadas' => 'No hay jornadas en la propuesta para persistir.',
            ]);
        }

        return $this->auditService->conMotivo(
            'Generación automática de rol de enfrentamientos (HU-032 / RF-07.1)',
            function () use ($grupo, $propuesta, $sobrescribir): array {
                return DB::transaction(function () use ($grupo, $propuesta, $sobrescribir): array {
                    $jornadasExistentes = $grupo->jornadas()->with('partidos')->get();

                    if ($jornadasExistentes->isNotEmpty()) {
                        if (! $sobrescribir) {
                            throw ValidationException::withMessages([
                                'grupo' => 'El grupo ya cuenta con jornadas programadas. Active la casilla de confirmación para reemplazarlas.',
                            ]);
                        }

                        // Verificar si hay partidos ya jugados o en juego
                        foreach ($jornadasExistentes as $jornadaEx) {
                            $partidosJugados = $jornadaEx->partidos()
                                ->whereIn('estado', ['en juego', 'finalizado'])
                                ->exists();

                            if ($partidosJugados) {
                                throw ValidationException::withMessages([
                                    'grupo' => "La jornada {$jornadaEx->numero_jornada} contiene partidos en curso o finalizados y no puede ser eliminada.",
                                ]);
                            }
                        }

                        // Eliminar jornadas y partidos previos en cascada
                        foreach ($jornadasExistentes as $jornadaEx) {
                            $jornadaEx->partidos()->delete();
                            $jornadaEx->delete();
                        }
                    }

                    $totalJornadas = 0;
                    $totalPartidos = 0;

                    foreach ($propuesta['jornadas'] as $itemJornada) {
                        $jornada = Jornada::create([
                            'grupo_id' => $grupo->id,
                            'numero_jornada' => (int) $itemJornada['numero_jornada'],
                            'fecha_inicio' => $itemJornada['fecha_inicio'],
                            'fecha_fin' => $itemJornada['fecha_fin'],
                            'equipo_descanso_id' => $itemJornada['equipo_descanso_id'] ? (int) $itemJornada['equipo_descanso_id'] : null,
                        ]);
                        $totalJornadas++;

                        foreach ($itemJornada['partidos'] as $itemPartido) {
                            Partido::create([
                                'jornada_id' => $jornada->id,
                                'equipo_local_id' => (int) $itemPartido['equipo_local_id'],
                                'equipo_visitante_id' => (int) $itemPartido['equipo_visitante_id'],
                                'fecha_partido' => $itemJornada['fecha_inicio'],
                                'hora_partido' => null,
                                'cancha_id' => null,
                                'arbitro_id' => null,
                                'estado' => 'programado',
                            ]);
                            $totalPartidos++;
                        }
                    }

                    return [
                        'jornadas_creadas' => $totalJornadas,
                        'partidos_creados' => $totalPartidos,
                    ];
                });
            }
        );
    }

    /**
     * Aplica la generación de enfrentamientos automáticos sobre una jornada individual existente.
     *
     * @throws ValidationException
     */
    public function aplicarEnfrentamientosAJornada(Jornada $jornada): Jornada
    {
        $grupo = $jornada->grupo;
        $this->validarGrupoParaGeneracion($grupo);

        if ($jornada->partidos()->exists()) {
            throw ValidationException::withMessages([
                'jornada' => 'La jornada ya cuenta con partidos programados. Debe eliminarlos antes de generar nuevos enfrentamientos.',
            ]);
        }

        $enfrentamientos = $this->calcularEnfrentamientosParaJornada($jornada);

        return $this->auditService->conMotivo(
            "Generación automática de partidos para jornada {$jornada->numero_jornada} (HU-032 / RF-07.1)",
            function () use ($jornada, $enfrentamientos): Jornada {
                return DB::transaction(function () use ($jornada, $enfrentamientos): Jornada {
                    $jornadaBloqueada = Jornada::query()->whereKey($jornada->id)->lockForUpdate()->firstOrFail();

                    // Si la jornada no tenía descanso asignado y el cálculo arroja descanso, actualizarlo
                    if ($jornadaBloqueada->equipo_descanso_id === null && $enfrentamientos['equipo_descanso_id'] !== null) {
                        $jornadaBloqueada->update(['equipo_descanso_id' => $enfrentamientos['equipo_descanso_id']]);
                    }

                    foreach ($enfrentamientos['partidos'] as $par) {
                        Partido::create([
                            'jornada_id' => $jornadaBloqueada->id,
                            'equipo_local_id' => $par['equipo_local_id'],
                            'equipo_visitante_id' => $par['equipo_visitante_id'],
                            'fecha_partido' => $jornadaBloqueada->fecha_inicio,
                            'hora_partido' => null,
                            'cancha_id' => null,
                            'arbitro_id' => null,
                            'estado' => 'programado',
                        ]);
                    }

                    return $jornadaBloqueada->fresh(['partidos.local', 'partidos.visitante', 'equipoDescanso']);
                });
            }
        );
    }
}
