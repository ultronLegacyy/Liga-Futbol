<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gol o tarjeta de la cédula (HU-036). Un autogol es un Gol del jugador que lo anotó y cuenta para el rival.
 *
 * @property int $id
 * @property int $cedula_id
 * @property int|null $jugador_id
 * @property string $tipo
 * @property bool $autogol
 * @property int|null $minuto
 * @property string|null $clave_envio
 */
class EventoPartido extends Model
{
    use Auditable;

    // La expulsión es la tarjeta Roja (decisión de Daniel del 11/11)
    public const TIPOS = ['Gol', 'Amarilla', 'Roja'];

    protected $table = 'eventos_partido';

    protected $fillable = [
        'cedula_id',
        'jugador_id',
        'tipo',
        'autogol',
        'minuto',
        'clave_envio',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'autogol' => 'boolean',
            'minuto' => 'integer',
        ];
    }

    /** @return BelongsTo<CedulaArbitral, $this> */
    public function cedula(): BelongsTo
    {
        return $this->belongsTo(CedulaArbitral::class, 'cedula_id');
    }

    /** @return BelongsTo<Jugador, $this> */
    public function jugador(): BelongsTo
    {
        return $this->belongsTo(Jugador::class, 'jugador_id');
    }
}
