<?php

namespace Database\Factories;

use App\Models\Persona;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Persona>
 */
class PersonaFactory extends Factory
{
    // Persona ficticia con una CURP de 18 caracteres que no se repite y que coincide con su fecha de nacimiento (HU-021)
    public function definition(): array
    {
        return [
            'nombre' => fake()->firstName(),
            'apellidos' => fake()->lastName().' '.fake()->lastName(),
            'fecha_nacimiento' => fake()->dateTimeBetween('-60 years', '-6 years')->format('Y-m-d'),
            'curp' => fn (array $atributos): string => self::curpPara((string) $atributos['fecha_nacimiento']),
        ];
    }

    /** CURP ficticia con la fecha AAMMDD y el diferenciador de siglo de la posición 17 (dígito antes del 2000, letra desde el 2000). */
    public static function curpPara(string $fechaNacimiento): string
    {
        $fecha = Carbon::parse($fechaNacimiento);
        $siglo = $fecha->year < 2000 ? fake()->regexify('[0-9]') : fake()->regexify('[A-Z]');

        // La parte al azar no se repite, así que la CURP completa tampoco
        $azar = fake()->unique()->regexify('[A-Z]{4}[HM][A-Z]{5}[0-9]');

        return substr($azar, 0, 4).$fecha->format('ymd').substr($azar, 4, 6).$siglo.substr($azar, 10, 1);
    }
}
