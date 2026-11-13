<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jugador de la plantilla en la alineación de la cédula (HU-036). El árbitro marca asistencia, número y capitán.
 * Sin Auditable: cada casilla marcada no es una operación crítica (RNF-33 audita marcador, goles y tarjetas).
 *
 * @property int $id
 * @property int $cedula_id
 * @property int|null $jugador_id
 * @property int $equipo_id
 * @property bool $asistio
 * @property int|null $numero_camiseta
 * @property bool $es_capitan
 */
class AlineacionPartido extends Model
{
    protected $table = 'alineaciones_partido';

    protected $fillable = [
        'cedula_id',
        'jugador_id',
        'equipo_id',
        'asistio',
        'numero_camiseta',
        'es_capitan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'asistio' => 'boolean',
            'es_capitan' => 'boolean',
            'numero_camiseta' => 'integer',
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

    /** @return BelongsTo<Equipo, $this> */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }
}
