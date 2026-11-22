<?php

namespace Database\Seeders;

use App\Models\Cancha;
use App\Models\Categoria;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\Partido;
use App\Models\Temporada;
use App\Services\GestionJugadorService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

// Programación publicada en la semana actual para revisar el portal (HU-062) y medirlo con Lighthouse (RNF-04, RNF-13):
// una jornada publicada con 8 partidos el sábado y el domingo, en 4 canchas. Se puede ejecutar varias veces.
// Uso, después de DatabaseSeeder: php artisan db:seed --class=PortalProgramacionDemoSeeder
class PortalProgramacionDemoSeeder extends Seeder
{
    public const NOMBRE = 'Temporada de demostración del portal';

    public const PARTIDOS = 8;

    public function run(): void
    {
        if (Temporada::where('nombre', self::NOMBRE)->exists()) {
            return;
        }

        $lunes = CarbonImmutable::parse(GestionJugadorService::hoy())->startOfWeek(CarbonImmutable::MONDAY);
        $temporada = Temporada::factory()->activa()->create(['nombre' => self::NOMBRE]);
        $categoria = Categoria::factory()->create(['temporada_id' => $temporada->id, 'nombre' => 'Primera Fuerza (demostración)', 'rama' => 'Libre']);
        $grupo = Grupo::factory()->create(['categoria_id' => $categoria->id, 'temporada_id' => $temporada->id, 'nombre' => 'Grupo Uno']);
        $jornada = Jornada::create([
            'grupo_id' => $grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => $lunes->addDays(5)->toDateString(),
            'fecha_fin' => $lunes->addDays(6)->toDateString(),
            'estado' => Jornada::ESTADO_PUBLICADA,
            'fecha_publicacion' => now(),
        ]);

        for ($numero = 1; $numero <= self::PARTIDOS; $numero++) {
            Partido::create([
                'jornada_id' => $jornada->id,
                'equipo_local_id' => $this->equipo($grupo, 'Local '.$numero)->id,
                'equipo_visitante_id' => $this->equipo($grupo, 'Visitante '.$numero)->id,
                'cancha_id' => Cancha::query()->firstOrCreate(
                    ['nombre' => 'Cancha de demostración '.(($numero - 1) % 4 + 1)],
                    ['direccion_ubicacion' => 'Unidad Deportiva Cuauhtémoc', 'capacidad_partidos_consecutivos' => 4],
                )->id,
                // Sábado los primeros 4, domingo los demás; dos horarios por cancha
                'fecha_partido' => $lunes->addDays($numero <= 4 ? 5 : 6)->toDateString(),
                'hora_partido' => $numero % 2 === 1 ? '09:00:00' : '11:30:00',
                'estado' => Partido::ESTADO_PROGRAMADO,
            ]);
        }
    }

    private function equipo(Grupo $grupo, string $nombre): Equipo
    {
        return Equipo::create([
            'club_id' => Club::create(['nombre' => 'Club '.$nombre.' (demostración)'])->id,
            'grupo_id' => $grupo->id,
            'nombre' => $nombre,
        ]);
    }
}
