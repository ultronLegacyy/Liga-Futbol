<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Club extends Model
{
    use HasFactory;

    // Nombre de la tabla asociada en la base de datos
    protected $table = 'clubes';

    // Atributos asignables de forma masiva
    protected $fillable = [
        'nombre',
        'logo',
        'colores',
        'fecha_afiliacion',
        'estado',
    ];

    // Relacion uno a muchos con usuarios adscritos al club
    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'club_id');
    }

    // Relacion uno a muchos con los equipos pertenecientes al club
    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class, 'club_id');
    }
}
