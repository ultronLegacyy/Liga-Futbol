<?php

namespace App\Services;

use App\Models\Arbitro;
use App\Models\Cancha;
use App\Models\Partido;
use App\Models\ReprogramacionPartido;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Orquestador de reprogramación de partidos con validación de disponibilidad y registro obligatorio de motivo (HU-029 / RF-06.4 / HU-025 / SCRUM-196)
class ReprogramacionPartidoService
{
    public function __construct(
        private readonly AsignacionCanchaService $canchaService
    ) {
    }

    /**
     * Reprograma fecha, hora, cancha o árbitro de un partido con validación de disponibilidad y motivo obligatorio.
     *
     * @param array{
     *     fecha_partido: string,
     *     hora_partido: string,
     *     cancha_id: int|string,
     *     arbitro_id?: int|string|null,
     *     motivo: string,
     *     estado?: string
     * } $datos
     *
     * @throws ValidationException
     */
    public function reprogramar(Partido $partido, array $datos, ?User $usuario = null): Partido
    {
        $motivo = trim($datos['motivo']);
        if (mb_strlen($motivo) < 10) {
            throw ValidationException::withMessages([
                'motivo' => 'El motivo de reprogramación es obligatorio y debe tener al menos 10 caracteres explicativos (HU-029).',
            ]);
        }

        $usuarioActuante = $usuario ?? auth()->user();
        if (! $usuarioActuante instanceof User) {
            throw ValidationException::withMessages([
                'usuario' => 'Se requiere un usuario autenticado para registrar la reprogramación en la bitácora oficial (HU-029).',
            ]);
        }
        $usuarioId = $usuarioActuante->id;

        $canchaId = ! empty($datos['cancha_id']) ? (int) $datos['cancha_id'] : null;
        if ($canchaId === null) {
            throw ValidationException::withMessages([
                'cancha_id' => 'La asignación de cancha es obligatoria para reprogramar un partido oficial (HU-025, HU-029).',
            ]);
        }

        $fechaNueva = Carbon::createFromFormat('Y-m-d', $datos['fecha_partido'])->toDateString();
        $horaNueva = Carbon::parse($datos['hora_partido'])->format('H:i:s');
        $arbitroNuevoId = array_key_exists('arbitro_id', $datos)
            ? (! empty($datos['arbitro_id']) ? (int) $datos['arbitro_id'] : null)
            : $partido->arbitro_id;

        $nuevoEstado = $datos['estado'] ?? Partido::ESTADO_REPROGRAMADO;

        // Validaciones previas a la transacción (garantía de inmutabilidad y máquina de estados - HU-029 / SCRUM-195)
        if (in_array($partido->estado, [Partido::ESTADO_FINALIZADO, Partido::ESTADO_CANCELADO], true)) {
            throw ValidationException::withMessages([
                'estado' => 'No es posible reprogramar un partido que ya ha finalizado o ha sido cancelado.',
            ]);
        }

        if ($partido->estado === Partido::ESTADO_BORRADOR) {
            throw ValidationException::withMessages([
                'estado' => 'Un partido en estado borrador no puede ser reprogramado.',
            ]);
        }

        if ($partido->estado === Partido::ESTADO_EN_JUEGO) {
            throw ValidationException::withMessages([
                'estado' => 'No es posible reprogramar un partido en juego. Debe suspenderse previamente.',
            ]);
        }

        if ($partido->estado === Partido::ESTADO_REPROGRAMADO && $nuevoEstado === Partido::ESTADO_REPROGRAMADO) {
            // Permitido: reprogramar un partido que ya se encuentra en estado reprogramado
        } elseif (! $partido->puedeTransicionarA($nuevoEstado)) {
            throw ValidationException::withMessages([
                'estado' => "La transición de estado '{$partido->estado}' a '{$nuevoEstado}' no está permitida por el flujo del sistema.",
            ]);
        }

        return DB::transaction(function () use (
            $partido,
            $fechaNueva,
            $horaNueva,
            $canchaId,
            $arbitroNuevoId,
            $nuevoEstado,
            $usuarioId,
            $motivo
        ): Partido {
            // Concurrencia (Obligatorio 4): bloquear la fila del partido primero
            $partidoBloqueado = Partido::query()->whereKey($partido->id)->lockForUpdate()->firstOrFail();

            // Partidos en estados terminales no se pueden reprogramar (HU-029 / SCRUM-195)
            if (in_array($partidoBloqueado->estado, [Partido::ESTADO_FINALIZADO, Partido::ESTADO_CANCELADO], true)) {
                throw ValidationException::withMessages([
                    'estado' => 'No es posible reprogramar un partido que ya ha finalizado o ha sido cancelado.',
                ]);
            }

            // Partidos en borrador o en juego no pueden ser reprogramados directamente
            if ($partidoBloqueado->estado === Partido::ESTADO_BORRADOR) {
                throw ValidationException::withMessages([
                    'estado' => 'Un partido en estado borrador no puede ser reprogramado.',
                ]);
            }

            if ($partidoBloqueado->estado === Partido::ESTADO_EN_JUEGO) {
                throw ValidationException::withMessages([
                    'estado' => 'No es posible reprogramar un partido en juego. Debe suspenderse previamente.',
                ]);
            }

            // Validar transición en la máquina de estados
            if ($partidoBloqueado->estado === Partido::ESTADO_REPROGRAMADO && $nuevoEstado === Partido::ESTADO_REPROGRAMADO) {
                // Permitido: reprogramar un partido ya reprogramado
            } elseif (! $partidoBloqueado->puedeTransicionarA($nuevoEstado)) {
                throw ValidationException::withMessages([
                    'estado' => "La transición de estado '{$partidoBloqueado->estado}' a '{$nuevoEstado}' no está permitida por el flujo del sistema.",
                ]);
            }

            // Limitar la fecha a la temporada (Obligatorio 5)
            $temporada = $partidoBloqueado->jornada->grupo->temporada;
            if ($temporada !== null) {
                $fechaCarbon = Carbon::parse($fechaNueva);
                if ($temporada->fecha_inicio && $fechaCarbon->lt(Carbon::parse($temporada->fecha_inicio)->startOfDay())) {
                    throw ValidationException::withMessages([
                        'fecha_partido' => 'La fecha de reprogramación no puede ser anterior al inicio de la temporada ('.Carbon::parse($temporada->fecha_inicio)->format('d/m/Y').').',
                    ]);
                }
                if ($temporada->fecha_fin && $fechaCarbon->gt(Carbon::parse($temporada->fecha_fin)->endOfDay())) {
                    throw ValidationException::withMessages([
                        'fecha_partido' => 'La fecha de reprogramación no puede ser posterior al fin de la temporada ('.Carbon::parse($temporada->fecha_fin)->format('d/m/Y').').',
                    ]);
                }
            }

            // Bloquear la cancha para serializar concurrencia (Obligatorio 4 y 5)
            $canchaBloqueada = Cancha::query()->whereKey($canchaId)->lockForUpdate()->firstOrFail();

            $duracionMinutos = (int) config('programacion.duracion_partido_minutos', 120);
            $inicio = Carbon::parse($fechaNueva)->setTimeFromTimeString($horaNueva);
            $fin = $inicio->copy()->addMinutes($duracionMinutos);

            // Validar disponibilidad de cancha con duración oficial del sistema
            $motivoCancha = $this->canchaService->motivoDeRechazo($canchaBloqueada, $inicio, $fin, $partidoBloqueado);
            if ($motivoCancha !== null) {
                throw ValidationException::withMessages([
                    'cancha_id' => $motivoCancha,
                ]);
            }

            // Validar que ningún equipo del partido tenga otro partido en el mismo horario (Obligatorio 5)
            $equiposIds = [$partidoBloqueado->equipo_local_id, $partidoBloqueado->equipo_visitante_id];
            $partidosEquipos = Partido::query()
                ->whereKeyNot($partidoBloqueado->id)
                ->whereNotIn('estado', [Partido::ESTADO_CANCELADO])
                ->where('fecha_partido', $fechaNueva)
                ->where(function ($consulta) use ($equiposIds) {
                    $consulta->whereIn('equipo_local_id', $equiposIds)
                        ->orWhereIn('equipo_visitante_id', $equiposIds);
                })
                ->with(['local', 'visitante'])
                ->get();

            foreach ($partidosEquipos as $otroPartido) {
                if ($otroPartido->hora_partido === null) {
                    continue;
                }
                $otroInicio = Carbon::parse($otroPartido->fecha_partido)->setTimeFromTimeString((string) $otroPartido->hora_partido);
                $otroFin = $otroInicio->copy()->addMinutes($duracionMinutos);

                if ($inicio->lessThan($otroFin) && $fin->greaterThan($otroInicio)) {
                    $equipoColision = in_array($otroPartido->equipo_local_id, $equiposIds, true)
                        ? ($otroPartido->local->nombre ?? 'Equipo local')
                        : ($otroPartido->visitante->nombre ?? 'Equipo visitante');

                    throw ValidationException::withMessages([
                        'fecha_partido' => "El equipo '{$equipoColision}' ya tiene asignado otro partido el {$inicio->format('d/m/Y')} de {$otroInicio->format('H:i')} a {$otroFin->format('H:i')}.",
                    ]);
                }
            }

            // Validar árbitro si fue asignado
            $arbitro = null;
            if ($arbitroNuevoId !== null) {
                $arbitro = Arbitro::query()->whereKey($arbitroNuevoId)->lockForUpdate()->firstOrFail();

                if ($temporada !== null && ! $arbitro->disponibleEn($temporada)) {
                    throw ValidationException::withMessages([
                        'arbitro_id' => 'El árbitro no se encuentra disponible o no tiene el rol asignado en esta temporada.',
                    ]);
                }

                $arbitroOcupado = Partido::query()
                    ->where('arbitro_id', $arbitro->id)
                    ->where('fecha_partido', $fechaNueva)
                    ->where('hora_partido', $horaNueva)
                    ->whereKeyNot($partidoBloqueado->id)
                    ->exists();

                if ($arbitroOcupado) {
                    throw ValidationException::withMessages([
                        'arbitro_id' => 'El árbitro ya tiene asignado otro partido en la misma fecha y horario.',
                    ]);
                }
            }

            $estadoAnterior = $partidoBloqueado->estado;
            $canchaAnteriorId = $partidoBloqueado->cancha_id;
            $fechaAnterior = $partidoBloqueado->fecha_partido ? Carbon::parse($partidoBloqueado->fecha_partido)->toDateString() : null;
            $horaAnterior = $partidoBloqueado->hora_partido;

            $partidoBloqueado->update([
                'fecha_partido' => $fechaNueva,
                'hora_partido' => $horaNueva,
                'cancha_id' => $canchaBloqueada->id,
                'arbitro_id' => $arbitro?->id,
                'estado' => $nuevoEstado,
            ]);

            // Guardar en la bitácora auditable de reprogramaciones (RNF-22)
            ReprogramacionPartido::create([
                'partido_id' => $partidoBloqueado->id,
                'usuario_id' => $usuarioId,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $nuevoEstado,
                'cancha_anterior_id' => $canchaAnteriorId,
                'cancha_nueva_id' => $canchaBloqueada->id,
                'fecha_anterior' => $fechaAnterior,
                'fecha_nueva' => $fechaNueva,
                'hora_anterior' => $horaAnterior,
                'hora_nueva' => $horaNueva,
                'motivo' => $motivo,
            ]);

            return $partidoBloqueado;
        });
    }
}
