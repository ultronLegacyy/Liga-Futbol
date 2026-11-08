<?php

namespace Database\Factories;

use App\Models\CargoFinanciero;
use App\Models\Club;
use App\Models\Temporada;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CargoFinanciero>
 */
class CargoFinancieroFactory extends Factory
{
    // Cuota de inscripción pendiente de un club nuevo en una temporada nueva
    public function definition(): array
    {
        return [
            // No hay factory de clubes: se crea con sus campos mínimos
            'club_id' => fn () => Club::create(['nombre' => 'Club '.fake()->unique()->numberBetween(1, 99999)])->id,
            'equipo_id' => null,
            'persona_id' => null,
            'temporada_id' => Temporada::factory(),
            'concepto' => 'Inscripcion',
            'monto' => fake()->randomFloat(2, 100, 5000),
            'vencimiento' => fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
            'estado' => 'Pendiente',
        ];
    }
}
