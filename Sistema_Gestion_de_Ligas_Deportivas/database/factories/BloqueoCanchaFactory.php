<?php

namespace Database\Factories;

use App\Models\BloqueoCancha;
use App\Models\Cancha;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BloqueoCancha>
 */
class BloqueoCanchaFactory extends Factory
{
    protected $model = BloqueoCancha::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $inicio = fake()->dateTimeBetween('+1 days', '+10 days');
        $fin = (clone $inicio)->modify('+'.fake()->numberBetween(2, 8).' hours');

        return [
            'cancha_id' => Cancha::factory(),
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'motivo' => fake()->randomElement([
                'Mantenimiento correctivo de empastado',
                'Lluvias intensas e inundacion de terreno de juego',
                'Evento civico municipal programado',
                'Reacondicionamiento de drenaje y porterias',
            ]),
        ];
    }
}
