<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evidencia asociada a un expediente disciplinario (tarjetas, incidencias, informe arbitral) (HU-044 / RF-10.1).
 *
 * @property int $id
 * @property int $expediente_id
 * @property string $tipo_evidencia
 * @property string $descripcion
 * @property int|null $minuto
 * @property int|null $evento_id
 * @property int|null $incidencia_id
 * @property string|null $archivo_ruta
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ExpedienteEvidencia extends Model
{
    use HasFactory;

    public const TIPO_TARJETA = 'evento_tarjeta';

    public const TIPO_INCIDENCIA = 'incidencia_partido';

    public const TIPO_INFORME = 'informe_arbitral';

    public const TIPO_ANEXO = 'anexo_digital';

    protected $table = 'expediente_evidencias';

    protected $fillable = [
        'expediente_id',
        'tipo_evidencia',
        'descripcion',
        'minuto',
        'evento_id',
        'incidencia_id',
        'archivo_ruta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minuto' => 'integer',
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
     * @return BelongsTo<EventoPartido, $this>
     */
    public function evento(): BelongsTo
    {
        return $this->belongsTo(EventoPartido::class, 'evento_id');
    }

    /**
     * @return BelongsTo<IncidenciaPartido, $this>
     */
    public function incidencia(): BelongsTo
    {
        return $this->belongsTo(IncidenciaPartido::class, 'incidencia_id');
    }
}
