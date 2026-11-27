<?php

namespace App\Services;

use App\Models\BloqueoCancha;
use App\Models\Cancha;
use App\Models\Equipo;
use App\Models\Jornada;
use App\Models\Partido;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Detector y explicador de conflictos de programación automática y semanal (HU-033, RF-07.3, RNF-19).
 *
 * Opera reutilizando las reglas oficiales de disponibilidad de cancha (HU-025) y ventana con cruce
 * de medianoche sin consultas N+1 (RNF-07 / RNF-08).
 */
class DetectorConflictosProgramacionService
{
    public function __construct(
        private readonly AsignacionCanchaService $canchaService,
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
        $todosPartidos = $jornada->partidos()->with(['local', 'visitante', 'cancha', 'arbitro'])->get();

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

        $duracionMinutos = $this->canchaService->duracionEnMinutos();
        $horariosCandidatos = $this->resolverHorarios($horarios);
        $contexto = $datosPrecargados ?? $this->precargarContextoParaJornada($jornada, $partidosActivos);

        $conflictos = [];
        $conflictos = array_merge($conflictos, $this->detectarDuplicidadYDescanso($jornada, $partidosActivos));

        /** @var array<int, array<string, array<int, array{inicio: Carbon, fin: Carbon, partido_id: int}>>> $slotsInternosCancha */
        $slotsInternosCancha = [];
        /** @var array<string, int> $conteoInternoCanchaDia */
        $conteoInternoCanchaDia = [];
        /** @var array<int, array<string, array<int, array{inicio: Carbon, fin: Carbon, partido_id: int}>>> $slotsInternosEquipo */
        $slotsInternosEquipo = [];
        /** @var array<int, array<string, array<int, array{inicio: Carbon, fin: Carbon, partido_id: int}>>> $slotsInternosArbitro */
        $slotsInternosArbitro = [];

        foreach ($partidosActivos as $partido) {
            $enfrentamiento = $this->formatearEnfrentamiento($partido);

            if ($partido->cancha_id === null || $partido->fecha_partido === null || $partido->hora_partido === null) {
                $conflictos[] = $this->diagnosticarPartidoSinHorario(
                    $jornada,
                    $partido,
                    $horariosCandidatos,
                    $duracionMinutos,
                    $contexto
                );

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
            $inicioTs = $inicio->getTimestamp();
            $finTs = $fin->getTimestamp();

            // Verificar traslape interno entre partidos de la misma jornada en la misma cancha (cruce de medianoche incluido)
            $slotsInternosCancha[$cancha->id] ??= [];
            $solapeInterno = $this->buscarSolapamientoPorFechas(
                $inicio,
                $fin,
                $slotsInternosCancha[$cancha->id],
                $partido->id
            );
            if ($solapeInterno !== null) {
                $conflictos[] = $this->construirConflicto(
                    codigoRegla: 'CANCHA_OCUPADA',
                    reglaOrigen: 'RF-05.3 / RF-07.2: Exclusividad horaria de cancha (no empalmes)',
                    severidad: 'bloqueante',
                    jornadaNumero: $jornada->numero_jornada,
                    partidoId: $partido->id,
                    enfrentamiento: $enfrentamiento,
                    fechaReferencia: $fechaStr,
                    causa: "Conflicto interno en cancha '{$cancha->nombre}': empalme entre las {$inicio->format('H:i')} y {$solapeInterno['inicio']->format('H:i')}.",
                    sugerencia: "Mueva el encuentro a otra franja horaria libre (separación mínima de {$duracionMinutos} minutos) o a otra cancha.",
                );
            }

            AsignacionCanchaService::registrarIntervaloConCruceMedianoche(
                $slotsInternosCancha[$cancha->id],
                $inicioTs,
                $finTs,
                ['inicio' => $inicio, 'fin' => $fin, 'partido_id' => $partido->id]
            );

            // Reutilizar la regla única oficial de HU-025 (estado, bloqueos y ocupación externa incluyendo día anterior)
            $motivoRechazoCancha = $this->canchaService->motivoDeRechazo($cancha, $inicio, $fin, $partido);
            if ($motivoRechazoCancha !== null) {
                $conflictos[] = $this->construirConflictoDesdeMotivoHu025(
                    cancha: $cancha,
                    motivoRechazo: $motivoRechazoCancha,
                    jornadaNumero: $jornada->numero_jornada,
                    partidoId: $partido->id,
                    enfrentamiento: $enfrentamiento,
                    fechaReferencia: $fechaStr
                );
            }

            // Verificar capacidad de partidos consecutivos por día de forma uniforme con el motor (HU-023 / RF-07.2)
            $claveCanchaDia = $cancha->id.'_'.$fechaStr;
            $conteoInternoCanchaDia[$claveCanchaDia] = ($conteoInternoCanchaDia[$claveCanchaDia] ?? 0) + 1;
            $externosEnDia = $contexto['conteo_externo_cancha_dia'][$claveCanchaDia] ?? 0;
            $totalDia = $conteoInternoCanchaDia[$claveCanchaDia] + $externosEnDia;
            if ($cancha->capacidad_partidos_consecutivos > 0 && $totalDia > $cancha->capacidad_partidos_consecutivos) {
                $conflictos[] = $this->construirConflicto(
                    codigoRegla: 'CANCHA_SIN_CAPACIDAD',
                    reglaOrigen: 'RF-07.2: Límite de capacidad por cancha y día',
                    severidad: 'bloqueante',
                    jornadaNumero: $jornada->numero_jornada,
                    partidoId: $partido->id,
                    enfrentamiento: $enfrentamiento,
                    fechaReferencia: $fechaStr,
                    causa: "La cancha '{$cancha->nombre}' tiene asignados {$totalDia} partidos el {$fechaStr}, superando su capacidad configurada de {$cancha->capacidad_partidos_consecutivos}.",
                    sugerencia: "Redistribuya partidos a otra fecha dentro de la jornada o amplíe la capacidad de '{$cancha->nombre}'.",
                );
            }

            // Verificar traslape horario por equipo (cruce de medianoche incluido)
            foreach ([(int) $partido->equipo_local_id, (int) $partido->equipo_visitante_id] as $equipoId) {
                $equipoNombre = $equipoId === (int) $partido->equipo_local_id
                    ? $this->nombreEquipo($partido->local, (int) $partido->equipo_local_id)
                    : $this->nombreEquipo($partido->visitante, (int) $partido->equipo_visitante_id);

                $slotsInternosEquipo[$equipoId] ??= [];
                $solapeInternoEquipo = $this->buscarSolapamientoPorFechas($inicio, $fin, $slotsInternosEquipo[$equipoId], $partido->id);
                $solapeExternoEquipo = $this->buscarSolapamientoPorFechas(
                    $inicio,
                    $fin,
                    $contexto['ocupacion_equipos'][$equipoId] ?? [],
                    $partido->id
                );
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

                AsignacionCanchaService::registrarIntervaloConCruceMedianoche(
                    $slotsInternosEquipo[$equipoId],
                    $inicioTs,
                    $finTs,
                    ['inicio' => $inicio, 'fin' => $fin, 'partido_id' => $partido->id]
                );
            }

            // Verificar empalme del árbitro asignado al partido (HU-026 / RF-06.1)
            if ($partido->arbitro_id !== null) {
                $arbitroId = (int) $partido->arbitro_id;
                $slotsInternosArbitro[$arbitroId] ??= [];
                $solapeArbitroInterno = $this->buscarSolapamientoPorFechas($inicio, $fin, $slotsInternosArbitro[$arbitroId], $partido->id);
                $solapeArbitroExterno = $this->buscarSolapamientoPorFechas(
                    $inicio,
                    $fin,
                    $contexto['ocupacion_arbitros'][$arbitroId] ?? [],
                    $partido->id
                );
                $solapeArbitro = $solapeArbitroInterno ?? $solapeArbitroExterno;

                if ($solapeArbitro !== null) {
                    $conflictos[] = $this->construirConflicto(
                        codigoRegla: 'ARBITRO_OCUPADO',
                        reglaOrigen: 'RF-06.1 / HU-026: Exclusividad horaria del árbitro asignado',
                        severidad: 'bloqueante',
                        jornadaNumero: $jornada->numero_jornada,
                        partidoId: $partido->id,
                        enfrentamiento: $enfrentamiento,
                        fechaReferencia: $fechaStr,
                        causa: "El árbitro asignado (#{$arbitroId}) ya tiene otro encuentro el {$fechaStr} de {$solapeArbitro['inicio']->format('H:i')} a {$solapeArbitro['fin']->format('H:i')}.",
                        sugerencia: 'Reasigne el árbitro o programe el encuentro en otra fecha u hora donde el árbitro esté disponible.',
                    );
                }

                AsignacionCanchaService::registrarIntervaloConCruceMedianoche(
                    $slotsInternosArbitro[$arbitroId],
                    $inicioTs,
                    $finTs,
                    ['inicio' => $inicio, 'fin' => $fin, 'partido_id' => $partido->id]
                );
            }
        }

        return $this->deduplicarConflictos($conflictos);
    }

    /**
     * Extrae la lista deduplicada de mensajes bloqueantes de un análisis detallado.
     * Centraliza el filtro único compartido por PublicacionProgramacionService y JornadaController.
     *
     * @param  array<int, array<string, mixed>>  $conflictosDetallados
     * @return array<int, string>
     */
    public function filtrarMensajesBloqueantes(array $conflictosDetallados): array
    {
        $mensajes = array_map(
            static fn (array $conflicto): string => (string) $conflicto['mensaje'],
            array_filter(
                $conflictosDetallados,
                static fn (array $conflicto): bool => ($conflicto['severidad'] ?? 'bloqueante') === 'bloqueante'
            )
        );

        return array_values(array_unique($mensajes));
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
     *     arbitro_id?: int|null,
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
     *     por_empalme_arbitro: int
     * }  $rechazos
     * @return array<string, mixed>
     */
    public function explicarImposibilidadPartido(array $datosPartido, array $rechazos): array
    {
        $enfrentamiento = "{$datosPartido['local_nombre']} vs {$datosPartido['visitante_nombre']}";
        $jornadaNumero = $datosPartido['jornada_numero'];
        $partidoId = $datosPartido['partido_id'];
        $fechaRef = $datosPartido['fecha_referencia'];

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
            'ARBITRO_OCUPADO' => $rechazos['por_empalme_arbitro'],
            'CANCHA_BLOQUEADA' => count($rechazos['por_bloqueo']),
            'CANCHA_SIN_CAPACIDAD' => $rechazos['por_capacidad'],
            'CANCHA_OCUPADA' => $rechazos['por_ocupacion_cancha'],
            'EQUIPO_EMPALME_HORARIO' => $rechazos['por_empalme_equipo'],
        ];
        arsort($conteos);
        $principal = (string) array_key_first($conteos);

        if ($principal === 'ARBITRO_OCUPADO' && $conteos['ARBITRO_OCUPADO'] > 0) {
            $arbitroId = $datosPartido['arbitro_id'] ?? null;
            $etiquetaArbitro = $arbitroId !== null ? " (#{$arbitroId})" : '';

            return $this->construirConflicto(
                codigoRegla: 'ARBITRO_OCUPADO',
                reglaOrigen: 'RF-06.1 / HU-026: Un árbitro no puede dirigir dos partidos en la misma fecha y horario',
                severidad: 'bloqueante',
                jornadaNumero: $jornadaNumero,
                partidoId: $partidoId,
                enfrentamiento: $enfrentamiento,
                fechaReferencia: $fechaRef,
                causa: "El árbitro asignado al partido{$etiquetaArbitro} ya tiene otro compromiso programado en las franjas evaluadas ({$rechazos['por_empalme_arbitro']} combinaciones descartadas por disponibilidad arbitral).",
                sugerencia: 'Cambie el árbitro del partido antes de reasignar o agregue franjas horarias donde el árbitro esté libre.',
            );
        }

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
                reglaOrigen: 'RF-07.2: Límite de capacidad por cancha y día',
                severidad: 'bloqueante',
                jornadaNumero: $jornadaNumero,
                partidoId: $partidoId,
                enfrentamiento: $enfrentamiento,
                fechaReferencia: $fechaRef,
                causa: "La demanda de partidos excede el tope configurado en las canchas operativas ({$rechazos['por_capacidad']} combinaciones descartadas por capacidad diaria).",
                sugerencia: 'Amplíe la ventana de fechas de la jornada, ajuste la capacidad configurada o habilite más canchas.',
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
     * Traduce el rechazo oficial de HU-025 (AsignacionCanchaService::motivoDeRechazo)
     * al formato estructurado de conflicto (RNF-19) manteniendo exactamente el mismo comportamiento.
     *
     * @return array<string, mixed>
     */
    private function construirConflictoDesdeMotivoHu025(
        Cancha $cancha,
        string $motivoRechazo,
        int $jornadaNumero,
        int $partidoId,
        string $enfrentamiento,
        string $fechaReferencia
    ): array {
        if (str_contains($motivoRechazo, 'estado')) {
            return $this->construirConflicto(
                codigoRegla: 'CANCHA_NO_DISPONIBLE',
                reglaOrigen: 'RF-05.1 / RF-07.2: Estado operativo de la cancha en catálogo',
                severidad: 'bloqueante',
                jornadaNumero: $jornadaNumero,
                partidoId: $partidoId,
                enfrentamiento: $enfrentamiento,
                fechaReferencia: $fechaReferencia,
                causa: "Cancha '{$cancha->nombre}': {$motivoRechazo}",
                sugerencia: "Reasigne el partido a una cancha en estado 'Disponible' o actualice el estado de '{$cancha->nombre}'.",
            );
        }

        if (str_contains($motivoRechazo, 'bloqueada')) {
            return $this->construirConflicto(
                codigoRegla: 'CANCHA_BLOQUEADA',
                reglaOrigen: 'RF-05.2 / RF-07.2: Bloqueo temporal de cancha por mantenimiento o contingencia',
                severidad: 'bloqueante',
                jornadaNumero: $jornadaNumero,
                partidoId: $partidoId,
                enfrentamiento: $enfrentamiento,
                fechaReferencia: $fechaReferencia,
                causa: "Cancha '{$cancha->nombre}': {$motivoRechazo}",
                sugerencia: "Seleccione otra cancha disponible o reprograme fuera del horario bloqueado en '{$cancha->nombre}'.",
            );
        }

        return $this->construirConflicto(
            codigoRegla: 'CANCHA_OCUPADA',
            reglaOrigen: 'RF-05.3 / RF-07.2: Exclusividad horaria de cancha entre categorías y grupos',
            severidad: 'bloqueante',
            jornadaNumero: $jornadaNumero,
            partidoId: $partidoId,
            enfrentamiento: $enfrentamiento,
            fechaReferencia: $fechaReferencia,
            causa: "Cancha '{$cancha->nombre}': {$motivoRechazo}",
            sugerencia: 'Cambie la sede o el horario del encuentro a una franja sin ocupación previa.',
        );
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
            'por_empalme_arbitro' => 0,
        ];

        $slotsDisponibles = 0;

        foreach ($fechas as $fechaStr) {
            foreach ($horariosCandidatos as $horaStr) {
                $inicio = Carbon::parse($fechaStr.' '.$horaStr);
                $fin = $inicio->copy()->addMinutes($duracionMinutos);

                if ($canchasOperativas->isNotEmpty()) {
                    if ($partido->arbitro_id !== null) {
                        $arbitroId = (int) $partido->arbitro_id;
                        $solapeArbitro = $this->buscarSolapamientoPorFechas(
                            $inicio,
                            $fin,
                            $contexto['ocupacion_arbitros'][$arbitroId] ?? [],
                            $partido->id
                        );
                        $claveExacta = $fechaStr.'|'.$horaStr.':00';
                        $choqueExacto = isset($contexto['arbitro_horarios_exactos'][$arbitroId][$claveExacta])
                            && $contexto['arbitro_horarios_exactos'][$arbitroId][$claveExacta] !== $partido->id;

                        if ($solapeArbitro !== null || $choqueExacto) {
                            $rechazos['por_empalme_arbitro'] += $canchasOperativas->count();
                            $rechazos['slots_evaluados'] += $canchasOperativas->count();

                            continue;
                        }
                    }

                    $solapeLocal = $this->buscarSolapamientoPorFechas(
                        $inicio,
                        $fin,
                        $contexto['ocupacion_equipos'][$partido->equipo_local_id] ?? [],
                        $partido->id
                    );
                    $solapeVisitante = $this->buscarSolapamientoPorFechas(
                        $inicio,
                        $fin,
                        $contexto['ocupacion_equipos'][$partido->equipo_visitante_id] ?? [],
                        $partido->id
                    );
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

                    $bucketsCancha = $contexto['ocupacion_todas_canchas'][$cancha->id] ?? [];
                    if ($this->buscarSolapamientoPorFechas($inicio, $fin, $bucketsCancha, $partido->id) !== null) {
                        $rechazos['por_ocupacion_cancha']++;

                        continue;
                    }

                    $slotsDisponibles++;
                }
            }
        }

        if ($canchasOperativas->isNotEmpty() && $slotsDisponibles > 0) {
            return $this->construirConflicto(
                codigoRegla: 'SIN_ASIGNACION_HORARIA',
                reglaOrigen: 'RF-06.5 / RF-07.2: Programación completa de cancha, fecha y hora requerida',
                severidad: 'bloqueante',
                jornadaNumero: $jornada->numero_jornada,
                partidoId: $partido->id,
                enfrentamiento: $this->formatearEnfrentamiento($partido),
                fechaReferencia: $this->normalizarFecha($jornada->fecha_inicio),
                causa: "El partido '{$this->formatearEnfrentamiento($partido)}' aún no cuenta con cancha u horario asignado completo (consultando el catálogo general de franjas existen espacios libres o ejecute nuevamente la asignación según las sedes y horarios elegidos).",
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
            'arbitro_id' => $partido->arbitro_id,
            'fecha_referencia' => $this->normalizarFecha($jornada->fecha_inicio),
        ], $rechazos);
    }

    /**
     * Precarga en consultas acotadas el estado necesario para analizar una jornada sin N+1 (RNF-08),
     * ampliando un día hacia atrás para capturar partidos que cruzan la medianoche (HU-025).
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

        $duracionMinutos = $this->canchaService->duracionEnMinutos();
        $fechaAnteriorMin = Carbon::parse($fechaMin)->subMinutes($duracionMinutos)->toDateString();

        $inicioVentana = Carbon::parse($fechaAnteriorMin.' 00:00:00');
        $finVentana = Carbon::parse($fechaMax.' 23:59:59')->addMinutes($duracionMinutos);

        $todasCanchas = Cancha::query()->orderBy('id')->get();
        $canchasPorId = $todasCanchas->keyBy('id');
        $canchasOperativas = $todasCanchas->filter(fn (Cancha $c): bool => $c->estado === 'Disponible')->values();

        $bloqueosPorCancha = BloqueoCancha::query()
            ->solapadosCon($inicioVentana, $finVentana)
            ->get()
            ->groupBy('cancha_id');

        $partidosVentana = Partido::query()
            ->with(['local', 'visitante'])
            ->queOcupanCancha()
            ->whereBetween('fecha_partido', [$fechaAnteriorMin, $fechaMax])
            ->get();

        $ocupacionTodas = [];
        $conteoTotalCanchaDia = [];
        $conteoExternoCanchaDia = [];
        $ocupacionEquipos = [];
        $ocupacionArbitros = [];

        foreach ($partidosVentana as $p) {
            $fechaStr = $this->normalizarFecha($p->fecha_partido);
            $horaStr = substr((string) $p->hora_partido, 0, 8);
            $inicio = Carbon::parse($fechaStr.' '.$horaStr);
            $fin = $inicio->copy()->addMinutes($duracionMinutos);
            $entry = ['inicio' => $inicio, 'fin' => $fin, 'partido_id' => $p->id];

            if ($p->cancha_id !== null) {
                $canchaId = (int) $p->cancha_id;
                $ocupacionTodas[$canchaId] ??= [];
                AsignacionCanchaService::registrarIntervaloConCruceMedianoche(
                    $ocupacionTodas[$canchaId],
                    $inicio->getTimestamp(),
                    $fin->getTimestamp(),
                    $entry
                );

                $clave = $canchaId.'_'.$fechaStr;
                $conteoTotalCanchaDia[$clave] = ($conteoTotalCanchaDia[$clave] ?? 0) + 1;
                if ($p->jornada_id !== $jornada->id) {
                    $conteoExternoCanchaDia[$clave] = ($conteoExternoCanchaDia[$clave] ?? 0) + 1;
                }
            }

            $localId = (int) $p->equipo_local_id;
            $visitanteId = (int) $p->equipo_visitante_id;
            $ocupacionEquipos[$localId] ??= [];
            $ocupacionEquipos[$visitanteId] ??= [];
            AsignacionCanchaService::registrarIntervaloConCruceMedianoche(
                $ocupacionEquipos[$localId],
                $inicio->getTimestamp(),
                $fin->getTimestamp(),
                $entry
            );
            AsignacionCanchaService::registrarIntervaloConCruceMedianoche(
                $ocupacionEquipos[$visitanteId],
                $inicio->getTimestamp(),
                $fin->getTimestamp(),
                $entry
            );

            if ($p->arbitro_id !== null) {
                $arbitroId = (int) $p->arbitro_id;
                $ocupacionArbitros[$arbitroId] ??= [];
                AsignacionCanchaService::registrarIntervaloConCruceMedianoche(
                    $ocupacionArbitros[$arbitroId],
                    $inicio->getTimestamp(),
                    $fin->getTimestamp(),
                    $entry
                );
            }
        }

        // Cargar también coincidencias exactas de partidos_arbitro_horario_unique (sin filtrar estado)
        $arbitrosIds = $partidosActivos->pluck('arbitro_id')->filter()->unique()->values()->all();
        $arbitroHorariosExactos = [];
        if ($arbitrosIds !== []) {
            $filasArbitros = Partido::query()
                ->whereIn('arbitro_id', $arbitrosIds)
                ->whereNotNull('fecha_partido')
                ->whereNotNull('hora_partido')
                ->whereBetween('fecha_partido', [$fechaAnteriorMin, $fechaMax])
                ->get(['id', 'arbitro_id', 'fecha_partido', 'hora_partido']);

            foreach ($filasArbitros as $fila) {
                $claveExacta = $this->normalizarFecha($fila->fecha_partido).'|'.substr((string) $fila->hora_partido, 0, 8);
                $arbitroHorariosExactos[(int) $fila->arbitro_id][$claveExacta] = $fila->id;
            }
        }

        return [
            'canchas_por_id' => $canchasPorId,
            'canchas_operativas' => $canchasOperativas,
            'bloqueos_por_cancha' => $bloqueosPorCancha,
            'ocupacion_todas_canchas' => $ocupacionTodas,
            'ocupacion_total_cancha_dia' => $conteoTotalCanchaDia,
            'conteo_externo_cancha_dia' => $conteoExternoCanchaDia,
            'ocupacion_equipos' => $ocupacionEquipos,
            'ocupacion_arbitros' => $ocupacionArbitros,
            'arbitro_horarios_exactos' => $arbitroHorariosExactos,
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
     * Busca solapamiento revisando todas las fechas calendario que abarca [inicio, fin).
     *
     * @param  array<string, array<int, array{inicio: Carbon, fin: Carbon, partido_id: int}>>  $bucketsPorFecha
     * @return array{inicio: Carbon, fin: Carbon, partido_id: int}|null
     */
    private function buscarSolapamientoPorFechas(
        Carbon $inicio,
        Carbon $fin,
        array $bucketsPorFecha,
        int $ignorarPartidoId
    ): ?array {
        $fechaInicio = $inicio->toDateString();
        $fechaFin = $fin->copy()->subSecond()->toDateString();
        $fechas = $fechaFin !== $fechaInicio ? [$fechaInicio, $fechaFin] : [$fechaInicio];

        foreach ($fechas as $fecha) {
            foreach ($bucketsPorFecha[$fecha] ?? [] as $slot) {
                if ($slot['partido_id'] === $ignorarPartidoId) {
                    continue;
                }

                if ($inicio->lessThan($slot['fin']) && $fin->greaterThan($slot['inicio'])) {
                    return $slot;
                }
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
