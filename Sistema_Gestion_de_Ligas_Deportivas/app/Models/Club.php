<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\Auditable;

class Club extends Model
{
    use HasFactory;
    use Auditable;

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

    // Equipos pertenecientes al club
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

    // Propietario legal del club
    /** @return BelongsTo<User, $this> */
    public function propietario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'propietario_usuario_id');
    }

    /** @return HasMany<HistorialVentaClub, $this> */
    public function historialVentas(): HasMany
    {
        return $this->hasMany(HistorialVentaClub::class, 'club_id');
    }
}
