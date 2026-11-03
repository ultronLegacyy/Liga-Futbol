<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialVentaClub extends Model
{
    use Auditable;

    protected $table = 'historial_ventas_club';

    protected $fillable = [
        'club_id',
        'propietario_anterior',
        'propietario_nuevo',
        'propietario_anterior_usuario_id',
        'propietario_nuevo_usuario_id',
        'carta_venta_pdf',
        'fecha_venta',
    ];

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /** @return BelongsTo<User, $this> */
    public function propietarioAnterior(): BelongsTo
    {
        return $this->belongsTo(User::class, 'propietario_anterior_usuario_id');
    }

    /** @return BelongsTo<User, $this> */
    public function propietarioNuevo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'propietario_nuevo_usuario_id');
    }
}
