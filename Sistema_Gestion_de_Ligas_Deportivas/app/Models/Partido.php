<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

// Partido de una jornada (RF-06.1). HU-025 usa su cancha y su horario, HU-026 su árbitro; HU-029 maneja estados/reprogramación, HU-031 inmutabilidad
class Partido extends Model
{
    use HasFactory;
    use Auditable;

    // Estados del ciclo de vida del partido (HU-029 / RF-06.3)
    public const ESTADO_BORRADOR = 'borrador';
    public const ESTADO_PROGRAMADO = 'programado';
    public const ESTADO_CONFIRMADO = 'confirmado';
    public const ESTADO_EN_JUEGO = 'en juego';
    public const ESTADO_FINALIZADO = 'finalizado';
    public const ESTADO_SUSPENDIDO = 'suspendido';
    public const ESTADO_CANCELADO = 'cancelado';
    public const ESTADO_REPROGRAMADO = 'reprogramado';

    // Estados de un partido programado que admiten asignar árbitro (RF-06.3)
    public const ESTADOS_ASIGNABLES = [self::ESTADO_PROGRAMADO, self::ESTADO_CONFIRMADO];

    // Matriz de transiciones válidas según el flujo oficial (HU-029 Criterio 1 / SCRUM-195)
    public const TRANSICIONES_VALIDAS = [
        self::ESTADO_BORRADOR => [self::ESTADO_PROGRAMADO, self::ESTADO_CANCELADO],
        self::ESTADO_PROGRAMADO => [self::ESTADO_CONFIRMADO, self::ESTADO_REPROGRAMADO, self::ESTADO_CANCELADO],
        self::ESTADO_CONFIRMADO => [self::ESTADO_EN_JUEGO, self::ESTADO_REPROGRAMADO, self::ESTADO_SUSPENDIDO, self::ESTADO_CANCELADO],
        self::ESTADO_EN_JUEGO => [self::ESTADO_FINALIZADO, self::ESTADO_SUSPENDIDO],
        self::ESTADO_SUSPENDIDO => [self::ESTADO_REPROGRAMADO, self::ESTADO_FINALIZADO, self::ESTADO_CANCELADO],
        self::ESTADO_REPROGRAMADO => [self::ESTADO_PROGRAMADO, self::ESTADO_CONFIRMADO],
        self::ESTADO_FINALIZADO => [],
        self::ESTADO_CANCELADO => [],
    ];

    protected $table = 'partidos';

    protected $fillable = [
        'jornada_id',
        'equipo_local_id',
        'equipo_visitante_id',
        'cancha_id',
        'arbitro_id',
        'fecha_partido',
        'hora_partido',
        'estado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_partido' => 'date:Y-m-d',
        ];
    }

    /**
     * Evalúa si una transición de estado es válida según el flujo del sistema (HU-029 CA1).
     */
    public function puedeTransicionarA(string $nuevoEstado): bool
    {
        if ($this->estado === $nuevoEstado) {
            return false;
        }

        $permitidos = self::TRANSICIONES_VALIDAS[$this->estado];

        return in_array($nuevoEstado, $permitidos, true);
    }

    /**
     * Retorna los estados a los que puede transicionar actualmente.
     *
     * @return array<string>
     */
    public function transicionesPermitidas(): array
    {
        return self::TRANSICIONES_VALIDAS[$this->estado];
    }

    /**
     * Determina si el partido permanece en estado borrador.
     */
    public function esBorrador(): bool
    {
        return $this->estado === self::ESTADO_BORRADOR;
    }

    /**
     * Evalúa si el partido es eliminable físicamente (HU-031 / RF-06.6 / SCRUM-203).
     * Regla estricta: solo se eliminan partidos en borrador sin historial ni cédula.
     */
    public function esEliminable(): bool
    {
        if ($this->estado !== self::ESTADO_BORRADOR) {
            return false;
        }

        // Si tiene cédula arbitral registrada, es intocable (HU-031 CA1)
        if ($this->cedula()->exists()) {
            return false;
        }

        // Si tiene bitácora de reprogramaciones
        if ($this->reprogramaciones()->exists()) {
            return false;
        }

        // Si tiene expedientes disciplinarios asociados
        if (DB::table('expedientes_disciplinarios')->where('partido_id', $this->id)->exists()) {
            return false;
        }

        return true;
    }

    /** @return BelongsTo<Jornada, $this> */
    public function jornada(): BelongsTo
    {
        return $this->belongsTo(Jornada::class);
    }

    /** @return BelongsTo<Equipo, $this> */
    public function local(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_local_id');
    }

    /** @return BelongsTo<Equipo, $this> */
    public function equipoLocal(): BelongsTo
    {
        return $this->local();
    }

    /** @return BelongsTo<Equipo, $this> */
    public function visitante(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_visitante_id');
    }

    /** @return BelongsTo<Equipo, $this> */
    public function equipoVisitante(): BelongsTo
    {
        return $this->visitante();
    }

    /** @return BelongsTo<Cancha, $this> */
    public function cancha(): BelongsTo
    {
        return $this->belongsTo(Cancha::class, 'cancha_id');
    }

    /** @return BelongsTo<Arbitro, $this> */
    public function arbitro(): BelongsTo
    {
        return $this->belongsTo(Arbitro::class);
    }

    /** @return HasMany<ReprogramacionPartido, $this> */
    public function reprogramaciones(): HasMany
    {
        return $this->hasMany(ReprogramacionPartido::class, 'partido_id')->latest();
    }

    /** @return HasOne<CedulaArbitral, $this> */
    public function cedula(): HasOne
    {
        return $this->hasOne(CedulaArbitral::class, 'partido_id');
    }

    /**
     * Partidos que ocupan su cancha: tienen horario y no están suspendidos ni cancelados (RF-05.3).
     *
     * @param  Builder<Partido>  $query
     * @return Builder<Partido>
     */
    public function scopeQueOcupanCancha(Builder $query): Builder
    {
        return $query->whereNotNull('fecha_partido')
            ->whereNotNull('hora_partido')
            ->whereNotIn('estado', [self::ESTADO_SUSPENDIDO, self::ESTADO_CANCELADO]);
    }
}
