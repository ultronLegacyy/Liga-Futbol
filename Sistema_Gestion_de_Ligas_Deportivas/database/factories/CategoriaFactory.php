<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Temporada;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Categoria>
 */
class CategoriaFactory extends Factory
{
    // Categoría de una temporada nueva, sin rango de años ni edad
    public function definition(): array
    {
        return [
            'temporada_id' => Temporada::factory(),
            'nombre' => 'Categoría '.fake()->unique()->numberBetween(1, 99999),
            'rama' => fake()->randomElement(Categoria::RAMAS),
        ];
    }
}
