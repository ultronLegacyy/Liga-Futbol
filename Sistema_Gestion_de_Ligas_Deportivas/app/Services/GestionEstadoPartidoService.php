<?php

namespace App\Services;

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
        // El motivo es obligatorio en todo cambio de estado con al menos 10 caracteres (HU-029 Criterio 2)
        $motivoLimpio = trim((string) $motivo);
        if (mb_strlen($motivoLimpio) < 10) {
            throw ValidationException::withMessages([
                'motivo' => 'El motivo es obligatorio en todo cambio de estado y debe contener al menos 10 caracteres explicativos (HU-029).',
            ]);
        }

        $usuarioActuante = $usuario ?? auth()->user();
        if (! $usuarioActuante instanceof User) {
            throw ValidationException::withMessages([
                'usuario' => 'Se requiere un usuario autenticado para registrar el cambio de estado en la bitácora oficial (HU-029).',
            ]);
        }
        $usuarioId = $usuarioActuante->id;

        return DB::transaction(function () use ($partido, $nuevoEstado, $motivoLimpio, $usuarioId): Partido {
            // Concurrencia (Obligatorio 4): bloquear la fila en base de datos antes de validar la transición
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
            $jornadaBloqueada = Jornada::query()->whereKey($partidoBloqueado->jornada_id)->lockForUpdate()->first();
            if ($jornadaBloqueada !== null && in_array($jornadaBloqueada->estado, [Jornada::ESTADO_EN_REVISION, Jornada::ESTADO_APROBADA], true)) {
                $jornadaBloqueada->update([
                    'estado' => Jornada::ESTADO_BORRADOR,
                    'aprobado_por_id' => null,
                    'fecha_aprobacion' => null,
                ]);
            }

            return $partidoBloqueado;
        });
    }
}
