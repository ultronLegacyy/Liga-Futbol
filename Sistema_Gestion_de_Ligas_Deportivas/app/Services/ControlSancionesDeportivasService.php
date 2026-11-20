<?php

namespace App\Services;

use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Sancion;
use Illuminate\Validation\ValidationException;

/**
 * Servicio para verificar sanciones vigentes e impedir alineación o programación (HU-047 / RF-10.4, IU-05).
 */
class ControlSancionesDeportivasService
{
    /**
     * Valida que un jugador (ni su club completo) tenga sanción inhabilitante antes de ser alineado en un partido (HU-047 Criterio 1, IU-05).
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

        // También verificar si el equipo completo tiene sanción colectiva que impida jugar
        $equipo = $jugadorModel->equipo;
        if ($equipo !== null) {
            $sancionEquipo = $this->obtenerSancionVigenteEquipo($equipo, $fechaReferencia);
            if ($sancionEquipo !== null) {
                $mensaje = "[IU-05] El equipo '{$equipo->nombre}' cuenta con una sanción disciplinaria vigente ({$sancionEquipo->explicacionRegla()}).";
                throw ValidationException::withMessages([
                    'jugador_id' => $mensaje,
                ]);
            }
        }
    }

    /**
     * Valida que un equipo no tenga sanción vigente que le impida ser programado (HU-047 Criterio 2, IU-05).
     *
     * @throws ValidationException
     */
    public function validarProgramacionEquipo(Equipo|int $equipo, ?string $campo = 'equipo_id', ?string $fechaPartido = null): void
    {
        $equipoModel = is_int($equipo) ? Equipo::findOrFail($equipo) : $equipo;
        $sancion = $this->obtenerSancionVigenteEquipo($equipoModel, $fechaPartido);

        if ($sancion !== null) {
            $clave = $campo ?? 'equipo_id';
            $mensaje = "[IU-05] Equipo inhabilitado para programación: '{$equipoModel->nombre}' cuenta con una sanción deportiva vigente ({$sancion->explicacionRegla()}).";

            throw ValidationException::withMessages([
                $clave => $mensaje,
            ]);
        }
    }

    /**
     * Determina si un equipo tiene alguna sanción disciplinaria vigente que le impida jugar.
     */
    public function equipoTieneSancionVigente(Equipo $equipo, ?string $fechaPartido = null): bool
    {
        return $this->obtenerSancionVigenteEquipo($equipo, $fechaPartido) !== null;
    }

    /**
     * Obtiene la sanción disciplinaria colectiva vigente que inhabilita a un equipo en la fecha indicada.
     * Solo considera sanciones colectivas al club (persona_id IS NULL) de tipos inhabilitantes.
     */
    public function obtenerSancionVigenteEquipo(Equipo $equipo, ?string $fechaPartido = null): ?Sancion
    {
        return Sancion::query()
            ->with(['expediente.articulo', 'articulo'])
            ->whereNull('persona_id')
            ->where('equipo_id', $equipo->id)
            ->inhabilitantes($fechaPartido)
            ->first();
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
