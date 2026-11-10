<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\PersonaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Identidad única y reutilizable de una persona (HU-013, RF-03.2); la CURP la distingue.
 *
 * @property int $id
 * @property string $nombre
 * @property string $apellidos
 * @property Carbon $fecha_nacimiento
 * @property string $curp
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Persona extends Model
{
    use Auditable;

    /** @use HasFactory<PersonaFactory> */
    use HasFactory;

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
     * También es su historial (HU-015, CA2): de la temporada más reciente a la más antigua.
     *
     * @return Collection<int, \Illuminate\Database\Eloquent\Collection<int, PersonaRol>>
     */
    public function rolesPorTemporada()
    {
        return $this->roles()
            ->select('persona_roles.*')
            ->join('temporadas', 'temporadas.id', '=', 'persona_roles.temporada_id')
            ->with('temporada:id,nombre,fecha_inicio,fecha_fin,estado')
            ->orderByDesc('temporadas.fecha_inicio')
            ->orderByDesc('temporadas.id')
            ->orderBy('persona_roles.rol')
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
