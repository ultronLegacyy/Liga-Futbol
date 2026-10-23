<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Grupo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grupo>
 */
class GrupoFactory extends Factory
{
    // El grupo siempre pertenece a la temporada de su categoría
    public function definition(): array
    {
        return [
            'categoria_id' => Categoria::factory(),
            'temporada_id' => fn (array $atributos) => Categoria::query()
                ->whereKey($atributos['categoria_id'])
                ->value('temporada_id'),
            'nombre' => 'Grupo '.fake()->unique()->numberBetween(1, 99999),
        ];
    }
}
