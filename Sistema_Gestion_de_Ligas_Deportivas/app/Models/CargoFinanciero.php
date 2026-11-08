<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\CargoFinancieroFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cuota o cargo a cobrar a un club, equipo o jugador en una temporada (HU-050, RF-13.1). Los pagos son de HU-051.
 *
 * @property int $id
 * @property string $monto
 * @property \Illuminate\Support\Carbon $vencimiento
 * @property string $estado
 */
class CargoFinanciero extends Model
{
    /** @use HasFactory<CargoFinancieroFactory> */
    use HasFactory;
    use Auditable;

    // Conceptos de la tabla y cómo se muestran en las vistas
    public const CONCEPTOS = [
        'Inscripcion' => 'Inscripción',
        'Arbitraje' => 'Arbitraje',
        'Cancha' => 'Cancha',
        'Credenciales' => 'Credenciales',
        'Multa' => 'Multa',
    ];

    // Conceptos que registra HU-050; credenciales y multas son de otras historias
    public const CONCEPTOS_REGISTRABLES = ['Inscripcion', 'Arbitraje', 'Cancha'];

    // Estados del adeudo y cómo se muestran; HU-050 solo crea cargos pendientes
    public const ESTADOS = [
        'Pendiente' => 'Pendiente',
        'Pagado' => 'Pagado',
        'Vencido' => 'Vencido',
    ];

    protected $table = 'cargos_financieros';

    protected $fillable = [
        'club_id',
        'equipo_id',
        'persona_id',
        'temporada_id',
        'concepto',
        'monto',
        'vencimiento',
        'estado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'vencimiento' => 'date',
        ];
    }

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /** @return BelongsTo<Equipo, $this> */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }

    /** @return BelongsTo<Persona, $this> */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /** @return BelongsTo<Temporada, $this> */
    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    /** @return HasMany<Pago, $this> */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'cargo_id');
    }

    /**
     * Suma de los pagos. Usa pagos_sum_monto si el listado lo cargó con withSum('pagos', 'monto'), para no repetir consultas.
     */
    public function montoPagado(): string
    {
        return self::formatoCentavos($this->centavosPagados());
    }

    // Lo que falta por pagar; nunca es negativo
    public function saldo(): string
    {
        return self::formatoCentavos($this->centavosDeSaldo());
    }

    public function centavosDeSaldo(): int
    {
        return max(0, self::aCentavos($this->monto) - $this->centavosPagados());
    }

    /**
     * Vencido se calcula al mostrar (HU-051): con saldo y el vencimiento ya pasado. En la base sigue Pendiente.
     */
    public function estadoMostrado(): string
    {
        $hoy = now('America/Mexico_City')->toDateString();

        if ($this->estado !== 'Pagado' && $this->centavosDeSaldo() > 0 && $this->vencimiento->toDateString() < $hoy) {
            return 'Vencido';
        }

        return $this->estado;
    }

    // Dinero en centavos enteros: decimal(10,2) cabe en un int y no se pierden decimales
    public static function aCentavos(mixed $monto): int
    {
        return (int) round(((float) $monto) * 100);
    }

    public static function formatoCentavos(int $centavos): string
    {
        return number_format($centavos / 100, 2, '.', '');
    }

    private function centavosPagados(): int
    {
        if (array_key_exists('pagos_sum_monto', $this->attributes)) {
            return self::aCentavos($this->attributes['pagos_sum_monto']);
        }

        if ($this->relationLoaded('pagos')) {
            return (int) $this->pagos->sum(fn (Pago $pago): int => self::aCentavos($pago->monto));
        }

        return self::aCentavos($this->pagos()->sum('monto'));
    }
}
