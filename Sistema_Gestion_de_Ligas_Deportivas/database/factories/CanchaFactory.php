<?php

namespace Database\Factories;

use App\Models\Cancha;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cancha>
 */
class CanchaFactory extends Factory
{
    // Cancha disponible, con el estado por defecto de la columna
    public function definition(): array
    {
        return [
            'nombre' => 'Cancha '.fake()->unique()->numberBetween(1, 9999),
            'direccion_ubicacion' => 'Unidad Deportiva, campo '.fake()->numberBetween(1, 40),
            'capacidad_partidos_consecutivos' => fake()->numberBetween(1, 4),
        ];
    }
}
