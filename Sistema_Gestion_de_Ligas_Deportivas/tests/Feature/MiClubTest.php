<?php

namespace Tests\Feature;

use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreaPartidoConCedula;
use Tests\TestCase;

/**
 * HAB-S7 · S7-T05 (SCRUM-238, RNF-29): «Mi club» muestra a cada quien solo lo suyo.
 */
class MiClubTest extends TestCase
{
    use CreaPartidoConCedula;
    use DatabaseTransactions;

    private Partido $partido;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-10 09:00:00', 'America/Mexico_City'));
        $this->seed(RoleAndPermissionSeeder::class);
        $this->partido = $this->partidoConArbitro();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_el_delegado_ve_su_club_sus_equipos_y_sus_proximos_partidos(): void
    {
        $local = $this->partido->local;
        $delegado = $this->cuenta('Delegado del Club', ['club_id' => $local->club_id]);

        $this->actingAs($delegado)->get(route('mi-club'))
            ->assertOk()
            ->assertSee('Tu club')
            ->assertSee($local->club->nombre)
            ->assertSeeInOrder(['Próximos partidos', '10:00', $local->nombre, $this->partido->visitante->nombre])
            ->assertSeeInOrder(['Equipos', $local->nombre, '3 activos', '1 suspendidos'])
            ->assertSee('Nadie del club tiene castigos vigentes publicados.')
            ->assertSee(route('jugadores.create'), false);
    }

    public function test_el_jugador_ve_solo_su_equipo(): void
    {
        $jugador = Jugador::query()->where('equipo_id', $this->partido->equipo_local_id)->orderBy('id')->firstOrFail();
        $otroEquipo = Equipo::query()->create([
            'club_id' => $this->partido->local->club_id,
            'grupo_id' => $this->partido->local->grupo_id,
            'nombre' => 'Segundo equipo del club',
        ]);
        $cuenta = $this->cuenta('Jugador', ['persona_id' => $jugador->persona_id]);

        $this->actingAs($cuenta)->get(route('mi-club'))
            ->assertOk()
            ->assertSee('Tu equipo')
            ->assertSee($this->partido->local->nombre)
            ->assertDontSee($otroEquipo->nombre)
            ->assertDontSee(route('jugadores.create'), false)
            ->assertSee('«Mi perfil»');
    }

    public function test_sin_club_ligado_explica_que_hacer(): void
    {
        $this->actingAs($this->cuenta('Delegado del Club'))->get(route('mi-club'))
            ->assertOk()->assertSee('Tu cuenta todavía no está ligada a un club.')->assertSee('Secretaría');

        $this->actingAs($this->cuenta('Jugador'))->get(route('mi-club'))
            ->assertOk()->assertSee('Vincula tu cuenta con tu registro de jugador');
    }

    public function test_otros_roles_no_entran_y_el_menu_solo_lo_ofrece_a_clubes(): void
    {
        $tesorero = $this->cuenta('Tesorero');
        $this->actingAs($tesorero)->get(route('mi-club'))->assertForbidden();
        $this->actingAs($tesorero)->get(route('panel.inicio'))->assertOk()->assertDontSee(route('mi-club'), false);

        $presidenteClub = $this->cuenta('Presidente del Club', ['club_id' => $this->partido->local->club_id]);
        $this->actingAs($presidenteClub)->get(route('panel.inicio'))->assertOk()->assertSee(route('mi-club'), false);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function cuenta(string $rol, array $datos = []): User
    {
        $usuario = User::query()->create(array_merge([
            'name' => $rol.' '.str()->random(5),
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'rol' => 'Consultor',
            'estatus' => 'activo',
        ], array_diff_key($datos, ['persona_id' => true])));
        // persona_id no se asigna en masa: se liga como lo hace la vinculación de cuenta
        if (isset($datos['persona_id'])) {
            $usuario->forceFill(['persona_id' => $datos['persona_id']])->save();
        }
        $usuario->assignRole($rol);

        return $usuario;
    }
}
