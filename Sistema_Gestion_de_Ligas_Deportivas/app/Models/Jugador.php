<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
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

    /** @return HasMany<HistorialTransferenciaJugador, $this> */
    public function transferenciasOrigen(): HasMany
    {
        return $this->hasMany(HistorialTransferenciaJugador::class, 'jugador_origen_id');
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

    /** @return HasManyThrough<DocumentoPersona, Persona, $this> */
    public function documentos(): HasManyThrough
    {
        return $this->hasManyThrough(DocumentoPersona::class, Persona::class, 'id', 'persona_id', 'persona_id', 'id');
    }

}
