<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
use App\Models\Persona;
use App\Models\PersonaRol;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VinculacionJugadorCurpTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_vincula_por_curp_a_persona_con_rol_de_jugador_sin_cuenta_y_audita_sin_guardar_la_curp(): void
    {
        $temporada = Temporada::factory()->activa()->create();
        $persona = $this->crearPersona('VINC900101HDFABC01');
        PersonaRol::query()->create([
            'persona_id' => $persona->id,
            'rol' => 'jugador',
            'temporada_id' => $temporada->id,
        ]);
        $cuenta = $this->crearCuentaJugador();

        $respuesta = $this->actingAs($cuenta)->postJson(route('jugadores.vincular'), [
            'curp' => '  vinc900101hdfabc01 ',
        ])->assertOk()
            ->assertJsonPath('mensaje', 'Si los datos corresponden a un jugador elegible, la cuenta quedará vinculada.');

        $this->assertDatabaseHas('usuarios', ['id' => $cuenta->id, 'persona_id' => $persona->id]);
        $registro = AuditoriaLog::query()
            ->where('accion', 'jugador_vinculado_curp')
            ->where('tabla_afectada', 'usuarios')
            ->where('registro_id', $cuenta->id)
            ->firstOrFail();
        $this->assertSame($cuenta->id, $registro->usuario_id);
        $this->assertSame(['persona_id' => $persona->id], $registro->valores_nuevos);
        $this->assertStringNotContainsString('VINC900101HDFABC01', json_encode($registro->toArray()) ?: '');
        $this->assertSame(200, $respuesta->status());
    }

    public function test_curp_no_encontrada_o_ya_vinculada_devuelve_el_mismo_mensaje_generico(): void
    {
        $temporada = Temporada::factory()->activa()->create();
        $persona = $this->crearPersona('YAUN900101HDFABC01');
        PersonaRol::query()->create([
            'persona_id' => $persona->id,
            'rol' => 'jugador',
            'temporada_id' => $temporada->id,
        ]);
        $cuentaVinculada = $this->crearCuentaJugador($persona);
        $cuentaSinVinculo = $this->crearCuentaJugador();

        $respuestaNoEncontrada = $this->actingAs($cuentaSinVinculo)
            ->postJson(route('jugadores.vincular'), ['curp' => 'NOVE900101HDFABC01'])
            ->assertOk();
        $respuestaYaVinculada = $this->actingAs($cuentaSinVinculo)
            ->postJson(route('jugadores.vincular'), ['curp' => 'YAUN900101HDFABC01'])
            ->assertOk();

        $this->assertSame($respuestaNoEncontrada->json(), $respuestaYaVinculada->json());
        $this->assertDatabaseHas('usuarios', ['id' => $cuentaVinculada->id, 'persona_id' => $persona->id]);
        $this->assertDatabaseHas('usuarios', ['id' => $cuentaSinVinculo->id, 'persona_id' => null]);
    }

    public function test_aplica_cinco_intentos_de_vinculacion_por_minuto(): void
    {
        $cuenta = $this->crearCuentaJugador();
        $this->actingAs($cuenta);

        for ($intento = 0; $intento < 5; $intento++) {
            $this->postJson(route('jugadores.vincular'), ['curp' => 'SINP900101HDFABC01'])->assertOk();
        }

        $this->postJson(route('jugadores.vincular'), ['curp' => 'SINP900101HDFABC01'])->assertStatus(429);
    }

    public function test_solo_administrador_general_desvincula_y_revoca_el_rol_jugador_con_auditoria(): void
    {
        $temporada = Temporada::factory()->activa()->create();
        $persona = $this->crearPersona('QUIT900101HDFABC01');
        PersonaRol::query()->create([
            'persona_id' => $persona->id,
            'rol' => 'jugador',
            'temporada_id' => $temporada->id,
        ]);
        $cuentaJugador = $this->crearCuentaJugador($persona);

        $this->actingAs($cuentaJugador)
            ->deleteJson(route('admin.jugadores.vinculo.destroy', $cuentaJugador))
            ->assertForbidden();

        $administrador = User::query()->create([
            'name' => 'Administrador HU-016',
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'rol' => 'Administrador General',
            'estatus' => 'activo',
        ]);
        $administrador->assignRole('Administrador General');

        $this->actingAs($administrador)
            ->deleteJson(route('admin.jugadores.vinculo.destroy', $cuentaJugador))
            ->assertOk();

        $this->assertDatabaseHas('usuarios', [
            'id' => $cuentaJugador->id,
            'persona_id' => null,
            'rol' => 'Consultor',
        ]);
        $this->assertFalse($cuentaJugador->fresh()->hasRole('Jugador'));
        // Se retira el acceso de la cuenta, sin borrar el rol deportivo ni su historial de temporada.
        $this->assertDatabaseHas('persona_roles', [
            'persona_id' => $persona->id,
            'rol' => 'jugador',
            'temporada_id' => $temporada->id,
        ]);
        $this->assertDatabaseHas('auditoria_logs', [
            'usuario_id' => $administrador->id,
            'accion' => 'jugador_desvinculado',
            'tabla_afectada' => 'usuarios',
            'registro_id' => $cuentaJugador->id,
        ]);
    }

    private function crearPersona(string $curp): Persona
    {
        return Persona::query()->create([
            'nombre' => 'Jugador',
            'apellidos' => 'CURP Test',
            'fecha_nacimiento' => '2010-01-01',
            'curp' => $curp,
        ]);
    }

    private function crearCuentaJugador(?Persona $persona = null): User
    {
        $cuenta = User::query()->create([
            'name' => 'Cuenta jugador',
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'rol' => 'Jugador',
            'estatus' => 'activo',
        ]);
        $cuenta->assignRole(Role::findByName('Jugador', 'web'));

        if ($persona !== null) {
            $cuenta->forceFill(['persona_id' => $persona->id])->save();
        }

        return $cuenta;
    }
}
