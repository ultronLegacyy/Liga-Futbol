<?php

namespace App\Services;

use App\Models\Cancha;
use App\Models\Jornada;
use App\Models\Partido;
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
     * @return array<string> Lista de conflictos encontrados, vacía si no hay colisiones.
     */
    public function detectarConflictos(Jornada $jornada): array
    {
        $conflictos = [];
        $partidos = $jornada->partidos()->with(['local', 'visitante', 'cancha'])->get();

        if ($partidos->isEmpty()) {
            $conflictos[] = 'La jornada no cuenta con partidos programados para publicar.';

            return $conflictos;
        }

        // 1. Validar equipos repetidos en la jornada (Criterio 2)
        $equiposContados = [];
        foreach ($partidos as $partido) {
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

        // 2. Validar canchas ocupadas o bloqueadas (Criterio 2)
        $slotsCancha = [];
        foreach ($partidos as $partido) {
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
            $fin = $inicio->copy()->addMinutes(90);

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
        if ($jornada->partidos()->count() === 0) {
            throw ValidationException::withMessages([
                'jornada' => 'No se puede enviar a revisión una jornada sin partidos programados.',
            ]);
        }

        return DB::transaction(function () use ($jornada): Jornada {
            $jornadaBloqueada = Jornada::query()->whereKey($jornada->id)->lockForUpdate()->firstOrFail();
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
        $usuarioActuante = $usuario ?? auth()->user();

        return DB::transaction(function () use ($jornada, $usuarioActuante): Jornada {
            $jornadaBloqueada = Jornada::query()->whereKey($jornada->id)->lockForUpdate()->firstOrFail();

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
        // Validar que no existan conflictos antes de permitir la publicación
        $conflictos = $this->detectarConflictos($jornada);
        if (! empty($conflictos)) {
            throw ValidationException::withMessages([
                'publicacion' => 'La programación contiene conflictos no resueltos y no puede publicarse: '.implode('; ', $conflictos),
                'conflictos' => $conflictos,
            ]);
        }

        return DB::transaction(function () use ($jornada): Jornada {
            $jornadaBloqueada = Jornada::query()->whereKey($jornada->id)->lockForUpdate()->firstOrFail();

            $jornadaBloqueada->estado = Jornada::ESTADO_PUBLICADA;
            $jornadaBloqueada->fecha_publicacion = now();
            $jornadaBloqueada->save();

            // Al publicar la jornada, los partidos en borrador o programados se confirman como oficiales
            $jornadaBloqueada->partidos()
                ->where('estado', Partido::ESTADO_BORRADOR)
                ->update(['estado' => Partido::ESTADO_PROGRAMADO]);

            return $jornadaBloqueada;
        });
    }
}
