<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Club extends Model
{
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
    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'club_id');
    }

    // Equipos pertenecientes al club
    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class, 'club_id');
    }

    // Directivos del club
    public function directivos(): HasMany
    {
        return $this->hasMany(DirectivoClub::class, 'club_id');
    }

    // Propietario legal del club
    public function propietario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'propietario_usuario_id');
    }
}
