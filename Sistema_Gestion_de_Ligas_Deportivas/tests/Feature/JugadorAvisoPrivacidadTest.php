<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Club;
use App\Models\ConsentimientoPrivacidadJugador;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Jugador;
use App\Models\Persona;
use App\Models\Temporada;
use App\Models\User;
use App\Services\ConsentimientoPrivacidadService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** HU-020 (OR-01): aviso de privacidad en todo registro y consentimiento del tutor de menores. */
class JugadorAvisoPrivacidadTest extends TestCase
{
    use DatabaseTransactions;

    private const ADULTO = '1995-05-10';

    private const MENOR = '2012-03-15';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Storage::fake('private');
        // El consentimiento del tutor solo aplica si la Liga admite menores: se desactiva la
        // edad mínima (config/jugadores.php). Con la regla activa lo prueba JugadorEdadMinimaLigaTest.
        config(['jugadores.edad_minima' => null]);
    }

    public function test_el_formulario_muestra_el_aviso_de_privacidad_y_la_seccion_del_tutor(): void
    {
        [$delegado] = $this->contexto('Infantil', 'Infantil A');

        $this->actingAs($delegado)->get(route('jugadores.create'))
            ->assertOk()
            ->assertSee('Aviso de privacidad')
            ->assertSee('versión '.config('privacidad.aviso_jugador.version'))
            ->assertSee('Derechos ARCO')
            ->assertSee('name="aviso_privacidad_aceptado"', false)
            ->assertSee('Consentimiento del tutor')
            ->assertSee('name="tutor[consentimiento]"', false)
            ->assertSee('data-requiere-tutor="1"', false);
    }

    public function test_el_formulario_no_marca_al_tutor_en_una_categoria_de_adultos(): void
    {
        [$delegado] = $this->contexto('Libre', 'Primera Fuerza');

        $this->actingAs($delegado)->get(route('jugadores.create'))
            ->assertOk()
            ->assertSee('data-requiere-tutor="0"', false)
            ->assertDontSee('data-requiere-tutor="1"', false);
    }

    public function test_ningun_registro_se_guarda_sin_confirmar_que_se_mostro_el_aviso(): void
    {
        [$delegado, $equipo, $temporada, $categoria] = $this->contexto('Libre', 'Primera Fuerza');
        $datos = $this->datos($equipo, $temporada, $categoria, self::ADULTO);
        unset($datos['aviso_privacidad_aceptado']);

        $this->actingAs($delegado)->postJson(route('jugadores.store'), $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('aviso_privacidad_aceptado');

        $this->assertDatabaseCount('jugadores_equipos', 0);
        $this->assertDatabaseCount('consentimientos_privacidad_jugador', 0);
    }

    public function test_un_adulto_de_rama_libre_se_registra_con_el_aviso_y_sin_tutor(): void
    {
        [$delegado, $equipo, $temporada, $categoria] = $this->contexto('Libre', 'Primera Fuerza');

        $this->actingAs($delegado)->post(route('jugadores.store'), $this->datos($equipo, $temporada, $categoria, self::ADULTO))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $jugador = Jugador::query()->sole();
        $constancia = $jugador->consentimientoPrivacidad;
        $this->assertNotNull($constancia);
        $this->assertSame(config('privacidad.aviso_jugador.version'), $constancia->version_aviso);
        $this->assertFalse($constancia->requiere_tutor);
        $this->assertNull($constancia->motivo_tutor);
        $this->assertNull($constancia->tutor_nombre_completo);
        $this->assertNull($constancia->tutor_consintio_en);
        $this->assertSame($delegado->id, $constancia->registrado_por_usuario_id);
        $this->assertSame($jugador->persona_id, $constancia->persona_id);
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function casosQueExigenTutor(): array
    {
        return [
            'rama infantil' => ['Infantil', 'Infantil A', self::ADULTO, ConsentimientoPrivacidadJugador::MOTIVO_RAMA_INFANTIL],
            'categoría Juvenil de rama libre' => ['Libre', 'Juvenil A', self::ADULTO, ConsentimientoPrivacidadJugador::MOTIVO_CATEGORIA_JUVENIL],
            'menor de edad en otra categoría' => ['Femenil', 'Femenil Primera', self::MENOR, ConsentimientoPrivacidadJugador::MOTIVO_MENOR_DE_EDAD],
        ];
    }

    #[DataProvider('casosQueExigenTutor')]
    public function test_sin_consentimiento_del_tutor_no_se_guarda_el_registro(string $rama, string $nombreCategoria, string $nacimiento, string $motivo): void
    {
        [$delegado, $equipo, $temporada, $categoria] = $this->contexto($rama, $nombreCategoria);
        $datos = $this->datos($equipo, $temporada, $categoria, $nacimiento);
        unset($datos['tutor']);

        $this->actingAs($delegado)->postJson(route('jugadores.store'), $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tutor.nombre_completo', 'tutor.parentesco', 'tutor.telefono', 'tutor.consentimiento']);

        $this->assertDatabaseCount('jugadores_equipos', 0);
        $this->assertDatabaseCount('consentimientos_privacidad_jugador', 0);
        $this->assertSame([], Storage::disk('private')->allFiles(), 'No deben quedar documentos de un registro rechazado.');
    }

    #[DataProvider('casosQueExigenTutor')]
    public function test_con_consentimiento_del_tutor_se_registra_y_guarda_la_constancia(string $rama, string $nombreCategoria, string $nacimiento, string $motivo): void
    {
        [$delegado, $equipo, $temporada, $categoria] = $this->contexto($rama, $nombreCategoria);

        $this->actingAs($delegado)->post(route('jugadores.store'), $this->datos($equipo, $temporada, $categoria, $nacimiento))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $constancia = Jugador::query()->sole()->consentimientoPrivacidad;
        $this->assertNotNull($constancia);
        $this->assertTrue($constancia->requiere_tutor);
        $this->assertSame($motivo, $constancia->motivo_tutor);
        $this->assertSame('María López Hernández', $constancia->tutor_nombre_completo);
        $this->assertSame('Madre', $constancia->tutor_parentesco);
        $this->assertSame('5512345678', $constancia->tutor_telefono);
        $this->assertSame('maria.lopez@correo.test', $constancia->tutor_correo);
        $this->assertNotNull($constancia->tutor_consintio_en);
    }

    public function test_los_datos_de_contacto_del_tutor_se_cifran_y_no_pasan_a_la_auditoria(): void
    {
        [$delegado, $equipo, $temporada, $categoria] = $this->contexto('Infantil', 'Infantil A');

        $this->actingAs($delegado)->post(route('jugadores.store'), $this->datos($equipo, $temporada, $categoria, self::MENOR))
            ->assertSessionHasNoErrors();

        $fila = DB::table('consentimientos_privacidad_jugador')->sole();
        $this->assertNotSame('5512345678', $fila->tutor_telefono);
        $this->assertStringNotContainsString('maria.lopez', (string) $fila->tutor_correo);

        $auditoria = DB::table('auditoria_logs')->where('accion', 'consentimiento_privacidad_registrado')->sole();
        $this->assertStringNotContainsString('5512345678', (string) $auditoria->valores_nuevos);
        $this->assertStringNotContainsString('maria.lopez', (string) $auditoria->valores_nuevos);
        $this->assertStringContainsString(ConsentimientoPrivacidadJugador::MOTIVO_RAMA_INFANTIL, (string) $auditoria->valores_nuevos);
    }

    public function test_los_datos_del_tutor_sin_marcar_su_consentimiento_no_bastan(): void
    {
        [$delegado, $equipo, $temporada, $categoria] = $this->contexto('Infantil', 'Infantil A');
        $datos = $this->datos($equipo, $temporada, $categoria, self::MENOR);
        unset($datos['tutor']['consentimiento']);

        $this->actingAs($delegado)->postJson(route('jugadores.store'), $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tutor.consentimiento')
            ->assertJsonMissingValidationErrors(['tutor.nombre_completo', 'tutor.telefono']);

        $this->assertDatabaseCount('jugadores_equipos', 0);
    }

    public function test_valida_el_formato_del_telefono_correo_y_parentesco_del_tutor(): void
    {
        [$delegado, $equipo, $temporada, $categoria] = $this->contexto('Infantil', 'Infantil A');
        $datos = $this->datos($equipo, $temporada, $categoria, self::MENOR);
        $datos['tutor']['telefono'] = '55-1234';
        $datos['tutor']['correo'] = 'no-es-correo';
        $datos['tutor']['parentesco'] = 'Vecino';

        $this->actingAs($delegado)->postJson(route('jugadores.store'), $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tutor.telefono', 'tutor.correo', 'tutor.parentesco']);
    }

    public function test_la_inscripcion_de_una_persona_existente_tambien_exige_aviso_y_tutor(): void
    {
        [$delegado, $equipo] = $this->contexto('Infantil', 'Infantil A');
        $persona = Persona::factory()->create(['fecha_nacimiento' => self::MENOR]);

        $this->actingAs($delegado)->postJson(route('equipos.jugadores.store', $equipo), ['persona_id' => $persona->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('aviso_privacidad_aceptado');

        $this->actingAs($delegado)->postJson(route('equipos.jugadores.store', $equipo), [
            'persona_id' => $persona->id,
            'aviso_privacidad_aceptado' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('tutor.consentimiento');

        $this->actingAs($delegado)->postJson(route('equipos.jugadores.store', $equipo), [
            'persona_id' => $persona->id,
            'aviso_privacidad_aceptado' => true,
            'tutor' => $this->tutor(),
        ])->assertCreated();

        $this->assertSame(1, ConsentimientoPrivacidadJugador::query()->where('persona_id', $persona->id)->where('requiere_tutor', true)->count());
    }

    public function test_la_mayoria_de_edad_se_cuenta_a_la_fecha_del_registro(): void
    {
        $servicio = app(ConsentimientoPrivacidadService::class);
        $categoria = Categoria::factory()->make(['rama' => 'Libre', 'nombre' => 'Primera Fuerza']);
        $fecha = now()->startOfDay();

        $cumpleHoy = Persona::factory()->make(['fecha_nacimiento' => $fecha->copy()->subYears(18)->toDateString()]);
        $cumpleManana = Persona::factory()->make(['fecha_nacimiento' => $fecha->copy()->subYears(18)->addDay()->toDateString()]);

        $this->assertNull($servicio->motivoTutor($cumpleHoy, $categoria, $fecha));
        $this->assertSame(ConsentimientoPrivacidadJugador::MOTIVO_MENOR_DE_EDAD, $servicio->motivoTutor($cumpleManana, $categoria, $fecha));
    }

    public function test_reconoce_la_categoria_juvenil_sin_importar_mayusculas_ni_acentos(): void
    {
        $servicio = app(ConsentimientoPrivacidadService::class);

        foreach (['Juvenil', 'JUVENIL B', 'juvenil femenil'] as $nombre) {
            $this->assertSame(
                ConsentimientoPrivacidadJugador::MOTIVO_CATEGORIA_JUVENIL,
                $servicio->motivoTutorPorCategoria(Categoria::factory()->make(['rama' => 'Libre', 'nombre' => $nombre])),
                $nombre,
            );
        }

        $this->assertNull($servicio->motivoTutorPorCategoria(Categoria::factory()->make(['rama' => 'Veteranos', 'nombre' => 'Veteranos 40'])));
    }

    public function test_un_arreglo_en_los_datos_del_tutor_no_provoca_un_error_500(): void
    {
        [$delegado, $equipo, $temporada, $categoria] = $this->contexto('Infantil', 'Infantil A');
        $datos = $this->datos($equipo, $temporada, $categoria, self::MENOR);
        $datos['tutor'] = ['nombre_completo' => ['x'], 'telefono' => ['x'], 'correo' => ['x'], 'parentesco' => ['x'], 'consentimiento' => '1'];

        $this->actingAs($delegado)->from(route('jugadores.create'))->post(route('jugadores.store'), $datos)
            ->assertRedirect(route('jugadores.create'))
            ->assertSessionHasErrors(['tutor.nombre_completo', 'tutor.telefono', 'tutor.parentesco']);

        $this->actingAs($delegado)->get(route('jugadores.create'))->assertOk();
        $this->assertDatabaseCount('jugadores_equipos', 0);
    }

    public function test_los_datos_del_tutor_que_no_son_un_arreglo_muestran_su_error_en_espanol(): void
    {
        [$delegado, $equipo, $temporada, $categoria] = $this->contexto('Infantil', 'Infantil A');
        $datos = $this->datos($equipo, $temporada, $categoria, self::MENOR);
        $datos['tutor'] = 'hola';
        $mensaje = 'Los datos del tutor no son válidos; captúralos en la sección «Consentimiento del tutor».';

        // followingRedirects: la página que ve el Delegado después de la redirección muestra el error.
        $this->actingAs($delegado)->from(route('jugadores.create'))->followingRedirects()
            ->post(route('jugadores.store'), $datos)
            ->assertOk()
            ->assertSee($mensaje);

        $this->assertDatabaseCount('jugadores_equipos', 0);
    }

    public function test_el_correo_del_tutor_largo_responde_en_espanol(): void
    {
        [$delegado, $equipo, $temporada, $categoria] = $this->contexto('Infantil', 'Infantil A');
        $datos = $this->datos($equipo, $temporada, $categoria, self::MENOR);
        $datos['tutor']['correo'] = str_repeat('a', 160).'@correo.test';

        $this->actingAs($delegado)->postJson(route('jugadores.store'), $datos)
            ->assertJsonValidationErrors(['tutor.correo' => 'El correo del tutor no debe tener más de 160 caracteres.']);
    }

    public function test_la_minoria_de_edad_se_cuenta_con_la_hora_de_mexico(): void
    {
        // 19:30 del 13/11 en México ya es 14/11 en UTC: quien cumple 18 el 14/11 todavía es menor.
        Carbon::setTestNow(Carbon::parse('2026-11-13 19:30', 'America/Mexico_City'));
        $servicio = app(ConsentimientoPrivacidadService::class);
        $categoria = Categoria::factory()->make(['rama' => 'Libre', 'nombre' => 'Primera Fuerza']);

        try {
            $cumpleManana = Persona::factory()->make(['fecha_nacimiento' => '2008-11-14']);
            $cumplioHoy = Persona::factory()->make(['fecha_nacimiento' => '2008-11-13']);

            $this->assertSame(ConsentimientoPrivacidadJugador::MOTIVO_MENOR_DE_EDAD, $servicio->motivoTutor($cumpleManana, $categoria));
            $this->assertNull($servicio->motivoTutor($cumplioHoy, $categoria));
        } finally {
            Carbon::setTestNow();
        }
    }

    /** @return array{User, Equipo, Temporada, Categoria} */
    private function contexto(string $rama, string $nombreCategoria): array
    {
        $club = Club::query()->create(['nombre' => 'Club Privacidad '.str()->random(5)]);
        $delegado = User::query()->create([
            'name' => 'Delegado privacidad',
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
        $categoria = Categoria::factory()->create(['temporada_id' => $temporada->id, 'rama' => $rama, 'nombre' => $nombreCategoria]);
        $grupo = Grupo::factory()->create(['categoria_id' => $categoria->id, 'temporada_id' => $temporada->id]);
        $equipo = Equipo::query()->create([
            'club_id' => $club->id,
            'temporada_id' => $temporada->id,
            'grupo_id' => $grupo->id,
            'nombre' => 'Equipo Privacidad',
        ]);

        return [$delegado, $equipo, $temporada, $categoria];
    }

    /** @return array<string, mixed> */
    private function datos(Equipo $equipo, Temporada $temporada, Categoria $categoria, string $nacimiento): array
    {
        return [
            'nombre' => 'Jugador',
            'apellidos' => 'Privacidad',
            'fecha_nacimiento' => $nacimiento,
            'curp' => 'PRIV010101HDFXXX01',
            'temporada_id' => $temporada->id,
            'categoria_id' => $categoria->id,
            'equipo_id' => $equipo->id,
            'documentos' => [
                'curp' => UploadedFile::fake()->create('curp.pdf', 80, 'application/pdf'),
                'fotografia' => UploadedFile::fake()->createWithContent('foto.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/kXcAAAAASUVORK5CYII=') ?: 'png'),
                'comprobante' => UploadedFile::fake()->create('domicilio.pdf', 90, 'application/pdf'),
            ],
            'aviso_privacidad_aceptado' => '1',
            'tutor' => $this->tutor(),
        ];
    }

    /** @return array<string, string> */
    private function tutor(): array
    {
        return [
            'nombre_completo' => 'María  López Hernández',
            'parentesco' => 'Madre',
            'telefono' => '5512345678',
            'correo' => 'Maria.Lopez@correo.test',
            'consentimiento' => '1',
        ];
    }
}
