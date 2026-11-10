<?php

namespace Database\Factories;

use App\Models\Persona;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Persona>
 */
class PersonaFactory extends Factory
{
    // Persona ficticia con una CURP de 18 caracteres que no se repite.
    // Mayor de edad: la Liga solo inscribe jugadores de 18 años o más (config/jugadores.php).
    public function definition(): array
    {
        return [
            'nombre' => fake()->firstName(),
            'apellidos' => fake()->lastName().' '.fake()->lastName(),
            'fecha_nacimiento' => fake()->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
            'curp' => fake()->unique()->regexify('[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[0-9A-Z][0-9]'),
        ];
    }
}
