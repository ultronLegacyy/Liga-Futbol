<?php

namespace Tests\Feature;

use App\Http\Controllers\CargoFinancieroController;
use App\Models\AuditoriaLog;
use App\Models\CargoFinanciero;
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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// HU-050 (SCRUM-178 y SCRUM-179): pantallas, permisos y criterios del registro de cuotas y cargos (RF-13.1)
class CargoFinancieroTest extends TestCase
{
    use DatabaseTransactions;

    private User $tesorero;

    private Temporada $temporada;

    private Grupo $grupo;

    private Club $club;

    private Equipo $equipo;

    private Persona $jugador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->tesorero = $this->crearUsuario('tesorero.cargos@liga.test', 'Tesorero');
        $this->grupo = Grupo::factory()->create();
        $this->temporada = Temporada::findOrFail($this->grupo->temporada_id);
        $this->temporada->update(['estado' => 'activa', 'nombre' => 'Temporada Cargos HU-050']);
        $this->club = Club::create(['nombre' => 'Club Pantallas HU-050']);
        $this->equipo = Equipo::create([
            'club_id' => $this->club->id,
            'temporada_id' => $this->temporada->id,
            'grupo_id' => $this->grupo->id,
            'nombre' => 'Equipo Pantallas HU-050',
        ]);
        $this->jugador = Persona::factory()->create(['nombre' => 'Mario', 'apellidos' => 'Jugador Cuota', 'curp' => 'JUCM100101HMNRRR01']);
        PersonaRol::factory()->create(['persona_id' => $this->jugador->id, 'rol' => 'jugador', 'temporada_id' => $this->temporada->id]);
    }

    // Criterio 1: la cuota de inscripción de un club, equipo o jugador queda asociada a su temporada
    #[DataProvider('inscripciones')]
    public function test_la_inscripcion_queda_asociada_a_su_temporada(string $tipo, bool $conEquipo, bool $conPersona): void
    {
        $respuesta = $this->actingAs($this->tesorero)->post(route('cargos.store'), $this->datos($tipo, $conEquipo, $conPersona));

        $cargo = CargoFinanciero::query()->latest('id')->firstOrFail();
        $respuesta->assertRedirect(route('cargos.show', $cargo))->assertSessionHas('success', 'Cargo registrado como pendiente.');

        $this->assertSame('Inscripcion', $cargo->concepto);
        $this->assertSame($this->temporada->id, $cargo->temporada_id);
        $this->assertSame($this->club->id, $cargo->club_id);
        $this->assertSame($conEquipo ? $this->equipo->id : null, $cargo->equipo_id);
        $this->assertSame($conPersona ? $this->jugador->id : null, $cargo->persona_id);
        $this->assertSame('Pendiente', $cargo->estado);

        $this->actingAs($this->tesorero)->get(route('cargos.index', ['temporada_id' => $this->temporada->id]))
            ->assertOk()
            ->assertSee('Temporada Cargos HU-050')
            ->assertSee('Inscripción');
    }

    /**
     * @return array<string, array{string, bool, bool}>
     */
    public static function inscripciones(): array
    {
        return [
            'club' => ['inscripcion_club', false, false],
            'equipo' => ['inscripcion_equipo', true, false],
            'jugador sin equipo' => ['inscripcion_jugador', false, true],
            'jugador con equipo' => ['inscripcion_jugador', true, true],
        ];
    }

    // Criterio 2: el pago de arbitraje o de cancha queda vinculado al club o equipo que lo generó
    #[DataProvider('pagosDeServicio')]
    public function test_el_cargo_de_arbitraje_o_cancha_queda_vinculado_a_su_club_y_equipo(string $tipo, string $concepto): void
    {
        $this->actingAs($this->tesorero)->post(route('cargos.store'), $this->datos($tipo, true))->assertRedirect();

        $cargo = CargoFinanciero::query()->latest('id')->firstOrFail();
        $this->assertSame($concepto, $cargo->concepto);
        $this->assertSame($this->club->id, $cargo->club_id);
        $this->assertSame($this->equipo->id, $cargo->equipo_id);
        $this->assertNull($cargo->persona_id);

        $this->actingAs($this->tesorero)->get(route('cargos.show', $cargo))
            ->assertOk()
            ->assertSee('Club Pantallas HU-050')
            ->assertSee('Equipo Pantallas HU-050');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function pagosDeServicio(): array
    {
        return [
            'arbitraje' => ['arbitraje', 'Arbitraje'],
            'cancha' => ['cancha', 'Cancha'],
        ];
    }

    // Un campo que el caso no usa se descarta aunque llegue
    public function test_la_inscripcion_de_un_club_descarta_equipo_y_persona(): void
    {
        $this->actingAs($this->tesorero)->post(route('cargos.store'), $this->datos('inscripcion_club', true, true))->assertRedirect();

        $cargo = CargoFinanciero::query()->latest('id')->firstOrFail();
        $this->assertNull($cargo->equipo_id);
        $this->assertNull($cargo->persona_id);
    }

    public function test_el_listado_filtra_por_temporada_club_y_concepto(): void
    {
        $otroClub = Club::create(['nombre' => 'Club Filtrado HU-050']);
        CargoFinanciero::factory()->create(['club_id' => $this->club->id, 'temporada_id' => $this->temporada->id, 'concepto' => 'Cancha']);
        CargoFinanciero::factory()->create(['club_id' => $otroClub->id, 'temporada_id' => $this->temporada->id, 'concepto' => 'Arbitraje']);

        $this->actingAs($this->tesorero)->get(route('cargos.index', ['club_id' => $this->club->id, 'concepto' => 'Cancha']))
            ->assertOk()
            ->assertSee('Club Pantallas HU-050')
            ->assertDontSee('<td class="px-4 py-3 font-semibold text-white">Club Filtrado HU-050</td>', false);

        $this->actingAs($this->tesorero)->get(route('cargos.index', ['concepto' => 'Arbitraje', 'temporada_id' => $this->temporada->id]))
            ->assertOk()
            ->assertSee('<td class="px-4 py-3 font-semibold text-white">Club Filtrado HU-050</td>', false)
            ->assertDontSee('<td class="px-4 py-3 font-semibold text-white">Club Pantallas HU-050</td>', false);
    }

    // La CURP no se muestra en el listado ni en el detalle
    public function test_no_se_muestra_la_curp_del_jugador(): void
    {
        $this->actingAs($this->tesorero)->post(route('cargos.store'), $this->datos('inscripcion_jugador', false, true));
        $cargo = CargoFinanciero::query()->latest('id')->firstOrFail();

        $this->actingAs($this->tesorero)->get(route('cargos.index'))->assertOk()->assertSee('Jugador Cuota, Mario')->assertDontSee('JUCM100101HMNRRR01');
        $this->actingAs($this->tesorero)->get(route('cargos.show', $cargo))->assertOk()->assertDontSee('JUCM100101HMNRRR01');
        $this->actingAs($this->tesorero)->get(route('cargos.create', ['tipo' => 'inscripcion_jugador', 'temporada_id' => $this->temporada->id]))
            ->assertOk()->assertSee('Jugador Cuota, Mario')->assertDontSee('JUCM100101HMNRRR01');
    }

    // Una temporada cerrada se consulta, pero no admite cargos nuevos (RF-01.4)
    public function test_una_temporada_cerrada_se_consulta_pero_no_admite_cargos(): void
    {
        $cargo = CargoFinanciero::factory()->create(['club_id' => $this->club->id, 'temporada_id' => $this->temporada->id]);
        $this->temporada->update(['estado' => 'cerrada']);

        $this->actingAs($this->tesorero)->get(route('cargos.show', $cargo))->assertOk()->assertSee('La temporada está cerrada: este cargo solo se consulta.');
        $this->actingAs($this->tesorero)->get(route('cargos.index', ['temporada_id' => $this->temporada->id]))->assertOk()->assertSee('Club Pantallas HU-050');
        $this->actingAs($this->tesorero)->get(route('cargos.create'))->assertOk()->assertDontSee('Temporada Cargos HU-050');
        // Con la temporada cerrada en la URL se queda en el paso 1
        $this->actingAs($this->tesorero)->get(route('cargos.create', ['tipo' => 'cancha', 'temporada_id' => $this->temporada->id]))
            ->assertOk()->assertSee('Continuar')->assertDontSee('Club al que se carga');

        $antes = CargoFinanciero::query()->count();
        $this->actingAs($this->tesorero)->post(route('cargos.store'), $this->datos('cancha'))
            ->assertSessionHasErrors(['temporada_id' => 'La temporada está cerrada: sus cargos solo se consultan (RF-01.4).']);
        $this->assertSame($antes, CargoFinanciero::query()->count());
    }

    // Permisos: el Tesorero y el Administrador General pueden ver y registrar
    public function test_el_administrador_general_puede_ver_y_registrar(): void
    {
        $admin = $this->crearUsuario('admin.cargos@liga.test', 'Administrador General');

        $this->actingAs($admin)->get(route('cargos.index'))->assertOk();
        $this->actingAs($admin)->get(route('cargos.create'))->assertOk();
        $this->actingAs($admin)->post(route('cargos.store'), $this->datos('arbitraje'))->assertRedirect();
        $this->actingAs($admin)->get(route('cargos.show', CargoFinanciero::query()->latest('id')->firstOrFail()))->assertOk();
    }

    #[DataProvider('rolesSinAcceso')]
    public function test_los_roles_sin_permiso_reciben_403_al_ver_y_registrar(string $rol): void
    {
        $usuario = $this->crearUsuario(str()->slug($rol).'.cargos@liga.test', $rol);
        $cargo = CargoFinanciero::factory()->create(['club_id' => $this->club->id, 'temporada_id' => $this->temporada->id]);
        $antes = CargoFinanciero::query()->count();

        $this->actingAs($usuario)->get(route('cargos.index'))->assertForbidden();
        $this->actingAs($usuario)->get(route('cargos.show', $cargo))->assertForbidden();
        $this->actingAs($usuario)->get(route('cargos.create'))->assertForbidden();
        $this->actingAs($usuario)->post(route('cargos.store'), $this->datos('arbitraje'))->assertForbidden();

        $this->assertSame($antes, CargoFinanciero::query()->count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rolesSinAcceso(): array
    {
        return [
            'Secretario' => ['Secretario'],
            'Programador de Partidos' => ['Programador de Partidos'],
            'Jugador' => ['Jugador'],
        ];
    }

    public function test_el_menu_muestra_cuotas_y_cargos_solo_con_permiso(): void
    {
        $this->actingAs($this->tesorero)->get(route('cargos.index'))->assertSee('Cuotas y cargos');
        $this->actingAs($this->crearUsuario('secretario.menu@liga.test', 'Secretario'))->get(route('personas.index'))
            ->assertDontSee(route('cargos.index'));
    }

    // Validación en español de cada campo
    #[DataProvider('camposInvalidos')]
    public function test_la_validacion_responde_en_espanol(string $tipo, array $cambios, string $campo, string $mensaje): void
    {
        $antes = CargoFinanciero::query()->count();

        $this->actingAs($this->tesorero)
            ->from(route('cargos.create', ['tipo' => $tipo, 'temporada_id' => $this->temporada->id]))
            ->post(route('cargos.store'), array_merge($this->datos($tipo, $tipo === 'inscripcion_equipo', $tipo === 'inscripcion_jugador'), $cambios))
            ->assertRedirect(route('cargos.create', ['tipo' => $tipo, 'temporada_id' => $this->temporada->id]))
            ->assertSessionHasErrors([$campo => $mensaje]);

        $this->assertSame($antes, CargoFinanciero::query()->count());
    }

    /**
     * @return array<string, array{string, array<string, mixed>, string, string}>
     */
    public static function camposInvalidos(): array
    {
        return [
            'tipo inválido' => ['arbitraje', ['tipo' => 'multa'], 'tipo', 'El tipo de cargo seleccionado no es válido.'],
            'tipo vacío' => ['arbitraje', ['tipo' => ''], 'tipo', 'El tipo de cargo es obligatorio.'],
            'temporada vacía' => ['arbitraje', ['temporada_id' => ''], 'temporada_id', 'La temporada es obligatoria.'],
            'club vacío' => ['arbitraje', ['club_id' => ''], 'club_id', 'El club es obligatorio.'],
            'club inexistente' => ['arbitraje', ['club_id' => 999999999], 'club_id', 'El club seleccionado no existe.'],
            'equipo de inscripción vacío' => ['inscripcion_equipo', ['equipo_id' => ''], 'equipo_id', 'El equipo es obligatorio para la inscripción de un equipo.'],
            'jugador vacío' => ['inscripcion_jugador', ['persona_id' => ''], 'persona_id', 'El jugador es obligatorio para la inscripción de un jugador.'],
            'monto vacío' => ['arbitraje', ['monto' => ''], 'monto', 'El monto es obligatorio.'],
            'monto cero' => ['arbitraje', ['monto' => '0'], 'monto', 'El monto debe ser mayor que cero.'],
            'monto con tres decimales' => ['arbitraje', ['monto' => '10.555'], 'monto', 'El monto admite como máximo dos decimales.'],
            'vencimiento vacío' => ['arbitraje', ['vencimiento' => ''], 'vencimiento', 'La fecha de vencimiento es obligatoria.'],
            'vencimiento con otro formato' => ['arbitraje', ['vencimiento' => '01/12/2026'], 'vencimiento', 'La fecha de vencimiento debe tener el formato AAAA-MM-DD.'],
        ];
    }

    public function test_un_equipo_de_otro_club_se_rechaza_en_espanol(): void
    {
        $ajeno = Equipo::create([
            'club_id' => Club::create(['nombre' => 'Club Ajeno HU-050'])->id,
            'temporada_id' => $this->temporada->id,
            'grupo_id' => $this->grupo->id,
            'nombre' => 'Equipo Ajeno HU-050',
        ]);

        $this->actingAs($this->tesorero)->post(route('cargos.store'), array_merge($this->datos('cancha'), ['equipo_id' => $ajeno->id]))
            ->assertSessionHasErrors(['equipo_id' => 'El equipo no pertenece al club seleccionado.']);
    }

    // RNF-33: cada cargo deja una sola fila de auditoría
    public function test_cada_cargo_registrado_deja_una_sola_fila_de_auditoria(): void
    {
        $ultimoId = (int) AuditoriaLog::query()->max('id');

        $this->actingAs($this->tesorero)->post(route('cargos.store'), $this->datos('inscripcion_equipo', true))->assertRedirect();

        $log = AuditoriaLog::query()->where('id', '>', $ultimoId)->sole();
        $this->assertSame('cargos_financieros', $log->tabla_afectada);
        $this->assertSame('crear', $log->accion);
        $this->assertSame($this->tesorero->id, $log->usuario_id);
    }

    // Todas las pantallas abren en español y sin @vite
    public function test_las_pantallas_responden_200(): void
    {
        $cargo = CargoFinanciero::factory()->create(['club_id' => $this->club->id, 'temporada_id' => $this->temporada->id]);

        $this->actingAs($this->tesorero)->get(route('cargos.index'))->assertOk()->assertSee('Cuotas de inscripción y cargos');
        $this->actingAs($this->tesorero)->get(route('cargos.create'))->assertOk()->assertSee('Tipo de cargo')->assertSee('Continuar');
        $this->actingAs($this->tesorero)->get(route('cargos.show', $cargo))->assertOk()->assertSee('Vencimiento');

        foreach (array_keys(CargoFinancieroController::TIPOS) as $tipo) {
            $pantalla = $this->actingAs($this->tesorero)->get(route('cargos.create', ['tipo' => $tipo, 'temporada_id' => $this->temporada->id]));
            $pantalla->assertOk()->assertSee('Club al que se carga')->assertSee('Fecha de vencimiento');

            in_array($tipo, ['inscripcion_club'], true) ? $pantalla->assertDontSee('name="equipo_id"', false) : $pantalla->assertSee('name="equipo_id"', false);
            $tipo === 'inscripcion_jugador' ? $pantalla->assertSee('name="persona_id"', false) : $pantalla->assertDontSee('name="persona_id"', false);
        }

        foreach (['index', 'create', 'show'] as $vista) {
            $this->assertStringNotContainsString('@vite', (string) file_get_contents(resource_path("views/cargos/{$vista}.blade.php")));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(string $tipo, bool $conEquipo = false, bool $conPersona = false): array
    {
        return [
            'tipo' => $tipo,
            'temporada_id' => $this->temporada->id,
            'club_id' => $this->club->id,
            'equipo_id' => $conEquipo ? $this->equipo->id : null,
            'persona_id' => $conPersona ? $this->jugador->id : null,
            'monto' => '850.00',
            'vencimiento' => '2026-12-15',
        ];
    }

    // La columna rol es un enum del Sprint 1; lo que autoriza es el rol de Spatie
    private function crearUsuario(string $email, string $rol): User
    {
        $usuario = User::create([
            'name' => 'Usuario '.$rol,
            'email' => $email,
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Consultor',
        ]);
        $usuario->assignRole($rol);

        return $usuario;
    }
}
