<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
}
