<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Club extends Model
{
    use Auditable;
    use HasFactory;

    protected $table = 'clubes';

    protected $fillable = [
        'nombre',
        'logo',
        'colores',
        'fecha_afiliacion',
        'estado',
        'propietario_usuario_id',
    ];

    // Usuarios adscritos al club
    /** @return HasMany<User, $this> */
    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'club_id');
    }

    // Relacion uno a muchos con los equipos pertenecientes al club
    /** @return HasMany<Equipo, $this> */
    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class, 'club_id');
    }

    // Directivos del club
    /** @return HasMany<DirectivoClub, $this> */
    public function directivos(): HasMany
    {
        return $this->hasMany(DirectivoClub::class, 'club_id');
    }

    // Tarjetones digitales del club (SCRUM-27)
    /** @return HasMany<TarjetonDigital, $this> */
    public function tarjetonesDigitales(): HasMany
    {
        return $this->hasMany(TarjetonDigital::class, 'club_id');
    }

    // Propietario legal del club (SCRUM-25)
    /** @return BelongsTo<User, $this> */
    public function propietario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'propietario_usuario_id');
    }

    // Historial de ventas del club (SCRUM-25)
    /** @return HasMany<HistorialVentaClub, $this> */
    public function historialVentas(): HasMany
    {
        return $this->hasMany(HistorialVentaClub::class, 'club_id');
    }
}
