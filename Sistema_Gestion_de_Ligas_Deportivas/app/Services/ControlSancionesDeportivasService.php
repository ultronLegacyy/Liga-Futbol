<?php

namespace App\Services;

use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Sancion;
use Illuminate\Validation\ValidationException;

/**
 * Servicio para verificar sanciones vigentes e impedir la participación de jugadores inhabilitados (HU-047 / RF-10.4, IU-05).
 */
class ControlSancionesDeportivasService
{
    /**
     * Valida que un jugador no cuente con sanción inhabilitante antes de ser alineado en un partido (HU-047 Criterio 1 y 3, IU-05).
     * Aplica desde que la sanción es dictaminada (RNF-43 / PO #48), incluso antes de validarse para boletín.
     *
     * @throws ValidationException
     */
    public function validarAlineacionJugador(Jugador|int $jugador, ?Partido $partido = null): void
    {
        $jugadorModel = is_int($jugador) ? Jugador::findOrFail($jugador) : $jugador;
        $fechaReferencia = $partido?->fecha_partido?->toDateString();

        $sancionJugador = $this->obtenerSancionVigenteJugador($jugadorModel, $fechaReferencia);

        if ($sancionJugador !== null) {
            $persona = $jugadorModel->persona;
            $nombre = $persona ? "{$persona->nombre} {$persona->apellidos}" : "Jugador #{$jugadorModel->id}";
            $mensaje = "[IU-05] Jugador no elegible: {$nombre} se encuentra inhabilitado por sanción deportiva vigente ({$sancionJugador->explicacionRegla()}).";

            throw ValidationException::withMessages([
                'jugador_id' => $mensaje,
            ]);
        }
    }

    /**
     * Determina si un jugador tiene alguna sanción disciplinaria vigente que le impida participar.
     */
    public function jugadorTieneSancionVigente(Jugador $jugador, ?string $fechaPartido = null): bool
    {
        return $this->obtenerSancionVigenteJugador($jugador, $fechaPartido) !== null;
    }

    /**
     * Obtiene la sanción disciplinaria vigente que inhabilita a un jugador en la fecha indicada.
     */
    public function obtenerSancionVigenteJugador(Jugador $jugador, ?string $fechaPartido = null): ?Sancion
    {
        return Sancion::query()
            ->with(['expediente.articulo', 'articulo'])
            ->where('persona_id', $jugador->persona_id)
            ->inhabilitantes($fechaPartido)
            ->first();
    }
}
