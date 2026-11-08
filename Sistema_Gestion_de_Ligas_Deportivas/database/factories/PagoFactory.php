<?php

namespace Database\Factories;

use App\Models\CargoFinanciero;
use App\Models\Pago;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pago>
 */
class PagoFactory extends Factory
{
    // Pago sin validar de un cargo nuevo
    public function definition(): array
    {
        return [
            'cargo_id' => CargoFinanciero::factory(),
            'monto' => fake()->randomFloat(2, 50, 1000),
            'metodo_pago' => fake()->randomElement(['Transferencia', 'Efectivo', 'Depósito']),
            'referencia' => fake()->bothify('REF-####-????'),
            'recibo_url' => null,
            'validado_por_tesorero_id' => null,
        ];
    }
}
