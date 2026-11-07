<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\PersonaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Identidad única y reutilizable de una persona (HU-013, RF-03.2); la CURP la distingue.
 *
 * @property int $id
 * @property string $nombre
 * @property string $apellidos
 * @property \Illuminate\Support\Carbon $fecha_nacimiento
 * @property string $curp
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
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

    // Inscripciones de la persona como jugador en equipos (HU-012)
    /** @return HasMany<Jugador, $this> */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(Jugador::class, 'persona_id');
    }

    /** @return HasMany<DocumentoPersona, $this> */
    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoPersona::class, 'persona_id');
    }

    // Registro en el padrón de árbitros, si es árbitro colegiado (HU-026)
    /** @return HasOne<Arbitro, $this> */
    public function arbitro(): HasOne
    {
        return $this->hasOne(Arbitro::class);
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

    // Cuotas y cargos financieros (HU-050)
    /** @return HasMany<CargoFinanciero, $this> */
    public function cargos(): HasMany
    {
        return $this->hasMany(CargoFinanciero::class);
    }
}
