<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Expediente disciplinario generado a partir de las incidencias de la cédula arbitral (HU-044 / RF-10.1).
 *
 * @property int $id
 * @property string|null $folio
 * @property int|null $cedula_id
 * @property int|null $partido_id
 * @property int|null $articulo_id
 * @property string|null $titulo
 * @property string|null $descripcion_hechos
 * @property string $origen
 * @property bool $generado_automaticamente
 * @property \Illuminate\Support\Carbon|null $fecha_apertura
 * @property string $estado
 * @property string|null $observaciones_comision
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Collection<int, CatalogoArticulo> $articulos
 * @property-read Collection<int, ExpedienteInvolucrado> $involucrados
 * @property-read Collection<int, ExpedienteEvidencia> $evidencias
 */
class ExpedienteDisciplinario extends Model
{
    use Auditable;
    use HasFactory;

    public const ESTADO_ABIERTO = 'Abierto';

    public const ESTADO_RESUELTO = 'Resuelto';

    protected $table = 'expedientes_disciplinarios';

    protected $fillable = [
        'folio',
        'cedula_id',
        'partido_id',
        'articulo_id',
        'titulo',
        'descripcion_hechos',
        'origen',
        'generado_automaticamente',
        'fecha_apertura',
        'estado',
        'observaciones_comision',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'generado_automaticamente' => 'boolean',
            'fecha_apertura' => 'datetime',
        ];
    }

    public function estaAbierto(): bool
    {
        return $this->estado === self::ESTADO_ABIERTO;
    }

    public function estaResuelto(): bool
    {
        return $this->estado === self::ESTADO_RESUELTO;
    }

    /**
     * @param  Builder<ExpedienteDisciplinario>  $query
     * @return Builder<ExpedienteDisciplinario>
     */
    public function scopeAbiertos(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_ABIERTO);
    }

    /**
     * @param  Builder<ExpedienteDisciplinario>  $query
     * @return Builder<ExpedienteDisciplinario>
     */
    public function scopeResueltos(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_RESUELTO);
    }

    /**
     * @return BelongsTo<CedulaArbitral, $this>
     */
    public function cedula(): BelongsTo
    {
        return $this->belongsTo(CedulaArbitral::class, 'cedula_id');
    }

    /**
     * @return BelongsTo<Partido, $this>
     */
    public function partido(): BelongsTo
    {
        return $this->belongsTo(Partido::class, 'partido_id');
    }

    /**
     * Artículo principal de referencia (compatibilidad con migración base).
     *
     * @return BelongsTo<CatalogoArticulo, $this>
     */
    public function articulo(): BelongsTo
    {
        return $this->belongsTo(CatalogoArticulo::class, 'articulo_id');
    }

    /**
     * Artículos del catálogo reglamentario presuntamente infringidos (muchos a muchos, HU-044 Criterio 2).
     *
     * @return BelongsToMany<CatalogoArticulo, $this>
     */
    public function articulos(): BelongsToMany
    {
        return $this->belongsToMany(
            CatalogoArticulo::class,
            'expediente_articulos',
            'expediente_id',
            'articulo_id'
        )->withPivot('observaciones')->withTimestamps();
    }

    /**
     * @return HasMany<ExpedienteInvolucrado, $this>
     */
    public function involucrados(): HasMany
    {
        return $this->hasMany(ExpedienteInvolucrado::class, 'expediente_id');
    }

    /**
     * @return HasMany<ExpedienteEvidencia, $this>
     */
    public function evidencias(): HasMany
    {
        return $this->hasMany(ExpedienteEvidencia::class, 'expediente_id');
    }

    /**
     * Personas involucradas en el expediente.
     *
     * @return BelongsToMany<Persona, $this>
     */
    public function personas(): BelongsToMany
    {
        return $this->belongsToMany(
            Persona::class,
            'expediente_involucrados',
            'expediente_id',
            'persona_id'
        )->withPivot(['tipo_involucrado', 'descripcion_participacion'])->withTimestamps();
    }

    /**
     * Equipos involucrados en el expediente.
     *
     * @return BelongsToMany<Equipo, $this>
     */
    public function equipos(): BelongsToMany
    {
        return $this->belongsToMany(
            Equipo::class,
            'expediente_involucrados',
            'expediente_id',
            'equipo_id'
        )->withPivot(['tipo_involucrado', 'descripcion_participacion'])->withTimestamps();
    }
}
