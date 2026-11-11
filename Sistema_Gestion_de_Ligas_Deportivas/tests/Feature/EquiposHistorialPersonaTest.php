<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Jugador;
use App\Models\Persona;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// HU-084: equipos de una persona en su historial, con su categoría en cada temporada (RF-03.4)
class EquiposHistorialPersonaTest extends TestCase
{
    use DatabaseTransactions;

    private User $secretario;

    private Club $club;

    private Temporada $anterior;

    private Temporada $actual;

    private Persona $persona;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->secretario = $this->crearUsuario('Secretario');
        $this->club = Club::create(['nombre' => 'Club Ficticio HU-084']);

        // Se crea primero la temporada más reciente, para comprobar que el orden sale de las fechas y no del id
        $this->actual = Temporada::factory()->activa()->create(['nombre' => 'Temporada 2026-2027 HU-084', 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2027-06-30']);
        $this->anterior = Temporada::factory()->cerrada()->create(['nombre' => 'Temporada 2025-2026 HU-084', 'fecha_inicio' => '2025-08-01', 'fecha_fin' => '2026-06-30']);

        $this->persona = Persona::factory()->create(['nombre' => 'Persona', 'apellidos' => 'De Prueba']);
    }

    // CA1: en cada temporada se ve el equipo y la categoría, de la más reciente a la más antigua
    public function test_el_historial_muestra_equipo_y_categoria_en_cada_temporada(): void
    {
        $this->inscribir($this->equipo($this->anterior, 'Juvenil A', 'Halcones'));
        $this->inscribir($this->equipo($this->actual, 'Primera Fuerza', 'Halcones'));

        $this->actingAs($this->secretario)
            ->get(route('personas.show', $this->persona))
            ->assertOk()
            ->assertSee('Equipos por temporada')
            ->assertSeeInOrder([
                'Temporada 2026-2027 HU-084', 'Halcones', 'Club Ficticio HU-084', 'Primera Fuerza', 'Activo',
                'Temporada 2025-2026 HU-084', 'Cerrada', 'Halcones', 'Juvenil A',
            ]);
    }

    // CA1: si en una temporada cambió de equipo (transferencia), aparecen los dos con su estado
    public function test_una_temporada_con_transferencia_muestra_los_dos_equipos(): void
    {
        $this->inscribir($this->equipo($this->actual, 'Libre Varonil', 'Halcones'), 'Baja');
        $this->inscribir($this->equipo($this->actual, 'Libre Varonil B', 'Coyotes'));

        $this->actingAs($this->secretario)
            ->get(route('personas.show', $this->persona))
            ->assertOk()
            ->assertSeeInOrder(['Coyotes', 'Libre Varonil B', 'Activo', 'Halcones', 'Libre Varonil', 'Baja']);
    }

    // CA1: solo aparecen los equipos de esa persona
    public function test_no_muestra_los_equipos_de_otra_persona(): void
    {
        $this->inscribir($this->equipo($this->actual, 'Primera Fuerza', 'Halcones'));
        Jugador::create([
            'persona_id' => Persona::factory()->create()->id,
            'equipo_id' => $this->equipo($this->anterior, 'Categoría ajena', 'Equipo ajeno')->id,
            'estado_elegibilidad' => 'Activo',
        ]);

        $this->actingAs($this->secretario)
            ->get(route('personas.show', $this->persona))
            ->assertOk()
            ->assertSee('Halcones')
            ->assertDontSee('Equipo ajeno')
            ->assertDontSee('Categoría ajena');

        $this->assertCount(1, $this->persona->equiposPorTemporada());
    }

    // Una persona que nunca jugó muestra el aviso en lugar de una tabla vacía
    public function test_una_persona_sin_equipos_muestra_el_aviso(): void
    {
        $this->actingAs($this->secretario)
            ->get(route('personas.show', $this->persona))
            ->assertOk()
            ->assertSee('La persona aún no ha estado inscrita como jugador en ningún equipo.');
    }

    // El historial sigue protegido por el permiso del padrón: quien no tiene personas.ver recibe 403
    public function test_quien_no_puede_ver_personas_no_ve_el_historial(): void
    {
        $this->inscribir($this->equipo($this->actual, 'Primera Fuerza', 'Halcones'));

        $this->actingAs($this->crearUsuario('Tesorero'))
            ->get(route('personas.show', $this->persona))
            ->assertForbidden();
    }

    private function equipo(Temporada $temporada, string $categoria, string $nombre): Equipo
    {
        $categoriaCreada = Categoria::factory()->create(['temporada_id' => $temporada->id, 'nombre' => $categoria]);
        $grupo = Grupo::factory()->create(['categoria_id' => $categoriaCreada->id, 'temporada_id' => $temporada->id, 'nombre' => 'Grupo único']);

        return $this->club->equipos()->create([
            'temporada_id' => $temporada->id,
            'grupo_id' => $grupo->id,
            'nombre' => $nombre,
        ]);
    }

    private function inscribir(Equipo $equipo, string $estado = 'Activo'): Jugador
    {
        return Jugador::create([
            'persona_id' => $this->persona->id,
            'equipo_id' => $equipo->id,
            'estado_elegibilidad' => $estado,
        ]);
    }

    private function crearUsuario(string $rol): User
    {
        $usuario = User::create([
            'name' => 'Usuario '.$rol,
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Consultor',
        ]);

        $usuario->assignRole($rol);

        return $usuario;
    }
}
