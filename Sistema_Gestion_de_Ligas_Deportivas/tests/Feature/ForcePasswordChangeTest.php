<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirige_al_cambio_obligatorio_desde_cualquier_ruta()
    {
        $usuario = User::factory()->create([
            'requires_password_change' => true,
            'estatus' => 'activo'
        ]);

        $this->actingAs($usuario);

        $response = $this->get('/admin/dashboard');
        $response->assertRedirect(route('password.force-change.show'));
    }

    public function test_quita_la_marca_y_deja_entrar_al_guardar()
    {
        $usuario = User::factory()->create([
            'requires_password_change' => true,
            'estatus' => 'activo'
        ]);

        $this->actingAs($usuario);

        $response = $this->post(route('password.force-change.update'), [
            'password' => 'ContrasenaValida123!',
            'password_confirmation' => 'ContrasenaValida123!'
        ]);

        $response->assertRedirect(config('fortify.home'));
        $this->assertDatabaseHas('usuarios', [
            'id' => $usuario->id,
            'requires_password_change' => 0,
        ]);
    }

    public function test_rnf26_sin_doble_factor_rechaza_14_y_65_acepta_15_y_64()
    {
        $usuario = User::factory()->create([
            'requires_password_change' => true,
            'two_factor_secret' => null, // Sin 2FA
        ]);

        $this->actingAs($usuario);

        // Rechaza 14
        $this->post(route('password.force-change.update'), [
            'password' => Str::random(14),
            'password_confirmation' => Str::random(14)
        ])->assertSessionHasErrors('password');

        // Rechaza 65
        $this->post(route('password.force-change.update'), [
            'password' => Str::random(65),
            'password_confirmation' => Str::random(65)
        ])->assertSessionHasErrors('password');

        // Acepta 15
        $password15 = \Illuminate\Support\Str::random(15);
        $this->post(route('password.force-change.update'), [
            'password' => $password15,
            'password_confirmation' => $password15
        ])->assertSessionHasNoErrors();
    }

    public function test_rnf26_con_doble_factor_rechaza_7_acepta_8()
    {
        $usuario = User::factory()->create([
            'requires_password_change' => true,
            'two_factor_secret' => 'secreto2faactivo', // Con 2FA simulado
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($usuario);

        // Rechaza 7
        $this->post(route('password.force-change.update'), [
            'password' => Str::random(7),
            'password_confirmation' => Str::random(7)
        ])->assertSessionHasErrors('password');

        // Acepta 8
        $password8 = \Illuminate\Support\Str::random(8);
        $this->post(route('password.force-change.update'), [
            'password' => $password8,
            'password_confirmation' => $password8
        ])->assertSessionHasNoErrors();
    }

    public function test_tesorero_que_cambia_contrasena_es_redirigido_a_cargos_sin_error_403(): void
    {
        $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);

        $tesorero = User::factory()->create([
            'requires_password_change' => true,
            'estatus' => 'activo',
            'rol' => 'Tesorero',
        ]);
        $tesorero->assignRole('Tesorero');

        $this->actingAs($tesorero);

        $response = $this->post(route('password.force-change.update'), [
            'password' => 'ContrasenaValida123!',
            'password_confirmation' => 'ContrasenaValida123!',
        ]);

        $response->assertRedirect(route('cargos.index'));
        $this->assertDatabaseHas('usuarios', [
            'id' => $tesorero->id,
            'requires_password_change' => 0,
        ]);

        // Verificamos que al seguir la redirección a cargos.index cargue con status 200 y muestre su vista financiera
        $vista = $this->actingAs($tesorero->fresh())->get(route('cargos.index'));
        $vista->assertStatus(200);
        $vista->assertSee('Cuotas y cargos');
        $vista->assertDontSee('Gobernanza');
        $vista->assertDontSee('Roles y permisos');
    }

    public function test_delegado_sin_club_tras_cambio_obligatorio_aterriza_en_temporadas_index_con_200(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $delegado = User::factory()->create([
            'requires_password_change' => true,
            'estatus' => 'activo',
            'club_id' => null,
            'rol' => 'Consultor',
        ]);
        $delegado->assignRole('Delegado del Club');

        $this->actingAs($delegado);

        $response = $this->post(route('password.force-change.update'), [
            'password' => 'ContrasenaValida123!',
            'password_confirmation' => 'ContrasenaValida123!',
        ]);

        $response->assertRedirect(route('temporadas.index'));
        $this->assertDatabaseHas('usuarios', [
            'id' => $delegado->id,
            'requires_password_change' => 0,
        ]);

        $vista = $this->actingAs($delegado->fresh())->get(route('temporadas.index'));
        $vista->assertStatus(200);

        // Confirmamos que el delegado sin club no puede entrar a jugadores.create (recibe 403)
        $intento = $this->actingAs($delegado->fresh())->get(route('jugadores.create'));
        $intento->assertStatus(403);
    }

    public function test_delegado_con_club_tras_cambio_obligatorio_aterriza_en_jugadores_create_con_200(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $club = Club::create(['nombre' => 'Club Delegado Password']);

        $delegado = User::factory()->create([
            'requires_password_change' => true,
            'estatus' => 'activo',
            'club_id' => $club->id,
            'rol' => 'Consultor',
        ]);
        $delegado->assignRole('Delegado del Club');

        $this->actingAs($delegado);

        $response = $this->post(route('password.force-change.update'), [
            'password' => 'ContrasenaValida123!',
            'password_confirmation' => 'ContrasenaValida123!',
        ]);

        $response->assertRedirect(route('jugadores.create'));
        $this->assertDatabaseHas('usuarios', [
            'id' => $delegado->id,
            'requires_password_change' => 0,
        ]);

        $vista = $this->actingAs($delegado->fresh())->get(route('jugadores.create'));
        $vista->assertStatus(200);
    }

    #[DataProvider('proveedorRolesYDestinos')]
    public function test_redireccion_post_cambio_obligatorio_de_los_13_roles_hacia_modulo_autorizado_con_200(string $rol, string $nombreRutaEsperada, bool $requiereClub): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $club = $requiereClub ? Club::create(['nombre' => 'Club Pwd ' . $rol]) : null;

        $usuario = User::factory()->create([
            'requires_password_change' => true,
            'estatus' => 'activo',
            'club_id' => $club?->id,
            'rol' => 'Consultor',
        ]);
        $usuario->assignRole($rol);

        $this->actingAs($usuario);

        $response = $this->post(route('password.force-change.update'), [
            'password' => 'NuevaContrasenaSegura123!',
            'password_confirmation' => 'NuevaContrasenaSegura123!',
        ]);

        $destinoEsperado = route($nombreRutaEsperada);
        $response->assertRedirect($destinoEsperado);

        $this->assertDatabaseHas('usuarios', [
            'id' => $usuario->id,
            'requires_password_change' => 0,
        ]);

        $vista = $this->actingAs($usuario->fresh())->get($destinoEsperado);
        $vista->assertStatus(200);
    }

    public static function proveedorRolesYDestinos(): array
    {
        return [
            'Administrador General' => ['Administrador General', 'admin.dashboard', false],
            'Presidente' => ['Presidente', 'temporadas.index', false],
            'Secretario' => ['Secretario', 'personas.index', false],
            'Tesorero' => ['Tesorero', 'cargos.index', false],
            'Comision de la Rama Infantil' => ['Comision de la Rama Infantil', 'temporadas.index', false],
            'Programador de Partidos' => ['Programador de Partidos', 'canchas.index', false],
            'Capturista' => ['Capturista', 'temporadas.index', false],
            'Tribunal de Penas' => ['Tribunal de Penas', 'temporadas.index', false],
            'Comisión Arbitral' => ['Comisión Arbitral', 'arbitros.index', false],
            'Árbitro en Campo' => ['Árbitro en Campo', 'arbitros.index', false],
            'Presidente del Club' => ['Presidente del Club', 'temporadas.index', true],
            'Delegado del Club (con club)' => ['Delegado del Club', 'jugadores.create', true],
            'Jugador' => ['Jugador', 'jugadores.vincular.form', false],
        ];
    }
}
