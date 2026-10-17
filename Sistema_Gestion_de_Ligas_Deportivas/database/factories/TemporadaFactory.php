<?php

namespace Database\Factories;

use App\Models\Temporada;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Temporada>
 */
class TemporadaFactory extends Factory
{
    // Estado por defecto: borrador, igual que el valor por defecto de la columna
    public function definition(): array
    {
        $inicio = fake()->dateTimeBetween('-1 year', '+1 year');

        return [
            'liga_id' => fn () => $this->ligaExistente(),
            'nombre' => 'Temporada '.fake()->numerify('20##-####'),
            'fecha_inicio' => $inicio->format('Y-m-d'),
            'fecha_fin' => (clone $inicio)->modify('+6 months')->format('Y-m-d'),
            'estado' => 'borrador',
        ];
    }

    // Temporada en curso, modificable por usuarios con permiso
    public function activa(): static
    {
        return $this->state(fn () => ['estado' => 'activa']);
    }

    // Temporada cerrada, solo de consulta (RF-01.4)
    public function cerrada(): static
    {
        return $this->state(fn () => ['estado' => 'cerrada']);
    }

    // Temporada en preparacion
    public function borrador(): static
    {
        return $this->state(fn () => ['estado' => 'borrador']);
    }

    // Reutiliza una liga existente o crea una minima para respetar la llave foranea
    private function ligaExistente(): int
    {
        $ligaId = DB::table('ligas')->value('id');

        if ($ligaId !== null) {
            return (int) $ligaId;
        }

        return DB::table('ligas')->insertGetId([
            'nombre' => 'Liga de Pruebas SGLD',
            'estado' => 'activa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
