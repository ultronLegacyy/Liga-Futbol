<?php

namespace App\Services;

use App\Models\Arbitro;
use App\Models\Partido;
use App\Models\Temporada;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Asignación de árbitros a partidos programados (HU-026, RF-06.1). La autorización le toca al controlador
class AsignacionArbitroService
{
    /**
     * Asigna el árbitro al partido. El partido conserva su local, visitante, cancha, fecha y hora (CA2)
     * y el árbitro no puede quedar en dos partidos a la misma fecha y hora (CA3).
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

        $ocupado = Partido::query()
            ->where('arbitro_id', $arbitro->id)
            ->where('fecha_partido', $partido->getRawOriginal('fecha_partido'))
            ->where('hora_partido', $partido->getRawOriginal('hora_partido'))
            ->whereKeyNot($partido->id)
            ->exists();

        if ($ocupado) {
            throw $this->choqueDeHorario();
        }
    }

    private function choqueDeHorario(): ValidationException
    {
        return ValidationException::withMessages([
            'arbitro_id' => 'El árbitro ya tiene asignado otro partido a la misma fecha y hora.',
        ]);
    }
}
