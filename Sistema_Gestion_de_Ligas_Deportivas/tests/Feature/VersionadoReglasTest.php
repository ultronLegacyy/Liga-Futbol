<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
use App\Models\Convocatoria;
use App\Models\Temporada;
use App\Models\User;
use App\Services\AuditService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class VersionadoReglasTest extends TestCase
{
    // Manejo transaccional para revertir cambios tras cada prueba (la auditoria no se puede borrar)
    use DatabaseTransactions;

    private const IP_PRUEBA = '203.0.113.40';

    private const MOTIVO = 'Se ajusta el reglamento de amonestaciones por acuerdo de la asamblea.';

    private const CONTENIDO_V1 = '%PDF-1.4 convocatoria version 1';

    private const CONTENIDO_V2 = '%PDF-1.4 convocatoria version 2';

    private const NOMBRE_ALTA = 'Temporada Alta con Motivo 2027';

    private const MOTIVO_ALTA = 'Alta de la temporada con la convocatoria aprobada en asamblea.';

    protected function setUp(): void
    {
        parent::setUp();

        // Sembrado de roles y permisos del sistema
        $this->seed(RoleAndPermissionSeeder::class);

        // Disco falso para no escribir en storage/ real
        Storage::fake('local');
    }

    public function test_administrador_registra_una_nueva_version_con_motivo_y_se_conserva_la_anterior(): void
    {
        $admin = $this->crearUsuario('admin_versionado@liga.com', 'Administrador General', 'Administrador General');
        [$temporada, $rutaV1] = $this->crearTemporadaConConvocatoria();

        $respuesta = $this->actingAs($admin)
            ->withServerVariables(['REMOTE_ADDR' => self::IP_PRUEBA])
            ->post(route('temporadas.convocatoria.versionar', $temporada), $this->datosVersion());

        $respuesta->assertRedirect();
        $respuesta->assertSessionHas('success', 'Se registró la versión 2 de la convocatoria.');

        // La version 1 se conserva intacta: fila y archivo
        $versionUno = Convocatoria::where('temporada_id', $temporada->id)->where('version', 1)->sole();
        $this->assertSame($rutaV1, $versionUno->archivo_pdf);
        Storage::disk('local')->assertExists($rutaV1);

        // La version 2 vive en el disco privado con su propio archivo
        $versionDos = Convocatoria::where('temporada_id', $temporada->id)->where('version', 2)->sole();
        $this->assertStringStartsWith('convocatorias/', $versionDos->archivo_pdf);
        $this->assertNotSame($rutaV1, $versionDos->archivo_pdf);
        $this->assertSame(self::CONTENIDO_V2, Storage::disk('local')->get($versionDos->archivo_pdf));
        $this->assertSame(2, Convocatoria::where('temporada_id', $temporada->id)->count());

        // La auditoria registra usuario, fecha, IP y motivo de la nueva version
        $log = AuditoriaLog::where('tabla_afectada', 'convocatorias')
            ->where('registro_id', $versionDos->id)
            ->where('accion', 'crear')
            ->sole();

        $this->assertSame($admin->id, $log->usuario_id);
        $this->assertSame(self::MOTIVO, $log->motivo);
        $this->assertSame(self::IP_PRUEBA, $log->ip);
        $this->assertSame(2, $log->valores_nuevos['version']);
        $this->assertNotNull($log->created_at);
        $this->assertTrue($log->created_at->between(now()->subMinute(), now()->addMinute()));

        // La version anterior no se modifico ni se elimino
        $this->assertSame(0, AuditoriaLog::where('tabla_afectada', 'convocatorias')
            ->where('registro_id', $versionUno->id)
            ->whereIn('accion', ['actualizar', 'eliminar'])
            ->count());
    }

    public function test_las_versiones_se_numeran_de_forma_consecutiva(): void
    {
        $admin = $this->crearUsuario('admin_consecutivo@liga.com', 'Administrador General', 'Administrador General');
        [$temporada] = $this->crearTemporadaConConvocatoria();

        $this->actingAs($admin)->post(route('temporadas.convocatoria.versionar', $temporada), $this->datosVersion())
            ->assertSessionHas('success', 'Se registró la versión 2 de la convocatoria.');
        $this->actingAs($admin)->post(route('temporadas.convocatoria.versionar', $temporada), $this->datosVersion())
            ->assertSessionHas('success', 'Se registró la versión 3 de la convocatoria.');

        $this->assertSame(
            [1, 2, 3],
            Convocatoria::where('temporada_id', $temporada->id)->orderBy('version')->pluck('version')->all()
        );
    }

    public function test_sin_motivo_se_rechaza_y_no_se_crea_version_ni_archivo(): void
    {
        $admin = $this->crearUsuario('admin_sin_motivo@liga.com', 'Administrador General', 'Administrador General');
        [$temporada, $rutaV1] = $this->crearTemporadaConConvocatoria();
        $ruta = route('temporadas.convocatoria.versionar', $temporada);

        // Sin el campo motivo
        $datos = $this->datosVersion();
        unset($datos['motivo']);
        $this->actingAs($admin)->post($ruta, $datos)->assertSessionHasErrors('motivo');

        // Con el motivo vacio o solo con espacios
        $this->actingAs($admin)->post($ruta, $this->datosVersion(''))->assertSessionHasErrors('motivo');
        $this->actingAs($admin)->post($ruta, $this->datosVersion('   '))->assertSessionHasErrors('motivo');

        $this->assertSoloExisteLaVersionUno($temporada, $rutaV1);
    }

    public function test_alta_de_temporada_registra_la_version_1_con_su_motivo(): void
    {
        $admin = $this->crearUsuario('admin_alta_motivo@liga.com', 'Administrador General', 'Administrador General');
        $this->asegurarLigaUno();

        $respuesta = $this->actingAs($admin)
            ->withServerVariables(['REMOTE_ADDR' => self::IP_PRUEBA])
            ->post(route('temporadas.store'), $this->datosAlta());

        $respuesta->assertRedirect();
        $respuesta->assertSessionHas('success', 'Temporada creada correctamente.');

        $temporada = Temporada::where('nombre', self::NOMBRE_ALTA)->sole();
        $versionUno = Convocatoria::where('temporada_id', $temporada->id)->sole();
        $this->assertSame(1, $versionUno->version);

        // La temporada y la version 1 quedan auditadas con el motivo del alta (RF-01.3)
        foreach (['temporadas' => $temporada->id, 'convocatorias' => $versionUno->id] as $tabla => $registroId) {
            $log = AuditoriaLog::where('tabla_afectada', $tabla)
                ->where('registro_id', $registroId)
                ->where('accion', 'crear')
                ->sole();

            $this->assertSame(self::MOTIVO_ALTA, $log->motivo);
            $this->assertSame($admin->id, $log->usuario_id);
            $this->assertSame(self::IP_PRUEBA, $log->ip);
        }

        // El detalle muestra el motivo y el usuario de la version 1
        $this->actingAs($admin)->get(route('temporadas.show', $temporada))
            ->assertOk()
            ->assertJsonPath('convocatorias.0.version', 1)
            ->assertJsonPath('convocatorias.0.motivo', self::MOTIVO_ALTA)
            ->assertJsonPath('convocatorias.0.usuario', $admin->name);
    }

    public function test_alta_sin_motivo_se_rechaza_y_no_se_crea_temporada_ni_archivo(): void
    {
        $admin = $this->crearUsuario('admin_alta_sin_motivo@liga.com', 'Administrador General', 'Administrador General');
        $this->asegurarLigaUno();

        // Sin el campo motivo
        $datos = $this->datosAlta();
        unset($datos['motivo']);
        $this->actingAs($admin)->post(route('temporadas.store'), $datos)->assertSessionHasErrors('motivo');

        // Con el motivo vacio o solo con espacios
        $this->actingAs($admin)->post(route('temporadas.store'), $this->datosAlta(''))->assertSessionHasErrors('motivo');
        $this->actingAs($admin)->post(route('temporadas.store'), $this->datosAlta('   '))->assertSessionHasErrors('motivo');

        $this->assertDatabaseMissing('temporadas', ['nombre' => self::NOMBRE_ALTA]);
        Storage::disk('local')->assertDirectoryEmpty('convocatorias');
    }

    public function test_formulario_de_alta_muestra_el_campo_motivo_y_su_error(): void
    {
        $admin = $this->crearUsuario('admin_formulario_alta@liga.com', 'Administrador General', 'Administrador General');
        $this->asegurarLigaUno();

        // El campo es obligatorio y su etiqueta esta asociada para lectores de pantalla y teclado
        $this->actingAs($admin)->get(route('temporadas.create'))
            ->assertOk()
            ->assertSee('<label for="motivo">Motivo:</label>', false)
            ->assertSee('id="motivo" name="motivo"', false);

        // Al faltar el motivo, el formulario vuelve con el mensaje de error del campo
        $datos = $this->datosAlta();
        unset($datos['motivo']);

        $this->actingAs($admin)
            ->from(route('temporadas.create'))
            ->followingRedirects()
            ->post(route('temporadas.store'), $datos)
            ->assertOk()
            ->assertSee('id="motivo-error"', false)
            ->assertSee('aria-invalid="true"', false);
    }

    public function test_archivo_que_no_es_pdf_se_rechaza_y_no_se_crea_version(): void
    {
        $admin = $this->crearUsuario('admin_no_pdf_version@liga.com', 'Administrador General', 'Administrador General');
        [$temporada, $rutaV1] = $this->crearTemporadaConConvocatoria();

        $datos = $this->datosVersion();
        $datos['convocatoria_pdf'] = UploadedFile::fake()->create('reglas.txt', 10, 'text/plain');

        $this->actingAs($admin)
            ->post(route('temporadas.convocatoria.versionar', $temporada), $datos)
            ->assertSessionHasErrors('convocatoria_pdf');

        $this->assertSoloExisteLaVersionUno($temporada, $rutaV1);
    }

    public function test_temporada_cerrada_responde_403_y_no_se_crea_version_ni_archivo(): void
    {
        $admin = $this->crearUsuario('admin_cerrada_version@liga.com', 'Administrador General', 'Administrador General');
        [$temporada, $rutaV1] = $this->crearTemporadaConConvocatoria(cerrada: true);

        $respuesta = $this->actingAs($admin)
            ->post(route('temporadas.convocatoria.versionar', $temporada), $this->datosVersion());

        $respuesta->assertForbidden();
        $respuesta->assertSee('Error: Una temporada cerrada no puede modificarse.');

        // La autorizacion ocurre antes de validar: sin datos tambien responde 403
        $this->actingAs($admin)
            ->post(route('temporadas.convocatoria.versionar', $temporada), [])
            ->assertForbidden();

        $this->assertSoloExisteLaVersionUno($temporada, $rutaV1);
    }

    public function test_usuario_sin_permiso_de_editar_temporadas_recibe_403(): void
    {
        // El rol Directivo de Club puede ver temporadas pero no editarlas
        $directivo = $this->crearUsuario('directivo_version@liga.com', 'Directivo Club', 'Directivo de Club');
        [$temporada, $rutaV1] = $this->crearTemporadaConConvocatoria();

        $this->actingAs($directivo)
            ->post(route('temporadas.convocatoria.versionar', $temporada), $this->datosVersion())
            ->assertForbidden();

        $this->assertSoloExisteLaVersionUno($temporada, $rutaV1);
    }

    public function test_invitado_es_redirigido_al_login_al_versionar_la_convocatoria(): void
    {
        [$temporada, $rutaV1] = $this->crearTemporadaConConvocatoria();

        $this->post(route('temporadas.convocatoria.versionar', $temporada), $this->datosVersion())
            ->assertRedirect(route('login'));

        $this->assertSoloExisteLaVersionUno($temporada, $rutaV1);
    }

    public function test_descarga_entrega_la_version_mas_alta_y_permite_consultar_las_anteriores(): void
    {
        $admin = $this->crearUsuario('admin_descarga_version@liga.com', 'Administrador General', 'Administrador General');
        [$temporada] = $this->crearTemporadaConConvocatoria();

        $this->actingAs($admin)
            ->post(route('temporadas.convocatoria.versionar', $temporada), $this->datosVersion())
            ->assertSessionHasNoErrors();

        // Sin parametro se entrega la version mas alta
        $vigente = $this->actingAs($admin)->get(route('temporadas.convocatoria', $temporada));
        $vigente->assertOk();
        $vigente->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(self::CONTENIDO_V2, $vigente->streamedContent());

        // Con ?version=1 se entrega el contenido de la primera version
        $anterior = $this->actingAs($admin)->get(route('temporadas.convocatoria', ['temporada' => $temporada, 'version' => 1]));
        $anterior->assertOk();
        $this->assertSame(self::CONTENIDO_V1, $anterior->streamedContent());

        // Versiones inexistentes o invalidas responden 404
        foreach (['99', '0', '-1', 'abc', ''] as $version) {
            $this->actingAs($admin)
                ->get(route('temporadas.convocatoria', ['temporada' => $temporada, 'version' => $version]))
                ->assertNotFound();
        }
    }

    public function test_detalle_de_la_temporada_lista_las_versiones_con_motivo_y_usuario(): void
    {
        $admin = $this->crearUsuario('admin_detalle_version@liga.com', 'Administrador General', 'Administrador General');
        $presidente = $this->crearUsuario('presidente_detalle_version@liga.com', 'Presidente', 'Presidente');

        // La version 1 la publica el administrador con su propio motivo
        $this->actingAs($admin);
        [$temporada] = app(AuditService::class)->conMotivo(
            'Publicacion inicial de la convocatoria.',
            fn () => $this->crearTemporadaConConvocatoria()
        );

        // La version 2 la registra el presidente por la ruta protegida
        $this->actingAs($presidente)
            ->post(route('temporadas.convocatoria.versionar', $temporada), $this->datosVersion())
            ->assertSessionHasNoErrors();

        $respuesta = $this->actingAs($admin)->getJson(route('temporadas.show', $temporada));

        $respuesta->assertOk();
        $respuesta->assertJsonCount(2, 'convocatorias');
        $respuesta->assertJsonPath('convocatoria_url', route('temporadas.convocatoria', $temporada));

        $respuesta->assertJsonPath('convocatorias.0.version', 1);
        $respuesta->assertJsonPath('convocatorias.0.motivo', 'Publicacion inicial de la convocatoria.');
        $respuesta->assertJsonPath('convocatorias.0.usuario', $admin->name);
        $respuesta->assertJsonPath('convocatorias.0.url', route('temporadas.convocatoria', ['temporada' => $temporada, 'version' => 1]));

        $respuesta->assertJsonPath('convocatorias.1.version', 2);
        $respuesta->assertJsonPath('convocatorias.1.motivo', self::MOTIVO);
        $respuesta->assertJsonPath('convocatorias.1.usuario', $presidente->name);
        $respuesta->assertJsonPath('convocatorias.1.url', route('temporadas.convocatoria', ['temporada' => $temporada, 'version' => 2]));

        $this->assertNotNull($respuesta->json('convocatorias.0.fecha'));
        $this->assertNotNull($respuesta->json('convocatorias.1.fecha'));
    }

    public function test_detalle_de_la_temporada_consulta_las_versiones_sin_n_mas_uno(): void
    {
        $admin = $this->crearUsuario('admin_consultas_version@liga.com', 'Administrador General', 'Administrador General');
        [$temporada] = $this->crearTemporadaConConvocatoria();

        foreach (range(1, 3) as $intento) {
            $this->actingAs($admin)
                ->post(route('temporadas.convocatoria.versionar', $temporada), $this->datosVersion())
                ->assertSessionHasNoErrors();
        }

        $consultas = [];
        DB::listen(function ($consulta) use (&$consultas) {
            $consultas[] = $consulta->sql;
        });

        $this->actingAs($admin)->getJson(route('temporadas.show', $temporada))->assertJsonCount(4, 'convocatorias');

        // Una sola consulta por tabla sin importar cuantas versiones existan
        $this->assertCount(1, preg_grep('/from `convocatorias`/', $consultas));
        $this->assertCount(1, preg_grep('/from `auditoria_logs`/', $consultas));
        $this->assertCount(1, preg_grep('/from `usuarios`/', $consultas));
    }

    public function test_no_pueden_existir_dos_convocatorias_con_la_misma_version(): void
    {
        [$temporada] = $this->crearTemporadaConConvocatoria();

        $this->expectException(UniqueConstraintViolationException::class);

        Convocatoria::create([
            'temporada_id' => $temporada->id,
            'version' => 1,
            'archivo_pdf' => 'convocatorias/duplicada.pdf',
            'reglas_versionadas' => json_encode([]),
        ]);
    }

    public function test_falla_al_registrar_la_auditoria_no_deja_version_ni_pdf(): void
    {
        $this->assertFallaNoDejaVersionNiPdf(
            'eloquent.creating: '.AuditoriaLog::class,
            'Falla simulada al registrar la auditoria.'
        );
    }

    public function test_falla_al_crear_la_convocatoria_no_deja_version_ni_pdf(): void
    {
        $this->assertFallaNoDejaVersionNiPdf(
            'eloquent.creating: '.Convocatoria::class,
            'Falla simulada al crear la convocatoria.'
        );
    }

    // Simula una falla durante el versionado y verifica que no quedan datos parciales (RNF-22)
    private function assertFallaNoDejaVersionNiPdf(string $evento, string $mensaje): void
    {
        $admin = $this->crearUsuario('admin_atomicidad_version@liga.com', 'Administrador General', 'Administrador General');
        [$temporada, $rutaV1] = $this->crearTemporadaConConvocatoria();

        Event::listen($evento, function () use ($mensaje) {
            throw new RuntimeException($mensaje);
        });

        $this->withoutExceptionHandling();
        $excepcion = null;

        try {
            $this->actingAs($admin)->post(route('temporadas.convocatoria.versionar', $temporada), $this->datosVersion());
        } catch (RuntimeException $capturada) {
            $excepcion = $capturada;
        } finally {
            Event::forget($evento);
        }

        // El controlador vuelve a lanzar la excepcion original
        $this->assertNotNull($excepcion);
        $this->assertSame($mensaje, $excepcion->getMessage());

        // No queda la version nueva, ni su PDF, ni su auditoria
        $this->assertSoloExisteLaVersionUno($temporada, $rutaV1);
        $this->assertSame(0, AuditoriaLog::where('tabla_afectada', 'convocatorias')
            ->where('motivo', self::MOTIVO)
            ->count());
    }

    // Verifica que la temporada conserva unicamente su version 1 y su archivo
    private function assertSoloExisteLaVersionUno(Temporada $temporada, string $rutaV1): void
    {
        $this->assertSame([1], Convocatoria::where('temporada_id', $temporada->id)->pluck('version')->all());
        $this->assertSame([$rutaV1], Storage::disk('local')->allFiles('convocatorias'));
    }

    // Crea un usuario activo con el rol de Spatie indicado
    private function crearUsuario(string $email, string $rolColumna, string $rolSpatie): User
    {
        $usuario = User::create([
            'name' => 'Usuario '.$rolSpatie,
            'email' => $email,
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => $rolColumna,
        ]);
        $usuario->assignRole($rolSpatie);

        return $usuario;
    }

    // Datos validos para registrar una nueva version de la convocatoria
    private function datosVersion(string $motivo = self::MOTIVO): array
    {
        return [
            'convocatoria_pdf' => UploadedFile::fake()->createWithContent('convocatoria-v2.pdf', self::CONTENIDO_V2),
            'motivo' => $motivo,
        ];
    }

    // Datos validos para el alta de una temporada con la version 1 de su convocatoria
    private function datosAlta(string $motivo = self::MOTIVO_ALTA): array
    {
        return [
            'nombre' => self::NOMBRE_ALTA,
            'fecha_inicio' => '2027-01-10',
            'fecha_fin' => '2027-06-30',
            'convocatoria_pdf' => UploadedFile::fake()->createWithContent('convocatoria-v1.pdf', self::CONTENIDO_V1),
            'motivo' => $motivo,
        ];
    }

    // El alta de temporadas asigna liga_id = 1; se garantiza que exista esa liga
    private function asegurarLigaUno(): void
    {
        DB::table('ligas')->insertOrIgnore([
            'id' => 1,
            'nombre' => 'Liga Municipal de Futbol Amateur de Morelia, A.C.',
            'estado' => 'activa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // Prepara una temporada con la version 1 de su convocatoria guardada en el disco privado
    private function crearTemporadaConConvocatoria(bool $cerrada = false): array
    {
        $temporada = $cerrada
            ? Temporada::factory()->cerrada()->create()
            : Temporada::factory()->activa()->create();

        $rutaPdf = UploadedFile::fake()
            ->createWithContent('convocatoria-v1.pdf', self::CONTENIDO_V1)
            ->store('convocatorias', 'local');

        Convocatoria::create([
            'temporada_id' => $temporada->id,
            'version' => 1,
            'archivo_pdf' => $rutaPdf,
            'reglas_versionadas' => json_encode([]),
        ]);

        return [$temporada, $rutaPdf];
    }
}
