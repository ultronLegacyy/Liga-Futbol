<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Registro de sanciones disciplinarias dictaminadas por el Tribunal de Penas (HU-045, HU-046, HU-047).
 *
 * @property int $id
 * @property int $expediente_id
 * @property int|null $persona_id
 * @property int|null $equipo_id
 * @property int|null $articulo_id
 * @property string $tipo_sancion
 * @property string|null $descripcion_sancion
 * @property string|null $motivo_resolucion
 * @property int $partidos_suspendidos
 * @property int $partidos_cumplidos
 * @property \Illuminate\Support\Carbon|null $fecha_inicio
 * @property \Illuminate\Support\Carbon|null $fecha_fin
 * @property int|null $partido_suspension_hasta_id
 * @property float|null $monto_multa
 * @property string $estado
 * @property bool $publicada_en_boletin
 * @property int|null $validado_por_usuario_id
 * @property \Illuminate\Support\Carbon|null $fecha_validacion
 * @property int|null $autorizado_por_usuario_id
 * @property string|null $autorizado_por_cargo
 * @property \Illuminate\Support\Carbon|null $fecha_publicacion
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read ExpedienteDisciplinario $expediente
 * @property-read Persona|null $persona
 * @property-read Equipo|null $equipo
 * @property-read CatalogoArticulo|null $articulo
 * @property-read Partido|null $partidoHasta
 * @property-read User|null $validadoPor
 * @property-read User|null $autorizadoPor
 */
class Sancion extends Model
{
    use Auditable;
    use HasFactory;

    public const ZONA_HORARIA = 'America/Mexico_City';

    public const ESTADO_VIGENTE = 'Vigente';

    public const ESTADO_CUMPLIDA = 'Cumplida';

    public const ESTADO_CANCELADA = 'Cancelada';

    public const TIPO_PARTIDOS = 'partidos_suspension';

    public const TIPO_TEMPORAL = 'suspension_temporal';

    public const TIPO_MULTA = 'multa_economica';

    public const TIPO_AMONESTACION = 'amonestacion';

    /** @var list<string> */
    public const TIPOS_PERMITIDOS = [
        self::TIPO_PARTIDOS,
        self::TIPO_TEMPORAL,
        self::TIPO_MULTA,
        self::TIPO_AMONESTACION,
    ];

    /** @var list<string> */
    public const TIPOS_INHABILITANTES = [
        self::TIPO_PARTIDOS,
        self::TIPO_TEMPORAL,
    ];

    protected $table = 'sanciones';

    protected $fillable = [
        'expediente_id',
        'persona_id',
        'equipo_id',
        'articulo_id',
        'tipo_sancion',
        'descripcion_sancion',
        'motivo_resolucion',
        'partidos_suspendidos',
        'partidos_cumplidos',
        'fecha_inicio',
        'fecha_fin',
        'partido_suspension_hasta_id',
        'monto_multa',
        'estado',
        'publicada_en_boletin',
        'validado_por_usuario_id',
        'fecha_validacion',
        'autorizado_por_usuario_id',
        'autorizado_por_cargo',
        'fecha_publicacion',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'partidos_suspendidos' => 'integer',
            'partidos_cumplidos' => 'integer',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'monto_multa' => 'decimal:2',
            'publicada_en_boletin' => 'boolean',
            'fecha_validacion' => 'datetime',
            'fecha_publicacion' => 'datetime',
        ];
    }

    public function estaVigente(): bool
    {
        return $this->estado === self::ESTADO_VIGENTE;
    }

    public function estaCumplida(): bool
    {
        return $this->estado === self::ESTADO_CUMPLIDA;
    }

    public function estaValidada(): bool
    {
        return $this->validado_por_usuario_id !== null && $this->fecha_validacion !== null;
    }

    public function partidosRestantes(): int
    {
        return max(0, $this->partidos_suspendidos - $this->partidos_cumplidos);
    }

    /**
     * Explicación de la sanción y regla originaria requerida por IU-05 (RF-10.4).
     */
    public function explicacionRegla(): string
    {
        $expediente = $this->expediente;
        $folio = $expediente->folio ?? "EXP-{$this->expediente_id}";
        $articuloCodigo = $this->articulo->codigo ?? ($expediente->articulo->codigo ?? 'Reglamento Disciplinario');
        $articuloDesc = $this->articulo->descripcion ?? ($expediente->articulo->descripcion ?? 'Infracción disciplinaria');

        $detalleSancion = match ($this->tipo_sancion) {
            self::TIPO_PARTIDOS => "{$this->partidos_suspendidos} partido(s) de suspensión ({$this->partidosRestantes()} pendiente(s))",
            self::TIPO_TEMPORAL => 'Suspensión temporal hasta '.($this->fecha_fin?->format('d/m/Y') ?? 'fecha por determinar'),
            default => $this->descripcion_sancion ?? 'Sanción disciplinaria vigente',
        };

        return "Expediente {$folio} | Regla: {$articuloCodigo} ({$articuloDesc}) | Dictamen: {$detalleSancion}";
    }

    /**
     * Filtra únicamente sanciones vigentes que inhabilitan la participación deportiva en la fecha dada (HU-047 / RF-10.4).
     * Excluye multas económicas y amonestaciones, y valida la ventana temporal en hora de Ciudad de México.
     *
     * @param  Builder<Sancion>  $query
     * @return Builder<Sancion>
     */
    public function scopeInhabilitantes(Builder $query, ?string $fechaReferencia = null): Builder
    {
        $fecha = $fechaReferencia !== null
            ? Carbon::parse($fechaReferencia, self::ZONA_HORARIA)->toDateString()
            : Carbon::today(self::ZONA_HORARIA)->toDateString();

        return $query->where('estado', self::ESTADO_VIGENTE)
            ->where(function (Builder $sub) use ($fecha): void {
                $sub->where(function (Builder $q): void {
                    $q->where('tipo_sancion', self::TIPO_PARTIDOS)
                        ->whereColumn('partidos_cumplidos', '<', 'partidos_suspendidos');
                })
                    ->orWhere(function (Builder $q) use ($fecha): void {
                        $q->where('tipo_sancion', self::TIPO_TEMPORAL)
                            ->where(function (Builder $f) use ($fecha): void {
                                $f->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', $fecha);
                            })
                            ->where(function (Builder $f) use ($fecha): void {
                                $f->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $fecha);
                            });
                    });
            });
    }

    /**
     * @param  Builder<Sancion>  $query
     * @return Builder<Sancion>
     */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_VIGENTE);
    }

    /**
     * Filtra únicamente sanciones validadas y formalmente publicadas en el boletín (RNF-44).
     *
     * @param  Builder<Sancion>  $query
     * @return Builder<Sancion>
     */
    public function scopePublicadasEnBoletin(Builder $query): Builder
    {
        return $query->where('publicada_en_boletin', true)
            ->whereNotNull('validado_por_usuario_id');
    }

    /**
     * @return BelongsTo<ExpedienteDisciplinario, $this>
     */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(ExpedienteDisciplinario::class, 'expediente_id');
    }

    /**
     * @return BelongsTo<Persona, $this>
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    /**
     * @return BelongsTo<Equipo, $this>
     */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    /**
     * @return BelongsTo<CatalogoArticulo, $this>
     */
    public function articulo(): BelongsTo
    {
        return $this->belongsTo(CatalogoArticulo::class, 'articulo_id');
    }

    /**
     * @return BelongsTo<Partido, $this>
     */
    public function partidoHasta(): BelongsTo
    {
        return $this->belongsTo(Partido::class, 'partido_suspension_hasta_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function validadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por_usuario_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function autorizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizado_por_usuario_id');
    }
}
