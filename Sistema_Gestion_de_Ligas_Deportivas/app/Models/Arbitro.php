<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Padrón de árbitros colegiados (HU-026, RF-03.2): cada árbitro es una persona con rol de árbitro (HU-013)
class Arbitro extends Model
{
    use Auditable;

    // «Activo» es la disponibilidad que hoy registra la Liga; un árbitro inactivo no se asigna
    public const ESTADOS = ['Activo', 'Inactivo'];

    protected $table = 'arbitros';

    protected $fillable = ['persona_id', 'colegio_pertenencia', 'tarifa_por_partido', 'estado'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tarifa_por_partido' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Persona, $this> */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    // Partidos a los que está asignado
    /** @return HasMany<Partido, $this> */
    public function partidos(): HasMany
    {
        return $this->hasMany(Partido::class);
    }

    // Puede asignarse a un partido de esa temporada: está activo y tiene rol de árbitro en ella (CA1)
    public function disponibleEn(Temporada $temporada): bool
    {
        return $this->estado === 'Activo'
            && PersonaRol::query()
                ->where('persona_id', $this->persona_id)
                ->where('rol', 'arbitro')
                ->where('temporada_id', $temporada->id)
                ->exists();
    }
}
