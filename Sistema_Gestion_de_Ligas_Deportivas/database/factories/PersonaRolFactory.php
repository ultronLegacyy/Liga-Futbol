<?php

namespace Database\Factories;

use App\Models\Persona;
use App\Models\PersonaRol;
use App\Models\Temporada;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonaRol>
 */
class PersonaRolFactory extends Factory
{
    // Rol de una persona nueva en una temporada nueva
    public function definition(): array
    {
        return [
            'persona_id' => Persona::factory(),
            'rol' => fake()->randomElement(PersonaRol::ROLES),
            'temporada_id' => Temporada::factory(),
        ];
    }
}
