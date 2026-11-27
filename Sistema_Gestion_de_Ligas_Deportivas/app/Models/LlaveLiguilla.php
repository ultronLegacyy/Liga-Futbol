<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cruce de liguilla generado desde la tabla final de un grupo (HU-055, RF-11.1).
 * Guarda el lugar de cada equipo y las reglas con que se generó (RNF-36).
 * HU-056: se juega en un solo partido; avanza el ganador o, si empatan, el mejor ubicado (decisión 3 de Daniel).
 * Sin visitante es un pase directo de la primera ronda: el local avanza sin jugar.
 *
 * @property int $id
 * @property int $fase_id
 * @property int|null $grupo_id
 * @property int|null $orden
 * @property int $ronda
 * @property int|null $partido_id
 * @property int $equipo_local_id
 * @property int|null $lugar_local
 * @property int|null $equipo_visitante_id
 * @property int|null $lugar_visitante
 * @property int $marcador_global_local
 * @property int $marcador_global_visitante
 * @property int|null $avanza_equipo_id
 * @property int|null $regla_temporada_id
 * @property int|null $regla_version
 * @property array<string, int>|null $criterios_clasificacion
 * @property int|null $generada_por_id
 */
class LlaveLiguilla extends Model
{
    use Auditable;

    protected $table = 'llaves_liguilla';

    protected $fillable = [
        'fase_id',
        'grupo_id',
        'orden',
        'ronda',
        'partido_id',
        'equipo_local_id',
        'lugar_local',
        'equipo_visitante_id',
        'lugar_visitante',
        'marcador_global_local',
        'marcador_global_visitante',
        'avanza_equipo_id',
        'regla_temporada_id',
        'regla_version',
        'criterios_clasificacion',
        'generada_por_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'ronda' => 'integer',
            'marcador_global_local' => 'integer',
            'marcador_global_visitante' => 'integer',
            'lugar_local' => 'integer',
            'lugar_visitante' => 'integer',
            'regla_version' => 'integer',
            'criterios_clasificacion' => 'array',
        ];
    }

    /** @return BelongsTo<FaseTorneo, $this> */
    public function fase(): BelongsTo
    {
        return $this->belongsTo(FaseTorneo::class, 'fase_id');
    }

    /** @return BelongsTo<Grupo, $this> */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    /** @return BelongsTo<Equipo, $this> */
    public function local(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_local_id');
    }

    /** @return BelongsTo<Equipo, $this> */
    public function visitante(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_visitante_id');
    }

    /** @return BelongsTo<ReglaTemporada, $this> */
    public function regla(): BelongsTo
    {
        return $this->belongsTo(ReglaTemporada::class, 'regla_temporada_id');
    }

    /** @return BelongsTo<User, $this> */
    public function generadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generada_por_id');
    }

    /** @return BelongsTo<Partido, $this> */
    public function partido(): BelongsTo
    {
        return $this->belongsTo(Partido::class);
    }

    /** @return BelongsTo<Equipo, $this> */
    public function avanza(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'avanza_equipo_id');
    }

    public function esPaseDirecto(): bool
    {
        return $this->equipo_visitante_id === null;
    }

    public function estaResuelta(): bool
    {
        return $this->avanza_equipo_id !== null;
    }

    // El equipo que perdió la llave ya resuelta queda eliminado (criterio 1)
    public function eliminadoId(): ?int
    {
        if (! $this->estaResuelta() || $this->esPaseDirecto()) {
            return null;
        }

        return $this->avanza_equipo_id === $this->equipo_local_id ? $this->equipo_visitante_id : $this->equipo_local_id;
    }

    // Lugar en la tabla general de la fase regular del equipo que avanzó; decide el empate y la siguiente ronda
    public function lugarDelQueAvanza(): ?int
    {
        return $this->avanza_equipo_id === $this->equipo_local_id ? $this->lugar_local : $this->lugar_visitante;
    }
}
