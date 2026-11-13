<?php

namespace App\Services;

use App\Models\Cancha;
use App\Models\Jornada;
use App\Models\Partido;
use App\Models\ReprogramacionPartido;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Orquestador del flujo de programación semanal y validación de conflictos (HU-030 / RF-06.5 / SCRUM-199, SCRUM-200)
class PublicacionProgramacionService
{
    public function __construct(
        private readonly AsignacionCanchaService $canchaService
    ) {
    }

    /**
     * Inspecciona una jornada e identifica cualquier conflicto de programación:
     * - Canchas bloqueadas por mantenimiento o clima (HU-025)
     * - Canchas ocupadas por empalmes horarios (RF-05.3)
     * - Equipos duplicados jugando más de un partido en la misma jornada
     *
     * Regla (Obligatorio 11): Los partidos cancelados no generan conflictos en canchas ni en duplicidad de equipos.
     *
     * @return array<string> Lista de conflictos encontrados, vacía si no hay colisiones.
     */
    public function detectarConflictos(Jornada $jornada): array
    {
        $conflictos = [];
        $todosPartidos = $jornada->partidos()->with(['local', 'visitante', 'cancha'])->get();

        if ($todosPartidos->isEmpty()) {
            $conflictos[] = 'La jornada no cuenta con partidos programados para publicar.';

            return $conflictos;
        }

        // Obligatorio 11: Ignorar partidos cancelados ya que no ocupan cancha ni disputan la jornada
        $partidosActivos = $todosPartidos->reject(fn (Partido $p) => $p->estado === Partido::ESTADO_CANCELADO);

        if ($partidosActivos->isEmpty()) {
            $conflictos[] = 'La jornada no cuenta con partidos activos programados para publicar.';

            return $conflictos;
        }

        // 1. Validar equipos repetidos en la jornada (Criterio 2)
        $equiposContados = [];
        foreach ($partidosActivos as $partido) {
            $localId = $partido->equipo_local_id;
            $visitanteId = $partido->equipo_visitante_id;

            $localNombre = $partido->local->nombre ?? "Equipo #{$localId}";
            $visitanteNombre = $partido->visitante->nombre ?? "Equipo #{$visitanteId}";

            if (isset($equiposContados[$localId])) {
                $conflictos[] = "El equipo '{$localNombre}' está programado más de una vez en esta jornada.";
            } else {
                $equiposContados[$localId] = true;
            }

            if (isset($equiposContados[$visitanteId])) {
                $conflictos[] = "El equipo '{$visitanteNombre}' está programado más de una vez en esta jornada.";
            } else {
                $equiposContados[$visitanteId] = true;
            }

            // Validar si el equipo en descanso está programado
            if ($jornada->equipo_descanso_id !== null) {
                if ($localId === $jornada->equipo_descanso_id || $visitanteId === $jornada->equipo_descanso_id) {
                    $equipoDescanso = $jornada->equipoDescanso->nombre ?? 'en descanso';
                    $conflictos[] = "El equipo '{$equipoDescanso}' tiene descanso asignado en la jornada y no puede jugar.";
                }
            }
        }

        // 2. Validar canchas ocupadas o bloqueadas (Criterio 2) con duración oficial (Obligatorio 5)
        $duracionMinutos = (int) config('programacion.duracion_partido_minutos', 120);
        $slotsCancha = [];

        foreach ($partidosActivos as $partido) {
            if ($partido->cancha_id === null || $partido->fecha_partido === null || $partido->hora_partido === null) {
                $rivales = ($partido->local->nombre ?? 'Local').' vs '.($partido->visitante->nombre ?? 'Visitante');
                $conflictos[] = "El partido '{$rivales}' no tiene cancha u horario asignado completo.";

                continue;
            }

            $cancha = $partido->cancha;
            if (! $cancha instanceof Cancha) {
                continue;
            }

            $inicio = Carbon::parse($partido->fecha_partido)->setTimeFromTimeString((string) $partido->hora_partido);
            $fin = $inicio->copy()->addMinutes($duracionMinutos);

            // Verificar si hay traslape interno entre partidos de la misma jornada en la misma cancha
            $claveSlot = $cancha->id.'_'.$partido->fecha_partido;
            if (! isset($slotsCancha[$claveSlot])) {
                $slotsCancha[$claveSlot] = [];
            }

            foreach ($slotsCancha[$claveSlot] as $slotOcupado) {
                /** @var Carbon $otroInicio */
                $otroInicio = $slotOcupado['inicio'];
                /** @var Carbon $otroFin */
                $otroFin = $slotOcupado['fin'];

                if ($inicio->lessThan($otroFin) && $fin->greaterThan($otroInicio)) {
                    $conflictos[] = "Conflicto interno en cancha '{$cancha->nombre}': empalme entre las {$inicio->format('H:i')} y {$otroInicio->format('H:i')}.";
                }
            }

            $slotsCancha[$claveSlot][] = [
                'inicio' => $inicio,
                'fin' => $fin,
                'partido_id' => $partido->id,
            ];

            // Verificar bloqueos o partidos de otras jornadas con AsignacionCanchaService
            $motivoRechazo = $this->canchaService->motivoDeRechazo($cancha, $inicio, $fin, $partido);
            if ($motivoRechazo !== null) {
                $conflictos[] = "Cancha '{$cancha->nombre}': {$motivoRechazo}";
            }
        }

        return array_values(array_unique($conflictos));
    }

    /**
     * Pasa la jornada al estado 'en_revision' para revisión previa a su aprobación.
     *
     * @throws ValidationException
     */
    public function enviarARevision(Jornada $jornada, ?User $usuario = null): Jornada
    {
        if ($jornada->estado !== Jornada::ESTADO_BORRADOR) {
            throw ValidationException::withMessages([
                'jornada' => "Solo una jornada en estado 'borrador' puede enviarse a revisión. Estado actual: '{$jornada->estado}'.",
            ]);
        }

        if ($jornada->partidos()->count() === 0) {
            throw ValidationException::withMessages([
                'jornada' => 'No se puede enviar a revisión una jornada sin partidos programados.',
            ]);
        }

        return DB::transaction(function () use ($jornada): Jornada {
            $jornadaBloqueada = Jornada::query()->whereKey($jornada->id)->lockForUpdate()->firstOrFail();

            if ($jornadaBloqueada->estado !== Jornada::ESTADO_BORRADOR) {
                throw ValidationException::withMessages([
                    'jornada' => "Solo una jornada en estado 'borrador' puede enviarse a revisión. Estado actual: '{$jornadaBloqueada->estado}'.",
                ]);
            }

            $jornadaBloqueada->estado = Jornada::ESTADO_EN_REVISION;
            $jornadaBloqueada->save();

            return $jornadaBloqueada;
        });
    }

    /**
     * Aprueba la programación semanal (Comité Ejecutivo según Anexo B).
     *
     * @throws ValidationException
     */
    public function aprobar(Jornada $jornada, ?User $usuario = null): Jornada
    {
        if ($jornada->estado !== Jornada::ESTADO_EN_REVISION) {
            throw ValidationException::withMessages([
                'jornada' => "Solo una jornada en estado 'en_revision' puede ser aprobada por el Comité Ejecutivo. Estado actual: '{$jornada->estado}'.",
            ]);
        }

        $usuarioActuante = $usuario ?? auth()->user();

        return DB::transaction(function () use ($jornada, $usuarioActuante): Jornada {
            $jornadaBloqueada = Jornada::query()->whereKey($jornada->id)->lockForUpdate()->firstOrFail();

            if ($jornadaBloqueada->estado !== Jornada::ESTADO_EN_REVISION) {
                throw ValidationException::withMessages([
                    'jornada' => "Solo una jornada en estado 'en_revision' puede ser aprobada por el Comité Ejecutivo. Estado actual: '{$jornadaBloqueada->estado}'.",
                ]);
            }

            $jornadaBloqueada->estado = Jornada::ESTADO_APROBADA;
            $jornadaBloqueada->aprobado_por_id = $usuarioActuante?->id;
            $jornadaBloqueada->fecha_aprobacion = now();
            $jornadaBloqueada->save();

            return $jornadaBloqueada;
        });
    }

    /**
     * Publica la programación semanal. Bloquea si existen conflictos (HU-030 Criterio 2).
     *
     * @throws ValidationException
     */
    public function publicar(Jornada $jornada, ?User $usuario = null): Jornada
    {
        if ($jornada->estado !== Jornada::ESTADO_APROBADA) {
            throw ValidationException::withMessages([
                'jornada' => "Solo una jornada previamente aprobada por el Comité Ejecutivo puede ser publicada. Estado actual: '{$jornada->estado}'.",
            ]);
        }

        // Validar que no existan conflictos antes de permitir la publicación
        $conflictos = $this->detectarConflictos($jornada);
        if (! empty($conflictos)) {
            throw ValidationException::withMessages([
                'publicacion' => 'La programación contiene conflictos no resueltos y no puede publicarse: '.implode('; ', $conflictos),
                'conflictos' => $conflictos,
            ]);
        }

        $usuarioActuante = $usuario ?? auth()->user();

        return DB::transaction(function () use ($jornada, $usuarioActuante): Jornada {
            $jornadaBloqueada = Jornada::query()->whereKey($jornada->id)->lockForUpdate()->firstOrFail();

            if ($jornadaBloqueada->estado !== Jornada::ESTADO_APROBADA) {
                throw ValidationException::withMessages([
                    'jornada' => "Solo una jornada previamente aprobada por el Comité Ejecutivo puede ser publicada. Estado actual: '{$jornadaBloqueada->estado}'.",
                ]);
            }

            $jornadaBloqueada->estado = Jornada::ESTADO_PUBLICADA;
            $jornadaBloqueada->fecha_publicacion = now();
            $jornadaBloqueada->save();

            // Obligatorio 3: Actualizar individualmente cada partido en borrador y registrar en bitácora auditable
            $partidosBorrador = $jornadaBloqueada->partidos()
                ->where('estado', Partido::ESTADO_BORRADOR)
                ->get();

            foreach ($partidosBorrador as $partidoBorrador) {
                $partidoBorrador->estado = Partido::ESTADO_PROGRAMADO;
                $partidoBorrador->save();

                if ($usuarioActuante instanceof User) {
                    ReprogramacionPartido::create([
                        'partido_id' => $partidoBorrador->id,
                        'usuario_id' => $usuarioActuante->id,
                        'estado_anterior' => Partido::ESTADO_BORRADOR,
                        'estado_nuevo' => Partido::ESTADO_PROGRAMADO,
                        'cancha_anterior_id' => $partidoBorrador->cancha_id,
                        'cancha_nueva_id' => $partidoBorrador->cancha_id,
                        'fecha_anterior' => $partidoBorrador->fecha_partido,
                        'fecha_nueva' => $partidoBorrador->fecha_partido,
                        'hora_anterior' => $partidoBorrador->hora_partido,
                        'hora_nueva' => $partidoBorrador->hora_partido,
                        'motivo' => 'Publicación oficial de la jornada semanal (HU-030).',
                    ]);
                }
            }

            return $jornadaBloqueada;
        });
    }
}
