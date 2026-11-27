<?php

namespace App\Services;

use App\Models\Arbitro;
use App\Models\Partido;
use App\Models\Temporada;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Asignación de árbitros a partidos programados (HU-026, RF-06.1). La autorización le toca al controlador
class AsignacionArbitroService
{
    public function __construct(
        private readonly AsignacionCanchaService $canchaService,
    ) {
    }

    /**
     * Asigna el árbitro al partido. El partido conserva su local, visitante, cancha, fecha y hora (CA2)
     * y el árbitro no puede quedar en dos partidos que se empalmen dentro de la duración del partido (CA3 / PO).
     */
    public function asignar(Partido $partido, Arbitro $arbitro): Partido
    {
        try {
            return DB::transaction(function () use ($partido, $arbitro): Partido {
                // Bloquear al árbitro ordena las asignaciones simultáneas del mismo árbitro
                $arbitro = Arbitro::query()->whereKey($arbitro->getKey())->lockForUpdate()->firstOrFail();
                $partido = Partido::query()->whereKey($partido->getKey())->lockForUpdate()->firstOrFail();

                $temporada = Temporada::query()
                    ->whereKey($partido->jornada()->firstOrFail()->grupo()->firstOrFail()->temporada_id)
                    ->sharedLock()
                    ->firstOrFail();

                $this->validar($partido, $arbitro, $temporada);

                $partido->update(['arbitro_id' => $arbitro->id]);

                return $partido;
            });
        } catch (UniqueConstraintViolationException) {
            // Respaldo del índice único si otra asignación ganó la carrera
            throw $this->choqueDeHorario();
        }
    }

    /**
     * Regla única de disponibilidad horaria para árbitros (HU-026 CA3, HU-027, HU-031, HU-033, HU-074):
     * impide misma fecha/hora exacta (índice único) y cualquier empalme dentro de la duración del partido (120 min),
     * considerando cruce de medianoche en ambos sentidos.
     */
    public function motivoDeRechazoHorario(Arbitro $arbitro, CarbonInterface $inicio, ?Partido $ignorar = null): ?string
    {
        $fecha = $inicio->toDateString();
        $hora = $inicio->format('H:i:s');

        // 1. Coincidencia exacta protegida por partidos_arbitro_horario_unique (aplica incluso si está cancelado/suspendido)
        $choqueExacto = Partido::query()
            ->where('arbitro_id', $arbitro->id)
            ->where('fecha_partido', $fecha)
            ->where('hora_partido', $hora)
            ->when($ignorar, fn ($q) => $q->whereKeyNot($ignorar->getKey()))
            ->exists();

        if ($choqueExacto) {
            return 'El árbitro ya tiene asignado otro partido a la misma fecha y hora.';
        }

        // 2. Ventana de duración del partido (120 min) en ambos sentidos con cruce de medianoche
        $minutos = $this->canchaService->duracionEnMinutos();
        $fin = $inicio->copy()->addMinutes($minutos);
        $fechaMin = $inicio->copy()->subMinutes($minutos)->toDateString();
        $fechaMax = $fin->toDateString();

        $partidos = Partido::query()
            ->where('arbitro_id', $arbitro->id)
            ->queOcupanCancha()
            ->whereBetween('fecha_partido', [$fechaMin, $fechaMax])
            ->when($ignorar, fn ($q) => $q->whereKeyNot($ignorar->getKey()))
            ->orderBy('fecha_partido')
            ->orderBy('hora_partido')
            ->get(['id', 'fecha_partido', 'hora_partido']);

        foreach ($partidos as $otro) {
            $inicioOcupado = Carbon::parse($otro->fecha_partido)->setTimeFromTimeString((string) $otro->hora_partido);
            $finOcupado = $inicioOcupado->copy()->addMinutes($minutos);

            if ($inicioOcupado->lessThan($fin) && $finOcupado->greaterThan($inicio)) {
                return 'El árbitro ya tiene asignado otro partido el '.$inicioOcupado->format('d/m/Y')
                    .' de '.$inicioOcupado->format('H:i').' a '.$finOcupado->format('H:i').'.';
            }
        }

        return null;
    }

    private function validar(Partido $partido, Arbitro $arbitro, Temporada $temporada): void
    {
        if ($temporada->estado === 'cerrada') {
            throw ValidationException::withMessages([
                'arbitro_id' => 'La temporada del partido está cerrada; la asignación solo se puede consultar.',
            ]);
        }

        if (! in_array($partido->estado, Partido::ESTADOS_ASIGNABLES, true)) {
            throw ValidationException::withMessages([
                'arbitro_id' => 'Solo se asigna árbitro a partidos programados o confirmados.',
            ]);
        }

        // Datos que debe conservar la asignación (RF-06.1); sin fecha y hora no se puede revisar el horario
        if ($partido->fecha_partido === null || $partido->hora_partido === null || $partido->cancha_id === null) {
            throw ValidationException::withMessages([
                'arbitro_id' => 'El partido debe tener cancha, fecha y hora antes de asignarle árbitro.',
            ]);
        }

        if (! $arbitro->disponibleEn($temporada)) {
            throw ValidationException::withMessages([
                'arbitro_id' => 'El árbitro debe estar activo y tener rol de árbitro en la temporada del partido.',
            ]);
        }

        $inicio = Carbon::parse($partido->getRawOriginal('fecha_partido'))
            ->setTimeFromTimeString((string) $partido->getRawOriginal('hora_partido'));
        $motivo = $this->motivoDeRechazoHorario($arbitro, $inicio, $partido);

        if ($motivo !== null) {
            throw ValidationException::withMessages(['arbitro_id' => $motivo]);
        }
    }

    private function choqueDeHorario(): ValidationException
    {
        return ValidationException::withMessages([
            'arbitro_id' => 'El árbitro ya tiene asignado otro partido a la misma fecha y hora.',
        ]);
    }
}
