<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preferencias blandas de programación por equipo (cancha, día y hora preferidos) (RF-07.2 / HU-074).
 *
 * @property int $id
 * @property int $equipo_id
 * @property int|null $cancha_id_preferida
 * @property string|null $dia_preferido
 * @property string|null $hora_preferida
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read Equipo $equipo
 * @property-read Cancha|null $canchaPreferida
 */
class PreferenciaEquipo extends Model
{
    use Auditable;

    protected $table = 'preferencias_equipo';

    protected $fillable = [
        'equipo_id',
        'cancha_id_preferida',
        'dia_preferido',
        'hora_preferida',
    ];

    /** @return BelongsTo<Equipo, $this> */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    /** @return BelongsTo<Cancha, $this> */
    public function canchaPreferida(): BelongsTo
    {
        return $this->belongsTo(Cancha::class, 'cancha_id_preferida');
    }

    /**
     * Normaliza `dia_preferido` al número ISO de día de semana (1 = lunes .. 7 = domingo).
     */
    public function diaPreferidoIso(): ?int
    {
        return self::normalizarDiaIso($this->dia_preferido);
    }

    /**
     * Normaliza `hora_preferida` a formato HH:MM para comparar contra franjas horarias.
     */
    public function horaNormalizada(): ?string
    {
        if ($this->hora_preferida === null || trim($this->hora_preferida) === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):([0-5]\d)(?::[0-5]\d)?$/', trim($this->hora_preferida), $coincidencias) !== 1) {
            return null;
        }

        $hora = (int) $coincidencias[1];
        if ($hora > 23) {
            return null;
        }

        return sprintf('%02d:%s', $hora, $coincidencias[2]);
    }

    /**
     * Convierte nombres de días (en español/inglés) o números a día ISO (1..7).
     */
    public static function normalizarDiaIso(?string $dia): ?int
    {
        if ($dia === null || trim($dia) === '') {
            return null;
        }

        $limpio = mb_strtolower(trim($dia));
        $limpio = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $limpio);

        return match ($limpio) {
            '1', 'lunes', 'monday', 'lun' => 1,
            '2', 'martes', 'tuesday', 'mar' => 2,
            '3', 'miercoles', 'wednesday', 'mie' => 3,
            '4', 'jueves', 'thursday', 'jue' => 4,
            '5', 'viernes', 'friday', 'vie' => 5,
            '6', 'sabado', 'saturday', 'sab' => 6,
            '0', '7', 'domingo', 'sunday', 'dom' => 7,
            default => null,
        };
    }
}
