<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipo extends Model
{
    use HasFactory;
    use Auditable;

    // Nombre de la tabla de persistencia relacional
    protected $table = 'equipos';

    // Atributos asignables de forma masiva
    protected $fillable = [
        'club_id',
        'temporada_id',
        'grupo_id',
        'nombre',
        'partidos_default_acumulados',
    ];

    // Relación muchos a uno con el club propietario del equipo
    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'club_id');
    }

    /** @return BelongsTo<Grupo, $this> */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    /** @return BelongsTo<Temporada, $this> */
    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class, 'temporada_id');
    }
    /** @return HasMany<Jugador, $this> */
    public function jugadores(): HasMany
    {
        return $this->hasMany(Jugador::class, 'equipo_id');
    }

    // Cuotas y cargos financieros (HU-050)
    /** @return HasMany<CargoFinanciero, $this> */
    public function cargos(): HasMany
    {
        return $this->hasMany(CargoFinanciero::class);
    }
}
