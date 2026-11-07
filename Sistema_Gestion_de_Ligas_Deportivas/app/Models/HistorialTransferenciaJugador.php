<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialTransferenciaJugador extends Model
{
    protected $table = 'historial_transferencias_jugador';

    /** @var list<string> */
    protected $fillable = [
        'persona_id',
        'jugador_origen_id',
        'jugador_destino_id',
        'club_origen_id',
        'club_destino_id',
        'equipo_origen_id',
        'equipo_destino_id',
        'temporada_id',
        'transferido_por_usuario_id',
    ];

    /** @return BelongsTo<Persona, $this> */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    /** @return BelongsTo<Jugador, $this> */
    public function inscripcionOrigen(): BelongsTo
    {
        return $this->belongsTo(Jugador::class, 'jugador_origen_id');
    }

    /** @return BelongsTo<Jugador, $this> */
    public function inscripcionDestino(): BelongsTo
    {
        return $this->belongsTo(Jugador::class, 'jugador_destino_id');
    }

    /** @return BelongsTo<Club, $this> */
    public function clubOrigen(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'club_origen_id');
    }

    /** @return BelongsTo<Club, $this> */
    public function clubDestino(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'club_destino_id');
    }

    /** @return BelongsTo<Equipo, $this> */
    public function equipoOrigen(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_origen_id');
    }

    /** @return BelongsTo<Equipo, $this> */
    public function equipoDestino(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_destino_id');
    }

    /** @return BelongsTo<Temporada, $this> */
    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class, 'temporada_id');
    }

    /** @return BelongsTo<User, $this> */
    public function transferidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferido_por_usuario_id');
    }
}
