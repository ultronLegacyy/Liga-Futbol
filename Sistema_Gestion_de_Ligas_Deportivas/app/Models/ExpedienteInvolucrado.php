<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persona o equipo vinculado como sujeto involucrado en un expediente disciplinario (HU-044 / RF-10.1).
 *
 * @property int $id
 * @property int $expediente_id
 * @property int|null $persona_id
 * @property int|null $equipo_id
 * @property string $tipo_involucrado
 * @property string|null $descripcion_participacion
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ExpedienteInvolucrado extends Model
{
    use Auditable;
    use HasFactory;

    protected $table = 'expediente_involucrados';

    protected $fillable = [
        'expediente_id',
        'persona_id',
        'equipo_id',
        'tipo_involucrado',
        'descripcion_participacion',
    ];

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
}
