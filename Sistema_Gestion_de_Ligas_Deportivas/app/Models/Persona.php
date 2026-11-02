<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\PersonaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Identidad única y reutilizable de una persona (HU-013, RF-03.2); la CURP la distingue
class Persona extends Model
{
    /** @use HasFactory<PersonaFactory> */
    use HasFactory;
    use Auditable;

    protected $table = 'personas';

    protected $fillable = ['nombre', 'apellidos', 'fecha_nacimiento', 'curp'];

    // La CURP no se copia a la bitácora ni a las respuestas JSON (RNF de datos personales)
    protected $hidden = ['curp'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
        ];
    }

    // Roles de la persona en cada temporada
    /** @return HasMany<PersonaRol, $this> */
    public function roles(): HasMany
    {
        return $this->hasMany(PersonaRol::class);
    }

    /**
     * Roles agrupados por temporada, para consultar qué papel tiene en cada una (HU-013, CA2).
     *
     * @return \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Collection<int, PersonaRol>>
     */
    public function rolesPorTemporada()
    {
        return $this->roles()
            ->with('temporada:id,nombre,estado')
            ->orderBy('temporada_id')
            ->orderBy('rol')
            ->get()
            ->groupBy('temporada_id');
    }
}
