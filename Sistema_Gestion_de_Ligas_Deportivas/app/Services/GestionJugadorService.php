<?php

namespace App\Services;

use App\Models\Equipo;
use App\Models\HistorialTransferenciaJugador;
use App\Models\Jugador;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class GestionJugadorService
{
    public function __construct(
        private AuditService $auditoria,
        private ReglaSaltoCategoriaService $reglaSaltoCategoria,
    ) {
    }

    public function transferir(
        Jugador $jugadorOrigen,
        Equipo $equipoDestino,
        User $usuario,
        int|string|null $dorsal = null,
    ): HistorialTransferenciaJugador {
        return $this->auditoria->conMotivo('Transferencia de jugador entre clubes', function () use ($jugadorOrigen, $equipoDestino, $usuario, $dorsal): HistorialTransferenciaJugador {
            $jugadorOrigen->loadMissing('equipo.grupo.categoria');
            $equipoDestino->loadMissing('club', 'temporada', 'grupo.categoria');
            $equipoOrigen = $jugadorOrigen->equipo;
            $temporadaDestino = $equipoDestino->temporada;

            if ($jugadorOrigen->estado_elegibilidad !== 'Activo') {
                throw ValidationException::withMessages([
                    'jugador' => 'Solo se pueden transferir jugadores activos.',
                ]);
            }

            if ($temporadaDestino === null || $temporadaDestino->estado !== 'activa') {
                throw ValidationException::withMessages([
                    'equipo_id' => 'El equipo de destino debe pertenecer a la temporada activa.',
                ]);
            }

            if ((int) $equipoOrigen->club_id === (int) $equipoDestino->club_id) {
                throw ValidationException::withMessages([
                    'equipo_id' => 'El equipo de destino debe pertenecer a otro club.',
                ]);
            }

            $otraInscripcionActiva = Jugador::query()
                ->where('persona_id', $jugadorOrigen->persona_id)
                ->where('estado_elegibilidad', 'Activo')
                ->whereKeyNot($jugadorOrigen->getKey())
                ->whereHas('equipo', fn (Builder $query): Builder => $query->where('temporada_id', $temporadaDestino->getKey()))
                ->exists();

            if ($otraInscripcionActiva) {
                throw ValidationException::withMessages([
                    'jugador' => 'El jugador ya tiene una inscripción activa en la temporada de destino.',
                ]);
            }

            $this->reglaSaltoCategoria->validar(
                $equipoOrigen->grupo->categoria,
                $equipoDestino->grupo->categoria,
            );

            $jugadorOrigen->forceFill(['estado_elegibilidad' => 'Transferido'])->save();

            $jugadorDestino = $equipoDestino->jugadores()->create([
                'persona_id' => $jugadorOrigen->persona_id,
                'dorsal' => $dorsal ?? $jugadorOrigen->dorsal,
                'estado_elegibilidad' => 'Activo',
            ]);

            $historial = HistorialTransferenciaJugador::query()->create([
                'persona_id' => $jugadorOrigen->persona_id,
                'jugador_origen_id' => $jugadorOrigen->getKey(),
                'jugador_destino_id' => $jugadorDestino->getKey(),
                'club_origen_id' => $equipoOrigen->club_id,
                'club_destino_id' => $equipoDestino->club_id,
                'equipo_origen_id' => $equipoOrigen->getKey(),
                'equipo_destino_id' => $equipoDestino->getKey(),
                'temporada_id' => $temporadaDestino->getKey(),
                'transferido_por_usuario_id' => $usuario->getKey(),
            ]);

            $this->auditoria->registrar('jugador_transferido', $historial, null, [
                'persona_id' => $jugadorOrigen->persona_id,
                'jugador_origen_id' => $jugadorOrigen->getKey(),
                'jugador_destino_id' => $jugadorDestino->getKey(),
                'club_origen_id' => $equipoOrigen->club_id,
                'club_destino_id' => $equipoDestino->club_id,
                'equipo_origen_id' => $equipoOrigen->getKey(),
                'equipo_destino_id' => $equipoDestino->getKey(),
                'temporada_id' => $temporadaDestino->getKey(),
            ]);

            return $historial;
        });
    }
}
