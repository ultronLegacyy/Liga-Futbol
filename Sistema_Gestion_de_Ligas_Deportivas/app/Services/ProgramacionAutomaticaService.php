<?php

namespace App\Services;

use App\Models\Arbitro;
use App\Models\BloqueoCancha;
use App\Models\Cancha;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\Partido;
use App\Models\PreferenciaEquipo;
use App\Models\Temporada;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Motor de asignación automática de cancha, fecha y hora (HU-074 / RF-07.2, RNF-07, RNF-08)
 * con control de concurrencia (Jornada -> Partido -> Cancha -> Árbitro), compatibilidad con
 * exclusividad arbitral (HU-026), ventana entre días (HU-025) y diagnóstico de conflictos (HU-033).
 */
class ProgramacionAutomaticaService
{
    public function __construct(
        private readonly DetectorConflictosProgramacionService $detectorConflictos,
        private readonly AuditService $audit,
        private readonly AsignacionCanchaService $canchaService,
    ) {
    }

    /**
     * Enriquece una propuesta en memoria de GeneradorEnfrentamientosService asignando cancha, fecha y hora
     * sin bloquear si alguno de los enfrentamientos no alcanza espacio disponible (HU-033 CA1).
     *
     * @param  array<string, mixed>  $propuesta
     * @param  array<string, mixed>  $opciones
     * @return array<string, mixed>
     */
    public function asignarPropuesta(Grupo $grupo, array $propuesta, array $opciones = []): array
    {
        $inicioCronometro = microtime(true);
        $jornadas = $propuesta['jornadas'] ?? [];

        if (! is_array($jornadas) || $jornadas === []) {
            $propuesta['resumen_horarios'] = $this->construirResumenVacio();
            $propuesta['conflictos'] = [];

            return $propuesta;
        }

        $fechaMin = null;
        $fechaMax = null;
        $equipoIds = [];

        foreach ($jornadas as $j) {
            $fi = (string) ($j['fecha_inicio'] ?? '');
            $ff = (string) ($j['fecha_fin'] ?? '');
            if ($fi !== '' && ($fechaMin === null || $fi < $fechaMin)) {
                $fechaMin = $fi;
            }
            if ($ff !== '' && ($fechaMax === null || $ff > $fechaMax)) {
                $fechaMax = $ff;
            }

            foreach (($j['partidos'] ?? []) as $p) {
                $equipoIds[] = (int) $p['equipo_local_id'];
                $equipoIds[] = (int) $p['equipo_visitante_id'];
            }
        }

        if ($fechaMin === null || $fechaMax === null) {
            $propuesta['resumen_horarios'] = $this->construirResumenVacio();
            $propuesta['conflictos'] = [];

            return $propuesta;
        }

        $canchasConsulta = $this->consultarCanchasCandidatas($opciones['cancha_ids'] ?? null, false);

        $estado = $this->inicializarEstadoEnMemoria(
            $canchasConsulta,
            $fechaMin,
            $fechaMax,
            array_values(array_unique($equipoIds)),
            [],
            $opciones
        );

        $totalPartidos = 0;
        $asignados = 0;
        $preferenciasRespetadas = 0;
        $conflictos = [];

        foreach ($jornadas as $idxJornada => $jornadaPropuesta) {
            $numeroJornada = (int) ($jornadaPropuesta['numero_jornada'] ?? ($idxJornada + 1));
            $ventanaSlots = $this->construirSlotsVentana(
                (string) $jornadaPropuesta['fecha_inicio'],
                (string) $jornadaPropuesta['fecha_fin'],
                $estado['horarios'],
                $estado['duracion_minutos']
            );

            $partidosActualizados = [];
            foreach (($jornadaPropuesta['partidos'] ?? []) as $partidoPropuesta) {
                $totalPartidos++;
                $localId = (int) $partidoPropuesta['equipo_local_id'];
                $visitanteId = (int) $partidoPropuesta['equipo_visitante_id'];
                $localNombre = (string) ($partidoPropuesta['equipo_local_nombre'] ?? $partidoPropuesta['local_nombre'] ?? "Equipo #{$localId}");
                $visitanteNombre = (string) ($partidoPropuesta['equipo_visitante_nombre'] ?? $partidoPropuesta['visitante_nombre'] ?? "Equipo #{$visitanteId}");

                $resultado = $this->asignarPartidoEnMemoria(
                    localId: $localId,
                    visitanteId: $visitanteId,
                    localNombre: $localNombre,
                    visitanteNombre: $visitanteNombre,
                    arbitroId: null,
                    numeroJornada: $numeroJornada,
                    partidoId: null,
                    fechaReferencia: (string) $jornadaPropuesta['fecha_inicio'],
                    fechaFinJornada: (string) $jornadaPropuesta['fecha_fin'],
                    ventanaSlots: $ventanaSlots,
                    estado: $estado,
                    slotPreferidoPropuesta: null
                );

                if ($resultado['asignado']) {
                    $asignados++;
                    if ($resultado['preferencia_aplicada']) {
                        $preferenciasRespetadas++;
                    }
                    $partidoPropuesta['cancha_id'] = $resultado['cancha_id'];
                    $partidoPropuesta['cancha_nombre'] = $resultado['cancha_nombre'];
                    $partidoPropuesta['fecha_partido'] = $resultado['fecha_partido'];
                    $partidoPropuesta['hora_partido'] = $resultado['hora_partido'];
                    $partidoPropuesta['preferencia_aplicada'] = $resultado['preferencia_aplicada'];
                    $partidoPropuesta['conflicto'] = null;
                } else {
                    $partidoPropuesta['cancha_id'] = null;
                    $partidoPropuesta['cancha_nombre'] = null;
                    $partidoPropuesta['fecha_partido'] = null;
                    $partidoPropuesta['hora_partido'] = null;
                    $partidoPropuesta['preferencia_aplicada'] = false;
                    $partidoPropuesta['conflicto'] = $resultado['conflicto'];
                    $conflictos[] = $resultado['conflicto'];
                }

                $partidosActualizados[] = $partidoPropuesta;
            }

            $propuesta['jornadas'][$idxJornada]['partidos'] = $partidosActualizados;
        }

        $propuesta['resumen_horarios'] = [
            'grupo_id' => $grupo->id,
            'total_partidos' => $totalPartidos,
            'asignados' => $asignados,
            'sin_asignar' => $totalPartidos - $asignados,
            'completado' => $totalPartidos === $asignados,
            'preferencias_respetadas' => $preferenciasRespetadas,
            'conflictos' => $conflictos,
            'jornadas_procesadas' => count($jornadas),
            'duracion_ms' => round((microtime(true) - $inicioCronometro) * 1000, 2),
        ];
        $propuesta['conflictos'] = $conflictos;

        return $propuesta;
    }

    /**
     * Asigna automáticamente cancha, fecha y hora a los partidos elegibles de una jornada persistida.
     * Bloquea primero la fila de la jornada para evitar modificaciones sobre jornadas ya enviadas a revisión.
     *
     * @param  array<string, mixed>  $opciones
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function asignarHorariosAJornada(Jornada $jornada, array $opciones = []): array
    {
        $jornada->loadMissing('grupo.temporada');

        if ($jornada->grupo->temporada?->estado === 'cerrada') {
            throw ValidationException::withMessages([
                'temporada' => 'Una temporada cerrada no admite programación automática de horarios.',
            ]);
        }

        return $this->asignarHorariosSobreConsulta(
            consultaJornadas: Jornada::query()->whereKey($jornada->id),
            opciones: $opciones,
            motivoAuditoria: "Asignación automática de horarios para Jornada {$jornada->numero_jornada} del grupo {$jornada->grupo->nombre}.",
            exigirBorradorUnico: true
        );
    }

    /**
     * Asigna automáticamente horarios a todas las jornadas en borrador de un grupo (RNF-07).
     *
     * @param  array<string, mixed>  $opciones
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function asignarHorariosAGrupo(Grupo $grupo, array $opciones = []): array
    {
        $grupo->loadMissing('temporada');

        if ($grupo->estaCerrado()) {
            throw ValidationException::withMessages([
                'temporada' => 'Una temporada cerrada no admite programación automática de horarios.',
            ]);
        }

        $consulta = Jornada::query()
            ->where('grupo_id', $grupo->id)
            ->where('estado', Jornada::ESTADO_BORRADOR)
            ->orderBy('numero_jornada');

        return $this->asignarHorariosSobreConsulta(
            consultaJornadas: $consulta,
            opciones: $opciones,
            motivoAuditoria: "Asignación automática masiva de horarios del grupo {$grupo->nombre}.",
            exigirBorradorUnico: false
        );
    }

    /**
     * Asigna horarios de forma masiva a las jornadas en borrador de toda una temporada (todas las categorías/grupos)
     * en una sola transacción serializada (`Jornada -> Partido -> Cancha -> Árbitro`).
     *
     * @param  array<string, mixed>  $opciones
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function asignarHorariosMasivoTemporada(Temporada $temporada, array $opciones = []): array
    {
        if ($temporada->estado === 'cerrada') {
            throw ValidationException::withMessages([
                'temporada' => 'Una temporada cerrada no admite programación automática de horarios.',
            ]);
        }

        $consulta = Jornada::query()
            ->whereHas('grupo', fn ($q) => $q->where('temporada_id', $temporada->id))
            ->where('estado', Jornada::ESTADO_BORRADOR)
            ->orderBy('fecha_inicio')
            ->orderBy('numero_jornada');

        if (isset($opciones['numero_jornada']) && is_numeric($opciones['numero_jornada'])) {
            $consulta->where('numero_jornada', (int) $opciones['numero_jornada']);
        }

        return $this->asignarHorariosSobreConsulta(
            consultaJornadas: $consulta,
            opciones: $opciones,
            motivoAuditoria: "Programación automática masiva para la temporada '{$temporada->nombre}'.",
            exigirBorradorUnico: false
        );
    }

    /**
     * Ejecuta la asignación dentro de la transacción única de AuditService respetando
     * el orden estricto de bloqueo (`Jornada -> Partido -> Cancha -> Árbitro`) para prevenir
     * carreras concurrentes y deadlocks.
     *
     * @param  Builder<Jornada>  $consultaJornadas
     * @param  array<string, mixed>  $opciones
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function asignarHorariosSobreConsulta(
        Builder $consultaJornadas,
        array $opciones,
        string $motivoAuditoria,
        bool $exigirBorradorUnico
    ): array {
        $inicioCronometro = microtime(true);
        $reemplazar = (bool) ($opciones['reemplazar_existentes'] ?? false);
        /** @var array<int, array<string, array<string, mixed>>> $slotsPropuesta */
        $slotsPropuesta = is_array($opciones['slots_preferidos_propuesta'] ?? null)
            ? $opciones['slots_preferidos_propuesta']
            : [];

        return $this->audit->conMotivo($motivoAuditoria, function () use ($consultaJornadas, $opciones, $reemplazar, $exigirBorradorUnico, $slotsPropuesta, $inicioCronometro): array {
            // 1. Bloqueo de jornadas en orden determinista de ID (mismo orden que ProgramacionJornadaService)
            $jornadasBloqueadas = (clone $consultaJornadas)
                ->reorder('id')
                ->lockForUpdate()
                ->get();

            if ($exigirBorradorUnico) {
                $jornadaUnica = $jornadasBloqueadas->first();
                if (! $jornadaUnica instanceof Jornada) {
                    return array_merge($this->construirResumenVacio(), [
                        'duracion_ms' => round((microtime(true) - $inicioCronometro) * 1000, 2),
                    ]);
                }

                $jornadaUnica->loadMissing('grupo.temporada');
                if ($jornadaUnica->grupo->temporada?->estado === 'cerrada') {
                    throw ValidationException::withMessages([
                        'temporada' => 'Una temporada cerrada no admite programación automática de horarios.',
                    ]);
                }

                if (! $jornadaUnica->esBorrador()) {
                    throw ValidationException::withMessages([
                        'jornada' => "Solo se pueden auto-asignar horarios a jornadas en estado 'borrador'. Estado actual: '{$jornadaUnica->estado}'.",
                    ]);
                }
            }

            $jornadasBorrador = $jornadasBloqueadas->filter(fn (Jornada $j): bool => $j->esBorrador())->values();
            if ($jornadasBorrador->isEmpty()) {
                return array_merge($this->construirResumenVacio(), [
                    'duracion_ms' => round((microtime(true) - $inicioCronometro) * 1000, 2),
                ]);
            }

            $jornadaIds = $jornadasBorrador->pluck('id')->all();

            // 2. Bloqueo de partidos elegibles en orden determinista de ID
            $partidos = Partido::query()
                ->with(['local', 'visitante'])
                ->whereIn('jornada_id', $jornadaIds)
                ->where('estado', Partido::ESTADO_BORRADOR)
                ->whereDoesntHave('cedula', fn ($q) => $q->conDatosCapturados())
                ->when(! $reemplazar, function ($q): void {
                    $q->where(function ($sub): void {
                        $sub->whereNull('cancha_id')
                            ->orWhereNull('fecha_partido')
                            ->orWhereNull('hora_partido');
                    });
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($partidos->isEmpty()) {
                return array_merge($this->construirResumenVacio(), [
                    'jornadas_procesadas' => $jornadasBorrador->count(),
                    'duracion_ms' => round((microtime(true) - $inicioCronometro) * 1000, 2),
                ]);
            }

            // 3. Bloqueo de canchas candidatas ANTES de leer la ocupación real en BD (serializa carreras por sede)
            $canchasBloqueadas = $this->consultarCanchasCandidatas($opciones['cancha_ids'] ?? null, true);

            // 4. Bloqueo de árbitros asignados a partidos del lote para prevenir carreras contra partidos_arbitro_horario_unique
            $arbitrosIds = $partidos->pluck('arbitro_id')
                ->filter()
                ->map(static fn ($id): int => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();

            if ($arbitrosIds !== []) {
                Arbitro::query()
                    ->whereIn('id', $arbitrosIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get(['id']);
            }

            $fechaMin = $this->normalizarFecha($jornadasBorrador->min('fecha_inicio'));
            $fechaMax = $this->normalizarFecha($jornadasBorrador->max('fecha_fin'));

            $equipoIds = $partidos
                ->flatMap(fn (Partido $p): array => [(int) $p->equipo_local_id, (int) $p->equipo_visitante_id])
                ->unique()
                ->values()
                ->all();

            $opcionesConExclusion = $opciones;
            if ($reemplazar) {
                $opcionesConExclusion['ignorar_partido_ids'] = $partidos->pluck('id')->all();
            }

            $estado = $this->inicializarEstadoEnMemoria(
                $canchasBloqueadas,
                $fechaMin,
                $fechaMax,
                $equipoIds,
                $arbitrosIds,
                $opcionesConExclusion
            );

            $jornadasPorOrden = $jornadasBorrador->sortBy([
                ['fecha_inicio', 'asc'],
                ['numero_jornada', 'asc'],
                ['id', 'asc'],
            ]);
            $partidosPorJornada = $partidos->groupBy('jornada_id');

            /** @var array<int, string> $fechaInicioPorJornada */
            $fechaInicioPorJornada = [];
            $totalPartidos = 0;
            $asignados = 0;
            $preferenciasRespetadas = 0;
            $conflictos = [];
            /** @var array<int, array{cancha_id: int, fecha_partido: string, hora_partido: string}> $cambiosPendientes */
            $cambiosPendientes = [];

            foreach ($jornadasPorOrden as $jornada) {
                $fechaInicioJornada = $this->normalizarFecha($jornada->fecha_inicio);
                $fechaFinJornada = $this->normalizarFecha($jornada->fecha_fin);
                $fechaInicioPorJornada[$jornada->id] = $fechaInicioJornada;

                if (isset($opciones['cancha_ids']) || isset($opciones['horarios'])) {
                    Cache::put(
                        "programacion_opciones_jornada_{$jornada->id}",
                        [
                            'cancha_ids' => is_array($opciones['cancha_ids'] ?? null)
                                ? array_values(array_map('intval', $opciones['cancha_ids']))
                                : null,
                            'horarios' => $estado['horarios'],
                        ],
                        now()->addHours(6)
                    );
                } else {
                    Cache::forget("programacion_opciones_jornada_{$jornada->id}");
                }

                /** @var Collection<int, Partido>|null $lote */
                $lote = $partidosPorJornada->get($jornada->id);
                if ($lote === null || $lote->isEmpty()) {
                    continue;
                }

                $ventanaSlots = $this->construirSlotsVentana(
                    $fechaInicioJornada,
                    $fechaFinJornada,
                    $estado['horarios'],
                    $estado['duracion_minutos']
                );

                foreach ($lote as $partido) {
                    $totalPartidos++;
                    $local = $partido->local;
                    $visitante = $partido->visitante;
                    $clavePar = $partido->equipo_local_id.'-'.$partido->equipo_visitante_id;
                    $slotPreferido = $slotsPropuesta[$jornada->numero_jornada][$clavePar] ?? null;

                    $resultado = $this->asignarPartidoEnMemoria(
                        localId: (int) $partido->equipo_local_id,
                        visitanteId: (int) $partido->equipo_visitante_id,
                        localNombre: $local instanceof Equipo ? $local->nombre : "Equipo #{$partido->equipo_local_id}",
                        visitanteNombre: $visitante instanceof Equipo ? $visitante->nombre : "Equipo #{$partido->equipo_visitante_id}",
                        arbitroId: $partido->arbitro_id !== null ? (int) $partido->arbitro_id : null,
                        numeroJornada: (int) $jornada->numero_jornada,
                        partidoId: $partido->id,
                        fechaReferencia: $fechaInicioJornada,
                        fechaFinJornada: $fechaFinJornada,
                        ventanaSlots: $ventanaSlots,
                        estado: $estado,
                        slotPreferidoPropuesta: $slotPreferido
                    );

                    if ($resultado['asignado']) {
                        $asignados++;
                        if ($resultado['preferencia_aplicada']) {
                            $preferenciasRespetadas++;
                        }
                        $cambiosPendientes[$partido->id] = [
                            'cancha_id' => (int) $resultado['cancha_id'],
                            'fecha_partido' => (string) $resultado['fecha_partido'],
                            'hora_partido' => (string) $resultado['hora_partido'].':00',
                        ];
                    } else {
                        $conflictos[] = $resultado['conflicto'];
                    }
                }
            }

            // Persistir partidos asignados; si se reasignó todo, limpiar horarios previos de los conflictivos
            foreach ($partidos as $partido) {
                if (isset($cambiosPendientes[$partido->id])) {
                    $datos = $cambiosPendientes[$partido->id];
                    $partido->cancha_id = $datos['cancha_id'];
                    $partido->fecha_partido = Carbon::parse($datos['fecha_partido']);
                    $partido->hora_partido = $datos['hora_partido'];
                    $partido->save();

                    continue;
                }

                if ($reemplazar && ($partido->cancha_id !== null || $partido->hora_partido !== null)) {
                    $partido->cancha_id = null;
                    $partido->fecha_partido = Carbon::parse($fechaInicioPorJornada[$partido->jornada_id] ?? $fechaMin);
                    $partido->hora_partido = null;
                    $partido->save();
                }
            }

            return [
                'total_partidos' => $totalPartidos,
                'asignados' => $asignados,
                'sin_asignar' => $totalPartidos - $asignados,
                'completado' => $totalPartidos === $asignados,
                'preferencias_respetadas' => $preferenciasRespetadas,
                'conflictos' => $conflictos,
                'jornadas_procesadas' => $jornadasBorrador->count(),
                'duracion_ms' => round((microtime(true) - $inicioCronometro) * 1000, 2),
            ];
        });
    }

    /**
     * @param  mixed  $canchaIds
     * @return Collection<int, Cancha>
     */
    private function consultarCanchasCandidatas(mixed $canchaIds, bool $bloquearParaActualizacion): Collection
    {
        $query = Cancha::query()->orderBy('id');

        if (is_array($canchaIds) && $canchaIds !== []) {
            $query->whereIn('id', array_map('intval', $canchaIds));
        }

        if ($bloquearParaActualizacion) {
            $query->lockForUpdate();
        }

        return $query->get(['id', 'nombre', 'capacidad_partidos_consecutivos', 'estado']);
    }

    /**
     * Precarga bloqueos, ocupación previa (ampliada hacia atrás por cruce de medianoche),
     * disponibilidad exacta/intervalar de árbitros y preferencias en consultas acotadas (RNF-07 / RNF-08).
     *
     * @param  Collection<int, Cancha>  $todasCanchas
     * @param  array<int, int>  $equipoIds
     * @param  array<int, int>  $arbitroIds
     * @param  array<string, mixed>  $opciones
     * @return array<string, mixed>
     */
    private function inicializarEstadoEnMemoria(
        Collection $todasCanchas,
        string $fechaMin,
        string $fechaMax,
        array $equipoIds,
        array $arbitroIds,
        array $opciones
    ): array {
        $duracionMinutos = $this->canchaService->duracionEnMinutos();
        $horarios = $this->resolverHorarios($opciones['horarios'] ?? []);

        $canchasOperativas = $todasCanchas
            ->filter(fn (Cancha $c): bool => $c->estado === 'Disponible')
            ->values();

        // Incluir día previo para detectar partidos que cruzan medianoche (23:00 -> 01:00)
        $fechaAnteriorMin = Carbon::parse($fechaMin.' 00:00:00')->subMinutes($duracionMinutos)->toDateString();
        $fechaPosteriorMax = Carbon::parse($fechaMax.' 23:59:59')->addMinutes($duracionMinutos)->toDateString();
        $inicioVentana = Carbon::parse($fechaAnteriorMin.' 00:00:00');
        $finVentana = Carbon::parse($fechaPosteriorMax.' 23:59:59');

        $bloqueosRaw = BloqueoCancha::query()
            ->solapadosCon($inicioVentana, $finVentana)
            ->get(['id', 'cancha_id', 'fecha_inicio', 'fecha_fin', 'motivo']);

        /** @var array<int, array<int, array{inicio_ts: int, fin_ts: int, motivo: string}>> $bloqueosPorCancha */
        $bloqueosPorCancha = [];
        foreach ($bloqueosRaw as $bloqueo) {
            if ($bloqueo->fecha_inicio === null || $bloqueo->fecha_fin === null) {
                continue;
            }

            $bloqueosPorCancha[$bloqueo->cancha_id][] = [
                'inicio_ts' => $this->toTimestamp($bloqueo->fecha_inicio),
                'fin_ts' => $this->toTimestamp($bloqueo->fecha_fin),
                'motivo' => (string) $bloqueo->motivo,
            ];
        }

        $ignorarIds = array_flip(array_map('intval', $opciones['ignorar_partido_ids'] ?? []));

        // Ampliar la ventana hacia atrás y hacia adelante por cruce de medianoche (HU-025 / HU-026)
        $partidosExistentes = Partido::query()
            ->queOcupanCancha()
            ->whereBetween('fecha_partido', [$fechaAnteriorMin, $fechaPosteriorMax])
            ->get(['id', 'cancha_id', 'arbitro_id', 'equipo_local_id', 'equipo_visitante_id', 'fecha_partido', 'hora_partido']);

        $canchaSlots = [];
        $canchaConteoDia = [];
        $equipoSlots = [];
        $arbitroSlots = [];
        $arbitroHorariosExactos = [];

        foreach ($partidosExistentes as $p) {
            if (isset($ignorarIds[$p->id])) {
                continue;
            }

            $fechaStr = $this->normalizarFecha($p->fecha_partido);
            $horaStr = substr((string) $p->hora_partido, 0, 8);
            $inicioTs = strtotime($fechaStr.' '.$horaStr) ?: 0;
            $finTs = $inicioTs + ($duracionMinutos * 60);
            $intervalo = [$inicioTs, $finTs];

            if ($p->cancha_id !== null) {
                $canchaId = (int) $p->cancha_id;
                $canchaSlots[$canchaId] ??= [];
                AsignacionCanchaService::registrarIntervaloConCruceMedianoche(
                    $canchaSlots[$canchaId],
                    $inicioTs,
                    $finTs,
                    $intervalo
                );
                $canchaConteoDia[$canchaId][$fechaStr] = ($canchaConteoDia[$canchaId][$fechaStr] ?? 0) + 1;
            }

            $localId = (int) $p->equipo_local_id;
            $visitanteId = (int) $p->equipo_visitante_id;
            $equipoSlots[$localId] ??= [];
            $equipoSlots[$visitanteId] ??= [];
            AsignacionCanchaService::registrarIntervaloConCruceMedianoche($equipoSlots[$localId], $inicioTs, $finTs, $intervalo);
            AsignacionCanchaService::registrarIntervaloConCruceMedianoche($equipoSlots[$visitanteId], $inicioTs, $finTs, $intervalo);

            if ($p->arbitro_id !== null) {
                $arbId = (int) $p->arbitro_id;
                $arbitroSlots[$arbId] ??= [];
                AsignacionCanchaService::registrarIntervaloConCruceMedianoche($arbitroSlots[$arbId], $inicioTs, $finTs, $intervalo);
            }
        }

        // Respaldo para el índice único partidos_arbitro_horario_unique (aplica incluso a partidos suspendidos o cancelados)
        if ($arbitroIds !== []) {
            $filasArbitros = Partido::query()
                ->whereIn('arbitro_id', $arbitroIds)
                ->whereNotNull('fecha_partido')
                ->whereNotNull('hora_partido')
                ->whereBetween('fecha_partido', [$fechaAnteriorMin, $fechaPosteriorMax])
                ->get(['id', 'arbitro_id', 'fecha_partido', 'hora_partido']);

            foreach ($filasArbitros as $fila) {
                if (isset($ignorarIds[$fila->id])) {
                    continue;
                }

                $claveExacta = $this->normalizarFecha($fila->fecha_partido).'|'.substr((string) $fila->hora_partido, 0, 8);
                $arbitroHorariosExactos[(int) $fila->arbitro_id][$claveExacta] = true;
            }
        }

        $preferencias = $equipoIds !== []
            ? PreferenciaEquipo::query()->whereIn('equipo_id', $equipoIds)->get()->keyBy('equipo_id')
            : collect();

        $equiposSancionados = ProgramacionJornadaService::motivosSancionVigentePorEquipos($equipoIds);

        return [
            'duracion_minutos' => $duracionMinutos,
            'horarios' => $horarios,
            'canchas_totales' => $todasCanchas->count(),
            'canchas_operativas' => $canchasOperativas,
            'canchas_operativas_por_id' => $canchasOperativas->keyBy('id'),
            'bloqueos_por_cancha' => $bloqueosPorCancha,
            'cancha_slots' => $canchaSlots,
            'cancha_conteo_dia' => $canchaConteoDia,
            'equipo_slots' => $equipoSlots,
            'arbitro_slots' => $arbitroSlots,
            'arbitro_horarios_exactos' => $arbitroHorariosExactos,
            'preferencias' => $preferencias,
            'equipos_sancionados' => $equiposSancionados,
        ];
    }

    /**
     * Intenta asignar el mejor slot válido (restricciones duras obligatorias + blandas por puntaje) en memoria.
     *
     * @param  array<int, array{fecha: string, fin_fecha: string, hora: string, dia_iso: int, inicio_ts: int, fin_ts: int}>  $ventanaSlots
     * @param  array<string, mixed>  $estado
     * @param  array<string, mixed>|null  $slotPreferidoPropuesta
     * @return array<string, mixed>
     */
    private function asignarPartidoEnMemoria(
        int $localId,
        int $visitanteId,
        string $localNombre,
        string $visitanteNombre,
        ?int $arbitroId,
        int $numeroJornada,
        ?int $partidoId,
        string $fechaReferencia,
        string $fechaFinJornada,
        array $ventanaSlots,
        array &$estado,
        ?array $slotPreferidoPropuesta
    ): array {
        // Restricción dura (HU-074 CA1 / HU-027 / PO): no se programan equipos con >= 3 defaults o sanción vigente de suspensión/desafiliación
        foreach ([[$localId, $localNombre], [$visitanteId, $visitanteNombre]] as [$equipoIdCheck, $equipoNombreCheck]) {
            if (isset($estado['equipos_sancionados'][$equipoIdCheck])) {
                return [
                    'asignado' => false,
                    'preferencia_aplicada' => false,
                    'conflicto' => $this->detectorConflictos->construirConflictoEquipoSancionado(
                        jornadaNumero: $numeroJornada,
                        partidoId: $partidoId,
                        enfrentamiento: "{$localNombre} vs {$visitanteNombre}",
                        fechaReferencia: $fechaReferencia,
                        equipoNombre: $equipoNombreCheck,
                        motivoSancion: (string) $estado['equipos_sancionados'][$equipoIdCheck],
                    ),
                ];
            }
        }

        /** @var Collection<int, Cancha> $canchasOperativas */
        $canchasOperativas = $estado['canchas_operativas'];

        /** @var PreferenciaEquipo|null $prefLocal */
        $prefLocal = $estado['preferencias']->get($localId);
        /** @var PreferenciaEquipo|null $prefVisitante */
        $prefVisitante = $estado['preferencias']->get($visitanteId);
        $tienePreferencias = ($prefLocal !== null) || ($prefVisitante !== null);

        $rechazos = [
            'canchas_totales' => $estado['canchas_totales'],
            'canchas_operativas' => $canchasOperativas->count(),
            'slots_evaluados' => 0,
            'por_bloqueo' => [],
            'por_capacidad' => 0,
            'por_ocupacion_cancha' => 0,
            'por_empalme_equipo' => 0,
            'por_empalme_arbitro' => 0,
        ];

        // Recomendado: si viene de confirmación tras previsualización, intentar conservar el slot visto solo si es válido en rango y franjas
        if ($slotPreferidoPropuesta !== null && $canchasOperativas->isNotEmpty()) {
            $candidatoPropuesta = $this->evaluarSlotEspecificoPropuesta(
                $slotPreferidoPropuesta,
                $localId,
                $visitanteId,
                $arbitroId,
                $fechaReferencia,
                $fechaFinJornada,
                $prefLocal,
                $prefVisitante,
                $estado
            );
            if ($candidatoPropuesta !== null) {
                return $this->confirmarAsignacionEnEstado(
                    $candidatoPropuesta,
                    $localId,
                    $visitanteId,
                    $arbitroId,
                    $estado
                );
            }
        }

        $mejorCandidato = null;
        $mejorPuntaje = PHP_INT_MIN;

        foreach ($ventanaSlots as $slot) {
            if ($canchasOperativas->isEmpty()) {
                break;
            }

            $fechaStr = $slot['fecha'];
            $finFechaStr = $slot['fin_fecha'];
            $horaStr = $slot['hora'];
            $inicioTs = $slot['inicio_ts'];
            $finTs = $slot['fin_ts'];

            // Restricción dura: exclusividad horaria de árbitro asignado (HU-026 / RF-06.1)
            if ($arbitroId !== null) {
                $claveExacta = $fechaStr.'|'.$horaStr.':00';
                if (
                    isset($estado['arbitro_horarios_exactos'][$arbitroId][$claveExacta])
                    || $this->haySolapeEnBuckets($inicioTs, $finTs, $fechaStr, $finFechaStr, $estado['arbitro_slots'][$arbitroId] ?? [])
                ) {
                    $rechazos['por_empalme_arbitro'] += $canchasOperativas->count();
                    $rechazos['slots_evaluados'] += $canchasOperativas->count();

                    continue;
                }
            }

            // Restricción dura: simultaneidad de equipos verificada antes de iterar las N canchas (O(1) vs O(N))
            if (
                $this->haySolapeEnBuckets($inicioTs, $finTs, $fechaStr, $finFechaStr, $estado['equipo_slots'][$localId] ?? [])
                || $this->haySolapeEnBuckets($inicioTs, $finTs, $fechaStr, $finFechaStr, $estado['equipo_slots'][$visitanteId] ?? [])
            ) {
                $rechazos['por_empalme_equipo'] += $canchasOperativas->count();
                $rechazos['slots_evaluados'] += $canchasOperativas->count();

                continue;
            }

            foreach ($canchasOperativas as $cancha) {
                $rechazos['slots_evaluados']++;

                // Restricción dura: bloqueo temporal de cancha (RF-05.2)
                $bloqueo = $this->buscarBloqueoEnMemoria($inicioTs, $finTs, $estado['bloqueos_por_cancha'][$cancha->id] ?? []);
                if ($bloqueo !== null) {
                    $rechazos['por_bloqueo'][] = "{$cancha->nombre} ({$bloqueo['motivo']})";

                    continue;
                }

                // Restricción dura: capacidad de partidos consecutivos por día (tratada igual en motor y detector)
                $ocupadosDia = $estado['cancha_conteo_dia'][$cancha->id][$fechaStr] ?? 0;
                if ($cancha->capacidad_partidos_consecutivos > 0 && $ocupadosDia >= $cancha->capacidad_partidos_consecutivos) {
                    $rechazos['por_capacidad']++;

                    continue;
                }

                // Restricción dura: exclusividad horaria de cancha (incluyendo cruce de medianoche de partidos previos)
                if ($this->haySolapeEnBuckets($inicioTs, $finTs, $fechaStr, $finFechaStr, $estado['cancha_slots'][$cancha->id] ?? [])) {
                    $rechazos['por_ocupacion_cancha']++;

                    continue;
                }

                // Restricción blanda (RF-07.2): puntaje por preferencias de equipo y balanceo de carga
                $puntajePreferencias = $this->calcularPuntajePreferencias(
                    $cancha->id,
                    $slot['dia_iso'],
                    $horaStr,
                    $prefLocal,
                    $prefVisitante
                );
                $puntajeFinal = $puntajePreferencias - ($ocupadosDia * 2);

                if ($puntajeFinal > $mejorPuntaje) {
                    $mejorPuntaje = $puntajeFinal;
                    $mejorCandidato = [
                        'cancha' => $cancha,
                        'fecha' => $fechaStr,
                        'hora' => $horaStr,
                        'inicio_ts' => $inicioTs,
                        'fin_ts' => $finTs,
                        'preferencia_aplicada' => $puntajePreferencias > 0,
                    ];

                    if (! $tienePreferencias && $ocupadosDia === 0) {
                        break 2;
                    }
                }
            }
        }

        if ($mejorCandidato === null) {
            $conflicto = $this->detectorConflictos->explicarImposibilidadPartido([
                'jornada_numero' => $numeroJornada,
                'partido_id' => $partidoId,
                'local_id' => $localId,
                'visitante_id' => $visitanteId,
                'local_nombre' => $localNombre,
                'visitante_nombre' => $visitanteNombre,
                'arbitro_id' => $arbitroId,
                'fecha_referencia' => $fechaReferencia,
            ], $rechazos);

            return [
                'asignado' => false,
                'preferencia_aplicada' => false,
                'conflicto' => $conflicto,
            ];
        }

        return $this->confirmarAsignacionEnEstado(
            $mejorCandidato,
            $localId,
            $visitanteId,
            $arbitroId,
            $estado
        );
    }

    /**
     * Valida si el slot visto en previsualización sigue cumpliendo todas las restricciones duras en BD.
     *
     * @param  array<string, mixed>  $slotPropuesta
     * @param  array<string, mixed>  $estado
     * @return array{cancha: Cancha, fecha: string, hora: string, inicio_ts: int, fin_ts: int, preferencia_aplicada: bool}|null
     */
    private function evaluarSlotEspecificoPropuesta(
        array $slotPropuesta,
        int $localId,
        int $visitanteId,
        ?int $arbitroId,
        string $fechaInicioJornada,
        string $fechaFinJornada,
        ?PreferenciaEquipo $prefLocal,
        ?PreferenciaEquipo $prefVisitante,
        array $estado
    ): ?array {
        $rawCancha = $slotPropuesta['cancha_id'] ?? null;
        $rawFecha = $slotPropuesta['fecha_partido'] ?? null;
        $rawHora = $slotPropuesta['hora_partido'] ?? null;
        if (! is_int($rawCancha) || ! is_string($rawFecha) || ! is_string($rawHora)) {
            return null;
        }

        $fechaDt = DateTimeImmutable::createFromFormat('Y-m-d', $rawFecha);
        $horaStr = substr(trim($rawHora), 0, 5);
        if (
            $fechaDt === false
            || $fechaDt->format('Y-m-d') !== $rawFecha
            || $rawFecha < $fechaInicioJornada
            || $rawFecha > $fechaFinJornada
            || ! in_array($horaStr, $estado['horarios'], true)
        ) {
            return null;
        }

        /** @var Cancha|null $cancha */
        $cancha = $estado['canchas_operativas_por_id']->get($rawCancha);
        if (! $cancha instanceof Cancha) {
            return null;
        }

        $fechaStr = $rawFecha;
        $inicioTs = strtotime($fechaStr.' '.$horaStr.':00') ?: 0;
        if ($inicioTs <= 0) {
            return null;
        }

        $finTs = $inicioTs + ($estado['duracion_minutos'] * 60);
        $finFechaStr = date('Y-m-d', max($inicioTs, $finTs - 1));

        if ($arbitroId !== null) {
            $claveExacta = $fechaStr.'|'.$horaStr.':00';
            if (
                isset($estado['arbitro_horarios_exactos'][$arbitroId][$claveExacta])
                || $this->haySolapeEnBuckets($inicioTs, $finTs, $fechaStr, $finFechaStr, $estado['arbitro_slots'][$arbitroId] ?? [])
            ) {
                return null;
            }
        }

        if (
            $this->haySolapeEnBuckets($inicioTs, $finTs, $fechaStr, $finFechaStr, $estado['equipo_slots'][$localId] ?? [])
            || $this->haySolapeEnBuckets($inicioTs, $finTs, $fechaStr, $finFechaStr, $estado['equipo_slots'][$visitanteId] ?? [])
        ) {
            return null;
        }

        if ($this->buscarBloqueoEnMemoria($inicioTs, $finTs, $estado['bloqueos_por_cancha'][$cancha->id] ?? []) !== null) {
            return null;
        }

        $ocupadosDia = $estado['cancha_conteo_dia'][$cancha->id][$fechaStr] ?? 0;
        if ($cancha->capacidad_partidos_consecutivos > 0 && $ocupadosDia >= $cancha->capacidad_partidos_consecutivos) {
            return null;
        }

        if ($this->haySolapeEnBuckets($inicioTs, $finTs, $fechaStr, $finFechaStr, $estado['cancha_slots'][$cancha->id] ?? [])) {
            return null;
        }

        $diaIso = Carbon::parse($fechaStr)->dayOfWeekIso;
        $puntaje = $this->calcularPuntajePreferencias($cancha->id, $diaIso, $horaStr, $prefLocal, $prefVisitante);

        return [
            'cancha' => $cancha,
            'fecha' => $fechaStr,
            'hora' => $horaStr,
            'inicio_ts' => $inicioTs,
            'fin_ts' => $finTs,
            'preferencia_aplicada' => $puntaje > 0,
        ];
    }

    /**
     * Registra en memoria el intervalo asignado (cancha, equipos y árbitro) preservando cruce de medianoche.
     *
     * @param  array{cancha: Cancha, fecha: string, hora: string, inicio_ts: int, fin_ts: int, preferencia_aplicada: bool}  $candidato
     * @param  array<string, mixed>  $estado
     * @return array<string, mixed>
     */
    private function confirmarAsignacionEnEstado(
        array $candidato,
        int $localId,
        int $visitanteId,
        ?int $arbitroId,
        array &$estado
    ): array {
        $canchaElegida = $candidato['cancha'];
        $fechaElegida = $candidato['fecha'];
        $horaElegida = $candidato['hora'];
        $inicioElegido = $candidato['inicio_ts'];
        $finElegido = $candidato['fin_ts'];
        $intervalo = [$inicioElegido, $finElegido];

        $estado['cancha_slots'][$canchaElegida->id] ??= [];
        AsignacionCanchaService::registrarIntervaloConCruceMedianoche(
            $estado['cancha_slots'][$canchaElegida->id],
            $inicioElegido,
            $finElegido,
            $intervalo
        );
        $estado['cancha_conteo_dia'][$canchaElegida->id][$fechaElegida] = ($estado['cancha_conteo_dia'][$canchaElegida->id][$fechaElegida] ?? 0) + 1;

        $estado['equipo_slots'][$localId] ??= [];
        $estado['equipo_slots'][$visitanteId] ??= [];
        AsignacionCanchaService::registrarIntervaloConCruceMedianoche($estado['equipo_slots'][$localId], $inicioElegido, $finElegido, $intervalo);
        AsignacionCanchaService::registrarIntervaloConCruceMedianoche($estado['equipo_slots'][$visitanteId], $inicioElegido, $finElegido, $intervalo);

        if ($arbitroId !== null) {
            $estado['arbitro_slots'][$arbitroId] ??= [];
            AsignacionCanchaService::registrarIntervaloConCruceMedianoche($estado['arbitro_slots'][$arbitroId], $inicioElegido, $finElegido, $intervalo);
            $estado['arbitro_horarios_exactos'][$arbitroId][$fechaElegida.'|'.$horaElegida.':00'] = true;
        }

        return [
            'asignado' => true,
            'cancha_id' => $canchaElegida->id,
            'cancha_nombre' => $canchaElegida->nombre,
            'fecha_partido' => $fechaElegida,
            'hora_partido' => $horaElegida,
            'preferencia_aplicada' => $candidato['preferencia_aplicada'],
            'conflicto' => null,
        ];
    }

    /**
     * Evalúa restricciones blandas (cancha, día y hora preferida) otorgando mayor peso al equipo local.
     */
    private function calcularPuntajePreferencias(
        int $canchaId,
        int $diaIso,
        string $horaStr,
        ?PreferenciaEquipo $prefLocal,
        ?PreferenciaEquipo $prefVisitante
    ): int {
        $pesos = (array) config('programacion.pesos_preferencias', []);
        $pesoLocal = (array) ($pesos['local'] ?? []);
        $pesoVisitante = (array) ($pesos['visitante'] ?? []);

        $puntaje = 0;

        if ($prefLocal !== null) {
            if ($prefLocal->cancha_id_preferida === $canchaId) {
                $puntaje += (int) ($pesoLocal['cancha'] ?? 40);
            }
            if ($prefLocal->diaPreferidoIso() === $diaIso) {
                $puntaje += (int) ($pesoLocal['dia'] ?? 25);
            }
            if ($prefLocal->horaNormalizada() === $horaStr) {
                $puntaje += (int) ($pesoLocal['hora'] ?? 20);
            }
        }

        if ($prefVisitante !== null) {
            if ($prefVisitante->cancha_id_preferida === $canchaId) {
                $puntaje += (int) ($pesoVisitante['cancha'] ?? 15);
            }
            if ($prefVisitante->diaPreferidoIso() === $diaIso) {
                $puntaje += (int) ($pesoVisitante['dia'] ?? 10);
            }
            if ($prefVisitante->horaNormalizada() === $horaStr) {
                $puntaje += (int) ($pesoVisitante['hora'] ?? 8);
            }
        }

        return $puntaje;
    }

    /**
     * Construye todos los intervalos `(fecha, hora)` candidatos dentro de una ventana de fechas.
     *
     * @param  array<int, string>  $horarios
     * @return array<int, array{fecha: string, fin_fecha: string, hora: string, dia_iso: int, inicio_ts: int, fin_ts: int}>
     */
    private function construirSlotsVentana(
        string $fechaInicio,
        string $fechaFin,
        array $horarios,
        int $duracionMinutos
    ): array {
        $slots = [];
        $cursor = Carbon::parse($fechaInicio);
        $limite = Carbon::parse($fechaFin);
        $duracionSegundos = $duracionMinutos * 60;

        while ($cursor->lessThanOrEqualTo($limite)) {
            $fechaStr = $cursor->toDateString();
            $diaIso = $cursor->dayOfWeekIso;

            foreach ($horarios as $horaStr) {
                $inicioTs = strtotime($fechaStr.' '.$horaStr.':00') ?: 0;
                $finTs = $inicioTs + $duracionSegundos;
                $finFecha = date('Y-m-d', max($inicioTs, $finTs - 1));
                $slots[] = [
                    'fecha' => $fechaStr,
                    'fin_fecha' => $finFecha,
                    'hora' => $horaStr,
                    'dia_iso' => $diaIso,
                    'inicio_ts' => $inicioTs,
                    'fin_ts' => $finTs,
                ];
            }

            $cursor->addDay();
        }

        return $slots;
    }

    /**
     * @param  array<string, array<int, array{0: int, 1: int}>>  $bucketsPorFecha
     */
    private function haySolapeEnBuckets(
        int $inicioTs,
        int $finTs,
        string $fechaInicio,
        string $fechaFin,
        array $bucketsPorFecha
    ): bool {
        $fechas = $fechaFin !== $fechaInicio ? [$fechaInicio, $fechaFin] : [$fechaInicio];

        foreach ($fechas as $fecha) {
            foreach ($bucketsPorFecha[$fecha] ?? [] as $intervalo) {
                if ($inicioTs < $intervalo[1] && $finTs > $intervalo[0]) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{inicio_ts: int, fin_ts: int, motivo: string}>  $bloqueos
     * @return array{inicio_ts: int, fin_ts: int, motivo: string}|null
     */
    private function buscarBloqueoEnMemoria(int $inicioTs, int $finTs, array $bloqueos): ?array
    {
        foreach ($bloqueos as $bloqueo) {
            if ($bloqueo['inicio_ts'] < $finTs && $bloqueo['fin_ts'] > $inicioTs) {
                return $bloqueo;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $horariosPersonalizados
     * @return array<int, string>
     */
    private function resolverHorarios(array $horariosPersonalizados): array
    {
        $lista = $horariosPersonalizados !== []
            ? $horariosPersonalizados
            : (array) config('programacion.horarios_defecto', ['09:00', '11:00', '13:00', '15:00']);

        $unicos = [];
        foreach ($lista as $h) {
            $limpio = substr(trim((string) $h), 0, 5);
            if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $limpio) === 1 && ! in_array($limpio, $unicos, true)) {
                $unicos[] = $limpio;
            }
        }

        sort($unicos);

        return $unicos;
    }

    private function normalizarFecha(CarbonInterface|string $fecha): string
    {
        return $fecha instanceof CarbonInterface ? $fecha->toDateString() : Carbon::parse($fecha)->toDateString();
    }

    private function toTimestamp(CarbonInterface|string $fechaHora): int
    {
        return $fechaHora instanceof CarbonInterface
            ? $fechaHora->getTimestamp()
            : (int) Carbon::parse($fechaHora)->getTimestamp();
    }

    /**
     * @return array<string, mixed>
     */
    private function construirResumenVacio(): array
    {
        return [
            'total_partidos' => 0,
            'asignados' => 0,
            'sin_asignar' => 0,
            'completado' => true,
            'preferencias_respetadas' => 0,
            'conflictos' => [],
            'jornadas_procesadas' => 0,
            'duracion_ms' => 0.0,
        ];
    }
}
