<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\MenuPanel;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * HAB-S7 · S7-T02 (SCRUM-235, IU-02, RNF-29): marco del panel con menú por área e inicio por rol.
 */
class PanelInicioTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_el_inicio_requiere_sesion(): void
    {
        $this->get(route('panel.inicio'))->assertRedirect(route('login'));
    }

    // Cada rol ve solo las tarjetas y el menú de sus módulos
    public function test_el_tesorero_ve_finanzas_y_no_gobierno_ni_disciplina(): void
    {
        $tesorero = $this->usuario('Tesorero');

        $this->actingAs($tesorero)->get(route('panel.inicio'))
            ->assertOk()
            ->assertSee('Finanzas')
            ->assertSee('Cuotas y cargos')
            ->assertSee('Adeudos por club')
            ->assertSee('Reporte financiero')
            ->assertSee(route('cargos.index'), false)
            ->assertDontSee('Gobernanza')
            ->assertDontSee('Roles y permisos')
            ->assertDontSee('Expedientes disciplinarios')
            ->assertDontSee(route('admin.usuarios.index'), false);
    }

    public function test_el_tribunal_ve_disciplina_y_no_finanzas(): void
    {
        $this->actingAs($this->usuario('Tribunal de Penas'))->get(route('panel.inicio'))
            ->assertOk()
            ->assertSee('Disciplina')
            ->assertSee('Expedientes disciplinarios')
            ->assertDontSee('Cuotas y cargos')
            ->assertDontSee(route('cargos.index'), false);
    }

    // Ninguna opción del menú responde 403 al rol que la ve
    public function test_ninguna_opcion_del_menu_responde_403(): void
    {
        foreach (['Tesorero', 'Secretario', 'Presidente', 'Tribunal de Penas', 'Comisión Arbitral', 'Capturista', 'Programador de Partidos', 'Comision de la Rama Infantil', 'Delegado del Club', 'Presidente del Club', 'Jugador'] as $rol) {
            $usuario = $this->usuario($rol);

            foreach (MenuPanel::para($usuario) as $area) {
                foreach ($area['opciones'] as $opcion) {
                    $estado = $this->actingAs($usuario)->get(route($opcion['ruta']))->getStatusCode();
                    $this->assertNotSame(403, $estado, "{$rol} ve «{$opcion['texto']}» y recibe 403");
                }
            }
        }
    }

    // S7-T04: el árbitro empieza en «Mis partidos»; los demás conservan el módulo de HU-071 a HU-073
    public function test_el_arbitro_empieza_en_mis_partidos(): void
    {
        $this->assertSame(route('cedulas.mis-partidos'), $this->usuario('Árbitro en Campo')->rutaInicio());
        $this->assertSame(route('arbitros.index'), $this->usuario('Comisión Arbitral')->rutaInicio());
        $this->assertSame(route('cargos.index'), $this->usuario('Tesorero')->rutaInicio());
    }

    public function test_el_marco_tiene_menu_inferior_saltar_contenido_y_saludo(): void
    {
        $usuario = $this->usuario('Secretario', 'Ana Secretaria');

        $html = $this->actingAs($usuario)->get(route('panel.inicio'))->assertOk()
            ->assertSee('Saltar al contenido')
            ->assertSee('Menú rápido')
            ->assertSee('aria-controls="menu-lateral"', false)
            ->assertSee('Ana')
            ->assertSee('Cerrar Sesión')
            ->getContent();

        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('panel.inicio'), '/').'"[^>]*aria-current="page"/', $html);
    }

    private function usuario(string $rol, ?string $nombre = null): User
    {
        $usuario = User::query()->create([
            'name' => $nombre ?? $rol.' '.str()->random(5),
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'rol' => 'Consultor',
            'estatus' => 'activo',
        ]);
        $usuario->assignRole($rol);

        return $usuario;
    }
}
