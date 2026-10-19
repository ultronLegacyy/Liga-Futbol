<?php

namespace App\Models;

use Database\Factories\TemporadaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
