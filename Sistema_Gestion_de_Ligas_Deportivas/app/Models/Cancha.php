<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\CanchaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Cancha o sede del catálogo que usa la programación de partidos (RF-05.1)
class Cancha extends Model
{
    /** @use HasFactory<CanchaFactory> */
    use HasFactory;
    use Auditable;

    protected $table = 'canchas';

    // El estado no se captura en el catálogo: los bloqueos por mantenimiento o lluvia son de HU-024
    protected $fillable = ['nombre', 'direccion_ubicacion', 'capacidad_partidos_consecutivos'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacidad_partidos_consecutivos' => 'integer',
        ];
    }
}
