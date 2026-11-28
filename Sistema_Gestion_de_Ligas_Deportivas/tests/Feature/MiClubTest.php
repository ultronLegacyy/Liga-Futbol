<?php

namespace Tests\Feature;

use App\Models\CatalogoArticulo;
use App\Models\Equipo;
use App\Models\ExpedienteDisciplinario;
use App\Models\Jornada;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Sancion;
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
        // «Mi club» muestra solo jornadas publicadas, como el portal
        Jornada::query()->whereKey($this->partido->jornada_id)->update(['estado' => Jornada::ESTADO_PUBLICADA]);
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

    // Revisión del #111: una persona cuya única inscripción está en «Baja» ya no ve su antiguo equipo
    public function test_el_jugador_dado_de_baja_no_ve_su_antiguo_equipo(): void
    {
        $baja = Jugador::query()->where('equipo_id', $this->partido->equipo_local_id)->where('estado_elegibilidad', 'Baja')->firstOrFail();

        $this->actingAs($this->cuenta('Jugador', ['persona_id' => $baja->persona_id]))->get(route('mi-club'))
            ->assertOk()
            ->assertSee('Vincula tu cuenta con tu registro de jugador')
            ->assertDontSee($this->partido->local->nombre);
    }

    // Revisión del #111: el castigo de quien se fue del club (inscripción en «Baja») ya no sale en el club
    public function test_los_castigos_del_club_no_incluyen_a_quien_esta_de_baja(): void
    {
        $local = $this->partido->local;
        $activo = Jugador::query()->where('equipo_id', $local->id)->where('estado_elegibilidad', 'Activo')->with('persona')->orderBy('id')->firstOrFail();
        $baja = Jugador::query()->where('equipo_id', $local->id)->where('estado_elegibilidad', 'Baja')->with('persona')->firstOrFail();
        $this->sancionPublicada($activo);
        $this->sancionPublicada($baja);

        $this->actingAs($this->cuenta('Delegado del Club', ['club_id' => $local->club_id]))->get(route('mi-club'))
            ->assertOk()
            ->assertSee(trim($activo->persona->nombre.' '.$activo->persona->apellidos))
            ->assertDontSee(trim($baja->persona->nombre.' '.$baja->persona->apellidos));
    }

    // Revisión del #111: un Delegado sin club que también es Jugador ve solo su equipo, como jugador
    public function test_delegado_sin_club_que_es_jugador_ve_solo_su_equipo(): void
    {
        $jugador = Jugador::query()->where('equipo_id', $this->partido->equipo_local_id)->where('estado_elegibilidad', 'Activo')->orderBy('id')->firstOrFail();
        $otroEquipo = Equipo::query()->create([
            'club_id' => $this->partido->local->club_id,
            'grupo_id' => $this->partido->local->grupo_id,
            'nombre' => 'Otro equipo del club',
        ]);
        $cuenta = $this->cuenta('Delegado del Club', ['persona_id' => $jugador->persona_id]);
        $cuenta->assignRole('Jugador');

        $this->actingAs($cuenta)->get(route('mi-club'))
            ->assertOk()
            ->assertSee('Tu equipo')
            ->assertSee($this->partido->local->nombre)
            ->assertDontSee($otroEquipo->nombre)
            ->assertDontSee(route('jugadores.create'), false);
    }

    // Revisión del #111: un partido de una jornada en revisión todavía no se muestra
    public function test_no_muestra_partidos_de_jornadas_sin_publicar(): void
    {
        Jornada::query()->whereKey($this->partido->jornada_id)->update(['estado' => Jornada::ESTADO_EN_REVISION]);
        $local = $this->partido->local;

        $this->actingAs($this->cuenta('Delegado del Club', ['club_id' => $local->club_id]))->get(route('mi-club'))
            ->assertOk()
            ->assertDontSee($this->partido->visitante->nombre);
    }

    private function sancionPublicada(Jugador $jugador): Sancion
    {
        $tribunal = $this->cuenta('Tribunal de Penas');
        $articulo = CatalogoArticulo::query()->firstOrCreate(['codigo' => 'ART-MICLUB'], ['descripcion' => 'Conducta antideportiva', 'castigo_base_partidos' => 2]);
        $expediente = ExpedienteDisciplinario::create([
            'folio' => 'EXP-MICLUB-'.$jugador->id,
            'partido_id' => $this->partido->id,
            'articulo_id' => $articulo->id,
            'titulo' => 'Expediente de Mi club',
            'descripcion_hechos' => 'Hechos de prueba',
            'origen' => 'cedula_arbitral',
            'estado' => ExpedienteDisciplinario::ESTADO_ABIERTO,
            'fecha_apertura' => now(),
            'generado_automaticamente' => true,
        ]);

        return Sancion::create([
            'expediente_id' => $expediente->id,
            'persona_id' => $jugador->persona_id,
            'articulo_id' => $articulo->id,
            'tipo_sancion' => Sancion::TIPO_PARTIDOS,
            'descripcion_sancion' => 'Suspensión',
            'partidos_suspendidos' => 2,
            'partidos_cumplidos' => 0,
            'fecha_inicio' => '2026-11-08',
            'estado' => Sancion::ESTADO_VIGENTE,
            'publicada_en_boletin' => true,
            'validado_por_usuario_id' => $tribunal->id,
            'fecha_validacion' => now(),
            'autorizado_por_usuario_id' => $tribunal->id,
            'autorizado_por_cargo' => 'Tribunal de Penas',
            'fecha_publicacion' => now(),
        ]);
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
