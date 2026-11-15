<?php

namespace App\Services;

use App\Models\CedulaArbitral;
use App\Models\Jornada;
use App\Models\Partido;
use App\Models\ReprogramacionPartido;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Control de máquina de estados del partido y transiciones válidas (HU-029 / RF-06.3 / SCRUM-195)
class GestionEstadoPartidoService
{
    /**
     * Ejecuta una transición de estado sobre un partido validando la máquina de estados.
     *
     * @throws ValidationException
     */
    public function cambiarEstado(Partido $partido, string $nuevoEstado, ?string $motivo = null, ?User $usuario = null): Partido
    {
        // El motivo es obligatorio en todo cambio de estado con al menos 10 caracteres
        $motivoLimpio = trim((string) $motivo);
        if (mb_strlen($motivoLimpio) < 10 || mb_strlen($motivoLimpio) > 1000) {
            throw ValidationException::withMessages([
                'motivo' => 'El motivo es obligatorio en todo cambio de estado y debe contener entre 10 y 1000 caracteres explicativos.',
            ]);
        }

        $usuarioActuante = $usuario ?? auth()->user();
        if (! $usuarioActuante instanceof User) {
            throw ValidationException::withMessages([
                'usuario' => 'Se requiere un usuario autenticado para registrar el cambio de estado en la bitácora oficial.',
            ]);
        }
        $usuarioId = $usuarioActuante->id;

        return DB::transaction(function () use ($partido, $nuevoEstado, $motivoLimpio, $usuarioId): Partido {
            // Concurrencia jerárquica estricta (Obligatorio Nuevo A):
            // 1. Jornada -> 2. Partido
            $jornadaBloqueada = Jornada::query()->whereKey($partido->jornada_id)->lockForUpdate()->firstOrFail();
            $partidoBloqueado = Partido::query()->whereKey($partido->id)->lockForUpdate()->firstOrFail();
            $estadoActual = $partidoBloqueado->estado;

            if (! $partidoBloqueado->puedeTransicionarA($nuevoEstado)) {
                $permitidos = implode(', ', $partidoBloqueado->transicionesPermitidas());
                $mensaje = empty($permitidos)
                    ? "El partido en estado '{$estadoActual}' es terminal y no admite más transiciones."
                    : "Transición no válida de '{$estadoActual}' a '{$nuevoEstado}'. Estados permitidos: {$permitidos}.";

                throw ValidationException::withMessages([
                    'nuevo_estado' => $mensaje,
                ]);
            }

            if ($partidoBloqueado->cedula()->where('estado', CedulaArbitral::CERRADA)->exists()) {
                throw ValidationException::withMessages([
                    'nuevo_estado' => 'No es posible cambiar el estado de un partido cuya cédula arbitral ya fue cerrada.',
                ]);
            }

            if ($nuevoEstado === Partido::ESTADO_FINALIZADO && $partidoBloqueado->cedula()->exists()) {
                throw ValidationException::withMessages([
                    'nuevo_estado' => 'Un partido con cédula arbitral registrada pasa a finalizado automáticamente al cerrarse la cédula.',
                ]);
            }

            if ($nuevoEstado === Partido::ESTADO_CANCELADO
                && Partido::query()->whereKey($partidoBloqueado->id)->conCedulaCapturada()->exists()) {
                throw ValidationException::withMessages([
                    'nuevo_estado' => 'No es posible cancelar un partido cuya cédula arbitral ya tiene datos capturados.',
                ]);
            }

            if ($nuevoEstado === Partido::ESTADO_PROGRAMADO && ($partidoBloqueado->cancha_id === null || $partidoBloqueado->fecha_partido === null || $partidoBloqueado->hora_partido === null)) {
                throw ValidationException::withMessages([
                    'nuevo_estado' => 'No es posible pasar un partido a estado programado sin tener asignada cancha, fecha y horario completo.',
                ]);
            }

            $partidoBloqueado->estado = $nuevoEstado;
            $partidoBloqueado->save();

            // Registrar auditoría de la transición en reprogramaciones_partidos (RNF-22)
            ReprogramacionPartido::create([
                'partido_id' => $partidoBloqueado->id,
                'usuario_id' => $usuarioId,
                'estado_anterior' => $estadoActual,
                'estado_nuevo' => $nuevoEstado,
                'cancha_anterior_id' => $partidoBloqueado->cancha_id,
                'cancha_nueva_id' => $partidoBloqueado->cancha_id,
                'fecha_anterior' => $partidoBloqueado->fecha_partido,
                'fecha_nueva' => $partidoBloqueado->fecha_partido,
                'hora_anterior' => $partidoBloqueado->hora_partido,
                'hora_nueva' => $partidoBloqueado->hora_partido,
                'motivo' => $motivoLimpio,
            ]);

            // Revertir la jornada a borrador si estaba en revisión o aprobada (Obligatorio 4 / HU-030)
            $jornadaBloqueada->revertirABorradorPorModificacion(false);

            return $partidoBloqueado;
        }, 3);
    }
}
