<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
 * @property string $origen
 * @property int|null $capturada_por_id
 * @property \Illuminate\Support\Carbon|null $capturada_en
 * @property string|null $cedula_papel_ruta
 * @property int|null $validada_por_id
 * @property \Illuminate\Support\Carbon|null $validada_en
 */
class CedulaArbitral extends Model
{
    use HasFactory;
    use Auditable;

    public const ABIERTA = 'abierta';

    public const CERRADA = 'cerrada';

    // HU-037: la cédula se captura en campo desde el celular o se transcribe desde la cédula en papel
    public const ORIGEN_CAMPO = 'campo';

    public const ORIGEN_PAPEL = 'papel';

    // Estados del partido en que el árbitro abre la cédula y captura (respuesta 24 de Medina, 14/11)
    public const ESTADOS_PARTIDO_CAPTURA = ['confirmado', 'en juego'];

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
        'origen',
        'cedula_papel_ruta',
        'capturada_por_id',
        'capturada_en',
        'validada_por_id',
        'validada_en',
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
            'capturada_en' => 'datetime',
            'validada_en' => 'datetime',
        ];
    }

    // Una cédula cerrada ya no se edita (HU-075)
    public function estaCerrada(): bool
    {
        return $this->estado === self::CERRADA;
    }

    // HU-040: el resultado es oficial solo cuando la cédula está cerrada y alguien con resultados.validar lo validó
    public function esOficial(): bool
    {
        return $this->estaCerrada() && $this->validada_en !== null;
    }

    /**
     * Resultados oficiales (HU-040): los únicos que cuentan para la tabla de posiciones y las estadísticas.
     *
     * @param  Builder<CedulaArbitral>  $query
     * @return Builder<CedulaArbitral>
     */
    public function scopeOficiales(Builder $query): Builder
    {
        return $query->where('estado', self::CERRADA)->whereNotNull('validada_en');
    }

    /**
     * Resultados capturados y cerrados que esperan la validación oficial (HU-040).
     *
     * @param  Builder<CedulaArbitral>  $query
     * @return Builder<CedulaArbitral>
     */
    public function scopePorValidar(Builder $query): Builder
    {
        return $query->where('estado', self::CERRADA)->whereNull('validada_en');
    }

    /**
     * Cédulas con algo capturado: marcador distinto de 0-0, goles o tarjetas, incidencias o asistencia marcada.
     * Su partido ya no se borra (RNF-30), porque el borrado en cascada no deja auditoría de lo capturado.
     *
     * @param  Builder<CedulaArbitral>  $query
     * @return Builder<CedulaArbitral>
     */
    public function scopeConDatosCapturados(Builder $query): Builder
    {
        return $query->where(fn (Builder $consulta) => $consulta
            ->where('goles_local', '>', 0)
            ->orWhere('goles_visitante', '>', 0)
            ->orWhereHas('eventos')
            ->orWhereHas('incidencias')
            ->orWhereHas('alineaciones', fn (Builder $alineacion) => $alineacion->where('asistio', true)));
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

    /** @return BelongsTo<User, $this> */
    public function capturadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'capturada_por_id');
    }

    public function esDePapel(): bool
    {
        return $this->origen === self::ORIGEN_PAPEL;
    }

    /**
     * Respuesta 51 del PO: el árbitro ya capturó algo en campo (alineación, marcador, informe, goles, tarjetas,
     * incidencias o firmas). Sobre una cédula así el Capturista no transcribe el papel; las diferencias se
     * corrigen después del cierre (HU-039).
     */
    public function tieneCapturaDeCampo(): bool
    {
        if ($this->esDePapel()) {
            return false;
        }

        return $this->firma_capitan_local !== null
            || $this->firma_capitan_visitante !== null
            || $this->envios()->exists()
            || $this->eventos()->exists()
            || $this->incidencias()->exists();
    }

    /** @return BelongsTo<User, $this> */
    public function validadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validada_por_id');
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

    /** @return HasMany<EnvioCedula, $this> */
    public function envios(): HasMany
    {
        return $this->hasMany(EnvioCedula::class, 'cedula_id');
    }

    /** @return HasMany<ExpedienteDisciplinario, $this> */
    public function expedientes(): HasMany
    {
        return $this->hasMany(ExpedienteDisciplinario::class, 'cedula_id');
    }

    /**
     * Determina si la cédula cuenta con incidencias disciplinarias (tarjetas, expulsiones, reportes arbitrales).
     * Requisito: RF-10.1 / HU-044 Criterio 1.
     */
    public function tieneIncidenciasDisciplinarias(): bool
    {
        // 1. Tarjetas amarillas o rojas registradas en eventos
        $tieneTarjetas = $this->eventos()->whereIn('tipo', ['Amarilla', 'Roja'])->exists();

        if ($tieneTarjetas) {
            return true;
        }

        // 2. Incidencias del partido reportadas
        if ($this->incidencias()->exists()) {
            return true;
        }

        // 3. Informe o reporte arbitral con contenido
        return ! empty(trim((string) $this->informe_arbitral));
    }
}
