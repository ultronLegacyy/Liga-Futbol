<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cambio de estado de una inscripción: quién lo generó, por qué y cuándo (HU-018, criterio 2).
 *
 * No usa Auditable: el cambio ya queda en la auditoría por la inscripción (Jugador), con
 * el motivo de AuditService::conMotivo(), y así cada operación deja una sola entrada.
 *
 * @property int $id
 * @property int $persona_id
 * @property int|null $jugador_id
 * @property string|null $estado_anterior
 * @property string $estado_nuevo
 * @property string $motivo
 * @property int $usuario_id
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class HistorialEstadoJugador extends Model
{
    protected $table = 'historial_estados_jugador';

    protected $fillable = [
        'persona_id',
        'jugador_id',
        'estado_anterior',
        'estado_nuevo',
        'motivo',
        'usuario_id',
    ];

    /** Registra un cambio de estado. Debe llamarse dentro de la transacción de la operación. */
    public static function registrar(Jugador $jugador, ?string $estadoAnterior, string $estadoNuevo, string $motivo, User $usuario): self
    {
        return self::query()->create([
            'persona_id' => $jugador->persona_id,
            'jugador_id' => $jugador->getKey(),
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'motivo' => trim($motivo),
            'usuario_id' => $usuario->getKey(),
        ]);
    }

    /** @return BelongsTo<Jugador, $this> */
    public function jugador(): BelongsTo
    {
        return $this->belongsTo(Jugador::class, 'jugador_id');
    }

    /** @return BelongsTo<Persona, $this> */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
