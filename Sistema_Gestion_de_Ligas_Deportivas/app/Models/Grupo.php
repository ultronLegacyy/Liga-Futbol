<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\GrupoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Subdivisión de una categoría dentro de su temporada (RF-02.2)
class Grupo extends Model
{
    /** @use HasFactory<GrupoFactory> */
    use HasFactory;
    use Auditable;

    protected $table = 'grupos';

    // Formatos de competencia documentados en la ERS para un grupo (glosario: Torneo de Liga); se amplía cuando la Liga confirme otros
    public const FORMATOS = [
        'todos_contra_todos' => 'Todos contra todos',
    ];

    // Criterios de clasificación del grupo (HU-008): lugares que pasan a la liguilla y a la de consolación
    public const CRITERIOS = [
        'clasifican_liguilla' => 'Equipos que clasifican a la liguilla',
        'clasifican_consolacion' => 'Equipos que clasifican a la liguilla de consolación',
    ];

    protected $fillable = ['categoria_id', 'temporada_id', 'nombre', 'formato_competencia', 'criterios_clasificacion'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'criterios_clasificacion' => 'array',
        ];
    }

    // La temporada cerrada solo se consulta (RF-01.4)
    public function estaCerrado(): bool
    {
        return $this->temporada?->estado === 'cerrada';
    }

    /** @return BelongsTo<Categoria, $this> */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /** @return BelongsTo<Temporada, $this> */
    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    /** @return HasMany<Equipo, $this> */
    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class, 'grupo_id');
    }
}
