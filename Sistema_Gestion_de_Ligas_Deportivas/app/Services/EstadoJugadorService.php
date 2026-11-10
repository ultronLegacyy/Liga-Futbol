<?php

namespace App\Services;

use App\Models\HistorialEstadoJugador;
use App\Models\Jugador;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Estados del jugador (HU-018, RF-04.3): solo los 4 estados oficiales, y cada cambio
 * queda en el historial con quién lo generó y por qué.
 *
 * Bloquea con el mismo orden que HU-017 (GestionJugadorService::bloquearContexto) para
 * no interbloquearse con una baja o una transferencia simultánea, y la Baja usa la misma
 * regla que HU-017 (GestionJugadorService::aplicarBaja).
 */
class EstadoJugadorService
{
    public function __construct(
        private AuditService $auditoria,
        private GestionJugadorService $gestion,
    ) {
    }

    public function cambiar(Jugador $jugador, string $estadoNuevo, string $motivo, User $usuario, ?string $fechaBaja = null): Jugador
    {
        if (! in_array($estadoNuevo, Jugador::ESTADOS_ELEGIBILIDAD, true)) {
            throw ValidationException::withMessages(['estado_elegibilidad' => 'El estado seleccionado no está permitido.']);
        }

        $jugadorId = (int) $jugador->getKey();
        $motivo = trim($motivo);

        // El trait Auditable de Jugador deja una sola entrada en la auditoría, con este motivo.
        return $this->auditoria->conMotivo('Cambio de estado del jugador: '.$motivo, function () use ($jugadorId, $estadoNuevo, $motivo, $usuario, $fechaBaja): Jugador {
            [$jugador, , $usuario] = $this->gestion->bloquearContexto($jugadorId, [], $usuario);
            Gate::forUser($usuario)->authorize('changeStatus', $jugador);

            if ($jugador->estado_elegibilidad === 'Baja') {
                throw ValidationException::withMessages(['jugador' => 'Una inscripción dada de baja no se puede reactivar ni cambiar de estado.']);
            }

            if ($jugador->estado_elegibilidad === $estadoNuevo) {
                throw ValidationException::withMessages(['estado_elegibilidad' => 'El jugador ya tiene el estado seleccionado.']);
            }

            if ($estadoNuevo === 'Baja') {
                if ($fechaBaja === null) {
                    throw ValidationException::withMessages(['fecha_baja' => 'La fecha de baja es obligatoria.']);
                }

                $this->gestion->aplicarBaja($jugador, $motivo, $fechaBaja, $usuario);

                return $jugador;
            }

            // HU-005, criterio 3: una temporada cerrada solo se consulta.
            $temporada = $this->gestion->temporadaDe($jugador);
            if ($temporada->estado !== 'activa') {
                throw ValidationException::withMessages(['jugador' => 'Solo se puede cambiar el estado de una inscripción de la temporada activa; la temporada '.$temporada->nombre.' solo se consulta.']);
            }

            $estadoAnterior = $jugador->estado_elegibilidad;
            $jugador->forceFill(['estado_elegibilidad' => $estadoNuevo])->save();
            HistorialEstadoJugador::registrar($jugador, $estadoAnterior, $estadoNuevo, $motivo, $usuario);

            return $jugador;
        });
    }
}
