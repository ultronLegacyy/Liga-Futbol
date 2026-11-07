<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $persona_id
 * @property int $equipo_id
 * @property int|null $dorsal
 * @property string $estado_elegibilidad
 * @property string|null $motivo_baja
 * @property \Illuminate\Support\Carbon|null $fecha_baja
 */

class Jugador extends Model
{
    use Auditable;

    protected $table = 'jugadores_equipos';

    protected $fillable = ['persona_id', 'equipo_id', 'dorsal', 'estado_elegibilidad', 'motivo_baja', 'fecha_baja'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['fecha_baja' => 'date'];
    }

    /** @return BelongsTo<Persona, $this> */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    /** @return BelongsTo<Equipo, $this> */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }
}
