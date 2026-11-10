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
use App\Services\AuditService;
use App\Services\RegistroJugadorService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\DatosPrivacidadJugador;
use Tests\TestCase;

class JugadorFormularioRegistroTest extends TestCase
{
    use DatabaseTransactions;
    use DatosPrivacidadJugador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Storage::fake('private');
    }

    public function test_formulario_muestra_equipos_de_temporadas_activas_para_el_club_del_delegado(): void
    {
        [$delegado, $equipo, $temporada, $categoria] = $this->crearContexto();
        $temporadaCerrada = Temporada::factory()->cerrada()->create();
        $categoriaCerrada = Categoria::factory()->create(['temporada_id' => $temporadaCerrada->id]);

        $this->actingAs($delegado)->get(route('jugadores.create'))
            ->assertOk()
            ->assertSee('Registrar jugador')
            ->assertSee('Temporada activa')
            ->assertSee($temporada->nombre)
            ->assertSee($categoria->nombre)
            ->assertSee($equipo->nombre)
            ->assertDontSee($temporadaCerrada->nombre)
            ->assertDontSee($categoriaCerrada->nombre);
    }

    public function test_inscribe_al_jugador_y_guarda_documentos_incluida_fotografia_subida_en_disco_privado(): void
    {
        [$delegado, $equipo, $temporada, $categoria] = $this->crearContexto();

        $respuesta = $this->actingAs($delegado)->post(route('jugadores.store'), $this->datosFormulario($equipo, $temporada, $categoria, [
            'fotografia' => $this->imagenPng('foto.png'),
        ]));

        $respuesta->assertRedirect(route('jugadores.gestion', Jugador::query()->firstOrFail()))->assertSessionHas('success');
        $persona = Persona::query()->where('curp', 'TEST010101HDFXXX01')->firstOrFail();
        $jugador = Jugador::query()->where('persona_id', $persona->id)->firstOrFail();
        $this->assertSame($equipo->id, $jugador->equipo_id);
        $this->assertDatabaseHas('persona_roles', [
            'persona_id' => $persona->id,
            'rol' => 'jugador',
            'temporada_id' => $temporada->id,
        ]);
        $this->assertCount(4, $persona->documentos);

        foreach ($persona->documentos as $documento) {
            $this->assertSame('private', $documento->disco);
            Storage::disk('private')->assertExists($documento->ruta_storage);
            Storage::disk('public')->assertMissing($documento->ruta_storage);
        }

        $this->assertDatabaseHas('documentos_persona', [
            'persona_id' => $persona->id,
            'tipo' => 'Foto',
            'disco' => 'private',
            'mime_type' => 'image/png',
        ]);
    }

    public function test_fallo_de_auditoria_despues_de_guardar_documentos_elimina_archivos_y_revierte_registro(): void
    {
        [$delegado, $equipo] = $this->crearContexto();
        $persona = Persona::factory()->create();
        $this->actingAs($delegado);

        $auditoriaReal = new AuditService();
        $auditoria = \Mockery::mock(AuditService::class)->makePartial();
        $auditoria->shouldReceive('registrar')->andReturnUsing(
            function (...$argumentos) use ($auditoriaReal) {
                if ($argumentos[0] === 'documento_jugador_registrado') {
                    throw new RuntimeException('Fallo inducido después de guardar el documento.');
                }

                return $auditoriaReal->registrar(...$argumentos);
            }
        );
        $this->app->instance(AuditService::class, $auditoria);

        try {
            app(RegistroJugadorService::class)->registrar($equipo, [
                'persona_id' => $persona->id,
                ...$this->privacidad(),
                'documentos' => [
                    'ine' => UploadedFile::fake()->create('ine.pdf', 40, 'application/pdf'),
                ],
            ], $delegado);
            $this->fail('El error de auditoría debe revertir la inscripción y eliminar el archivo guardado.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo inducido después de guardar el documento.', $exception->getMessage());
        }

        $this->assertSame([], Storage::disk('private')->allFiles());
        $this->assertDatabaseCount('jugadores_equipos', 0);
        $this->assertDatabaseCount('documentos_persona', 0);
        $this->assertSame(0, DB::table('auditoria_logs')->where('accion', 'documento_jugador_registrado')->count());
    }
    public function test_acepta_fotografia_capturada_desde_camara_en_base64(): void
    {
        [$delegado, $equipo, $temporada, $categoria] = $this->crearContexto();
        $datos = $this->datosFormulario($equipo, $temporada, $categoria);
        $datos['fotografia_base64'] = 'data:image/png;base64,'.$this->pngBase64();

        $this->actingAs($delegado)->post(route('jugadores.store'), $datos)
            ->assertRedirect(route('jugadores.gestion', Jugador::query()->firstOrFail()))
            ->assertSessionHas('success');

        $persona = Persona::query()->where('curp', 'TEST010101HDFXXX01')->firstOrFail();
        $foto = $persona->documentos()->where('tipo', 'Foto')->firstOrFail();
        $this->assertSame('private', $foto->disco);
        $this->assertSame('Foto', $foto->tipo);
        $this->assertSame('image/png', $foto->mime_type);
        Storage::disk('private')->assertExists($foto->ruta_storage);
    }

    public function test_permite_registro_con_ine_sin_exigir_documento_curp_adicional(): void
    {
        [$delegado, $equipo, $temporada, $categoria] = $this->crearContexto();
        $datos = $this->datosFormulario($equipo, $temporada, $categoria, [
            'fotografia' => $this->imagenPng('foto.png'),
        ]);
        unset($datos['documentos']['curp']);

        $this->actingAs($delegado)->post(route('jugadores.store'), $datos)
            ->assertRedirect(route('jugadores.gestion', Jugador::query()->firstOrFail()))
            ->assertSessionHas('success');

        $personaId = Persona::query()->where('curp', 'TEST010101HDFXXX01')->value('id');
        $this->assertDatabaseHas('documentos_persona', ['persona_id' => $personaId, 'tipo' => 'INE']);
        $this->assertDatabaseMissing('documentos_persona', ['persona_id' => $personaId, 'tipo' => 'CURP']);
    }

    public function test_nuevo_registro_exige_una_identificacion_ine_o_curp(): void
    {
        [$delegado, $equipo, $temporada, $categoria] = $this->crearContexto();
        $datos = $this->datosFormulario($equipo, $temporada, $categoria, [
            'fotografia' => $this->imagenPng('foto.png'),
        ]);
        unset($datos['documentos']['ine'], $datos['documentos']['curp']);

        $this->actingAs($delegado)->from(route('jugadores.create'))
            ->post(route('jugadores.store'), $datos)
            ->assertRedirect(route('jugadores.create'))
            ->assertSessionHasErrors('documentos');

        $this->assertDatabaseMissing('personas', ['curp' => 'TEST010101HDFXXX01']);
    }

    public function test_rechaza_base64_invalido_o_documentacion_faltante_sin_crear_persona(): void
    {
        [$delegado, $equipo, $temporada, $categoria] = $this->crearContexto();
        $datos = $this->datosFormulario($equipo, $temporada, $categoria);
        $datos['fotografia_base64'] = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
        unset($datos['documentos']['comprobante']);

        $this->actingAs($delegado)->from(route('jugadores.create'))->post(route('jugadores.store'), $datos)
            ->assertRedirect(route('jugadores.create'))
            ->assertSessionHasErrors(['fotografia_base64', 'documentos.comprobante']);

        $this->assertDatabaseMissing('personas', ['curp' => 'TEST010101HDFXXX01']);
    }

    public function test_no_permite_asignar_equipo_de_otra_categoria_o_temporada(): void
    {
        [$delegado, $equipo, $temporada, $categoria] = $this->crearContexto();
        $otraTemporada = Temporada::factory()->activa()->create();
        $otraCategoria = Categoria::factory()->create(['temporada_id' => $otraTemporada->id]);

        $this->actingAs($delegado)->from(route('jugadores.create'))->post(route('jugadores.store'), [
            ...$this->datosFormulario($equipo, $temporada, $categoria, ['fotografia' => $this->imagenPng('foto.png')]),
            'temporada_id' => $otraTemporada->id,
            'categoria_id' => $otraCategoria->id,
        ])->assertRedirect(route('jugadores.create'))->assertSessionHasErrors(['equipo_id']);

        $this->assertDatabaseMissing('personas', ['curp' => 'TEST010101HDFXXX01']);
    }

    public function test_equipo_ausente_o_invalido_devuelve_errores_de_validacion_y_no_403(): void
    {
        [$delegado, $equipo, $temporada, $categoria] = $this->crearContexto();
        $datos = $this->datosFormulario($equipo, $temporada, $categoria, ['fotografia' => $this->imagenPng('foto.png')]);

        $sinEquipo = $datos;
        unset($sinEquipo['equipo_id']);
        $this->actingAs($delegado)
            ->from(route('jugadores.create'))
            ->post(route('jugadores.store'), $sinEquipo)
            ->assertRedirect(route('jugadores.create'))
            ->assertSessionHasErrors('equipo_id');

        $equipoInvalido = $datos;
        $equipoInvalido['equipo_id'] = 999999;
        $this->actingAs($delegado)
            ->from(route('jugadores.create'))
            ->post(route('jugadores.store'), $equipoInvalido)
            ->assertRedirect(route('jugadores.create'))
            ->assertSessionHasErrors('equipo_id');

        $equipoNoNumerico = $datos;
        $equipoNoNumerico['equipo_id'] = 'equipo-invalido';
        $this->actingAs($delegado)
            ->from(route('jugadores.create'))
            ->post(route('jugadores.store'), $equipoNoNumerico)
            ->assertRedirect(route('jugadores.create'))
            ->assertSessionHasErrors('equipo_id');

        $jsonSinEquipo = $this->actingAs($delegado)
            ->postJson(route('jugadores.store'), $sinEquipo);
        $jsonSinEquipo->assertUnprocessable()->assertJsonValidationErrors('equipo_id');

        $jsonEquipoInvalido = $this->actingAs($delegado)
            ->postJson(route('jugadores.store'), $equipoInvalido);
        $jsonEquipoInvalido->assertUnprocessable()->assertJsonValidationErrors('equipo_id');
    }

    public function test_delegado_no_puede_inscribir_en_equipo_de_otro_club(): void
    {
        [$delegado, , $temporada, $categoria] = $this->crearContexto();
        $otroClub = Club::query()->create(['nombre' => 'Otro club '.str()->random(5)]);
        $grupo = Grupo::factory()->create([
            'categoria_id' => $categoria->id,
            'temporada_id' => $temporada->id,
        ]);
        $equipoOtroClub = Equipo::query()->create([
            'club_id' => $otroClub->id,
            'temporada_id' => $temporada->id,
            'grupo_id' => $grupo->id,
            'nombre' => 'Equipo ajeno',
        ]);
        $datos = $this->datosFormulario($equipoOtroClub, $temporada, $categoria, [
            'fotografia' => $this->imagenPng('foto.png'),
        ]);

        $this->actingAs($delegado)
            ->post(route('jugadores.store'), $datos)
            ->assertForbidden();

        $this->assertDatabaseMissing('personas', ['curp' => 'TEST010101HDFXXX01']);
    }

    public function test_migracion_revierte_columnas_de_documentos_persona_en_sqlite(): void
    {
        $conexionOriginal = DB::getDefaultConnection();
        config(['database.connections.documentos_rollback_test' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        DB::purge('documentos_rollback_test');
        DB::setDefaultConnection('documentos_rollback_test');

        try {
            Schema::create('personas', function (Blueprint $table): void {
                $table->id();
            });
            Schema::create('documentos_persona', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('persona_id');
                $table->string('tipo');
                $table->string('ruta_storage');
                $table->string('disco')->default('private');
                $table->string('mime_type')->default('application/octet-stream');
                $table->unsignedBigInteger('tamano_bytes')->default(0);
                $table->timestamps();
            });
            DB::table('documentos_persona')->insert([
                'persona_id' => 1,
                'tipo' => 'Comprobante',
                'ruta_storage' => 'personas/1/comprobante.pdf',
                'disco' => 'private',
                'mime_type' => 'application/pdf',
                'tamano_bytes' => 100,
            ]);

            $migracion = require database_path('migrations/2026_11_07_120000_expandir_documentos_persona_para_registro_jugador.php');
            try {
                $migracion->down();
                $this->fail('La reversión debe detenerse antes de borrar comprobantes o sus metadatos.');
            } catch (RuntimeException) {
                $this->assertDatabaseCount('documentos_persona', 1);
                $this->assertTrue(Schema::hasColumn('documentos_persona', 'disco'));
                $this->assertTrue(Schema::hasColumn('documentos_persona', 'mime_type'));
                $this->assertTrue(Schema::hasColumn('documentos_persona', 'tamano_bytes'));
            }
        } finally {
            DB::setDefaultConnection($conexionOriginal);
            DB::purge('documentos_rollback_test');
        }
    }

    public function test_permiso_atomico_jugadores_crear_autoriza_el_formulario(): void
    {
        Permission::query()->firstOrCreate(['name' => 'jugadores.crear', 'guard_name' => 'web']);
        $usuario = User::query()->create([
            'name' => 'Usuario padrón',
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'rol' => 'Administrador General',
            'estatus' => 'activo',
        ]);
        $usuario->givePermissionTo('jugadores.crear');

        $this->actingAs($usuario)->get(route('jugadores.create'))->assertOk();
    }

    public function test_reinscripcion_por_curp_reutiliza_la_persona_existente(): void
    {
        [$delegado, $equipo, $temporada, $categoria] = $this->crearContexto();
        $persona = Persona::factory()->create([
            'nombre' => 'Jugador Formulario',
            'apellidos' => 'Prueba',
            'fecha_nacimiento' => '2006-01-01',
            'curp' => 'TEST010101HDFXXX01',
        ]);
        $datos = $this->datosFormulario($equipo, $temporada, $categoria, ['fotografia' => $this->imagenPng('foto.png')]);

        $this->actingAs($delegado)->post(route('jugadores.store'), $datos)
            ->assertRedirect(route('jugadores.gestion', Jugador::query()->firstOrFail()))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('personas', 1);
        $this->assertDatabaseHas('jugadores_equipos', [
            'persona_id' => $persona->id,
            'equipo_id' => $equipo->id,
        ]);
    }

    public function test_rechaza_curp_existente_con_datos_personales_diferentes_sin_crear_inscripcion(): void
    {
        [$delegado, $equipo, $temporada, $categoria] = $this->crearContexto();

        $persona = Persona::factory()->create([
            'nombre' => 'Persona Original',
            'apellidos' => 'Apellido Original',
            'fecha_nacimiento' => '2006-01-01',
            'curp' => 'TEST010101HDFXXX01',
        ]);

        $datos = $this->datosFormulario(
            $equipo,
            $temporada,
            $categoria,
            ['fotografia' => $this->imagenPng('foto.png')]
        );

        $this->actingAs($delegado)
            ->postJson(route('jugadores.store'), $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('curp');

        $this->assertDatabaseCount('personas', 1);
        $this->assertDatabaseCount('jugadores_equipos', 0);
        $this->assertDatabaseCount('documentos_persona', 0);

        $this->assertDatabaseHas('personas', [
            'id' => $persona->id,
            'nombre' => 'Persona Original',
            'apellidos' => 'Apellido Original',
        ]);

        $this->assertSame([], Storage::disk('private')->allFiles());
    }
    public function test_rechaza_fecha_nacimiento_con_formato_incorrecto(): void
    {
        [$delegado, $equipo, $temporada, $categoria] = $this->crearContexto();

        $datos = $this->datosFormulario(
            $equipo,
            $temporada,
            $categoria,
            ['fotografia' => $this->imagenPng('foto.png')]
        );

        $datos['fecha_nacimiento'] = '01/01/2010';

        $this->actingAs($delegado)
            ->postJson(route('jugadores.store'), $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fecha_nacimiento');

        $this->assertDatabaseCount('personas', 0);
        $this->assertDatabaseCount('jugadores_equipos', 0);
        $this->assertDatabaseCount('documentos_persona', 0);
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    #[DataProvider('curpsConFormatoInvalido')]
    public function test_rechaza_curp_con_formato_invalido(string $curp): void
    {
        [$delegado, $equipo, $temporada, $categoria] = $this->crearContexto();

        $datos = $this->datosFormulario(
            $equipo,
            $temporada,
            $categoria,
            ['fotografia' => $this->imagenPng('foto.png')]
        );
        $datos['curp'] = $curp;

        $this->actingAs($delegado)
            ->postJson(route('jugadores.store'), $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('curp');

        $this->assertDatabaseCount('personas', 0);
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    /** @return array<string, array{string}> */
    public static function curpsConFormatoInvalido(): array
    {
        return [
            'dígitos en lugar de letras iniciales' => ['1234900101HMNRRL01'],
            'letras en la fecha' => ['PEGAAB0101HMNRRL01'],
            'sexo inválido' => ['PEGA900101ZMNRRL01'],
            'dígito verificador no numérico' => ['PEGA900101HMNRRL0A'],
        ];
    }

    public function test_servicio_revalida_club_del_delegado_dentro_de_la_transaccion(): void
    {
        [$delegado, $equipo] = $this->crearContexto();
        $otroClub = Club::query()->create(['nombre' => 'Club actualizado '.str()->random(5)]);
        $persona = Persona::factory()->create();
        DB::table('usuarios')->where('id', $delegado->id)->update(['club_id' => $otroClub->id]);

        try {
            app(RegistroJugadorService::class)->registrar($equipo, ['persona_id' => $persona->id] + $this->privacidad(), $delegado);
            $this->fail('El servicio debe rechazar el delegado cuyo club ya no es propietario del equipo.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('jugadores_equipos', 0);
            $this->assertDatabaseCount('documentos_persona', 0);
        }
    }
    /** @return array{User, Equipo, Temporada, Categoria} */
    private function crearContexto(): array
    {
        $club = Club::query()->create(['nombre' => 'Club Formulario '.str()->random(5)]);
        $delegado = User::query()->create([
            'name' => 'Delegado formulario',
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'rol' => 'Directivo Club',
            'estatus' => 'activo',
            'club_id' => $club->id,
        ]);
        $rolDelegado = Role::query()->firstOrCreate(['name' => 'Delegado del Club', 'guard_name' => 'web']);
        $rolDelegado->givePermissionTo('jugadores.crear');
        $delegado->assignRole($rolDelegado);

        $temporada = Temporada::factory()->activa()->create();
        $categoria = Categoria::factory()->create(['temporada_id' => $temporada->id, 'nombre' => 'Juvenil Formulario']);
        $grupo = Grupo::factory()->create([
            'categoria_id' => $categoria->id,
            'temporada_id' => $temporada->id,
        ]);
        $equipo = Equipo::query()->create([
            'club_id' => $club->id,
            'temporada_id' => $temporada->id,
            'grupo_id' => $grupo->id,
            'nombre' => 'Equipo Formulario',
        ]);

        return [$delegado, $equipo, $temporada, $categoria];
    }

    /** @param array{fotografia?: UploadedFile} $fotografia */
    private function datosFormulario(Equipo $equipo, Temporada $temporada, Categoria $categoria, array $fotografia = []): array
    {
        return [
            'nombre' => 'Jugador Formulario',
            'apellidos' => 'Prueba',
            'fecha_nacimiento' => '2006-01-01',
            'curp' => 'TEST010101HDFXXX01',
            'temporada_id' => $temporada->id,
            'categoria_id' => $categoria->id,
            'equipo_id' => $equipo->id,
            'dorsal' => 18,
            'documentos' => [
                'ine' => UploadedFile::fake()->create('ine.pdf', 100, 'application/pdf'),
                'curp' => UploadedFile::fake()->create('curp.pdf', 80, 'application/pdf'),
                'fotografia' => $fotografia['fotografia'] ?? null,
                'comprobante' => UploadedFile::fake()->create('domicilio.pdf', 90, 'application/pdf'),
            ],
            // HU-020: jugador menor de una categoría Juvenil: aviso y consentimiento del tutor.
            'aviso_privacidad_aceptado' => '1',
            'tutor' => [
                'nombre_completo' => 'Tutora Formulario Prueba',
                'parentesco' => 'Madre',
                'telefono' => '5512345678',
                'consentimiento' => '1',
            ],
        ];
    }

    private function imagenPng(string $nombre): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, base64_decode($this->pngBase64()) ?: 'png');
    }

    private function pngBase64(): string
    {
        return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/kXcAAAAASUVORK5CYII=';
    }
}
