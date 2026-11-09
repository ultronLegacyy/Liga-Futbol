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
            ->assertJsonPath('mensaje', 'Si los datos corresponden a un registro elegible, la cuenta quedará vinculada.');

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

    public function test_curp_con_formato_invalido_se_rechaza_en_espanol_sin_consultar_registros(): void
    {
        $cuenta = $this->crearCuentaJugador();

        $this->actingAs($cuenta)
            ->postJson(route('jugadores.vincular'), ['curp' => '1234567890ABCDEFGH'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['curp' => 'La CURP no tiene un formato válido.']);

        $this->assertDatabaseHas('usuarios', ['id' => $cuenta->id, 'persona_id' => null]);
    }

    public function test_cinco_fallos_bloquean_vinculacion_durante_quince_minutos(): void
    {
        $temporada = Temporada::factory()->activa()->create();
        $persona = $this->crearPersona('VINC900101HDFABC01');

        PersonaRol::query()->create([
            'persona_id' => $persona->id,
            'rol' => 'jugador',
            'temporada_id' => $temporada->id,
        ]);

        $cuenta = $this->crearCuentaJugador();
        $this->actingAs($cuenta);

        for ($intento = 0; $intento < 5; $intento++) {
            $this->postJson(route('jugadores.vincular'), [
                'curp' => 'SINP900101HDFABC01',
            ])->assertOk();
        }

        // Una CURP correcta no debe vincularse durante el bloqueo.
        $this->postJson(route('jugadores.vincular'), [
            'curp' => $persona->curp,
        ])->assertOk();

        $this->assertDatabaseHas('usuarios', [
            'id' => $cuenta->id,
            'persona_id' => null,
        ]);

        // Al finalizar el bloqueo, la vinculacion debe funcionar.
        $this->travel(16)->minutes();

        $this->postJson(route('jugadores.vincular'), [
            'curp' => $persona->curp,
        ])->assertOk();

        $this->assertDatabaseHas('usuarios', [
            'id' => $cuenta->id,
            'persona_id' => $persona->id,
        ]);
    }

    public function test_solo_administrador_general_desvincula_conserva_rol_y_permite_revincular_con_auditoria(): void
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
            'rol' => 'Jugador',
        ]);
        $this->assertTrue($cuentaJugador->fresh()->hasRole('Jugador'));
        // Se conserva el rol de la cuenta y el historial deportivo de la persona.
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

        // El jugador puede volver a vincular su cuenta con la misma CURP.
        $this->actingAs($cuentaJugador->fresh())
            ->postJson(route('jugadores.vincular'), ['curp' => $persona->curp])
            ->assertOk();

        $this->assertDatabaseHas('usuarios', [
            'id' => $cuentaJugador->id,
            'persona_id' => $persona->id,
            'rol' => 'Jugador',
        ]);
        $this->assertTrue($cuentaJugador->fresh()->hasRole('Jugador'));

        // El jugador puede volver a vincular su cuenta con la misma CURP.
        $this->actingAs($cuentaJugador->fresh())
            ->postJson(route('jugadores.vincular'), ['curp' => $persona->curp])
            ->assertOk();

        $this->assertDatabaseHas('usuarios', [
            'id' => $cuentaJugador->id,
            'persona_id' => $persona->id,
            'rol' => 'Jugador',
        ]);
        $this->assertTrue($cuentaJugador->fresh()->hasRole('Jugador'));
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
