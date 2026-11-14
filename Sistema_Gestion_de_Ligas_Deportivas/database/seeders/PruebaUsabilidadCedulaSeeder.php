<?php

namespace Database\Seeders;

use App\Models\Arbitro;
use App\Models\Categoria;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Persona;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

// Datos para la prueba de usabilidad de la cédula (HU-075, criterio 3, RNF-14): un partido programado por persona,
// asignado al árbitro sembrado, con dos equipos de 7 jugadores activos. Ver docs/arbitros/prueba-usabilidad-cedula.md.
// Uso, después de DatabaseSeeder: php artisan db:seed --class=PruebaUsabilidadCedulaSeeder
class PruebaUsabilidadCedulaSeeder extends Seeder
{
    public const NOMBRE = 'Temporada de prueba de usabilidad (cédula)';

    public const PARTIDOS = 5;

    public const CORREO_ARBITRO = 'arbitro@ligafutbol.com';

    public function run(): void
    {
        // Se puede ejecutar varias veces sin duplicar nada
        if (Temporada::where('nombre', self::NOMBRE)->exists()) {
            return;
        }

        $usuario = User::where('email', self::CORREO_ARBITRO)->first();
        $arbitro = $usuario?->persona_id === null ? null : Arbitro::where('persona_id', $usuario->persona_id)->first();

        if ($arbitro === null) {
            throw new RuntimeException('Falta la cuenta '.self::CORREO_ARBITRO.' enlazada a un árbitro: corre antes DatabaseSeeder.');
        }

        $temporada = Temporada::factory()->activa()->create(['nombre' => self::NOMBRE]);
        $grupo = Grupo::factory()->create(['categoria_id' => Categoria::factory()->create(['temporada_id' => $temporada->id])->id]);
        $jornada = Jornada::create(['grupo_id' => $grupo->id, 'numero_jornada' => 1, 'fecha_inicio' => now()->toDateString(), 'fecha_fin' => now()->toDateString()]);

        for ($persona = 1; $persona <= self::PARTIDOS; $persona++) {
            Partido::create([
                'jornada_id' => $jornada->id,
                'equipo_local_id' => $this->equipo($grupo, 'Rojos '.$persona)->id,
                'equipo_visitante_id' => $this->equipo($grupo, 'Azules '.$persona)->id,
                'arbitro_id' => $arbitro->id,
                'fecha_partido' => now()->toDateString(),
                // Una hora distinta por partido: el árbitro no puede tener dos partidos a la misma hora
                'hora_partido' => sprintf('%02d:00:00', 8 + $persona),
                'estado' => 'programado',
            ]);
        }
    }

    private function equipo(Grupo $grupo, string $nombre): Equipo
    {
        $equipo = Equipo::create([
            'club_id' => Club::create(['nombre' => 'Club '.$nombre.' (prueba)'])->id,
            'grupo_id' => $grupo->id,
            'nombre' => $nombre,
        ]);

        foreach (range(1, 7) as $dorsal) {
            Jugador::create([
                'persona_id' => Persona::factory()->create()->id,
                'equipo_id' => $equipo->id,
                'dorsal' => $dorsal,
                'estado_elegibilidad' => 'Activo',
            ]);
        }

        return $equipo;
    }
}
