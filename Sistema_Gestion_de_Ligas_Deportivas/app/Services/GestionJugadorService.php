<?php

namespace App\Services;

use App\Models\Equipo;
use App\Models\Jugador;
use Illuminate\Validation\ValidationException;

class GestionJugadorService
{
    public function __construct(
        private AuditService $auditoria,
        private ReglaSaltoCategoriaService $reglaSaltoCategoria,
    ) {
    }

    public function darDeBaja(Jugador $jugador, string $motivo, string $fecha): Jugador
    {
        return $this->auditoria->conMotivo('Baja de jugador: '.trim($motivo), function () use ($jugador, $motivo, $fecha): Jugador {
            if ($jugador->estado_elegibilidad !== 'Activo') {
                throw ValidationException::withMessages([
                    'jugador' => 'Solo se puede dar de baja a un jugador activo.',
                ]);
            }

            $anteriores = [
                'estado_elegibilidad' => $jugador->estado_elegibilidad,
                'motivo_baja' => $jugador->motivo_baja,
                'fecha_baja' => $jugador->fecha_baja?->toDateString(),
            ];

            $jugador->forceFill([
                'estado_elegibilidad' => 'Baja',
                'motivo_baja' => trim($motivo),
                'fecha_baja' => $fecha,
            ])->save();

            $this->auditoria->registrar('jugador_dado_de_baja', $jugador, $anteriores, [
                'estado_elegibilidad' => 'Baja',
                'motivo_baja' => trim($motivo),
                'fecha_baja' => $fecha,
            ]);

            return $jugador;
        });
    }

    public function renovar(Jugador $jugadorAnterior, Equipo $equipoDestino, int|string|null $dorsal = null): Jugador
    {
        return $this->auditoria->conMotivo('Renovación de inscripción de jugador', function () use ($jugadorAnterior, $equipoDestino, $dorsal): Jugador {
            $temporadaDestino = $equipoDestino->temporada;

            if ($temporadaDestino === null || $temporadaDestino->estado !== 'activa') {
                throw ValidationException::withMessages([
                    'equipo_id' => 'La renovación debe dirigirse a un equipo de la temporada activa.',
                ]);
            }

            if ((int) $jugadorAnterior->equipo->temporada_id === (int) $temporadaDestino->getKey()) {
                throw ValidationException::withMessages([
                    'equipo_id' => 'El jugador ya pertenece a la temporada seleccionada.',
                ]);
            }

            if ((int) $jugadorAnterior->equipo->club_id !== (int) $equipoDestino->club_id) {
                throw ValidationException::withMessages([
                    'equipo_id' => 'La renovación debe conservar el club de origen del jugador.',
                ]);
            }

            $inscripcionExistente = Jugador::query()
                ->where('persona_id', $jugadorAnterior->persona_id)
                ->whereHas('equipo', fn ($query) => $query->where('temporada_id', $temporadaDestino->getKey()))
                ->exists();

            if ($inscripcionExistente) {
                throw ValidationException::withMessages([
                    'jugador' => 'El jugador ya cuenta con una inscripción en la temporada activa.',
                ]);
            }

            $this->reglaSaltoCategoria->validar(
                $jugadorAnterior->equipo->grupo->categoria,
                $equipoDestino->grupo->categoria,
            );

            $renovacion = $equipoDestino->jugadores()->create([
                'persona_id' => $jugadorAnterior->persona_id,
                'dorsal' => $dorsal ?? $jugadorAnterior->dorsal,
                'estado_elegibilidad' => 'Activo',
            ]);

            $this->auditoria->registrar('jugador_renovado', $renovacion, [
                'jugador_anterior_id' => $jugadorAnterior->getKey(),
                'temporada_anterior_id' => $jugadorAnterior->equipo->temporada_id,
            ], [
                'persona_id' => $renovacion->persona_id,
                'equipo_id' => $equipoDestino->getKey(),
                'temporada_id' => $temporadaDestino->getKey(),
            ]);

            return $renovacion;
        });
    }
}
