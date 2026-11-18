<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\GestionJugadorService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Asamblea de delegados de la Liga (RF-14): su convocatoria y su lista de asistencia (HU-053).
 *
 * @property int $id
 * @property string $tipo
 * @property \Illuminate\Support\Carbon $fecha
 * @property string|null $hora
 * @property string|null $lugar
 * @property string|null $orden_del_dia
 * @property string $estado
 * @property int|null $temporada_id
 * @property int|null $convocada_por_usuario_id
 * @property \Illuminate\Support\Carbon|null $asistencia_registrada_en
 * @property string|null $acta_pdf_url
 * @property string|null $acuerdos
 */
class Asamblea extends Model
{
    use Auditable;

    public const TIPOS = ['Ordinaria', 'Extraordinaria'];

    public const ESTADO_CONVOCADA = 'Convocada';

    public const ESTADO_CELEBRADA = 'Celebrada';

    protected $table = 'asambleas';

    protected $fillable = [
        'tipo',
        'fecha',
        'hora',
        'lugar',
        'orden_del_dia',
        'estado',
        'temporada_id',
        'convocada_por_usuario_id',
        'asistencia_registrada_en',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'asistencia_registrada_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Temporada, $this> */
    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    /** @return BelongsTo<User, $this> */
    public function convocadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'convocada_por_usuario_id');
    }

    /** @return HasMany<AsistenciaAsamblea, $this> */
    public function asistencias(): HasMany
    {
        return $this->hasMany(AsistenciaAsamblea::class);
    }

    /** @return HasMany<AcuerdoAsamblea, $this> */
    public function acuerdosRegistrados(): HasMany
    {
        return $this->hasMany(AcuerdoAsamblea::class)->orderBy('numero');
    }

    /** @return HasMany<ComunicadoAsamblea, $this> */
    public function comunicados(): HasMany
    {
        return $this->hasMany(ComunicadoAsamblea::class)->latest()->latest('id');
    }

    public function tieneAsistenciaRegistrada(): bool
    {
        return $this->asistencia_registrada_en !== null;
    }

    /**
     * La asistencia se registra el día de la asamblea o después, con la hora de México
     * (decisión del Product Owner, respuesta 33 de la revisión del #73).
     */
    public function admiteAsistencia(): bool
    {
        return $this->fecha->toDateString() <= GestionJugadorService::hoy();
    }
}
