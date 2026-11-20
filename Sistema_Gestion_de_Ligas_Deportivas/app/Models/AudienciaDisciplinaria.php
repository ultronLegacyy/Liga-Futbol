<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de comparecencia, alegatos y resolución de un expediente disciplinario (HU-045 / RF-10.2).
 *
 * @property int $id
 * @property int $expediente_id
 * @property \Illuminate\Support\Carbon $fecha_audiencia
 * @property string|null $lugar_o_medio
 * @property string $comparecientes
 * @property string $alegatos_defensa
 * @property string|null $resolucion
 * @property int|null $registrado_por_usuario_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read ExpedienteDisciplinario $expediente
 * @property-read User|null $registradoPor
 */
class AudienciaDisciplinaria extends Model
{
    use Auditable;
    use HasFactory;

    protected $table = 'audiencias_disciplinarias';

    protected $fillable = [
        'expediente_id',
        'fecha_audiencia',
        'lugar_o_medio',
        'comparecientes',
        'alegatos_defensa',
        'resolucion',
        'registrado_por_usuario_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_audiencia' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ExpedienteDisciplinario, $this>
     */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(ExpedienteDisciplinario::class, 'expediente_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_usuario_id');
    }
}
