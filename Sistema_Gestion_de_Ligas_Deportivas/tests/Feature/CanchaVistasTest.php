<?php

namespace Tests\Feature;

use App\Models\Cancha;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// Vistas del catálogo de canchas y sedes (S2-T24, HU-023)
class CanchaVistasTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = $this->crearUsuario('admin_vistas_canchas@liga.com', 'Administrador General');
    }

    // CA1: el catálogo muestra las canchas con su capacidad y el acceso al alta
    public function test_el_administrador_ve_el_catalogo_con_sus_canchas(): void
    {
        Cancha::factory()->create(['nombre' => 'Cancha 7 U.D.C.', 'capacidad_partidos_consecutivos' => 4]);

        $this->actingAs($this->admin)->get(route('canchas.index'))
            ->assertOk()
            ->assertViewIs('canchas.index')
            ->assertSee('Cancha 7 U.D.C.')
            ->assertSee(route('canchas.create'), false)
            ->assertSee(route('canchas.index'), false);
    }

    public function test_el_catalogo_vacio_lo_indica(): void
    {
        Cancha::query()->delete();

        $this->actingAs($this->admin)->get(route('canchas.index'))
            ->assertOk()
            ->assertSee('No hay canchas registradas.');
    }

    // CA1: el alta desde el formulario deja la cancha en el catálogo
    public function test_el_alta_desde_el_formulario_vuelve_al_catalogo_con_la_cancha(): void
    {
        $this->actingAs($this->admin)->get(route('canchas.create'))
            ->assertOk()
            ->assertSee('Registrar cancha')
            ->assertSee('name="capacidad_partidos_consecutivos"', false);

        $this->actingAs($this->admin)
            ->from(route('canchas.create'))
            ->post(route('canchas.store'), [
                'nombre' => 'Campo Particular Las Flores',
                'direccion_ubicacion' => 'Av. Las Flores 120, Morelia',
                'capacidad_partidos_consecutivos' => 2,
            ])
            ->assertRedirect(route('canchas.index'));

        $this->actingAs($this->admin)->get(route('canchas.index'))
            ->assertSee('Cancha registrada correctamente.')
            ->assertSee('Campo Particular Las Flores');
    }

    // CA2: la edición parte de los datos actuales y los cambios se ven al consultarla
    public function test_la_edicion_muestra_los_datos_actuales_y_el_detalle_los_cambios(): void
    {
        $cancha = Cancha::factory()->create(['nombre' => 'Cancha 3 U.D.C.', 'capacidad_partidos_consecutivos' => 2]);

        $this->actingAs($this->admin)->get(route('canchas.edit', $cancha))
            ->assertOk()
            ->assertSee('value="Cancha 3 U.D.C."', false)
            ->assertSee('value="2"', false);

        $this->actingAs($this->admin)
            ->from(route('canchas.edit', $cancha))
            ->put(route('canchas.update', $cancha), [
                'nombre' => 'Cancha 3 U.D.C. (techada)',
                'direccion_ubicacion' => null,
                'capacidad_partidos_consecutivos' => 5,
            ])
            ->assertRedirect(route('canchas.show', $cancha));

        $this->actingAs($this->admin)->get(route('canchas.show', $cancha))
            ->assertOk()
            ->assertViewIs('canchas.show')
            ->assertSee('Cancha actualizada correctamente.')
            ->assertSee('Cancha 3 U.D.C. (techada)')
            ->assertSee('Sin dirección registrada');
    }

    // CA3: un dato inválido vuelve al formulario con el mensaje en español y conserva lo capturado
    public function test_un_dato_invalido_muestra_el_error_en_espanol_en_el_formulario(): void
    {
        $this->actingAs($this->admin)
            ->from(route('canchas.create'))
            ->followingRedirects()
            ->post(route('canchas.store'), [
                'nombre' => '',
                'direccion_ubicacion' => 'Calle Uno 1',
                'capacidad_partidos_consecutivos' => 0,
            ])
            ->assertOk()
            ->assertViewIs('canchas.create')
            ->assertSee('El nombre de la cancha es obligatorio.')
            ->assertSee('La capacidad de partidos consecutivos debe ser de al menos 1.')
            ->assertSee('value="Calle Uno 1"', false)
            ->assertSee('aria-invalid="true"', false);
    }

    // CA4: quien solo consulta ve el catálogo sin alta ni edición, y esas pantallas le dan 403
    public function test_un_rol_de_consulta_no_ve_alta_ni_edicion(): void
    {
        // El Secretario consulta canchas, pero no las registra ni las edita
        $secretario = $this->crearUsuario('secretario_vistas_canchas@liga.com', 'Secretario');
        $cancha = Cancha::factory()->create(['nombre' => 'Cancha 9 U.D.C.']);

        $this->actingAs($secretario)->get(route('canchas.index'))
            ->assertOk()
            ->assertSee('Cancha 9 U.D.C.')
            ->assertDontSee(route('canchas.create'), false)
            ->assertDontSee(route('canchas.edit', $cancha), false);

        $this->actingAs($secretario)->get(route('canchas.show', $cancha))
            ->assertOk()
            ->assertDontSee(route('canchas.edit', $cancha), false);

        $this->actingAs($secretario)->get(route('canchas.create'))->assertForbidden();
        $this->actingAs($secretario)->get(route('canchas.edit', $cancha))->assertForbidden();
    }

    // Sin permiso para consultar canchas no hay catálogo
    public function test_sin_permiso_no_hay_catalogo(): void
    {
        $tesorero = $this->crearUsuario('tesorero_vistas_canchas@liga.com', 'Tesorero');
        $cancha = Cancha::factory()->create();

        $this->actingAs($tesorero)->get(route('canchas.index'))->assertForbidden();
        $this->actingAs($tesorero)->get(route('canchas.show', $cancha))->assertForbidden();
        $this->actingAs($tesorero)->get(route('canchas.create'))->assertForbidden();
    }

    // El menú lateral enlaza el catálogo solo a quien puede consultarlo
    public function test_el_menu_enlaza_las_canchas_segun_el_permiso(): void
    {
        $enlace = 'href="'.route('canchas.index').'"';

        // El listado de temporadas usa el mismo layout que el resto del panel
        $this->actingAs($this->admin)->get(route('temporadas.index'))
            ->assertOk()
            ->assertSee($enlace, false);

        // Sin campos.ver no aparece el enlace; se dibuja el layout directo porque el Tesorero no tiene ninguna pantalla del panel
        $tesorero = $this->crearUsuario('tesorero_menu_canchas@liga.com', 'Tesorero');
        $this->actingAs($tesorero);

        $this->assertStringNotContainsString($enlace, view('layouts.admin')->render());
    }

    // La columna rol solo admite los roles del Sprint 1; el rol de Spatie es el que autoriza
    private function crearUsuario(string $email, string $rol): User
    {
        $usuario = User::create([
            'name' => 'Usuario '.$rol,
            'email' => $email,
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => $rol,
        ]);

        $usuario->assignRole($rol);

        return $usuario;
    }
}
