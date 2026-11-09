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

                // Alternancia de localías para balancear partidos casa/fuera (Sistema Berger canónico)
                if ($i === 0) {
                    if ($r % 2 === 0) {
                        $local = $t1;
                        $visitante = $t2;
                    } else {
                        $local = $t2;
                        $visitante = $t1;
                    }
                } else {
                    if ($i % 2 === 1) {
                        $local = $t1;
                        $visitante = $t2;
                    } else {
                        $local = $t2;
                        $visitante = $t1;
                    }
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

        $temporada = $grupo->temporada;
        if ($temporada && $temporada->fecha_fin && ! empty($jornadasFormateadas)) {
            $ultimaJornada = end($jornadasFormateadas);
            if (Carbon::parse($ultimaJornada['fecha_fin'])->gt(Carbon::parse($temporada->fecha_fin))) {
                throw ValidationException::withMessages([
                    'fecha_inicio' => "El calendario generado finaliza el {$ultimaJornada['fecha_fin']}, superando el término de la temporada ({$temporada->fecha_fin}). Reduzca el intervalo entre fechas o las vueltas.",
                ]);
            }
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

        // Si la jornada ya tiene descanso asignado (RF-06.2), buscar la ronda que respete dicho descanso
        if ($jornada->equipo_descanso_id !== null) {
            $descansoExistente = (int) $jornada->equipo_descanso_id;
            if (! in_array($descansoExistente, $equiposIds, true)) {
                throw ValidationException::withMessages([
                    'jornada' => 'El equipo en descanso asignado a la jornada no pertenece a los equipos de este grupo.',
                ]);
            }

            if ($numEquipos % 2 === 0) {
                throw ValidationException::withMessages([
                    'jornada' => 'El grupo tiene un número par de equipos y no requiere descanso.',
                ]);
            }

            foreach ($rol as $ronda) {
                if ($ronda['equipo_descanso_id'] === $descansoExistente) {
                    return [
                        'equipo_descanso_id' => $descansoExistente,
                        'partidos' => $ronda['partidos'],
                    ];
                }
            }
        }

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
     * Valida exhaustivamente la estructura y reglas de negocio de la propuesta antes de persistir.
     *
     * @param  array<string, mixed>  $propuesta
     *
     * @throws ValidationException
     */
    public function validarPropuestaRol(Grupo $grupo, array $propuesta): void
    {
        $this->validarGrupoParaGeneracion($grupo);

        if (! isset($propuesta['jornadas']) || ! is_array($propuesta['jornadas']) || empty($propuesta['jornadas'])) {
            throw ValidationException::withMessages([
                'fixture_payload' => 'La propuesta de enfrentamientos no contiene jornadas válidas.',
            ]);
        }

        $equiposValidos = $grupo->equipos()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $equiposValidosMap = array_flip($equiposValidos);
        $numEquipos = count($equiposValidos);
        $esImpar = ($numEquipos % 2 !== 0);

        $temporada = $grupo->temporada;
        $temporadaInicio = $temporada?->fecha_inicio ? Carbon::parse($temporada->fecha_inicio)->startOfDay() : null;
        $temporadaFin = $temporada?->fecha_fin ? Carbon::parse($temporada->fecha_fin)->endOfDay() : null;

        $numerosJornadaVistos = [];

        foreach ($propuesta['jornadas'] as $index => $jornada) {
            if (! is_array($jornada)) {
                throw ValidationException::withMessages([
                    'fixture_payload' => "La jornada en posición {$index} no tiene un formato válido.",
                ]);
            }

            $numero = $jornada['numero_jornada'] ?? null;
            if (! is_numeric($numero) || (int) $numero < 1) {
                throw ValidationException::withMessages([
                    'fixture_payload' => "El número de jornada '{$numero}' no es válido.",
                ]);
            }
            $numero = (int) $numero;
            if (isset($numerosJornadaVistos[$numero])) {
                throw ValidationException::withMessages([
                    'fixture_payload' => "El número de jornada {$numero} está repetido en la propuesta.",
                ]);
            }
            $numerosJornadaVistos[$numero] = true;

            // Validación de formato y coherencia de fechas
            $inicioStr = $jornada['fecha_inicio'] ?? null;
            $finStr = $jornada['fecha_fin'] ?? null;

            if (! is_string($inicioStr) || ! is_string($finStr)) {
                throw ValidationException::withMessages([
                    'fixture_payload' => "Las fechas de la jornada {$numero} deben ser cadenas válidas.",
                ]);
            }

            try {
                $fechaInicio = Carbon::createFromFormat('Y-m-d', $inicioStr)->startOfDay();
                $fechaFin = Carbon::createFromFormat('Y-m-d', $finStr)->endOfDay();
            } catch (\Throwable) {
                throw ValidationException::withMessages([
                    'fixture_payload' => "Las fechas de la jornada {$numero} deben tener formato AAAA-MM-DD válido.",
                ]);
            }

            if ($fechaFin->lt($fechaInicio)) {
                throw ValidationException::withMessages([
                    'fixture_payload' => "La fecha de fin ({$finStr}) de la jornada {$numero} no puede ser anterior a la de inicio ({$inicioStr}).",
                ]);
            }

            if ($temporadaInicio && $fechaInicio->lt($temporadaInicio)) {
                throw ValidationException::withMessages([
                    'fixture_payload' => "La fecha de inicio ({$inicioStr}) de la jornada {$numero} es anterior al inicio de la temporada.",
                ]);
            }

            if ($temporadaFin && $fechaFin->gt($temporadaFin)) {
                throw ValidationException::withMessages([
                    'fixture_payload' => "La fecha de fin ({$finStr}) de la jornada {$numero} es posterior al término de la temporada.",
                ]);
            }

            // Validación de descanso
            $descansoId = ! empty($jornada['equipo_descanso_id'])
                ? (int) $jornada['equipo_descanso_id']
                : null;

            if (! $esImpar && $descansoId !== null) {
                throw ValidationException::withMessages([
                    'fixture_payload' => "La jornada {$numero} no debe tener descanso en un grupo con número par de equipos.",
                ]);
            }

            if ($descansoId !== null && ! isset($equiposValidosMap[$descansoId])) {
                throw ValidationException::withMessages([
                    'fixture_payload' => "El equipo en descanso de la jornada {$numero} no pertenece al grupo.",
                ]);
            }

            // Validación de partidos
            $partidos = $jornada['partidos'] ?? [];
            if (! is_array($partidos)) {
                throw ValidationException::withMessages([
                    'fixture_payload' => "Los partidos de la jornada {$numero} deben ser una lista.",
                ]);
            }

            $equiposEnJornada = [];
            if ($descansoId !== null) {
                $equiposEnJornada[$descansoId] = 'descanso';
            }

            foreach ($partidos as $pIndex => $p) {
                if (! is_array($p) || ! isset($p['equipo_local_id'], $p['equipo_visitante_id'])) {
                    throw ValidationException::withMessages([
                        'fixture_payload' => "El partido #{$pIndex} de la jornada {$numero} no contiene equipos válidos.",
                    ]);
                }

                $localId = (int) $p['equipo_local_id'];
                $visitanteId = (int) $p['equipo_visitante_id'];

                if (! isset($equiposValidosMap[$localId])) {
                    throw ValidationException::withMessages([
                        'fixture_payload' => "El equipo local #{$localId} en la jornada {$numero} no pertenece al grupo.",
                    ]);
                }

                if (! isset($equiposValidosMap[$visitanteId])) {
                    throw ValidationException::withMessages([
                        'fixture_payload' => "El equipo visitante #{$visitanteId} en la jornada {$numero} no pertenece al grupo.",
                    ]);
                }

                if ($localId === $visitanteId) {
                    throw ValidationException::withMessages([
                        'fixture_payload' => "El equipo #{$localId} no puede jugar contra sí mismo en la jornada {$numero}.",
                    ]);
                }

                if ($descansoId !== null && ($localId === $descansoId || $visitanteId === $descansoId)) {
                    throw ValidationException::withMessages([
                        'fixture_payload' => "El equipo en descanso no puede jugar partidos en la jornada {$numero}.",
                    ]);
                }

                if (isset($equiposEnJornada[$localId])) {
                    throw ValidationException::withMessages([
                        'fixture_payload' => "El equipo #{$localId} está duplicado en la jornada {$numero}.",
                    ]);
                }
                $equiposEnJornada[$localId] = 'jugando';

                if (isset($equiposEnJornada[$visitanteId])) {
                    throw ValidationException::withMessages([
                        'fixture_payload' => "El equipo #{$visitanteId} está duplicado en la jornada {$numero}.",
                    ]);
                }
                $equiposEnJornada[$visitanteId] = 'jugando';
            }
        }
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
        $this->validarPropuestaRol($grupo, $propuesta);

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

                        // Verificar si hay partidos ya jugados, en juego, suspendidos o confirmados
                        foreach ($jornadasExistentes as $jornadaEx) {
                            $partidosProtegidos = $jornadaEx->partidos()
                                ->whereIn('estado', ['confirmado', 'en juego', 'suspendido', 'finalizado'])
                                ->exists();

                            if ($partidosProtegidos) {
                                throw ValidationException::withMessages([
                                    'grupo' => "La jornada {$jornadaEx->numero_jornada} contiene partidos en estado confirmado, en juego, suspendido o finalizado y no puede ser reemplazada.",
                                ]);
                            }
                        }

                        // Eliminar jornadas y partidos previos individualmente para disparar auditoría (RNF-30)
                        foreach ($jornadasExistentes as $jornadaEx) {
                            foreach ($jornadaEx->partidos as $partidoEx) {
                                $partidoEx->delete();
                            }
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

        return $this->auditService->conMotivo(
            "Generación automática de partidos para jornada {$jornada->numero_jornada} (HU-032 / RF-07.1)",
            function () use ($jornada): Jornada {
                return DB::transaction(function () use ($jornada): Jornada {
                    $jornadaBloqueada = Jornada::query()->whereKey($jornada->id)->lockForUpdate()->firstOrFail();

                    // Concurrencia serializada (RNF-33): comprobar existencia de partidos dentro del lock
                    if ($jornadaBloqueada->partidos()->exists()) {
                        throw ValidationException::withMessages([
                            'jornada' => 'La jornada ya cuenta con partidos programados. Debe eliminarlos antes de generar nuevos enfrentamientos.',
                        ]);
                    }

                    $enfrentamientos = $this->calcularEnfrentamientosParaJornada($jornadaBloqueada);

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
