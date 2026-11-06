<?php

namespace App\Services;

use App\Models\CargoFinanciero;
use App\Models\Equipo;
use App\Models\PersonaRol;
use App\Models\Temporada;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Registro de cuotas de inscripción y cargos de arbitraje y cancha por temporada (HU-050, RF-13.1).
// Solo crea cargos pendientes: los pagos y el estado del adeudo son de HU-051. La autorización le toca a quien lo llame
class RegistroCargoService
{
    private const MENSAJES = [
        'concepto.required' => 'El concepto es obligatorio.',
        'concepto.in' => 'El concepto debe ser inscripción, arbitraje o cancha.',
        'temporada_id.required' => 'La temporada es obligatoria.',
        'temporada_id.integer' => 'La temporada seleccionada no existe.',
        'temporada_id.exists' => 'La temporada seleccionada no existe.',
        'club_id.required' => 'El club es obligatorio.',
        'club_id.integer' => 'El club seleccionado no existe.',
        'club_id.exists' => 'El club seleccionado no existe.',
        'equipo_id.integer' => 'El equipo seleccionado no existe.',
        'equipo_id.exists' => 'El equipo seleccionado no existe.',
        'persona_id.integer' => 'La persona seleccionada no existe.',
        'persona_id.exists' => 'La persona seleccionada no existe.',
        'monto.required' => 'El monto es obligatorio.',
        'monto.numeric' => 'El monto debe ser un número.',
        'monto.gt' => 'El monto debe ser mayor que cero.',
        'monto.decimal' => 'El monto admite como máximo dos decimales.',
        'monto.max' => 'El monto no puede ser mayor que 99,999,999.99.',
        'vencimiento.required' => 'La fecha de vencimiento es obligatoria.',
        'vencimiento.date' => 'La fecha de vencimiento no es válida.',
    ];

    /**
     * Registra un cargo pendiente ligado a su temporada (criterio 1) y a su club o equipo (criterio 2).
     *
     * @param  array<string, mixed>  $datos
     *
     * @throws ValidationException
     */
    public function registrar(array $datos): CargoFinanciero
    {
        // El estado no se recibe de fuera: todo cargo nace pendiente
        $validos = Validator::make($datos, [
            'concepto' => ['bail', 'required', Rule::in(CargoFinanciero::CONCEPTOS_REGISTRABLES)],
            'temporada_id' => ['bail', 'required', 'integer', 'exists:temporadas,id'],
            'club_id' => ['bail', 'required', 'integer', 'exists:clubes,id'],
            'equipo_id' => ['bail', 'nullable', 'integer', 'exists:equipos,id'],
            'persona_id' => ['bail', 'nullable', 'integer', 'exists:personas,id'],
            // decimal(10,2): hasta 99,999,999.99 y dos decimales
            'monto' => ['bail', 'required', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
            'vencimiento' => ['bail', 'required', 'date'],
        ], self::MENSAJES)->validate();

        $equipoId = isset($validos['equipo_id']) ? (int) $validos['equipo_id'] : null;
        $personaId = isset($validos['persona_id']) ? (int) $validos['persona_id'] : null;

        // Arbitraje y cancha los genera un club o un equipo, nunca una persona
        if ($validos['concepto'] !== 'Inscripcion' && $personaId !== null) {
            throw ValidationException::withMessages([
                'persona_id' => 'Un cargo de arbitraje o de cancha se liga al club o al equipo, no a una persona.',
            ]);
        }

        return DB::transaction(function () use ($validos, $equipoId, $personaId): CargoFinanciero {
            // Lectura compartida: la temporada no se cierra mientras se registra el cargo
            $temporada = Temporada::query()
                ->whereKey($validos['temporada_id'])
                ->sharedLock()
                ->firstOrFail();

            if ($temporada->estado === 'cerrada') {
                throw ValidationException::withMessages([
                    'temporada_id' => 'La temporada está cerrada: sus cargos solo se consultan (RF-01.4).',
                ]);
            }

            $clubId = (int) $validos['club_id'];

            if ($equipoId !== null) {
                $this->validarEquipo($equipoId, $clubId, $temporada);
            }

            // Cuota de un jugador: la persona debe tener ese rol en la temporada (HU-013, D-037)
            if ($personaId !== null) {
                $this->validarJugador($personaId, $temporada);
            }

            return CargoFinanciero::create([
                'club_id' => $clubId,
                'equipo_id' => $equipoId,
                'persona_id' => $personaId,
                'temporada_id' => $temporada->id,
                'concepto' => $validos['concepto'],
                'monto' => $validos['monto'],
                'vencimiento' => $validos['vencimiento'],
                'estado' => 'Pendiente',
            ]);
        });
    }

    // El equipo debe ser del club al que se carga y de la temporada, a través de su grupo
    private function validarEquipo(int $equipoId, int $clubId, Temporada $temporada): void
    {
        $equipo = Equipo::query()->with('grupo:id,temporada_id')->findOrFail($equipoId);

        if ((int) $equipo->club_id !== $clubId) {
            throw ValidationException::withMessages([
                'equipo_id' => 'El equipo no pertenece al club seleccionado.',
            ]);
        }

        if ((int) $equipo->grupo?->temporada_id !== (int) $temporada->id) {
            throw ValidationException::withMessages([
                'equipo_id' => 'El equipo no pertenece a la temporada seleccionada.',
            ]);
        }
    }

    private function validarJugador(int $personaId, Temporada $temporada): void
    {
        $esJugador = PersonaRol::query()
            ->where('persona_id', $personaId)
            ->where('rol', 'jugador')
            ->where('temporada_id', $temporada->id)
            ->exists();

        if (! $esJugador) {
            throw ValidationException::withMessages([
                'persona_id' => 'La persona no tiene rol de jugador en la temporada seleccionada.',
            ]);
        }
    }
}
