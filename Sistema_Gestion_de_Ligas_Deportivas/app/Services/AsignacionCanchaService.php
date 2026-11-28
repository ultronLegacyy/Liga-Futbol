<?php

namespace App\Services;

use App\Models\Cancha;
use App\Models\Partido;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

// Impide asignar una cancha bloqueada o ya ocupada en el mismo horario (HU-025, RF-05.3)
class AsignacionCanchaService
{
    /**
     * Devuelve por qué no se puede usar la cancha en ese horario, o null si está libre.
     */
    public function motivoDeRechazo(Cancha $cancha, CarbonInterface $inicio, CarbonInterface $fin, ?Partido $ignorar = null): ?string
    {
        if (in_array($cancha->estado, ['Inactiva', 'Mantenimiento'], true)) {
            return "La cancha no está disponible: su estado es {$cancha->estado}.";
        }

        $bloqueo = $cancha->bloqueos()->solapadosCon($inicio, $fin)->orderBy('fecha_inicio')->first();

        if ($bloqueo) {
            return 'La cancha está bloqueada del '.$bloqueo->fecha_inicio->format('d/m/Y H:i')
                .' al '.$bloqueo->fecha_fin->format('d/m/Y H:i').". Motivo: {$bloqueo->motivo}.";
        }

        $minutos = $this->duracionEnMinutos();

        $partidos = $cancha->partidos()
            ->queOcupanCancha()
            // Un partido que empezó antes puede seguir ocupando la cancha, incluso desde el día anterior
            ->whereBetween('fecha_partido', [$inicio->copy()->subMinutes($minutos)->toDateString(), $fin->toDateString()])
            ->when($ignorar, fn ($consulta) => $consulta->whereKeyNot($ignorar->getKey()))
            ->orderBy('fecha_partido')
            ->orderBy('hora_partido')
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
        $fin = $inicio->copy()->addMinutes($this->duracionEnMinutos());

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

    // Una duración vacía, en 0 o negativa haría que ningún partido se solapara: se detiene en vez de permitirlo todo
    public function duracionEnMinutos(): int
    {
        $minutos = (int) config('programacion.duracion_partido_minutos');

        if ($minutos <= 0) {
            throw new RuntimeException('PARTIDO_DURACION_MINUTOS debe ser un número entero mayor que cero.');
        }

        return $minutos;
    }

    /**
     * Registra un intervalo [inicioTs, finTs) en todos los buckets diarios que toca (cruce de medianoche incluido).
     * Permite reutilizar exactamente la regla de ventana entre días de HU-025 en O(1) por fecha (RNF-07).
     *
     * @param  array<string, array<int, array<int|string, mixed>>>  $bucketsPorFecha
     * @param  array<int|string, mixed>  $entrada
     */
    public static function registrarIntervaloConCruceMedianoche(
        array &$bucketsPorFecha,
        int $inicioTs,
        int $finTs,
        array $entrada
    ): void {
        $fechaInicio = date('Y-m-d', $inicioTs);
        $bucketsPorFecha[$fechaInicio][] = $entrada;

        // Si el fin real cae en un día posterior (excluyendo exactamente las 00:00:00 porque fin es abierto [inicio, fin))
        $finInclusiveTs = max($inicioTs, $finTs - 1);
        $fechaFin = date('Y-m-d', $finInclusiveTs);
        if ($fechaFin !== $fechaInicio) {
            $bucketsPorFecha[$fechaFin][] = $entrada;
        }
    }
}
