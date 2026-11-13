<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Jornada de un grupo con programación, estados y publicación semanal (HU-027, RF-06.1; HU-028, RF-06.2; HU-030, RF-06.5)
class Jornada extends Model
{
    use HasFactory;
    use Auditable;

    // Estados del ciclo de vida de la jornada (HU-030 / RF-06.5 / SCRUM-199)
    public const ESTADO_BORRADOR = 'borrador';
    public const ESTADO_EN_REVISION = 'en_revision';
    public const ESTADO_APROBADA = 'aprobada';
    public const ESTADO_PUBLICADA = 'publicada';

    protected $table = 'jornadas';

    protected $fillable = [
        'grupo_id',
        'numero_jornada',
        'fecha_inicio',
        'fecha_fin',
        'equipo_descanso_id',
        'estado',
        'aprobado_por_id',
        'fecha_aprobacion',
        'fecha_publicacion',
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
            'fecha_aprobacion' => 'datetime',
            'fecha_publicacion' => 'datetime',
        ];
    }

    public function esBorrador(): bool
    {
        return $this->estado === self::ESTADO_BORRADOR;
    }

    public function esEnRevision(): bool
    {
        return $this->estado === self::ESTADO_EN_REVISION;
    }

    public function esAprobada(): bool
    {
        return $this->estado === self::ESTADO_APROBADA;
    }

    public function esPublicada(): bool
    {
        return $this->estado === self::ESTADO_PUBLICADA;
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

    /** @return BelongsTo<User, $this> */
    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por_id');
    }

    public function tieneDescanso(): bool
    {
        return $this->equipo_descanso_id !== null;
    }
}
