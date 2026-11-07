<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Jornada de un grupo con programación de partidos y equipo en descanso (HU-027, RF-06.1; HU-028, RF-06.2)
class Jornada extends Model
{
    use HasFactory;
    use Auditable;

    protected $table = 'jornadas';

    protected $fillable = [
        'grupo_id',
        'numero_jornada',
        'fecha_inicio',
        'fecha_fin',
        'equipo_descanso_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numero_jornada' => 'integer',
            'fecha_inicio' => 'date:Y-m-d',
            'fecha_fin' => 'date:Y-m-d',
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

    /** @return BelongsTo<Equipo, $this> */
    public function equipoDescanso(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_descanso_id');
    }

    public function tieneDescanso(): bool
    {
        return $this->equipo_descanso_id !== null;
    }
}
