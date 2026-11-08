<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Persona;
use App\Models\PersonaRol;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// HU-015: historial de equipos y personas por temporada (RF-03.4)
class HistorialTemporadasTest extends TestCase
{
    use DatabaseTransactions;

    private User $secretario;

    private Club $club;

    private Temporada $anterior;

    private Temporada $actual;

    private Equipo $equipoActual;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->secretario = $this->crearUsuario('secretario_historial@liga.com', 'Secretario');
        $this->club = Club::create(['nombre' => 'Club Historial Ficticio']);

        // Se crea primero la temporada más reciente, para comprobar que el orden sale de las fechas y no del id
        $this->actual = Temporada::factory()->activa()->create(['nombre' => 'Temporada 2026-2027 de prueba', 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2027-06-30']);
        $this->anterior = Temporada::factory()->cerrada()->create(['nombre' => 'Temporada 2025-2026 de prueba', 'fecha_inicio' => '2025-08-01', 'fecha_fin' => '2026-06-30']);

        $this->equipo($this->anterior, 'Juvenil A', 'Grupo Norte', 'Halcones');
        $this->equipoActual = $this->equipo($this->actual, 'Primera Fuerza', 'Grupo Sur', 'Halcones');
    }

    // CA1: el historial del equipo muestra temporada, categoría y grupo, de la más reciente a la más antigua
    public function test_el_historial_del_equipo_muestra_temporadas_categorias_y_grupos(): void
    {
        $this->actingAs($this->secretario)
            ->get(route('equipos.historial.show', $this->equipoActual))
            ->assertOk()
            ->assertSee('Halcones')
            ->assertSee('Club Historial Ficticio')
            ->assertSeeInOrder([
                'Temporada 2026-2027 de prueba', '01/08/2026', 'Primera Fuerza', 'Grupo Sur',
                'Temporada 2025-2026 de prueba', 'Cerrada', '01/08/2025', 'Juvenil A', 'Grupo Norte',
            ]);
    }

    // CA1: el historial no mezcla otros equipos del club ni equipos de otro club con el mismo nombre
    public function test_el_historial_solo_junta_el_mismo_club_y_nombre(): void
    {
        $this->equipo($this->anterior, 'Categoría de otro equipo', 'Grupo de otro equipo', 'Águilas');
        $otroClub = Club::create(['nombre' => 'Otro club ficticio']);
        $this->equipo($this->anterior, 'Categoría de otro club', 'Grupo de otro club', 'Halcones', $otroClub);

        $this->actingAs($this->secretario)
            ->get(route('equipos.historial.show', $this->equipoActual))
            ->assertOk()
            ->assertDontSee('Categoría de otro equipo')
            ->assertDontSee('Categoría de otro club');
    }

    // El listado agrupa por club y nombre y cuenta las temporadas
    public function test_el_listado_agrupa_los_equipos_por_club_y_nombre(): void
    {
        $this->actingAs($this->secretario)
            ->get(route('equipos.historial.index'))
            ->assertOk()
            ->assertSeeInOrder(['Halcones', 'Club Historial Ficticio', '2', route('equipos.historial.show', $this->equipoActual)], false);
    }

    // RNF-29: el Presidente del Club solo consulta el historial de los equipos de su club
    public function test_un_directivo_de_club_solo_ve_los_equipos_de_su_club(): void
    {
        $otroClub = Club::create(['nombre' => 'Otro club ficticio']);
        $equipoAjeno = $this->equipo($this->actual, 'Categoría ajena', 'Grupo ajeno', 'Pumas', $otroClub);
        $presidente = $this->crearUsuario('presidente_club_historial@liga.com', 'Presidente del Club', $this->club->id);

        $this->actingAs($presidente)->get(route('equipos.historial.show', $this->equipoActual))->assertOk();
        $this->actingAs($presidente)->get(route('equipos.historial.show', $equipoAjeno))->assertForbidden();
        $this->actingAs($presidente)->get(route('equipos.historial.index'))
            ->assertOk()
            ->assertSee('Halcones')
            ->assertDontSee('Pumas');
    }

    // Un rol sin permiso de ver equipos no consulta el historial
    public function test_un_rol_sin_permiso_no_consulta_el_historial(): void
    {
        $tesorero = $this->crearUsuario('tesorero_historial@liga.com', 'Tesorero');

        $this->actingAs($tesorero)->get(route('equipos.historial.index'))->assertForbidden();
        $this->actingAs($tesorero)->get(route('equipos.historial.show', $this->equipoActual))->assertForbidden();
    }

    // CA2: el historial de la persona muestra sus roles en cada temporada, de la más reciente a la más antigua
    public function test_el_historial_de_la_persona_muestra_sus_roles_por_temporada(): void
    {
        $persona = Persona::factory()->create();
        PersonaRol::factory()->create(['persona_id' => $persona->id, 'rol' => 'jugador', 'temporada_id' => $this->anterior->id]);
        PersonaRol::factory()->create(['persona_id' => $persona->id, 'rol' => 'delegado', 'temporada_id' => $this->actual->id]);
        PersonaRol::factory()->create(['persona_id' => $persona->id, 'rol' => 'arbitro', 'temporada_id' => $this->actual->id]);

        $this->actingAs($this->secretario)
            ->get(route('personas.show', $persona))
            ->assertOk()
            ->assertSee('Historial de roles por temporada')
            ->assertSeeInOrder([
                'Temporada 2026-2027 de prueba', '01/08/2026', 'Delegado', 'Árbitro',
                'Temporada 2025-2026 de prueba', 'Cerrada · solo consulta', '01/08/2025', 'Jugador',
            ]);
    }

    private function equipo(Temporada $temporada, string $categoria, string $grupo, string $nombre, ?Club $club = null): Equipo
    {
        $categoriaCreada = Categoria::factory()->create(['temporada_id' => $temporada->id, 'nombre' => $categoria]);
        $grupoCreado = Grupo::factory()->create(['categoria_id' => $categoriaCreada->id, 'temporada_id' => $temporada->id, 'nombre' => $grupo]);

        return ($club ?? $this->club)->equipos()->create([
            'temporada_id' => $temporada->id,
            'grupo_id' => $grupoCreado->id,
            'nombre' => $nombre,
        ]);
    }

    private function crearUsuario(string $email, string $rol, ?int $clubId = null): User
    {
        $usuario = User::create([
            'name' => 'Usuario '.$rol,
            'email' => $email,
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            // La columna rol usa «Directivo Club» para los roles de club, como en TarjetonDigitalTest
            'rol' => $clubId === null ? $rol : 'Directivo Club',
            'club_id' => $clubId,
        ]);

        $usuario->assignRole($rol);

        return $usuario;
    }
}
