<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Jugador extends Model
{
    use Auditable;

    protected $table = 'jugadores_equipos';

    protected $fillable = ['persona_id', 'equipo_id', 'dorsal', 'estado_elegibilidad'];

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

    /** @return HasMany<HistorialTransferenciaJugador, $this> */
    public function transferenciasOrigen(): HasMany
    {
        return $this->hasMany(HistorialTransferenciaJugador::class, 'jugador_origen_id');
    }

    /** @return HasMany<HistorialTransferenciaJugador, $this> */
    public function transferenciasDestino(): HasMany
    {
        return $this->hasMany(HistorialTransferenciaJugador::class, 'jugador_destino_id');
    }
}
