<?php

namespace Tests\Feature;

use App\Models\Convocatoria;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ConvocatoriaPrivadaTest extends TestCase
{
    // Manejo transaccional para revertir cambios tras cada prueba
    use DatabaseTransactions;

    private const NOMBRE_TEMPORADA = 'Temporada Convocatoria Privada 2027';

    protected function setUp(): void
    {
        parent::setUp();

        // Sembrado de roles y permisos del sistema
        $this->seed(RoleAndPermissionSeeder::class);

        // Discos falsos para no escribir en storage/ real
        Storage::fake('local');
        Storage::fake('public');

        // El alta de temporadas asigna liga_id = 1; se garantiza que exista esa liga
        DB::table('ligas')->insertOrIgnore([
            'id' => 1,
            'nombre' => 'Liga Municipal de Futbol Amateur de Morelia, A.C.',
            'estado' => 'activa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_administrador_crea_temporada_con_convocatoria_en_disco_privado(): void
    {
        $admin = $this->crearUsuario('admin_convocatoria@liga.com', 'Administrador General', 'Administrador General');

        $respuesta = $this->actingAs($admin)->post('/temporadas', $this->datosTemporada());

        $respuesta->assertRedirect();
        $respuesta->assertSessionHas('success', 'Temporada creada correctamente.');

        // La temporada y su convocatoria quedan registradas
        $temporada = Temporada::where('nombre', self::NOMBRE_TEMPORADA)->first();
        $this->assertNotNull($temporada);

        $convocatoria = Convocatoria::where('temporada_id', $temporada->id)->first();
        $this->assertNotNull($convocatoria);

        // El PDF vive en el disco privado bajo convocatorias/ y no en el disco publico
        $this->assertStringStartsWith('convocatorias/', $convocatoria->archivo_pdf);
        Storage::disk('local')->assertExists($convocatoria->archivo_pdf);
        Storage::disk('public')->assertMissing($convocatoria->archivo_pdf);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_convocatoria_no_tiene_url_directa_en_storage(): void
    {
        $admin = $this->crearUsuario('admin_storage@liga.com', 'Administrador General', 'Administrador General');
        [, $rutaPdf] = $this->crearTemporadaConConvocatoria();

        // Ni un invitado ni el administrador pueden abrir el archivo por /storage
        $this->get('/storage/'.$rutaPdf)->assertNotFound();
        $this->actingAs($admin)->get('/storage/'.$rutaPdf)->assertNotFound();
    }

    public function test_invitado_es_redirigido_al_login_al_descargar_la_convocatoria(): void
    {
        [$temporada] = $this->crearTemporadaConConvocatoria();

        $this->get(route('temporadas.convocatoria', $temporada))->assertRedirect(route('login'));
    }

    public function test_usuario_sin_permiso_de_ver_temporadas_recibe_403_al_descargar_la_convocatoria(): void
    {
        // El rol Tesorero no tiene asignado temporadas.ver
        $tesorero = $this->crearUsuario('tesorero_convocatoria@liga.com', 'Tesorero', 'Tesorero');
        [$temporada] = $this->crearTemporadaConConvocatoria();

        $this->actingAs($tesorero)->get(route('temporadas.convocatoria', $temporada))->assertForbidden();
    }

    public function test_administrador_descarga_la_convocatoria_como_pdf_no_indexable(): void
    {
        $admin = $this->crearUsuario('admin_descarga@liga.com', 'Administrador General', 'Administrador General');
        [$temporada, $rutaPdf] = $this->crearTemporadaConConvocatoria();

        $respuesta = $this->actingAs($admin)->get(route('temporadas.convocatoria', $temporada));

        $respuesta->assertOk();
        $respuesta->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('noindex', (string) $respuesta->headers->get('X-Robots-Tag'));
        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $respuesta->headers->get('Cache-Control'));
        $this->assertStringStartsWith('inline', (string) $respuesta->headers->get('Content-Disposition'));
        $this->assertSame(Storage::disk('local')->get($rutaPdf), $respuesta->streamedContent());
    }

    public function test_temporada_sin_archivo_de_convocatoria_responde_404(): void
    {
        $admin = $this->crearUsuario('admin_sin_archivo@liga.com', 'Administrador General', 'Administrador General');
        $temporada = Temporada::factory()->activa()->create();

        // Sin convocatoria registrada
        $this->actingAs($admin)->get(route('temporadas.convocatoria', $temporada))->assertNotFound();

        // Con registro pero sin archivo en el disco
        Convocatoria::create([
            'temporada_id' => $temporada->id,
            'archivo_pdf' => 'convocatorias/inexistente.pdf',
            'reglas_versionadas' => json_encode([]),
        ]);
        $this->actingAs($admin)->get(route('temporadas.convocatoria', $temporada))->assertNotFound();
    }

    public function test_invitado_no_puede_crear_temporadas_y_es_redirigido_al_login(): void
    {
        $respuesta = $this->post('/temporadas', $this->datosTemporada());

        $respuesta->assertRedirect(route('login'));
        $this->assertDatabaseMissing('temporadas', ['nombre' => self::NOMBRE_TEMPORADA]);
        Storage::disk('local')->assertDirectoryEmpty('convocatorias');
    }

    public function test_usuario_sin_permiso_de_crear_temporadas_recibe_403(): void
    {
        // El rol Presidente del Club puede ver temporadas pero no crearlas
        $directivo = $this->crearUsuario('directivo_convocatoria@liga.com', 'Directivo Club', 'Presidente del Club');

        $respuesta = $this->actingAs($directivo)->post('/temporadas', $this->datosTemporada());

        $respuesta->assertForbidden();
        $this->assertDatabaseMissing('temporadas', ['nombre' => self::NOMBRE_TEMPORADA]);
        Storage::disk('local')->assertDirectoryEmpty('convocatorias');
    }

    public function test_archivo_que_no_es_pdf_se_rechaza_y_no_se_crea_la_temporada(): void
    {
        $admin = $this->crearUsuario('admin_no_pdf@liga.com', 'Administrador General', 'Administrador General');

        $respuesta = $this->actingAs($admin)->post('/temporadas', $this->datosTemporada(
            UploadedFile::fake()->create('convocatoria.txt', 10, 'text/plain')
        ));

        $respuesta->assertSessionHasErrors('convocatoria_pdf');
        $this->assertDatabaseMissing('temporadas', ['nombre' => self::NOMBRE_TEMPORADA]);
        Storage::disk('local')->assertDirectoryEmpty('convocatorias');
    }

    public function test_falla_al_registrar_la_convocatoria_no_deja_temporada_ni_pdf_huerfano(): void
    {
        $admin = $this->crearUsuario('admin_atomicidad@liga.com', 'Administrador General', 'Administrador General');
        $evento = 'eloquent.creating: '.Convocatoria::class;

        // Se simula una falla justo al registrar la convocatoria (RNF-22)
        Event::listen($evento, function () {
            throw new RuntimeException('Falla simulada al registrar la convocatoria.');
        });

        $this->withoutExceptionHandling();
        $excepcion = null;

        try {
            $this->actingAs($admin)->post('/temporadas', $this->datosTemporada());
        } catch (RuntimeException $capturada) {
            $excepcion = $capturada;
        } finally {
            Event::forget($evento);
        }

        // El controlador vuelve a lanzar la excepcion original
        $this->assertNotNull($excepcion);
        $this->assertSame('Falla simulada al registrar la convocatoria.', $excepcion->getMessage());

        // No quedan datos parciales: ni la temporada ni el PDF
        $this->assertDatabaseMissing('temporadas', ['nombre' => self::NOMBRE_TEMPORADA]);
        Storage::disk('local')->assertDirectoryEmpty('convocatorias');
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

    // Datos validos para el alta de una temporada con su convocatoria
    private function datosTemporada(?UploadedFile $archivo = null): array
    {
        return [
            'nombre' => self::NOMBRE_TEMPORADA,
            'fecha_inicio' => '2027-01-10',
            'fecha_fin' => '2027-06-30',
            'convocatoria_pdf' => $archivo ?? UploadedFile::fake()->create('convocatoria.pdf', 100, 'application/pdf'),
            'motivo' => 'Alta de la temporada con su convocatoria aprobada en asamblea.',
        ];
    }

    // Prepara una temporada activa con su PDF guardado en el disco privado
    private function crearTemporadaConConvocatoria(): array
    {
        $temporada = Temporada::factory()->activa()->create();
        $rutaPdf = UploadedFile::fake()
            ->create('convocatoria.pdf', 100, 'application/pdf')
            ->store('convocatorias', 'local');

        Convocatoria::create([
            'temporada_id' => $temporada->id,
            'archivo_pdf' => $rutaPdf,
            'reglas_versionadas' => json_encode([]),
        ]);

        return [$temporada, $rutaPdf];
    }
}
