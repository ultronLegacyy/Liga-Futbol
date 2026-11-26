<?php

namespace App\Services;

use App\Models\BloqueoCancha;
use App\Models\Cancha;
use App\Models\Equipo;
use App\Models\Jornada;
use App\Models\Partido;
use App\Models\Sancion;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Detector y explicador de conflictos de programación automática y semanal (HU-033, RF-07.3, RNF-19).
 *
 * Opera sobre datos precargados en memoria cuando se invoca en procesos masivos para preservar O(1) consultas
 * adicionales (RNF-07 / RNF-08) y explica cada conflicto indicando regla de origen, causa concreta y sugerencia.
 */
class DetectorConflictosProgramacionService
{
    public function __construct(
        private readonly ControlSancionesDeportivasService $sancionesService,
    ) {
    }

    /**
     * Analiza una jornada persistida y devuelve el listado estructurado de conflictos (RNF-19).
     *
     * @param  array<int, string>  $horarios
     * @param  array<string, mixed>|null  $datosPrecargados
     * @return array<int, array<string, mixed>>
     */
    public function analizarJornada(Jornada $jornada, array $horarios = [], ?array $datosPrecargados = null): array
    {
        $jornada->loadMissing(['grupo', 'equipoDescanso']);
        $todosPartidos = $jornada->partidos()->with(['local', 'visitante', 'cancha'])->get();

        if ($todosPartidos->isEmpty()) {
            return [$this->construirConflicto(
                codigoRegla: 'JORNADA_SIN_PARTIDOS',
                reglaOrigen: 'RF-06.5: Integridad mínima de la jornada semanal',
                severidad: 'bloqueante',
                jornadaNumero: $jornada->numero_jornada,
                partidoId: null,
                enfrentamiento: 'Jornada '.$jornada->numero_jornada,
                fechaReferencia: $this->normalizarFecha($jornada->fecha_inicio),
                causa: 'La jornada no cuenta con partidos programados.',
                sugerencia: 'Genere los enfrentamientos del rol regular o agregue partidos antes de publicar.',
            )];
        }

        $partidosActivos = $todosPartidos->reject(fn (Partido $p): bool => $p->estado === Partido::ESTADO_CANCELADO);

        if ($partidosActivos->isEmpty()) {
            return [$this->construirConflicto(
                codigoRegla: 'JORNADA_SIN_PARTIDOS_ACTIVOS',
                reglaOrigen: 'RF-06.5: Partidos activos requeridos para publicar',
                severidad: 'bloqueante',
                jornadaNumero: $jornada->numero_jornada,
                partidoId: null,
                enfrentamiento: 'Jornada '.$jornada->numero_jornada,
                fechaReferencia: $this->normalizarFecha($jornada->fecha_inicio),
                causa: 'Todos los partidos de la jornada se encuentran cancelados.',
                sugerencia: 'Reprograme o active al menos un enfrentamiento válido en la jornada.',
            )];
        }

        $duracionMinutos = (int) config('programacion.duracion_partido_minutos', 120);
        $horariosCandidatos = $this->resolverHorarios($horarios);
        $contexto = $datosPrecargados ?? $this->precargarContextoParaJornada($jornada, $partidosActivos);

        $conflictos = [];
        $conflictos = array_merge($conflictos, $this->detectarDuplicidadYDescanso($jornada, $partidosActivos));

        /** @var array<string, array<int, array{inicio: Carbon, fin: Carbon, partido_id: int}>> $slotsInternosCancha */
        $slotsInternosCancha = [];
        /** @var array<string, int> $conteoInternoCanchaDia */
        $conteoInternoCanchaDia = [];
        /** @var array<int, array<int, array{inicio: Carbon, fin: Carbon, partido_id: int}>> $slotsInternosEquipo */
        $slotsInternosEquipo = [];

        foreach ($partidosActivos as $partido) {
            $enfrentamiento = $this->formatearEnfrentamiento($partido);
            $fechaPartido = $partido->fecha_partido !== null
                ? $this->normalizarFecha($partido->fecha_partido)
                : $this->normalizarFecha($jornada->fecha_inicio);

            $conflictoSancion = $this->verificarSancionColectivaEnPartido(
                $partido,
                $jornada->numero_jornada,
                $fechaPartido,
                $contexto['sanciones_equipo']
            );
            if ($conflictoSancion !== null) {
                $conflictos[] = $conflictoSancion;
            }

            if ($partido->cancha_id === null || $partido->fecha_partido === null || $partido->hora_partido === null) {
                if ($conflictoSancion === null) {
                    $conflictos[] = $this->diagnosticarPartidoSinHorario(
                        $jornada,
                        $partido,
                        $horariosCandidatos,
                        $duracionMinutos,
                        $contexto
                    );
                }

                continue;
            }

            $cancha = $contexto['canchas_por_id'][$partido->cancha_id] ?? $partido->cancha;
            if (! $cancha instanceof Cancha) {
                continue;
            }

            $fechaStr = $this->normalizarFecha($partido->fecha_partido);
            $horaStr = substr((string) $partido->hora_partido, 0, 8);
            $inicio = Carbon::parse($fechaStr.' '.$horaStr);
            $fin = $inicio->copy()->addMinutes($duracionMinutos);

            if ($cancha->estado !== 'Disponible') {
                $conflictos[] = $this->construirConflicto(
                    codigoRegla: 'CANCHA_NO_DISPONIBLE',
                    reglaOrigen: 'RF-05.1 / RF-07.2: Estado operativo de la cancha en catálogo',
                    severidad: 'bloqueante',
                    jornadaNumero: $jornada->numero_jornada,
                    partidoId: $partido->id,
                    enfrentamiento: $enfrentamiento,
                    fechaReferencia: $fechaStr,
                    causa: "La cancha '{$cancha->nombre}' se encuentra en estado '{$cancha->estado}' y no admite partidos.",
                    sugerencia: "Reasigne el partido a una cancha en estado 'Disponible' o actualice el estado de '{$cancha->nombre}'.",
                );
            }

            $bloqueo = $this->buscarBloqueoSolapado($cancha->id, $inicio, $fin, $contexto['bloqueos_por_cancha']);
            if ($bloqueo instanceof BloqueoCancha) {
                $inicioFmt = $bloqueo->fecha_inicio?->format('d/m/Y H:i') ?? '';
                $finFmt = $bloqueo->fecha_fin?->format('d/m/Y H:i') ?? '';
                $conflictos[] = $this->construirConflicto(
                    codigoRegla: 'CANCHA_BLOQUEADA',
                    reglaOrigen: 'RF-05.2 / RF-07.2: Bloqueo temporal de cancha por mantenimiento o contingencia',
                    severidad: 'bloqueante',
                    jornadaNumero: $jornada->numero_jornada,
                    partidoId: $partido->id,
                    enfrentamiento: $enfrentamiento,
                    fechaReferencia: $fechaStr,
                    causa: "La cancha '{$cancha->nombre}' tiene bloqueo vigente del {$inicioFmt} al {$finFmt} (motivo: {$bloqueo->motivo}).",
                    sugerencia: "Seleccione otra cancha disponible o reprograme fuera del horario bloqueado en '{$cancha->nombre}'.",
                );
            }

            $claveCanchaDia = $cancha->id.'_'.$fechaStr;
            $slotsInternosCancha[$claveCanchaDia] ??= [];

            foreach ($slotsInternosCancha[$claveCanchaDia] as $slotPrevio) {
                if ($inicio->lessThan($slotPrevio['fin']) && $fin->greaterThan($slotPrevio['inicio'])) {
                    $conflictos[] = $this->construirConflicto(
                        codigoRegla: 'CANCHA_OCUPADA',
                        reglaOrigen: 'RF-05.3 / RF-07.2: Exclusividad horaria de cancha (no empalmes)',
                        severidad: 'bloqueante',
                        jornadaNumero: $jornada->numero_jornada,
                        partidoId: $partido->id,
                        enfrentamiento: $enfrentamiento,
                        fechaReferencia: $fechaStr,
                        causa: "La cancha '{$cancha->nombre}' presenta empalme horario el {$fechaStr} a las {$inicio->format('H:i')} con otro partido de la misma jornada ({$slotPrevio['inicio']->format('H:i')}).",
                        sugerencia: "Mueva el encuentro a otra franja horaria libre (separación mínima de {$duracionMinutos} minutos) o a otra cancha.",
                    );
                    break;
                }
            }

            $slotsInternosCancha[$claveCanchaDia][] = ['inicio' => $inicio, 'fin' => $fin, 'partido_id' => $partido->id];

            // Verificar empalmes externos registrados en otras jornadas/grupos
            $ocupacionExterna = $this->buscarSolapamientoEnSlots(
                $inicio,
                $fin,
                $contexto['ocupacion_canchas_externas'][$claveCanchaDia] ?? [],
                $partido->id
            );
            if ($ocupacionExterna !== null) {
                $conflictos[] = $this->construirConflicto(
                    codigoRegla: 'CANCHA_OCUPADA',
                    reglaOrigen: 'RF-05.3 / RF-07.2: Exclusividad horaria de cancha entre categorías y grupos',
                    severidad: 'bloqueante',
                    jornadaNumero: $jornada->numero_jornada,
                    partidoId: $partido->id,
                    enfrentamiento: $enfrentamiento,
                    fechaReferencia: $fechaStr,
                    causa: "La cancha '{$cancha->nombre}' ya está ocupada el {$fechaStr} de {$ocupacionExterna['inicio']->format('H:i')} a {$ocupacionExterna['fin']->format('H:i')} por otro encuentro ({$ocupacionExterna['detalle']}).",
                    sugerencia: 'Cambie la sede o el horario del encuentro a una franja sin ocupación previa.',
                );
            }

            // Verificar capacidad de partidos consecutivos por día
            $conteoInternoCanchaDia[$claveCanchaDia] = ($conteoInternoCanchaDia[$claveCanchaDia] ?? 0) + 1;
            $externosEnDia = count($contexto['ocupacion_canchas_externas'][$claveCanchaDia] ?? []);
            $totalDia = $conteoInternoCanchaDia[$claveCanchaDia] + $externosEnDia;
            if ($cancha->capacidad_partidos_consecutivos > 0 && $totalDia > $cancha->capacidad_partidos_consecutivos) {
                $conflictos[] = $this->construirConflicto(
                    codigoRegla: 'CANCHA_SIN_CAPACIDAD',
                    reglaOrigen: 'RF-07.2: Límite de partidos consecutivos diarios por cancha',
                    severidad: 'advertencia',
                    jornadaNumero: $jornada->numero_jornada,
                    partidoId: $partido->id,
                    enfrentamiento: $enfrentamiento,
                    fechaReferencia: $fechaStr,
                    causa: "La cancha '{$cancha->nombre}' tiene asignados {$totalDia} partidos el {$fechaStr}, superando su capacidad configurada de {$cancha->capacidad_partidos_consecutivos} por día.",
                    sugerencia: "Redistribuya partidos a otra fecha dentro de la jornada o amplíe la capacidad de '{$cancha->nombre}'.",
                );
            }

            // Verificar traslape horario por equipo (mismo día y franja)
            foreach ([(int) $partido->equipo_local_id, (int) $partido->equipo_visitante_id] as $equipoId) {
                $equipoNombre = $equipoId === (int) $partido->equipo_local_id
                    ? $this->nombreEquipo($partido->local, (int) $partido->equipo_local_id)
                    : $this->nombreEquipo($partido->visitante, (int) $partido->equipo_visitante_id);

                $slotsInternosEquipo[$equipoId] ??= [];
                $solapeInternoEquipo = $this->buscarSolapamientoEnSlots($inicio, $fin, $slotsInternosEquipo[$equipoId], $partido->id);
                $solapeExternoEquipo = $this->buscarSolapamientoEnSlots($inicio, $fin, $contexto['ocupacion_equipos'][$equipoId] ?? [], $partido->id);
                $solapeEquipo = $solapeInternoEquipo ?? $solapeExternoEquipo;

                if ($solapeEquipo !== null) {
                    $conflictos[] = $this->construirConflicto(
                        codigoRegla: 'EQUIPO_EMPALME_HORARIO',
                        reglaOrigen: 'RF-07.2: No simultaneidad de partidos para un mismo equipo',
                        severidad: 'bloqueante',
                        jornadaNumero: $jornada->numero_jornada,
                        partidoId: $partido->id,
                        enfrentamiento: $enfrentamiento,
                        fechaReferencia: $fechaStr,
                        causa: "El equipo '{$equipoNombre}' ya tiene otro encuentro programado el {$fechaStr} entre {$solapeEquipo['inicio']->format('H:i')} y {$solapeEquipo['fin']->format('H:i')}.",
                        sugerencia: "Programe '{$enfrentamiento}' en un horario o día distinto donde ambos equipos estén libres.",
                    );
                }

                $slotsInternosEquipo[$equipoId][] = ['inicio' => $inicio, 'fin' => $fin, 'partido_id' => $partido->id];
            }
        }

        return $this->deduplicarConflictos($conflictos);
    }

    /**
     * Genera una explicación clara (RNF-19) cuando el motor de programación automática
     * no logra asignar cancha, fecha u hora a un enfrentamiento tras evaluar todos los slots candidatos.
     *
     * @param  array{
     *     jornada_numero: int|null,
     *     partido_id: int|null,
     *     local_id: int,
     *     visitante_id: int,
     *     local_nombre: string,
     *     visitante_nombre: string,
     *     fecha_referencia: string|null
     * }  $datosPartido
     * @param  array{
     *     canchas_totales: int,
     *     canchas_operativas: int,
     *     slots_evaluados: int,
     *     por_bloqueo: array<int, string>,
     *     por_capacidad: int,
     *     por_ocupacion_cancha: int,
     *     por_empalme_equipo: int,
     *     sancion_local: Sancion|null,
     *     sancion_visitante: Sancion|null
     * }  $rechazos
     * @return array<string, mixed>
     */
    public function explicarImposibilidadPartido(array $datosPartido, array $rechazos): array
    {
        $enfrentamiento = "{$datosPartido['local_nombre']} vs {$datosPartido['visitante_nombre']}";
        $jornadaNumero = $datosPartido['jornada_numero'];
        $partidoId = $datosPartido['partido_id'];
        $fechaRef = $datosPartido['fecha_referencia'];

        if (($rechazos['sancion_local'] ?? null) instanceof Sancion) {
            /** @var Sancion $sancion */
            $sancion = $rechazos['sancion_local'];

            return $this->construirConflicto(
                codigoRegla: 'SANCION_EQUIPO_VIGENTE',
                reglaOrigen: 'RF-07.2 / RF-10.4: Restricción por sanción disciplinaria vigente al equipo',
                severidad: 'bloqueante',
                jornadaNumero: $jornadaNumero,
                partidoId: $partidoId,
                enfrentamiento: $enfrentamiento,
                fechaReferencia: $fechaRef,
                causa: "El equipo local '{$datosPartido['local_nombre']}' cuenta con una sanción disciplinaria vigente ({$sancion->explicacionRegla()}).",
                sugerencia: 'Verifique el expediente disciplinario o espere a que concluya la sanción antes de programar este partido.',
            );
        }

        if (($rechazos['sancion_visitante'] ?? null) instanceof Sancion) {
            /** @var Sancion $sancion */
            $sancion = $rechazos['sancion_visitante'];

            return $this->construirConflicto(
                codigoRegla: 'SANCION_EQUIPO_VIGENTE',
                reglaOrigen: 'RF-07.2 / RF-10.4: Restricción por sanción disciplinaria vigente al equipo',
                severidad: 'bloqueante',
                jornadaNumero: $jornadaNumero,
                partidoId: $partidoId,
                enfrentamiento: $enfrentamiento,
                fechaReferencia: $fechaRef,
                causa: "El equipo visitante '{$datosPartido['visitante_nombre']}' cuenta con una sanción disciplinaria vigente ({$sancion->explicacionRegla()}).",
                sugerencia: 'Verifique el expediente disciplinario o espere a que concluya la sanción antes de programar este partido.',
            );
        }

        if ($rechazos['canchas_operativas'] === 0) {
            $causa = $rechazos['canchas_totales'] === 0
                ? 'No existen canchas registradas en el catálogo del sistema.'
                : "Ninguna de las {$rechazos['canchas_totales']} canchas registradas se encuentra en estado 'Disponible' (todas están en Mantenimiento o Inactivas).";

            return $this->construirConflicto(
                codigoRegla: 'SIN_CANCHAS_OPERATIVAS',
                reglaOrigen: 'RF-05.1 / RF-07.2: Disponibilidad de infraestructura deportiva',
                severidad: 'bloqueante',
                jornadaNumero: $jornadaNumero,
                partidoId: $partidoId,
                enfrentamiento: $enfrentamiento,
                fechaReferencia: $fechaRef,
                causa: $causa,
                sugerencia: "Registre o habilite al menos una cancha en estado 'Disponible' en el catálogo de canchas.",
            );
        }

        if ($rechazos['slots_evaluados'] === 0) {
            return $this->construirConflicto(
                codigoRegla: 'SIN_VENTANA_HORARIA',
                reglaOrigen: 'RF-07.2: Ventana de fechas y franjas horarias de la jornada',
                severidad: 'bloqueante',
                jornadaNumero: $jornadaNumero,
                partidoId: $partidoId,
                enfrentamiento: $enfrentamiento,
                fechaReferencia: $fechaRef,
                causa: 'No se generaron franjas fecha/hora válidas dentro del rango de la jornada.',
                sugerencia: 'Amplíe la fecha de fin de la jornada o configure al menos un horario disponible.',
            );
        }

        $conteos = [
            'CANCHA_BLOQUEADA' => count($rechazos['por_bloqueo']),
            'CANCHA_SIN_CAPACIDAD' => $rechazos['por_capacidad'],
            'CANCHA_OCUPADA' => $rechazos['por_ocupacion_cancha'],
            'EQUIPO_EMPALME_HORARIO' => $rechazos['por_empalme_equipo'],
        ];
        arsort($conteos);
        $principal = (string) array_key_first($conteos);

        if ($principal === 'CANCHA_BLOQUEADA' && $conteos['CANCHA_BLOQUEADA'] > 0) {
            $ejemplos = array_slice(array_values(array_unique($rechazos['por_bloqueo'])), 0, 2);
            $detalleBloqueos = implode('; ', $ejemplos);

            return $this->construirConflicto(
                codigoRegla: 'CANCHA_BLOQUEADA',
                reglaOrigen: 'RF-05.2 / RF-07.2: Exclusión de canchas con bloqueo temporal vigente',
                severidad: 'bloqueante',
                jornadaNumero: $jornadaNumero,
                partidoId: $partidoId,
                enfrentamiento: $enfrentamiento,
                fechaReferencia: $fechaRef,
                causa: "No quedaron horarios libres porque las canchas operativas presentan bloqueos temporales en las franjas evaluadas ({$detalleBloqueos}).",
                sugerencia: 'Amplíe los días de la jornada, levante algún bloqueo si ya no aplica o agregue otra sede disponible.',
            );
        }

        if ($principal === 'CANCHA_SIN_CAPACIDAD' && $conteos['CANCHA_SIN_CAPACIDAD'] > 0) {
            return $this->construirConflicto(
                codigoRegla: 'CANCHA_SIN_CAPACIDAD',
                reglaOrigen: 'RF-07.2: Límite de capacidad de partidos consecutivos por cancha y día',
                severidad: 'bloqueante',
                jornadaNumero: $jornadaNumero,
                partidoId: $partidoId,
                enfrentamiento: $enfrentamiento,
                fechaReferencia: $fechaRef,
                causa: "La demanda de partidos excede la capacidad diaria configurada en las canchas operativas ({$rechazos['por_capacidad']} combinaciones descartadas por tope de partidos consecutivos).",
                sugerencia: 'Amplíe la ventana de fechas de la jornada, incremente la capacidad de partidos consecutivos o habilite más canchas.',
            );
        }

        if ($principal === 'EQUIPO_EMPALME_HORARIO' && $conteos['EQUIPO_EMPALME_HORARIO'] > 0) {
            return $this->construirConflicto(
                codigoRegla: 'EQUIPO_EMPALME_HORARIO',
                reglaOrigen: 'RF-07.2: Un equipo no puede disputar dos partidos simultáneos',
                severidad: 'bloqueante',
                jornadaNumero: $jornadaNumero,
                partidoId: $partidoId,
                enfrentamiento: $enfrentamiento,
                fechaReferencia: $fechaRef,
                causa: "Uno o ambos equipos ya tienen compromisos previos en todos los horarios disponibles de la ventana evaluada ({$rechazos['por_empalme_equipo']} franjas descartadas por simultaneidad).",
                sugerencia: 'Agregue franjas horarias adicionales o extienda los días de la jornada para separar los compromisos.',
            );
        }

        return $this->construirConflicto(
            codigoRegla: 'CANCHA_OCUPADA',
            reglaOrigen: 'RF-05.3 / RF-07.2: Capacidad horaria total de canchas disponibles',
            severidad: 'bloqueante',
            jornadaNumero: $jornadaNumero,
            partidoId: $partidoId,
            enfrentamiento: $enfrentamiento,
            fechaReferencia: $fechaRef,
            causa: "Todas las franjas fecha/hora de las {$rechazos['canchas_operativas']} canchas disponibles están ocupadas por otros partidos ({$rechazos['por_ocupacion_cancha']} franjas con ocupación previa).",
            sugerencia: 'Agregue más horarios en la configuración, amplíe los días de la jornada o asigne más canchas.',
        );
    }

    /**
     * Evalúa en memoria si un equipo posee alguna sanción colectiva vigente en la fecha dada.
     *
     * @param  Collection<int, Collection<int, Sancion>>  $sancionesPorEquipo
     */
    public function buscarSancionActivaEnMemoria(int $equipoId, string $fecha, Collection $sancionesPorEquipo): ?Sancion
    {
        /** @var Collection<int, Sancion>|null $lista */
        $lista = $sancionesPorEquipo->get($equipoId);
        if ($lista === null || $lista->isEmpty()) {
            return null;
        }

        foreach ($lista as $sancion) {
            if ($sancion->estado !== Sancion::ESTADO_VIGENTE) {
                continue;
            }

            if ($sancion->tipo_sancion === Sancion::TIPO_PARTIDOS && $sancion->partidos_cumplidos < $sancion->partidos_suspendidos) {
                return $sancion;
            }

            if ($sancion->tipo_sancion === Sancion::TIPO_TEMPORAL) {
                $inicioOk = $sancion->fecha_inicio === null || $this->normalizarFecha($sancion->fecha_inicio) <= $fecha;
                $finOk = $sancion->fecha_fin === null || $this->normalizarFecha($sancion->fecha_fin) >= $fecha;
                if ($inicioOk && $finOk) {
                    return $sancion;
                }
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, Partido>  $partidosActivos
     * @return array<int, array<string, mixed>>
     */
    private function detectarDuplicidadYDescanso(Jornada $jornada, Collection $partidosActivos): array
    {
        $conflictos = [];
        $equiposContados = [];

        foreach ($partidosActivos as $partido) {
            $enfrentamiento = $this->formatearEnfrentamiento($partido);
            $fechaRef = $partido->fecha_partido !== null
                ? $this->normalizarFecha($partido->fecha_partido)
                : $this->normalizarFecha($jornada->fecha_inicio);

            $pares = [
                (int) $partido->equipo_local_id => $this->nombreEquipo($partido->local, (int) $partido->equipo_local_id),
                (int) $partido->equipo_visitante_id => $this->nombreEquipo($partido->visitante, (int) $partido->equipo_visitante_id),
            ];

            foreach ($pares as $equipoId => $nombreEquipo) {
                if (isset($equiposContados[$equipoId])) {
                    $conflictos[] = $this->construirConflicto(
                        codigoRegla: 'EQUIPO_DUPLICADO_JORNADA',
                        reglaOrigen: 'RF-06.1 / RF-07.1: Un equipo solo disputa un partido por jornada regular',
                        severidad: 'bloqueante',
                        jornadaNumero: $jornada->numero_jornada,
                        partidoId: $partido->id,
                        enfrentamiento: $enfrentamiento,
                        fechaReferencia: $fechaRef,
                        causa: "El equipo '{$nombreEquipo}' está programado más de una vez en la Jornada {$jornada->numero_jornada}.",
                        sugerencia: 'Elimine o reprograme el encuentro duplicado antes de publicar la jornada.',
                    );
                } else {
                    $equiposContados[$equipoId] = true;
                }

                if ($jornada->equipo_descanso_id !== null && $equipoId === (int) $jornada->equipo_descanso_id) {
                    $conflictos[] = $this->construirConflicto(
                        codigoRegla: 'EQUIPO_EN_DESCANSO_PROGRAMADO',
                        reglaOrigen: 'RF-06.2: Exclusión del equipo en descanso semanal',
                        severidad: 'bloqueante',
                        jornadaNumero: $jornada->numero_jornada,
                        partidoId: $partido->id,
                        enfrentamiento: $enfrentamiento,
                        fechaReferencia: $fechaRef,
                        causa: "El equipo '{$nombreEquipo}' tiene descanso oficial asignado en la Jornada {$jornada->numero_jornada} y no debe disputar partido.",
                        sugerencia: 'Retire el partido donde aparece el equipo en descanso o actualice la asignación de descanso.',
                    );
                }
            }
        }

        return $conflictos;
    }

    /**
     * @param  Collection<int, Collection<int, Sancion>>  $sancionesPorEquipo
     * @return array<string, mixed>|null
     */
    private function verificarSancionColectivaEnPartido(
        Partido $partido,
        int $numeroJornada,
        string $fechaReferencia,
        Collection $sancionesPorEquipo
    ): ?array {
        $enfrentamiento = $this->formatearEnfrentamiento($partido);

        $sancionLocal = $this->buscarSancionActivaEnMemoria((int) $partido->equipo_local_id, $fechaReferencia, $sancionesPorEquipo);
        if ($sancionLocal instanceof Sancion) {
            $localNombre = $this->nombreEquipo($partido->local, (int) $partido->equipo_local_id);

            return $this->construirConflicto(
                codigoRegla: 'SANCION_EQUIPO_VIGENTE',
                reglaOrigen: 'RF-07.2 / RF-10.4: Restricción por sanción disciplinaria vigente al equipo',
                severidad: 'bloqueante',
                jornadaNumero: $numeroJornada,
                partidoId: $partido->id,
                enfrentamiento: $enfrentamiento,
                fechaReferencia: $fechaReferencia,
                causa: "El equipo local '{$localNombre}' tiene sanción colectiva vigente ({$sancionLocal->explicacionRegla()}).",
                sugerencia: 'Suspenda temporalmente o retire la programación del equipo hasta que concluya la sanción.',
            );
        }

        $sancionVisitante = $this->buscarSancionActivaEnMemoria((int) $partido->equipo_visitante_id, $fechaReferencia, $sancionesPorEquipo);
        if ($sancionVisitante instanceof Sancion) {
            $visitanteNombre = $this->nombreEquipo($partido->visitante, (int) $partido->equipo_visitante_id);

            return $this->construirConflicto(
                codigoRegla: 'SANCION_EQUIPO_VIGENTE',
                reglaOrigen: 'RF-07.2 / RF-10.4: Restricción por sanción disciplinaria vigente al equipo',
                severidad: 'bloqueante',
                jornadaNumero: $numeroJornada,
                partidoId: $partido->id,
                enfrentamiento: $enfrentamiento,
                fechaReferencia: $fechaReferencia,
                causa: "El equipo visitante '{$visitanteNombre}' tiene sanción colectiva vigente ({$sancionVisitante->explicacionRegla()}).",
                sugerencia: 'Suspenda temporalmente o retire la programación del equipo hasta que concluya la sanción.',
            );
        }

        return null;
    }

    /**
     * Diagnostica por qué un partido sin cancha/horario no tiene espacio disponible en la ventana de la jornada.
     *
     * @param  array<int, string>  $horariosCandidatos
     * @param  array<string, mixed>  $contexto
     * @return array<string, mixed>
     */
    private function diagnosticarPartidoSinHorario(
        Jornada $jornada,
        Partido $partido,
        array $horariosCandidatos,
        int $duracionMinutos,
        array $contexto
    ): array {
        /** @var Collection<int, Cancha> $canchasOperativas */
        $canchasOperativas = $contexto['canchas_operativas'];
        $fechas = $this->expandirFechas($jornada->fecha_inicio, $jornada->fecha_fin);

        $rechazos = [
            'canchas_totales' => count($contexto['canchas_por_id']),
            'canchas_operativas' => $canchasOperativas->count(),
            'slots_evaluados' => 0,
            'por_bloqueo' => [],
            'por_capacidad' => 0,
            'por_ocupacion_cancha' => 0,
            'por_empalme_equipo' => 0,
            'sancion_local' => null,
            'sancion_visitante' => null,
        ];

        $slotsDisponibles = 0;

        // Simular qué pasó en cada slot de la ventana actual de la jornada
        foreach ($fechas as $fechaStr) {
            foreach ($horariosCandidatos as $horaStr) {
                $inicio = Carbon::parse($fechaStr.' '.$horaStr);
                $fin = $inicio->copy()->addMinutes($duracionMinutos);

                if ($canchasOperativas->isNotEmpty()) {
                    $solapeLocal = $this->buscarSolapamientoEnSlots($inicio, $fin, $contexto['ocupacion_equipos'][$partido->equipo_local_id] ?? [], $partido->id);
                    $solapeVisitante = $this->buscarSolapamientoEnSlots($inicio, $fin, $contexto['ocupacion_equipos'][$partido->equipo_visitante_id] ?? [], $partido->id);
                    if ($solapeLocal !== null || $solapeVisitante !== null) {
                        $rechazos['por_empalme_equipo'] += $canchasOperativas->count();
                        $rechazos['slots_evaluados'] += $canchasOperativas->count();

                        continue;
                    }
                }

                foreach ($canchasOperativas as $cancha) {
                    $rechazos['slots_evaluados']++;

                    $bloqueo = $this->buscarBloqueoSolapado($cancha->id, $inicio, $fin, $contexto['bloqueos_por_cancha']);
                    if ($bloqueo instanceof BloqueoCancha) {
                        $rechazos['por_bloqueo'][] = "{$cancha->nombre} ({$bloqueo->motivo})";

                        continue;
                    }

                    $claveCanchaDia = $cancha->id.'_'.$fechaStr;
                    $ocupacionDia = $contexto['ocupacion_total_cancha_dia'][$claveCanchaDia] ?? 0;
                    if ($cancha->capacidad_partidos_consecutivos > 0 && $ocupacionDia >= $cancha->capacidad_partidos_consecutivos) {
                        $rechazos['por_capacidad']++;

                        continue;
                    }

                    $slotsCancha = $contexto['ocupacion_todas_canchas'][$claveCanchaDia] ?? [];
                    if ($this->buscarSolapamientoEnSlots($inicio, $fin, $slotsCancha, $partido->id) !== null) {
                        $rechazos['por_ocupacion_cancha']++;

                        continue;
                    }

                    $slotsDisponibles++;
                }
            }
        }

        // Si había slots teóricamente libres, significa que aún no se ha ejecutado asignación manual/automática
        if ($canchasOperativas->isNotEmpty() && $slotsDisponibles > 0) {
            return $this->construirConflicto(
                codigoRegla: 'SIN_ASIGNACION_HORARIA',
                reglaOrigen: 'RF-06.5 / RF-07.2: Programación completa de cancha, fecha y hora requerida',
                severidad: 'bloqueante',
                jornadaNumero: $jornada->numero_jornada,
                partidoId: $partido->id,
                enfrentamiento: $this->formatearEnfrentamiento($partido),
                fechaReferencia: $this->normalizarFecha($jornada->fecha_inicio),
                causa: "El partido '{$this->formatearEnfrentamiento($partido)}' aún no tiene cancha u horario asignado completo, aunque existen franjas disponibles en la jornada.",
                sugerencia: 'Ejecute la asignación automática de horarios o capture manualmente cancha, fecha y hora.',
            );
        }

        return $this->explicarImposibilidadPartido([
            'jornada_numero' => $jornada->numero_jornada,
            'partido_id' => $partido->id,
            'local_id' => (int) $partido->equipo_local_id,
            'visitante_id' => (int) $partido->equipo_visitante_id,
            'local_nombre' => $this->nombreEquipo($partido->local, (int) $partido->equipo_local_id),
            'visitante_nombre' => $this->nombreEquipo($partido->visitante, (int) $partido->equipo_visitante_id),
            'fecha_referencia' => $this->normalizarFecha($jornada->fecha_inicio),
        ], $rechazos);
    }

    /**
     * Precarga en 4 consultas acotadas el estado completo necesario para analizar una jornada sin N+1 (RNF-08).
     *
     * @param  Collection<int, Partido>  $partidosActivos
     * @return array<string, mixed>
     */
    private function precargarContextoParaJornada(Jornada $jornada, Collection $partidosActivos): array
    {
        $fechaMin = $this->normalizarFecha($jornada->fecha_inicio);
        $fechaMax = $this->normalizarFecha($jornada->fecha_fin);

        foreach ($partidosActivos as $partido) {
            if ($partido->fecha_partido !== null) {
                $f = $this->normalizarFecha($partido->fecha_partido);
                if ($f < $fechaMin) {
                    $fechaMin = $f;
                }
                if ($f > $fechaMax) {
                    $fechaMax = $f;
                }
            }
        }

        $inicioVentana = Carbon::parse($fechaMin.' 00:00:00');
        $finVentana = Carbon::parse($fechaMax.' 23:59:59');

        $todasCanchas = Cancha::query()->orderBy('id')->get();
        $canchasPorId = $todasCanchas->keyBy('id');
        $canchasOperativas = $todasCanchas->filter(fn (Cancha $c): bool => $c->estado === 'Disponible')->values();

        $bloqueosPorCancha = BloqueoCancha::query()
            ->solapadosCon($inicioVentana, $finVentana)
            ->get()
            ->groupBy('cancha_id');

        $duracionMinutos = (int) config('programacion.duracion_partido_minutos', 120);
        $equipoIds = $partidosActivos
            ->flatMap(fn (Partido $p): array => [(int) $p->equipo_local_id, (int) $p->equipo_visitante_id])
            ->unique()
            ->values()
            ->all();

        $sancionesEquipo = $this->sancionesService->precargarSancionesVigentesEquipos($equipoIds);

        $partidosVentana = Partido::query()
            ->with(['local', 'visitante'])
            ->queOcupanCancha()
            ->whereBetween('fecha_partido', [$fechaMin, $fechaMax])
            ->get();

        $ocupacionExternas = [];
        $ocupacionTodas = [];
        $conteoTotalCanchaDia = [];
        $ocupacionEquipos = [];

        foreach ($partidosVentana as $p) {
            $fechaStr = $this->normalizarFecha($p->fecha_partido);
            $horaStr = substr((string) $p->hora_partido, 0, 8);
            $inicio = Carbon::parse($fechaStr.' '.$horaStr);
            $fin = $inicio->copy()->addMinutes($duracionMinutos);
            $detalle = $this->formatearEnfrentamiento($p);
            $entry = ['inicio' => $inicio, 'fin' => $fin, 'partido_id' => $p->id, 'detalle' => $detalle];

            if ($p->cancha_id !== null) {
                $clave = $p->cancha_id.'_'.$fechaStr;
                $ocupacionTodas[$clave][] = $entry;
                $conteoTotalCanchaDia[$clave] = ($conteoTotalCanchaDia[$clave] ?? 0) + 1;

                if ($p->jornada_id !== $jornada->id) {
                    $ocupacionExternas[$clave][] = $entry;
                }
            }

            $ocupacionEquipos[(int) $p->equipo_local_id][] = $entry;
            $ocupacionEquipos[(int) $p->equipo_visitante_id][] = $entry;
        }

        return [
            'canchas_por_id' => $canchasPorId,
            'canchas_operativas' => $canchasOperativas,
            'bloqueos_por_cancha' => $bloqueosPorCancha,
            'sanciones_equipo' => $sancionesEquipo,
            'ocupacion_canchas_externas' => $ocupacionExternas,
            'ocupacion_todas_canchas' => $ocupacionTodas,
            'ocupacion_total_cancha_dia' => $conteoTotalCanchaDia,
            'ocupacion_equipos' => $ocupacionEquipos,
        ];
    }

    /**
     * @param  Collection<int, Collection<int, BloqueoCancha>>  $bloqueosPorCancha
     */
    private function buscarBloqueoSolapado(
        int $canchaId,
        CarbonInterface $inicio,
        CarbonInterface $fin,
        Collection $bloqueosPorCancha
    ): ?BloqueoCancha {
        /** @var Collection<int, BloqueoCancha>|null $bloqueos */
        $bloqueos = $bloqueosPorCancha->get($canchaId);
        if ($bloqueos === null) {
            return null;
        }

        foreach ($bloqueos as $bloqueo) {
            if ($bloqueo->fecha_inicio === null || $bloqueo->fecha_fin === null) {
                continue;
            }

            if ($bloqueo->fecha_inicio->lessThan($fin) && $bloqueo->fecha_fin->greaterThan($inicio)) {
                return $bloqueo;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{inicio: Carbon, fin: Carbon, partido_id: int, detalle?: string}>  $slots
     * @return array{inicio: Carbon, fin: Carbon, partido_id: int, detalle?: string}|null
     */
    private function buscarSolapamientoEnSlots(Carbon $inicio, Carbon $fin, array $slots, int $ignorarPartidoId): ?array
    {
        foreach ($slots as $slot) {
            if ($slot['partido_id'] === $ignorarPartidoId) {
                continue;
            }

            if ($inicio->lessThan($slot['fin']) && $fin->greaterThan($slot['inicio'])) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $horarios
     * @return array<int, string>
     */
    private function resolverHorarios(array $horarios): array
    {
        $lista = $horarios !== [] ? $horarios : (array) config('programacion.horarios_defecto', ['09:00', '11:00', '13:00', '15:00']);
        $normalizados = [];
        foreach ($lista as $h) {
            $limpio = substr(trim((string) $h), 0, 5);
            if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $limpio) === 1 && ! in_array($limpio, $normalizados, true)) {
                $normalizados[] = $limpio;
            }
        }

        return $normalizados;
    }

    /**
     * @return array<int, string>
     */
    private function expandirFechas(CarbonInterface|string $inicio, CarbonInterface|string $fin): array
    {
        $cursor = Carbon::parse($this->normalizarFecha($inicio));
        $limite = Carbon::parse($this->normalizarFecha($fin));
        $fechas = [];

        while ($cursor->lessThanOrEqualTo($limite)) {
            $fechas[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $fechas;
    }

    private function normalizarFecha(CarbonInterface|string $fecha): string
    {
        return $fecha instanceof CarbonInterface ? $fecha->toDateString() : Carbon::parse($fecha)->toDateString();
    }

    private function formatearEnfrentamiento(Partido $partido): string
    {
        return $this->nombreEquipo($partido->local, (int) $partido->equipo_local_id, 'Local')
            .' vs '
            .$this->nombreEquipo($partido->visitante, (int) $partido->equipo_visitante_id, 'Visitante');
    }

    private function nombreEquipo(?Equipo $equipo, int $equipoId, ?string $respaldo = null): string
    {
        if ($equipo instanceof Equipo) {
            return $equipo->nombre;
        }

        return $respaldo ?? "Equipo #{$equipoId}";
    }

    /**
     * Construye el objeto estándar de conflicto según RNF-19.
     *
     * @return array<string, mixed>
     */
    private function construirConflicto(
        string $codigoRegla,
        string $reglaOrigen,
        string $severidad,
        ?int $jornadaNumero,
        ?int $partidoId,
        string $enfrentamiento,
        ?string $fechaReferencia,
        string $causa,
        string $sugerencia
    ): array {
        return [
            'codigo_regla' => $codigoRegla,
            'regla_origen' => $reglaOrigen,
            'severidad' => $severidad,
            'jornada_numero' => $jornadaNumero,
            'partido_id' => $partidoId,
            'enfrentamiento' => $enfrentamiento,
            'fecha_referencia' => $fechaReferencia,
            'causa' => $causa,
            'sugerencia' => $sugerencia,
            'mensaje' => "[{$reglaOrigen}] {$enfrentamiento}: {$causa}",
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $conflictos
     * @return array<int, array<string, mixed>>
     */
    private function deduplicarConflictos(array $conflictos): array
    {
        $vistos = [];
        $unicos = [];

        foreach ($conflictos as $conflicto) {
            $clave = ($conflicto['codigo_regla'] ?? '').'|'.($conflicto['partido_id'] ?? '').'|'.($conflicto['mensaje'] ?? '');
            if (! isset($vistos[$clave])) {
                $vistos[$clave] = true;
                $unicos[] = $conflicto;
            }
        }

        return $unicos;
    }
}
