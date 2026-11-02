<?php

namespace App\Services;

use App\Models\Cancha;
use App\Models\Partido;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Impide asignar una cancha bloqueada o ya ocupada en el mismo horario (HU-025, RF-05.3)
class AsignacionCanchaService
{
    /**
     * Devuelve por qué no se puede usar la cancha en ese horario, o null si está libre.
     */
    public function motivoDeRechazo(Cancha $cancha, CarbonInterface $inicio, CarbonInterface $fin, ?Partido $ignorar = null): ?string
    {
        if ($cancha->estado !== 'Disponible') {
            return "La cancha no está disponible: su estado es {$cancha->estado}.";
        }

        $bloqueo = $cancha->bloqueos()->solapadosCon($inicio, $fin)->orderBy('fecha_inicio')->first();

        if ($bloqueo) {
            return 'La cancha está bloqueada del '.$bloqueo->fecha_inicio->format('d/m/Y H:i')
                .' al '.$bloqueo->fecha_fin->format('d/m/Y H:i').". Motivo: {$bloqueo->motivo}.";
        }

        $minutos = (int) config('programacion.duracion_partido_minutos');

        // El día anterior también cuenta: un partido nocturno puede terminar después de medianoche
        $partidos = $cancha->partidos()
            ->queOcupanCancha()
            ->whereBetween('fecha_partido', [$inicio->copy()->subDay()->toDateString(), $fin->toDateString()])
            ->when($ignorar, fn ($consulta) => $consulta->whereKeyNot($ignorar->getKey()))
            ->get();

        foreach ($partidos as $partido) {
            $inicioOcupado = Carbon::parse($partido->fecha_partido)->setTimeFromTimeString((string) $partido->hora_partido);
            $finOcupado = $inicioOcupado->copy()->addMinutes($minutos);

            if ($inicioOcupado->lessThan($fin) && $finOcupado->greaterThan($inicio)) {
                return 'La cancha ya está ocupada por otro partido el '.$inicioOcupado->format('d/m/Y')
                    .' de '.$inicioOcupado->format('H:i').' a '.$finOcupado->format('H:i').'.';
            }
        }

        return null;
    }

    /**
     * Asigna la cancha y el horario al partido, o lanza el motivo como error de validación de cancha_id.
     *
     * @throws ValidationException
     */
    public function asignar(Partido $partido, Cancha $cancha, CarbonInterface $inicio): void
    {
        $fin = $inicio->copy()->addMinutes((int) config('programacion.duracion_partido_minutos'));

        DB::transaction(function () use ($partido, $cancha, $inicio, $fin): void {
            // El bloqueo de la fila evita que dos programadores ocupen el mismo horario a la vez
            $canchaBloqueada = Cancha::query()->lockForUpdate()->findOrFail($cancha->getKey());
            $motivo = $this->motivoDeRechazo($canchaBloqueada, $inicio, $fin, $partido);

            if ($motivo !== null) {
                throw ValidationException::withMessages(['cancha_id' => $motivo]);
            }

            $partido->forceFill([
                'cancha_id' => $canchaBloqueada->getKey(),
                'fecha_partido' => $inicio->toDateString(),
                'hora_partido' => $inicio->format('H:i:s'),
            ])->save();
        });
    }
}
