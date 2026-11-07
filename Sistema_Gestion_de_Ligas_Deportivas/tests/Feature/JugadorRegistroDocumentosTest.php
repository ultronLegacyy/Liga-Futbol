<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Jugador;
use App\Models\Persona;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JugadorRegistroDocumentosTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Role::query()->firstOrCreate(['name' => 'Delegado del Club', 'guard_name' => 'web'])
            ->givePermissionTo('jugadores.crear');
        Storage::fake('private');
    }

    public function test_registro_vincula_jugador_a_equipo_categoria_temporada_y_guarda_documentos_en_disco_privado(): void
    {
        [$club, $delegado, $equipo] = $this->crearEquipoDelegado();
        $persona = $this->crearPersona();

        $respuesta = $this->actingAs($delegado)->post(route('equipos.jugadores.store', $equipo), [
            'persona_id' => $persona->id,
            'dorsal' => 12,
            'documentos' => [
                'ine' => UploadedFile::fake()->create('ine.pdf', 80, 'application/pdf'),
                'curp' => UploadedFile::fake()->create('curp.pdf', 50, 'application/pdf'),
                'fotografia' => $this->crearFotografiaFake('foto.png'),
                'comprobante' => UploadedFile::fake()->create('domicilio.pdf', 75, 'application/pdf'),
            ],
        ])->assertCreated();

        $jugador = Jugador::query()->findOrFail($respuesta->json('inscripcion.id'));
        $this->assertSame($equipo->id, $jugador->equipo_id);
        $this->assertSame($persona->id, $jugador->persona_id);
        $respuesta->assertJsonPath('inscripcion.categoria_id', $equipo->grupo->categoria_id)
            ->assertJsonPath('inscripcion.temporada_id', $equipo->temporada_id);
        $this->assertCount(4, $jugador->documentos);

        foreach ($jugador->documentos as $documento) {
            $this->assertSame('private', $documento->disco);
            Storage::disk('private')->assertExists($documento->ruta_storage);
            $this->assertDatabaseHas('auditoria_logs', [
                'tabla_afectada' => 'jugador_documentos',
                'registro_id' => $documento->id,
                'accion' => 'crear',
            ]);
        }

        $this->assertSame($club->id, $equipo->club_id);
        $this->assertDatabaseHas('auditoria_logs', [
            'tabla_afectada' => 'jugadores_equipos',
            'registro_id' => $jugador->id,
            'motivo' => 'Inscripción de jugador en equipo',
        ]);
    }

    public function test_fotografia_capturada_o_seleccionada_se_acepta_como_archivo_de_imagen(): void
    {
        [$club, $delegado, $equipo] = $this->crearEquipoDelegado();
        $persona = $this->crearPersona();

        $this->actingAs($delegado)->post(route('equipos.jugadores.store', $equipo), [
            'persona_id' => $persona->id,
            'documentos' => ['fotografia' => $this->crearFotografiaFake('captura-camara.png')],
        ])->assertCreated();

        $documento = Jugador::query()->where('persona_id', $persona->id)->firstOrFail()->documentos()->firstOrFail();
        $this->assertSame('Fotografia', $documento->tipo);
        Storage::disk('private')->assertExists($documento->ruta_storage);
    }

    public function test_jugador_solo_consulta_sus_datos_y_documentos_y_recibe_403_para_otro_jugador(): void
    {
        [, , $equipo] = $this->crearEquipoDelegado();
        $personaPropia = $this->crearPersona();
        $personaAjena = $this->crearPersona();
        $propio = $equipo->jugadores()->create(['persona_id' => $personaPropia->id]);
        $ajeno = $equipo->jugadores()->create(['persona_id' => $personaAjena->id]);
        $archivo = UploadedFile::fake()->create('ine.pdf', 20, 'application/pdf');
        $ruta = $archivo->store('jugadores/'.$propio->id, 'private');
        $documento = $propio->documentos()->create([
            'tipo' => 'INE', 'disco' => 'private', 'ruta_storage' => $ruta,
            'mime_type' => 'application/pdf', 'tamano_bytes' => 20 * 1024,
        ]);
        $usuario = $this->crearCuentaJugador($personaPropia);

        $this->actingAs($usuario)->getJson(route('jugadores.show', $propio))->assertOk();
        $this->get(route('jugadores.documentos.show', [$propio, $documento]))->assertOk();
        $this->getJson(route('jugadores.show', $ajeno))->assertForbidden();

        $documentoAjeno = $ajeno->documentos()->create([
            'tipo' => 'INE', 'disco' => 'private', 'ruta_storage' => $ruta,
            'mime_type' => 'application/pdf', 'tamano_bytes' => 100,
        ]);
        $this->get(route('jugadores.documentos.show', [$ajeno, $documentoAjeno]))->assertForbidden();
        $this->putJson('/jugadores/'.$ajeno->getKey(), ['dorsal' => 99])->assertForbidden();
    }

    public function test_directivo_y_delegado_del_club_pueden_consultar_ficha_y_documentos_de_sus_jugadores(): void
    {
        [$club, $delegado, $equipo] = $this->crearEquipoDelegado();
        $persona = $this->crearPersona();
        $jugador = $equipo->jugadores()->create(['persona_id' => $persona->id]);
        $ruta = 'jugadores/'.$jugador->id.'/ine.pdf';
        Storage::disk('private')->put($ruta, 'contenido privado');
        $documento = $jugador->documentos()->create([
            'tipo' => 'INE',
            'disco' => 'private',
            'ruta_storage' => $ruta,
            'mime_type' => 'application/pdf',
            'tamano_bytes' => 17,
        ]);

        $this->actingAs($delegado)->getJson(route('jugadores.show', $jugador))->assertOk();
        $this->get(route('jugadores.documentos.show', [$jugador, $documento]))->assertOk();

        $presidente = User::query()->create([
            'name' => 'Presidente del club',
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'rol' => 'Directivo Club',
            'estatus' => 'activo',
            'club_id' => $club->id,
        ]);
        $presidente->assignRole('Presidente del Club');
        $this->actingAs($presidente)->getJson(route('jugadores.show', $jugador))->assertOk();

        [, $delegadoAjeno] = $this->crearEquipoDelegado();
        $this->actingAs($delegadoAjeno)->getJson(route('jugadores.show', $jugador))->assertForbidden();
    }

    public function test_vinculacion_por_curp_no_revela_si_el_registro_existe_y_se_audita(): void
    {
        [, , $equipo] = $this->crearEquipoDelegado();
        $persona = $this->crearPersona();
        $equipo->jugadores()->create(['persona_id' => $persona->id]);
        $usuario = $this->crearCuentaJugador();

        $respuestaValida = $this->actingAs($usuario)->postJson(route('jugadores.vincular'), ['curp' => $persona->curp])
            ->assertOk();
        $respuestaInvalida = $this->postJson(route('jugadores.vincular'), ['curp' => 'ZZZZ000000HDFXXX00'])
            ->assertOk();

        $this->assertSame($respuestaValida->json(), $respuestaInvalida->json());
        $this->assertDatabaseHas('usuarios', ['id' => $usuario->id, 'persona_id' => $persona->id]);
        $this->assertDatabaseHas('auditoria_logs', [
            'accion' => 'jugador_vinculado_curp',
            'tabla_afectada' => 'usuarios',
            'registro_id' => $usuario->id,
        ]);
    }

    public function test_segundo_usuario_no_puede_vincular_una_curp_ya_asociada_y_la_respuesta_es_generica(): void
    {
        [, , $equipo] = $this->crearEquipoDelegado();
        $persona = $this->crearPersona();
        $equipo->jugadores()->create(['persona_id' => $persona->id]);
        $cuentaVinculada = $this->crearCuentaJugador($persona);
        $segundoUsuario = $this->crearCuentaJugador();

        $respuestaVinculada = $this->actingAs($segundoUsuario)
            ->postJson(route('jugadores.vincular'), ['curp' => $persona->curp])
            ->assertOk();
        $respuestaNoEncontrada = $this->postJson(route('jugadores.vincular'), ['curp' => 'ZZZZ000000HDFXXX00'])
            ->assertOk();

        $this->assertSame($respuestaNoEncontrada->json(), $respuestaVinculada->json());
        $this->assertDatabaseHas('usuarios', ['id' => $cuentaVinculada->id, 'persona_id' => $persona->id]);
        $this->assertDatabaseHas('usuarios', ['id' => $segundoUsuario->id, 'persona_id' => null]);
        $this->assertDatabaseMissing('auditoria_logs', [
            'accion' => 'jugador_vinculado_curp',
            'registro_id' => $segundoUsuario->id,
        ]);
    }

    public function test_vinculacion_por_curp_aplica_rate_limit(): void
    {
        $usuario = $this->crearCuentaJugador();
        $this->actingAs($usuario);

        for ($intento = 0; $intento < 5; $intento++) {
            $this->postJson(route('jugadores.vincular'), ['curp' => 'ZZZZ000000HDFXXX00'])->assertOk();
        }

        $this->postJson(route('jugadores.vincular'), ['curp' => 'ZZZZ000000HDFXXX00'])->assertStatus(429);
    }

    public function test_solo_administrador_general_puede_desvincular_y_la_operacion_se_audita(): void
    {
        [, , $equipo] = $this->crearEquipoDelegado();
        $persona = $this->crearPersona();
        $jugador = $equipo->jugadores()->create(['persona_id' => $persona->id]);
        $usuario = $this->crearCuentaJugador($persona);

        $this->actingAs($usuario)->deleteJson(route('jugadores.vinculo.destroy', $jugador))->assertForbidden();

        $admin = User::create([
            'name' => 'Admin HU-016', 'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'), 'rol' => 'Administrador General', 'estatus' => 'activo',
        ]);
        $admin->assignRole('Administrador General');

        $this->actingAs($admin)->deleteJson(route('jugadores.vinculo.destroy', $jugador))->assertOk();
        $this->assertDatabaseHas('usuarios', ['id' => $usuario->id, 'persona_id' => null]);
        $this->assertDatabaseHas('auditoria_logs', [
            'accion' => 'jugador_desvinculado',
            'tabla_afectada' => 'usuarios',
            'registro_id' => $usuario->id,
        ]);
    }

    private function crearPersona(): Persona
    {
        return Persona::query()->create([
            'nombre' => 'Jugador',
            'apellidos' => 'HU-016',
            'fecha_nacimiento' => '2010-01-01',
            'curp' => str()->upper(str()->random(18)),
        ]);
    }

    /** @return array{Club, User, Equipo} */
    private function crearEquipoDelegado(): array
    {
        $club = Club::query()->create(['nombre' => 'Club HU-016 '.str()->random(6)]);
        $delegado = User::query()->create([
            'name' => 'Delegado HU-016', 'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'), 'rol' => 'Directivo Club',
            'estatus' => 'activo', 'club_id' => $club->id,
        ]);
        $delegado->assignRole('Delegado del Club');
        $temporada = Temporada::factory()->activa()->create();
        $categoria = Categoria::factory()->create(['temporada_id' => $temporada->id]);
        $grupo = Grupo::factory()->create(['categoria_id' => $categoria->id, 'temporada_id' => $temporada->id]);
        $equipo = Equipo::query()->create([
            'club_id' => $club->id, 'temporada_id' => $temporada->id,
            'grupo_id' => $grupo->id, 'nombre' => 'Equipo HU-016',
        ]);

        return [$club, $delegado, $equipo];
    }

    private function crearCuentaJugador(?Persona $persona = null): User
    {
        $usuario = User::query()->create([
            'name' => 'Jugador cuenta', 'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'), 'rol' => 'Jugador',
            'estatus' => 'activo',
        ]);

        if ($persona !== null) {
            $usuario->forceFill(['persona_id' => $persona->id])->save();
        }

        return $usuario;
    }

    private function crearFotografiaFake(string $nombre): UploadedFile
    {
        $contenido = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/kXcAAAAASUVORK5CYII=');

        return UploadedFile::fake()->createWithContent($nombre, $contenido ?: 'png');
    }
}
