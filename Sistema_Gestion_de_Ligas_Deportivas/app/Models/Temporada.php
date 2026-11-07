<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\TemporadaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Temporada extends Model
{
    /** @use HasFactory<TemporadaFactory> */
    use HasFactory;
    use Auditable;
    protected $table = 'temporadas';
    protected $fillable = ['liga_id', 'nombre', 'fecha_inicio', 'fecha_fin', 'estado'];

    // Convocatorias publicadas para la temporada
    /** @return HasMany<Convocatoria, $this> */
    public function convocatorias(): HasMany
    {
        return $this->hasMany(Convocatoria::class);
    }

    // Categorías de la temporada (RF-02.1)
    /** @return HasMany<Categoria, $this> */
    public function categorias(): HasMany
    {
        return $this->hasMany(Categoria::class);
    }

    // Grupos de todas las categorías de la temporada (RF-02.2)
    /** @return HasMany<Grupo, $this> */
    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class);
    }

    /** @return HasMany<Equipo, $this> */
    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class);
    }

    /** @return HasManyThrough<Jugador, Equipo, $this> */
    public function jugadores(): HasManyThrough
    {
        return $this->hasManyThrough(Jugador::class, Equipo::class, 'temporada_id', 'equipo_id', 'id', 'id');
    }

    /** @return HasMany<TarjetonDigital, $this> */
    public function tarjetonesDigitales(): HasMany
    {
        return $this->hasMany(TarjetonDigital::class);
    }

    // Cuotas y cargos financieros (HU-050)
    /** @return HasMany<CargoFinanciero, $this> */
    public function cargos(): HasMany
    {
        return $this->hasMany(CargoFinanciero::class);
    }
}
