<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\CargoFinancieroFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Cuota o cargo a cobrar a un club, equipo o jugador en una temporada (HU-050, RF-13.1). Los pagos son de HU-051
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
}
