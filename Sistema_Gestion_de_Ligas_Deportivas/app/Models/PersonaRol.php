<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\PersonaRolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Papel de una persona en una temporada (HU-013, RF-03.2); no es el rol de la cuenta de acceso (Spatie)
class PersonaRol extends Model
{
    /** @use HasFactory<PersonaRolFactory> */
    use HasFactory;
    use Auditable;

    // Roles de RF-03.2
    public const ROLES = ['jugador', 'delegado', 'arbitro', 'directivo'];

    // Cómo se muestra cada rol en las vistas
    public const ETIQUETAS = [
        'jugador' => 'Jugador',
        'delegado' => 'Delegado',
        'arbitro' => 'Árbitro',
        'directivo' => 'Directivo',
    ];

    protected $table = 'persona_roles';

    protected $fillable = ['persona_id', 'rol', 'temporada_id'];

    /** @return BelongsTo<Persona, $this> */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /** @return BelongsTo<Temporada, $this> */
    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }
}
