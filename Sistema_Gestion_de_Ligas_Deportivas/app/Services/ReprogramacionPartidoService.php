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
     *     cancha_id?: int|string|null,
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
                'motivo' => 'El motivo de reprogramación es obligatorio y debe tener al menos 10 caracteres explicativos.',
            ]);
        }

        // Partidos finalizados no se pueden reprogramar
        if ($partido->estado === Partido::ESTADO_FINALIZADO) {
            throw ValidationException::withMessages([
                'estado' => 'No es posible reprogramar un partido que ya ha finalizado.',
            ]);
        }

        $fechaNueva = Carbon::parse($datos['fecha_partido'])->toDateString();
        $horaNueva = Carbon::parse($datos['hora_partido'])->format('H:i:s');
        $canchaNuevaId = ! empty($datos['cancha_id']) ? (int) $datos['cancha_id'] : null;
        $arbitroNuevoId = array_key_exists('arbitro_id', $datos)
            ? (! empty($datos['arbitro_id']) ? (int) $datos['arbitro_id'] : null)
            : $partido->arbitro_id;

        $cancha = $canchaNuevaId !== null ? Cancha::query()->findOrFail($canchaNuevaId) : null;
        $arbitro = $arbitroNuevoId !== null ? Arbitro::query()->findOrFail($arbitroNuevoId) : null;

        // Validar disponibilidad de cancha (HU-025)
        if ($cancha !== null) {
            $inicio = Carbon::parse($fechaNueva)->setTimeFromTimeString($horaNueva);
            $fin = $inicio->copy()->addMinutes(90);

            $motivoCancha = $this->canchaService->motivoDeRechazo($cancha, $inicio, $fin, $partido);
            if ($motivoCancha !== null) {
                throw ValidationException::withMessages([
                    'cancha_id' => $motivoCancha,
                ]);
            }
        }

        // Validar disponibilidad de árbitro si fue asignado
        if ($arbitro !== null) {
            $temporada = $partido->jornada->grupo->temporada;
            if ($temporada !== null && ! $arbitro->disponibleEn($temporada)) {
                throw ValidationException::withMessages([
                    'arbitro_id' => 'El árbitro no se encuentra disponible o no tiene el rol asignado en esta temporada.',
                ]);
            }

            $arbitroOcupado = Partido::query()
                ->where('arbitro_id', $arbitro->id)
                ->where('fecha_partido', $fechaNueva)
                ->where('hora_partido', $horaNueva)
                ->whereKeyNot($partido->id)
                ->exists();

            if ($arbitroOcupado) {
                throw ValidationException::withMessages([
                    'arbitro_id' => 'El árbitro ya tiene asignado otro partido en la misma fecha y horario.',
                ]);
            }
        }

        $usuarioActuante = $usuario ?? auth()->user();
        $usuarioId = $usuarioActuante instanceof User ? $usuarioActuante->id : 1;

        $estadoAnterior = $partido->estado;
        $canchaAnteriorId = $partido->cancha_id;
        $fechaAnterior = $partido->fecha_partido ? Carbon::parse($partido->fecha_partido)->toDateString() : null;
        $horaAnterior = $partido->hora_partido;

        // El nuevo estado pasa a reprogramado salvo que explícitamente se confirme o programe
        $nuevoEstado = $datos['estado'] ?? Partido::ESTADO_REPROGRAMADO;

        return DB::transaction(function () use (
            $partido,
            $fechaNueva,
            $horaNueva,
            $cancha,
            $arbitro,
            $nuevoEstado,
            $usuarioId,
            $estadoAnterior,
            $canchaAnteriorId,
            $fechaAnterior,
            $horaAnterior,
            $motivo
        ): Partido {
            $partidoBloqueado = Partido::query()->whereKey($partido->id)->lockForUpdate()->firstOrFail();

            $partidoBloqueado->update([
                'fecha_partido' => $fechaNueva,
                'hora_partido' => $horaNueva,
                'cancha_id' => $cancha?->id,
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
                'cancha_nueva_id' => $cancha?->id,
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
