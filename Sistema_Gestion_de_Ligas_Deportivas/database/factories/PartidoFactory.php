<?php

namespace Database\Factories;

use App\Models\Jornada;
use App\Models\Partido;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partido>
 */
class PartidoFactory extends Factory
{
    protected $model = Partido::class;

    public function definition(): array
    {
        $fecha = Carbon::now()->addDays(fake()->numberBetween(1, 30));

        return [
            'jornada_id' => Jornada::factory(),
            'equipo_local_id' => 1,
            'equipo_visitante_id' => 2,
            'cancha_id' => null,
            'arbitro_id' => null,
            'fecha_partido' => $fecha->toDateString(),
            'hora_partido' => '10:00:00',
            'estado' => 'programado',
        ];
    }
}
