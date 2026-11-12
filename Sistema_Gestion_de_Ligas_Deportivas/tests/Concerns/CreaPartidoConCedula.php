<?php

namespace Tests\Concerns;

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
use Illuminate\Support\Facades\Hash;

// Partido con dos equipos, plantillas y árbitro para las pruebas de la cédula (HU-036)
trait CreaPartidoConCedula
{
    private int $consecutivo = 0;

    private function partidoConArbitro(?Arbitro $arbitro = null): Partido
    {
        $temporada = Temporada::factory()->activa()->create();
        $grupo = Grupo::factory()->create(['categoria_id' => Categoria::factory()->create(['temporada_id' => $temporada->id])->id]);
        $jornada = Jornada::create(['grupo_id' => $grupo->id, 'numero_jornada' => 1, 'fecha_inicio' => '2026-11-14', 'fecha_fin' => '2026-11-15']);

        return Partido::create([
            'jornada_id' => $jornada->id,
            'equipo_local_id' => $this->equipo($grupo)->id,
            'equipo_visitante_id' => $this->equipo($grupo)->id,
            'arbitro_id' => ($arbitro ?? $this->arbitro())->id,
            'fecha_partido' => '2026-11-14',
            'hora_partido' => '10:00:00',
            'estado' => 'programado',
        ]);
    }

    private function equipo(Grupo $grupo): Equipo
    {
        $this->consecutivo++;
        $equipo = Equipo::create([
            'club_id' => Club::create(['nombre' => 'Club Cédula '.$this->consecutivo])->id,
            'grupo_id' => $grupo->id,
            'nombre' => 'Equipo Cédula '.$this->consecutivo,
        ]);

        // Tres jugadores activos, uno suspendido y una baja (que no entra en la alineación)
        foreach (['Activo', 'Activo', 'Activo', 'Suspendido', 'Baja'] as $indice => $estado) {
            Jugador::create([
                'persona_id' => Persona::factory()->create(['nombre' => 'Jugador '.($indice + 1), 'apellidos' => 'Equipo '.$this->consecutivo])->id,
                'equipo_id' => $equipo->id,
                'dorsal' => $indice + 1,
                'estado_elegibilidad' => $estado,
            ]);
        }

        return $equipo;
    }

    private function arbitro(): Arbitro
    {
        return Arbitro::create(['persona_id' => Persona::factory()->create(['nombre' => 'Árbitro', 'apellidos' => 'Cédula'])->id, 'estado' => 'Activo']);
    }

    // Usuario con rol; si es árbitro, enlazado a su persona como en HU-016
    private function usuario(string $rol, ?Arbitro $arbitro = null): User
    {
        $this->consecutivo++;
        $usuario = User::create([
            'name' => 'Usuario '.$rol,
            'email' => 'cedula'.$this->consecutivo.'@liga.test',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Consultor',
        ]);
        $usuario->forceFill(['persona_id' => $arbitro?->persona_id])->save();
        $usuario->assignRole($rol);

        return $usuario;
    }
}
