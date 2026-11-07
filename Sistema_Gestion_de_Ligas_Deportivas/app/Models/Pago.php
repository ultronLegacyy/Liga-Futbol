<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\PagoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Pago parcial o total de un cargo financiero. Se modela en HU-050; registrarlo y validarlo es de HU-051
class Pago extends Model
{
    /** @use HasFactory<PagoFactory> */
    use HasFactory;
    use Auditable;

    protected $table = 'pagos';

    protected $fillable = [
        'cargo_id',
        'monto',
        'metodo_pago',
        'referencia',
        'recibo_url',
        'validado_por_tesorero_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<CargoFinanciero, $this> */
    public function cargo(): BelongsTo
    {
        return $this->belongsTo(CargoFinanciero::class, 'cargo_id');
    }

    /** @return BelongsTo<User, $this> */
    public function validadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por_tesorero_id');
    }
}
