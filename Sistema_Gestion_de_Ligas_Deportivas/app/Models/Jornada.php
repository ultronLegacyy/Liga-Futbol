<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Jornada de un grupo (RF-06.1). Modelo mínimo para HU-026; la programación de jornadas la completa HU-014
class Jornada extends Model
{
    protected $table = 'jornadas';

    protected $fillable = ['grupo_id', 'numero_jornada', 'fecha_inicio', 'fecha_fin'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    /** @return BelongsTo<Grupo, $this> */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    /** @return HasMany<Partido, $this> */
    public function partidos(): HasMany
    {
        return $this->hasMany(Partido::class);
    }
}
