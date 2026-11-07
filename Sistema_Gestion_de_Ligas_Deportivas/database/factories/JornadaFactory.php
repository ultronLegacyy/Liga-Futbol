<?php

namespace Database\Factories;

use App\Models\Grupo;
use App\Models\Jornada;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Jornada>
 */
class JornadaFactory extends Factory
{
    protected $model = Jornada::class;

    public function definition(): array
    {
        $inicio = Carbon::now()->addDays(fake()->numberBetween(1, 30));

        return [
            'grupo_id' => Grupo::factory(),
            'numero_jornada' => fake()->unique()->numberBetween(1, 9999),
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_fin' => $inicio->copy()->addDays(2)->toDateString(),
            'equipo_descanso_id' => null,
        ];
    }
}
