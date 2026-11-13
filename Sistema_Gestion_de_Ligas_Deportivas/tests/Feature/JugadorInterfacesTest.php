<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class JugadorInterfacesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_jugador_puede_abrir_formulario_de_vinculacion(): void
    {
        $jugador = $this->crearUsuario('Jugador');

        $this->actingAs($jugador)
            ->get(route('jugadores.vincular.form'))
            ->assertOk()
            ->assertViewIs('jugadores.vincular')
            ->assertSee('name="curp"', false);
    }

    public function test_usuario_sin_rol_jugador_no_puede_abrir_formulario(): void
    {
        $usuario = $this->crearUsuario('Tesorero');

        $this->actingAs($usuario)
            ->get(route('jugadores.vincular.form'))
            ->assertForbidden();
    }

    public function test_administrador_ve_boton_para_jugador_vinculado(): void
    {
        $admin = $this->crearUsuario('Administrador General');
        $jugador = $this->crearUsuario('Jugador');

        $persona = Persona::query()->create([
            'nombre' => 'Jugador',
            'apellidos' => 'Interfaz Test',
            'fecha_nacimiento' => '2006-01-01',
            'curp' => 'INTE060101HDFABC01',
        ]);

        $jugador->forceFill(['persona_id' => $persona->id])->save();

        $this->actingAs($admin)
            ->get(route('admin.usuarios.index'))
            ->assertOk()
            ->assertSee(route('admin.jugadores.vinculo.destroy', $jugador), false)
            ->assertSee('Desvincular cuenta');
    }

    public function test_administrador_no_ve_boton_para_jugador_sin_vinculacion(): void
    {
        $admin = $this->crearUsuario('Administrador General');
        $jugador = $this->crearUsuario('Jugador');

        $this->actingAs($admin)
            ->get(route('admin.usuarios.index'))
            ->assertOk()
            ->assertDontSee(route('admin.jugadores.vinculo.destroy', $jugador), false)
            ->assertDontSee('Desvincular cuenta');
    }

    private function crearUsuario(string $rol): User
    {
        $usuario = User::query()->create([
            'name' => 'Usuario '.$rol,
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => $rol,
        ]);

        $usuario->assignRole($rol);

        return $usuario;
    }
}
