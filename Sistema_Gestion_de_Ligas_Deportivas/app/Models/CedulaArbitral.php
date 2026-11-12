<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cédula arbitral de un partido (HU-036, RF-07.1): alineación, marcador, eventos e incidencias.
 * La reutilizan HU-037 (captura desde papel), HU-040, HU-041, HU-044 y HU-075 (firmas y cierre).
 *
 * @property int $id
 * @property int $partido_id
 * @property string $estado
 * @property int $goles_local
 * @property int $goles_visitante
 * @property \Illuminate\Support\Carbon|null $cerrada_en
 */
class CedulaArbitral extends Model
{
    use Auditable;

    public const ABIERTA = 'abierta';

    public const CERRADA = 'cerrada';

    protected $table = 'cedulas_arbitrales';

    protected $fillable = [
        'partido_id',
        'estado',
        'goles_local',
        'goles_visitante',
        'informe_arbitral',
        'firma_capitan_local',
        'firma_capitan_visitante',
        'cerrada_por_id',
        'cerrada_en',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'goles_local' => 'integer',
            'goles_visitante' => 'integer',
            'cerrada_en' => 'datetime',
        ];
    }

    // Una cédula cerrada ya no se edita (HU-075)
    public function estaCerrada(): bool
    {
        return $this->estado === self::CERRADA;
    }

    /** @return BelongsTo<Partido, $this> */
    public function partido(): BelongsTo
    {
        return $this->belongsTo(Partido::class);
    }

    /** @return BelongsTo<User, $this> */
    public function cerradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrada_por_id');
    }

    /** @return HasMany<AlineacionPartido, $this> */
    public function alineaciones(): HasMany
    {
        return $this->hasMany(AlineacionPartido::class, 'cedula_id');
    }

    /** @return HasMany<EventoPartido, $this> */
    public function eventos(): HasMany
    {
        return $this->hasMany(EventoPartido::class, 'cedula_id');
    }

    /** @return HasMany<IncidenciaPartido, $this> */
    public function incidencias(): HasMany
    {
        return $this->hasMany(IncidenciaPartido::class, 'cedula_id');
    }
}
