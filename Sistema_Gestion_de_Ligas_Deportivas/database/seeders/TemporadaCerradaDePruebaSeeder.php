<?php

namespace Database\Seeders;

use App\Models\Temporada;
use Illuminate\Database\Seeder;

// Temporada cerrada con categorías y grupos para la demostración y las pruebas manuales (RF-01.4).
// Uso: php artisan db:seed --class=TemporadaCerradaDePruebaSeeder
class TemporadaCerradaDePruebaSeeder extends Seeder
{
    public const NOMBRE = 'Temporada 2025-2026 (cerrada de prueba)';

    public function run(): void
    {
        // Se puede ejecutar varias veces sin duplicar la temporada
        if (Temporada::where('nombre', self::NOMBRE)->exists()) {
            return;
        }

        Temporada::factory()->cerrada()->conCategoriasYGrupos()->create([
            'nombre' => self::NOMBRE,
            'fecha_inicio' => '2025-08-01',
            'fecha_fin' => '2026-06-30',
        ]);
    }
}
