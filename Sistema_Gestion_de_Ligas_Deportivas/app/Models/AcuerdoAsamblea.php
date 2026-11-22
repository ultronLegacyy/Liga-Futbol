<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Acuerdo de una asamblea (HU-054, RF-14.2 y RF-14.3).
 *
 * es_regla_permanente solo cambia con AsambleaService::convertirEnReglaPermanente(),
 * que exige el permiso asambleas.acuerdos.autorizar; por eso no está en $fillable.
 *
 * @property int $id
 * @property int $asamblea_id
 * @property int $numero
 * @property string $descripcion
 * @property bool $modifica_reglamento
 * @property int|null $temporada_id
 * @property bool $es_regla_permanente
 * @property int|null $autorizado_por_usuario_id
 * @property \Illuminate\Support\Carbon|null $autorizado_en
 * @property string|null $motivo_autorizacion
 * @property int $registrado_por_usuario_id
 */
class AcuerdoAsamblea extends Model
{
    use Auditable;

    protected $table = 'acuerdos_asamblea';

    protected $fillable = [
        'asamblea_id',
        'numero',
        'descripcion',
        'modifica_reglamento',
        'temporada_id',
        'registrado_por_usuario_id',
    ];

    protected $attributes = [
        'es_regla_permanente' => false,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'modifica_reglamento' => 'boolean',
            'es_regla_permanente' => 'boolean',
            'autorizado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Asamblea, $this> */
    public function asamblea(): BelongsTo
    {
        return $this->belongsTo(Asamblea::class);
    }

    /** @return BelongsTo<Temporada, $this> */
    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    /** @return BelongsTo<User, $this> */
    public function autorizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizado_por_usuario_id');
    }
}
