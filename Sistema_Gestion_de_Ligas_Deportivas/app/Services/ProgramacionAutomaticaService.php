<?php

namespace App\Services;

use App\Models\BloqueoCancha;
use App\Models\Cancha;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\Partido;
use App\Models\PreferenciaEquipo;
use App\Models\Sancion;
use App\Models\Temporada;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Motor de asignación automática de cancha, fecha y hora (HU-074 / RF-07.2, RNF-07, RNF-08)
 * integrado con diagnóstico no bloqueante de conflictos (HU-033 / RF-07.3, RNF-19).
 */
class ProgramacionAutomaticaService
{
    public function __construct(
        private readonly ControlSancionesDeportivasService $sancionesService,
        private readonly DetectorConflictosProgramacionService $detectorConflictos,
        private readonly AuditService $audit,
    ) {
    }

    /**
     * Enriches una propuesta en memoria de GeneradorEnfrentamientosService asignando cancha, fecha y hora
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

        $estado = $this->inicializarEstadoEnMemoria(
            $fechaMin,
            $fechaMax,
            array_values(array_unique($equipoIds)),
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
                $localNombre = (string) ($partidoPropuesta['local_nombre'] ?? "Equipo #{$localId}");
                $visitanteNombre = (string) ($partidoPropuesta['visitante_nombre'] ?? "Equipo #{$visitanteId}");

                $resultado = $this->asignarPartidoEnMemoria(
                    localId: $localId,
                    visitanteId: $visitanteId,
                    localNombre: $localNombre,
                    visitanteNombre: $visitanteNombre,
                    numeroJornada: $numeroJornada,
                    partidoId: null,
                    fechaReferencia: (string) $jornadaPropuesta['fecha_inicio'],
                    ventanaSlots: $ventanaSlots,
                    estado: $estado,
                    opciones: $opciones
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
     * Si algún partido no tiene slot válido, persiste los que sí pudieron asignarse y reporta los conflictos.
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

        if (! $jornada->esBorrador()) {
            throw ValidationException::withMessages([
                'jornada' => "Solo se pueden auto-asignar horarios a jornadas en estado 'borrador'. Estado actual: '{$jornada->estado}'.",
            ]);
        }

        return $this->asignarHorariosSobreColeccionJornadas(
            collect([$jornada]),
            $opciones,
            "Asignación automática de horarios para Jornada {$jornada->numero_jornada} del grupo {$jornada->grupo->nombre}."
        );
    }

    /**
     * Asigna automáticamente horarios a todas las jornadas en borrador de un grupo (p. ej. grupo de hasta 50 equipos / RNF-07).
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

        $jornadas = Jornada::query()
            ->where('grupo_id', $grupo->id)
            ->where('estado', Jornada::ESTADO_BORRADOR)
            ->orderBy('numero_jornada')
            ->get();

        if ($jornadas->isEmpty()) {
            return $this->construirResumenVacio();
        }

        return $this->asignarHorariosSobreColeccionJornadas(
            $jornadas,
            $opciones,
            "Asignación automática masiva de horarios del grupo {$grupo->nombre}."
        );
    }

    /**
     * Asigna horarios de forma masiva a las jornadas en borrador de toda una temporada (todas las categorías/grupos)
     * en un solo paso en memoria y transacción única (RNF-07: ~400 partidos / 40 canchas <= 60s).
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

        $query = Jornada::query()
            ->whereHas('grupo', fn ($q) => $q->where('temporada_id', $temporada->id))
            ->where('estado', Jornada::ESTADO_BORRADOR)
            ->orderBy('fecha_inicio')
            ->orderBy('numero_jornada')
            ->orderBy('id');

        if (isset($opciones['numero_jornada']) && is_numeric($opciones['numero_jornada'])) {
            $query->where('numero_jornada', (int) $opciones['numero_jornada']);
        }

        $jornadas = $query->get();
        if ($jornadas->isEmpty()) {
            return $this->construirResumenVacio();
        }

        return $this->asignarHorariosSobreColeccionJornadas(
            $jornadas,
            $opciones,
            "Programación automática masiva para la temporada '{$temporada->nombre}'."
        );
    }

    /**
     * @param  Collection<int, Jornada>  $jornadas
     * @param  array<string, mixed>  $opciones
     * @return array<string, mixed>
     */
    private function asignarHorariosSobreColeccionJornadas(
        Collection $jornadas,
        array $opciones,
        string $motivoAuditoria
    ): array {
        $inicioCronometro = microtime(true);
        $reemplazar = (bool) ($opciones['reemplazar_existentes'] ?? false);

        return $this->audit->conMotivo($motivoAuditoria, function () use ($jornadas, $opciones, $reemplazar, $inicioCronometro): array {
            return DB::transaction(function () use ($jornadas, $opciones, $reemplazar, $inicioCronometro): array {
                $jornadaIds = $jornadas->pluck('id')->all();

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
                    ->orderBy('jornada_id')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($partidos->isEmpty()) {
                    return array_merge($this->construirResumenVacio(), [
                        'jornadas_procesadas' => $jornadas->count(),
                        'duracion_ms' => round((microtime(true) - $inicioCronometro) * 1000, 2),
                    ]);
                }

                $fechaMin = $this->normalizarFecha($jornadas->min('fecha_inicio'));
                $fechaMax = $this->normalizarFecha($jornadas->max('fecha_fin'));

                $equipoIds = $partidos
                    ->flatMap(fn (Partido $p): array => [(int) $p->equipo_local_id, (int) $p->equipo_visitante_id])
                    ->unique()
                    ->values()
                    ->all();

                $opcionesConExclusion = $opciones;
                if ($reemplazar) {
                    $opcionesConExclusion['ignorar_partido_ids'] = $partidos->pluck('id')->all();
                }

                $estado = $this->inicializarEstadoEnMemoria($fechaMin, $fechaMax, $equipoIds, $opcionesConExclusion);

                $partidosPorJornada = $partidos->groupBy('jornada_id');
                /** @var array<int, string> $fechaInicioPorJornada */
                $fechaInicioPorJornada = [];

                $totalPartidos = 0;
                $asignados = 0;
                $preferenciasRespetadas = 0;
                $conflictos = [];
                /** @var array<int, array{cancha_id: int, fecha_partido: string, hora_partido: string}> $cambiosPendientes */
                $cambiosPendientes = [];

                foreach ($jornadas as $jornada) {
                    $fechaInicioPorJornada[$jornada->id] = $this->normalizarFecha($jornada->fecha_inicio);

                    /** @var Collection<int, Partido>|null $lote */
                    $lote = $partidosPorJornada->get($jornada->id);
                    if ($lote === null || $lote->isEmpty()) {
                        continue;
                    }

                    $ventanaSlots = $this->construirSlotsVentana(
                        $fechaInicioPorJornada[$jornada->id],
                        $this->normalizarFecha($jornada->fecha_fin),
                        $estado['horarios'],
                        $estado['duracion_minutos']
                    );

                    foreach ($lote as $partido) {
                        $totalPartidos++;
                        $local = $partido->local;
                        $visitante = $partido->visitante;
                        $resultado = $this->asignarPartidoEnMemoria(
                            localId: (int) $partido->equipo_local_id,
                            visitanteId: (int) $partido->equipo_visitante_id,
                            localNombre: $local instanceof Equipo ? $local->nombre : "Equipo #{$partido->equipo_local_id}",
                            visitanteNombre: $visitante instanceof Equipo ? $visitante->nombre : "Equipo #{$partido->equipo_visitante_id}",
                            numeroJornada: (int) $jornada->numero_jornada,
                            partidoId: $partido->id,
                            fechaReferencia: $fechaInicioPorJornada[$jornada->id],
                            ventanaSlots: $ventanaSlots,
                            estado: $estado,
                            opciones: $opciones
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

                // Persistir partidos asignados; si se reasignó todo, limpiar horarios previos de los conflictivos para evitar empalmes residuales
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
                    'jornadas_procesadas' => $jornadas->count(),
                    'duracion_ms' => round((microtime(true) - $inicioCronometro) * 1000, 2),
                ];
            }, 3);
        });
    }

    /**
     * Precarga canchas, bloqueos, ocupación previa, preferencias y sanciones en 5 consultas acotadas (RNF-07 / RNF-08).
     *
     * @param  array<int, int>  $equipoIds
     * @param  array<string, mixed>  $opciones
     * @return array<string, mixed>
     */
    private function inicializarEstadoEnMemoria(
        string $fechaMin,
        string $fechaMax,
        array $equipoIds,
        array $opciones
    ): array {
        $duracionMinutos = (int) ($opciones['duracion_partido_minutos'] ?? config('programacion.duracion_partido_minutos', 120));
        $horarios = $this->resolverHorarios($opciones['horarios'] ?? []);

        $todasCanchas = Cancha::query()
            ->orderBy('id')
            ->get(['id', 'nombre', 'capacidad_partidos_consecutivos', 'estado']);

        if (isset($opciones['cancha_ids']) && is_array($opciones['cancha_ids']) && $opciones['cancha_ids'] !== []) {
            $filtroCanchas = array_flip(array_map('intval', $opciones['cancha_ids']));
            $todasCanchas = $todasCanchas->filter(fn (Cancha $c): bool => isset($filtroCanchas[$c->id]))->values();
        }

        $canchasOperativas = $todasCanchas
            ->filter(fn (Cancha $c): bool => $c->estado === 'Disponible')
            ->values();

        $inicioVentana = Carbon::parse($fechaMin.' 00:00:00');
        $finVentana = Carbon::parse($fechaMax.' 23:59:59');

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

        $partidosExistentes = Partido::query()
            ->queOcupanCancha()
            ->whereBetween('fecha_partido', [$fechaMin, $fechaMax])
            ->get(['id', 'cancha_id', 'equipo_local_id', 'equipo_visitante_id', 'fecha_partido', 'hora_partido']);

        $canchaSlots = [];
        $canchaConteoDia = [];
        $equipoSlots = [];

        foreach ($partidosExistentes as $p) {
            if (isset($ignorarIds[$p->id])) {
                continue;
            }

            $fechaStr = $this->normalizarFecha($p->fecha_partido);
            $horaStr = substr((string) $p->hora_partido, 0, 8);
            $inicioTs = strtotime($fechaStr.' '.$horaStr) ?: 0;
            $finTs = $inicioTs + ($duracionMinutos * 60);

            if ($p->cancha_id !== null) {
                $canchaSlots[$p->cancha_id][$fechaStr][] = [$inicioTs, $finTs];
                $canchaConteoDia[$p->cancha_id][$fechaStr] = ($canchaConteoDia[$p->cancha_id][$fechaStr] ?? 0) + 1;
            }

            $equipoSlots[(int) $p->equipo_local_id][$fechaStr][] = [$inicioTs, $finTs];
            $equipoSlots[(int) $p->equipo_visitante_id][$fechaStr][] = [$inicioTs, $finTs];
        }

        $preferencias = $equipoIds !== []
            ? PreferenciaEquipo::query()->whereIn('equipo_id', $equipoIds)->get()->keyBy('equipo_id')
            : collect();

        $sancionesEquipo = $this->sancionesService->precargarSancionesVigentesEquipos($equipoIds);

        return [
            'duracion_minutos' => $duracionMinutos,
            'horarios' => $horarios,
            'canchas_totales' => $todasCanchas->count(),
            'canchas_operativas' => $canchasOperativas,
            'bloqueos_por_cancha' => $bloqueosPorCancha,
            'cancha_slots' => $canchaSlots,
            'cancha_conteo_dia' => $canchaConteoDia,
            'equipo_slots' => $equipoSlots,
            'preferencias' => $preferencias,
            'sanciones_equipo' => $sancionesEquipo,
        ];
    }

    /**
     * Intenta asignar el mejor slot válido (restricciones duras obligatorias + blandas por puntaje) en memoria.
     *
     * @param  array<int, array{fecha: string, hora: string, dia_iso: int, inicio_ts: int, fin_ts: int}>  $ventanaSlots
     * @param  array<string, mixed>  $estado
     * @param  array<string, mixed>  $opciones
     * @return array<string, mixed>
     */
    private function asignarPartidoEnMemoria(
        int $localId,
        int $visitanteId,
        string $localNombre,
        string $visitanteNombre,
        int $numeroJornada,
        ?int $partidoId,
        string $fechaReferencia,
        array $ventanaSlots,
        array &$estado,
        array $opciones
    ): array {
        /** @var Collection<int, Cancha> $canchasOperativas */
        $canchasOperativas = $estado['canchas_operativas'];
        $respetarCapacidad = (bool) ($opciones['respetar_capacidad_cancha'] ?? true);

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
            'sancion_local' => null,
            'sancion_visitante' => null,
        ];

        $mejorCandidato = null;
        $mejorPuntaje = PHP_INT_MIN;

        foreach ($ventanaSlots as $slot) {
            $fechaStr = $slot['fecha'];
            $horaStr = $slot['hora'];
            $inicioTs = $slot['inicio_ts'];
            $finTs = $slot['fin_ts'];

            // Restricción dura: sanción colectiva vigente en la fecha candidata (RF-07.2 / RF-10.4)
            $sancionLocal = $this->detectorConflictos->buscarSancionActivaEnMemoria($localId, $fechaStr, $estado['sanciones_equipo']);
            if ($sancionLocal instanceof Sancion) {
                $rechazos['sancion_local'] = $sancionLocal;

                continue;
            }

            $sancionVisitante = $this->detectorConflictos->buscarSancionActivaEnMemoria($visitanteId, $fechaStr, $estado['sanciones_equipo']);
            if ($sancionVisitante instanceof Sancion) {
                $rechazos['sancion_visitante'] = $sancionVisitante;

                continue;
            }

            if ($canchasOperativas->isEmpty()) {
                break;
            }

            // Restricción dura: simultaneidad de equipos verificada antes de iterar las N canchas (O(1) vs O(N))
            if (
                $this->haySolapeEnLista($inicioTs, $finTs, $estado['equipo_slots'][$localId][$fechaStr] ?? [])
                || $this->haySolapeEnLista($inicioTs, $finTs, $estado['equipo_slots'][$visitanteId][$fechaStr] ?? [])
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

                // Restricción dura: capacidad de partidos consecutivos por día
                $ocupadosDia = $estado['cancha_conteo_dia'][$cancha->id][$fechaStr] ?? 0;
                if ($respetarCapacidad && $cancha->capacidad_partidos_consecutivos > 0 && $ocupadosDia >= $cancha->capacidad_partidos_consecutivos) {
                    $rechazos['por_capacidad']++;

                    continue;
                }

                // Restricción dura: exclusividad horaria de cancha (RF-05.3)
                if ($this->haySolapeEnLista($inicioTs, $finTs, $estado['cancha_slots'][$cancha->id][$fechaStr] ?? [])) {
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

                    // Atajo de rendimiento (RNF-07): sin preferencias y con cancha vacía ese día, tomar primer slot válido
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
                'fecha_referencia' => $fechaReferencia,
            ], $rechazos);

            return [
                'asignado' => false,
                'preferencia_aplicada' => false,
                'conflicto' => $conflicto,
            ];
        }

        /** @var Cancha $canchaElegida */
        $canchaElegida = $mejorCandidato['cancha'];
        $fechaElegida = (string) $mejorCandidato['fecha'];
        $horaElegida = (string) $mejorCandidato['hora'];
        $inicioElegido = (int) $mejorCandidato['inicio_ts'];
        $finElegido = (int) $mejorCandidato['fin_ts'];

        // Actualizar índices en memoria inmediatamente para preservar exclusividad dentro del mismo lote
        $estado['cancha_slots'][$canchaElegida->id][$fechaElegida][] = [$inicioElegido, $finElegido];
        $estado['cancha_conteo_dia'][$canchaElegida->id][$fechaElegida] = ($estado['cancha_conteo_dia'][$canchaElegida->id][$fechaElegida] ?? 0) + 1;
        $estado['equipo_slots'][$localId][$fechaElegida][] = [$inicioElegido, $finElegido];
        $estado['equipo_slots'][$visitanteId][$fechaElegida][] = [$inicioElegido, $finElegido];

        return [
            'asignado' => true,
            'cancha_id' => $canchaElegida->id,
            'cancha_nombre' => $canchaElegida->nombre,
            'fecha_partido' => $fechaElegida,
            'hora_partido' => $horaElegida,
            'preferencia_aplicada' => (bool) $mejorCandidato['preferencia_aplicada'],
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
        $puntaje = 0;

        if ($prefLocal !== null) {
            if ($prefLocal->cancha_id_preferida === $canchaId) {
                $puntaje += 40;
            }
            if ($prefLocal->diaPreferidoIso() === $diaIso) {
                $puntaje += 25;
            }
            if ($prefLocal->horaNormalizada() === $horaStr) {
                $puntaje += 20;
            }
        }

        if ($prefVisitante !== null) {
            if ($prefVisitante->cancha_id_preferida === $canchaId) {
                $puntaje += 15;
            }
            if ($prefVisitante->diaPreferidoIso() === $diaIso) {
                $puntaje += 10;
            }
            if ($prefVisitante->horaNormalizada() === $horaStr) {
                $puntaje += 8;
            }
        }

        return $puntaje;
    }

    /**
     * Construye todos los intervalos `(fecha, hora)` candidatos dentro de una ventana de fechas.
     *
     * @param  array<int, string>  $horarios
     * @return array<int, array{fecha: string, hora: string, dia_iso: int, inicio_ts: int, fin_ts: int}>
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
                $slots[] = [
                    'fecha' => $fechaStr,
                    'hora' => $horaStr,
                    'dia_iso' => $diaIso,
                    'inicio_ts' => $inicioTs,
                    'fin_ts' => $inicioTs + $duracionSegundos,
                ];
            }

            $cursor->addDay();
        }

        return $slots;
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $lista
     */
    private function haySolapeEnLista(int $inicioTs, int $finTs, array $lista): bool
    {
        foreach ($lista as $intervalo) {
            if ($inicioTs < $intervalo[1] && $finTs > $intervalo[0]) {
                return true;
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
