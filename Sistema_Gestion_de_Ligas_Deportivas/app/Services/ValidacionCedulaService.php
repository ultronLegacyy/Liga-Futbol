<?php

namespace App\Services;

use App\Models\AlineacionPartido;
use App\Models\CedulaArbitral;
use App\Models\EventoPartido;
use App\Models\Jugador;

/**
 * Validaciones de la cédula antes de cerrarla (HU-075, criterio 4, RNF-16). Solo lee; no guarda nada.
 * La reutiliza la captura desde papel (HU-037). La elegibilidad completa (HU-019) no va aquí: la conecta el S7.
 *
 * Revisa a los jugadores que asistieron o que tienen un gol o una tarjeta, y detecta:
 * - jugador no registrado: ya no está en la plantilla de su equipo, se dio de baja o quedó vacío (venta del club);
 * - jugador suspendido (HU-018);
 * - goles que no suman el marcador (un autogol cuenta para el rival);
 * - tarjeta a un jugador no alineado (sin asistencia marcada).
 */
class ValidacionCedulaService
{
    public const NO_REGISTRADO = 'no_registrado';

    public const SUSPENDIDO = 'suspendido';

    public const MARCADOR = 'marcador';

    public const TARJETA_NO_ALINEADO = 'tarjeta_no_alineado';

    /**
     * @return list<array{tipo: string, mensaje: string}>
     */
    public function validar(CedulaArbitral $cedula): array
    {
        $partido = $cedula->partido()->with(['local:id,nombre', 'visitante:id,nombre'])->firstOrFail();
        $equipos = [
            (int) $partido->equipo_local_id => (string) $partido->local?->nombre,
            (int) $partido->equipo_visitante_id => (string) $partido->visitante?->nombre,
        ];

        $alineaciones = $cedula->alineaciones()->with('jugador.persona:id,nombre,apellidos')->get();
        $eventos = $cedula->eventos()->with('jugador.persona:id,nombre,apellidos')->orderBy('minuto')->orderBy('id')->get();
        $porJugador = $alineaciones->whereNotNull('jugador_id')->keyBy('jugador_id');

        $hallazgos = [];
        $revisados = [];

        // Jugadores que participaron: los que asistieron y los que tienen un gol o una tarjeta
        foreach ($alineaciones->where('asistio', true) as $fila) {
            $this->revisarJugador($fila->jugador, (int) $fila->equipo_id, $equipos, $hallazgos, $revisados);
        }

        foreach ($eventos as $evento) {
            $fila = $evento->jugador_id === null ? null : $porJugador->get($evento->jugador_id);

            if ($evento->jugador_id === null || $fila === null) {
                $hallazgos[] = [
                    'tipo' => self::NO_REGISTRADO,
                    'mensaje' => $this->descripcion($evento).' no tiene un jugador registrado en la alineación.',
                ];

                continue;
            }

            $this->revisarJugador($evento->jugador, (int) $fila->equipo_id, $equipos, $hallazgos, $revisados);

            if ($evento->tipo !== 'Gol' && ! $fila->asistio) {
                $hallazgos[] = [
                    'tipo' => self::TARJETA_NO_ALINEADO,
                    'mensaje' => $this->descripcion($evento).' es para '.$this->nombre($evento->jugador).' ('.$equipos[(int) $fila->equipo_id].'), que no está marcado como asistente.',
                ];
            }
        }

        return [...$hallazgos, ...$this->revisarMarcador($cedula, $eventos, $porJugador, $equipos)];
    }

    /**
     * @param  array<int, string>  $equipos
     * @param  list<array{tipo: string, mensaje: string}>  $hallazgos
     * @param  array<int, true>  $revisados
     */
    private function revisarJugador(?Jugador $jugador, int $equipoId, array $equipos, array &$hallazgos, array &$revisados): void
    {
        if ($jugador !== null && isset($revisados[$jugador->id])) {
            return;
        }

        if ($jugador === null || (int) $jugador->equipo_id !== $equipoId || $jugador->estado_elegibilidad === 'Baja') {
            $hallazgos[] = [
                'tipo' => self::NO_REGISTRADO,
                'mensaje' => ($jugador === null ? 'Un jugador de '.$equipos[$equipoId] : $this->nombre($jugador).' ('.$equipos[$equipoId].')').' no está registrado en la plantilla del equipo.',
            ];
        } elseif ($jugador->estado_elegibilidad === 'Suspendido') {
            $hallazgos[] = [
                'tipo' => self::SUSPENDIDO,
                'mensaje' => $this->nombre($jugador).' ('.$equipos[$equipoId].') está suspendido y aparece en la cédula.',
            ];
        }

        if ($jugador !== null) {
            $revisados[$jugador->id] = true;
        }
    }

    /**
     * Los goles de cada equipo deben sumar su marcador; un autogol cuenta para el rival.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, EventoPartido>  $eventos
     * @param  \Illuminate\Support\Collection<int|string, AlineacionPartido>  $porJugador
     * @param  array<int, string>  $equipos
     * @return list<array{tipo: string, mensaje: string}>
     */
    private function revisarMarcador(CedulaArbitral $cedula, $eventos, $porJugador, array $equipos): array
    {
        [$local, $visitante] = array_keys($equipos);
        $goles = [$local => 0, $visitante => 0];

        foreach ($eventos->where('tipo', 'Gol') as $gol) {
            $fila = $gol->jugador_id === null ? null : $porJugador->get($gol->jugador_id);

            if ($fila === null) {
                continue;
            }

            $equipoDelJugador = (int) $fila->equipo_id;
            $goles[$gol->autogol ? ($equipoDelJugador === $local ? $visitante : $local) : $equipoDelJugador]++;
        }

        $hallazgos = [];

        foreach ([$local => (int) $cedula->goles_local, $visitante => (int) $cedula->goles_visitante] as $equipoId => $marcador) {
            if ($goles[$equipoId] !== $marcador) {
                $hallazgos[] = [
                    'tipo' => self::MARCADOR,
                    'mensaje' => 'Los goles registrados de '.$equipos[$equipoId].' ('.$goles[$equipoId].') no suman su marcador ('.$marcador.').',
                ];
            }
        }

        return $hallazgos;
    }

    private function descripcion(EventoPartido $evento): string
    {
        $tipo = $evento->tipo === 'Gol' ? 'El gol' : 'La tarjeta '.mb_strtolower($evento->tipo);

        return $tipo.($evento->minuto !== null ? ' del minuto '.$evento->minuto : '');
    }

    private function nombre(?Jugador $jugador): string
    {
        return $jugador?->persona ? $jugador->persona->nombre.' '.$jugador->persona->apellidos : 'Un jugador';
    }
}
