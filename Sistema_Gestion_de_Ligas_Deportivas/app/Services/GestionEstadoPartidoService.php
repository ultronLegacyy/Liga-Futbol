<?php

namespace App\Services;

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
        $estadoActual = $partido->estado;

        if (! $partido->puedeTransicionarA($nuevoEstado)) {
            $permitidos = implode(', ', $partido->transicionesPermitidas());
            $mensaje = empty($permitidos)
                ? "El partido en estado '{$estadoActual}' es terminal y no admite más transiciones."
                : "Transición no válida de '{$estadoActual}' a '{$nuevoEstado}'. Estados permitidos: {$permitidos}.";

            throw ValidationException::withMessages([
                'nuevo_estado' => $mensaje,
            ]);
        }

        // Si cambia a cancelado, suspendido o reprogramado, el motivo es obligatorio (Criterio 2)
        if (in_array($nuevoEstado, [Partido::ESTADO_CANCELADO, Partido::ESTADO_SUSPENDIDO, Partido::ESTADO_REPROGRAMADO], true)) {
            $motivoLimpio = trim((string) $motivo);
            if (mb_strlen($motivoLimpio) < 5) {
                throw ValidationException::withMessages([
                    'motivo' => 'El motivo es obligatorio y debe contener al menos 5 caracteres descriptivos.',
                ]);
            }
        }

        $usuarioActuante = $usuario ?? auth()->user();
        $usuarioId = $usuarioActuante?->id;

        return DB::transaction(function () use ($partido, $estadoActual, $nuevoEstado, $motivo, $usuarioId): Partido {
            $partidoBloqueado = Partido::query()->whereKey($partido->id)->lockForUpdate()->firstOrFail();

            $partidoBloqueado->estado = $nuevoEstado;
            $partidoBloqueado->save();

            // Registrar auditoría de la transición en reprogramaciones_partidos
            if ($usuarioId !== null) {
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
                    'motivo' => $motivo ?? "Cambio de estado de '{$estadoActual}' a '{$nuevoEstado}'.",
                ]);
            }

            return $partidoBloqueado;
        });
    }
}
