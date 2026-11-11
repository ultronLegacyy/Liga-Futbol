<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
use App\Models\Categoria;
use App\Models\Club;
use App\Models\DocumentoPersona;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Jugador;
use App\Models\Persona;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// HU-022: protección de los datos personales del jugador y su historial de cambios (RF-04.5, RNF-30)
class ProteccionDatosJugadorTest extends TestCase
{
    use DatabaseTransactions;

    private Club $club;

    private User $delegado;

    private Equipo $equipo;

    private Jugador $jugador;

    private DocumentoPersona $documento;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        Storage::fake('private');
        Storage::fake('public');

        $this->club = Club::create(['nombre' => 'Club HU-022 '.str()->random(6)]);
        $this->delegado = $this->crearDelegado($this->club, 'Delegado HU-022');
        $this->equipo = $this->crearEquipo($this->club, 'Equipo HU-022');

        $persona = $this->crearPersona();
        $this->jugador = $this->equipo->jugadores()->create(['persona_id' => $persona->id]);

        $ruta = 'personas/'.$persona->id.'/comprobante.pdf';
        Storage::disk('private')->put($ruta, 'comprobante de domicilio privado');
        $this->documento = $persona->documentos()->create([
            'tipo' => 'Comprobante', 'disco' => 'private', 'ruta_storage' => $ruta,
            'mime_type' => 'application/pdf', 'tamano_bytes' => 32,
        ]);
    }

    // CA1: el documento (incluido el comprobante de domicilio) no está en el disco público ni tiene URL /storage
    public function test_el_documento_no_tiene_url_publica(): void
    {
        Storage::disk('private')->assertExists($this->documento->ruta_storage);
        Storage::disk('public')->assertMissing($this->documento->ruta_storage);

        $this->get('/storage/'.$this->documento->ruta_storage)->assertNotFound();
        $this->actingAs($this->delegado)->get('/storage/'.$this->documento->ruta_storage)->assertNotFound();
    }

    // CA1: la ficha en JSON no expone la ruta interna del archivo, solo el enlace que pide autorización
    public function test_la_ficha_no_expone_la_ruta_del_archivo(): void
    {
        $respuesta = $this->actingAs($this->delegado)
            ->getJson(route('jugadores.show', $this->jugador))
            ->assertOk()
            ->assertJsonPath('jugador.documentos.0.descarga', route('jugadores.documentos.show', [$this->jugador, $this->documento]));

        $this->assertStringNotContainsString($this->documento->ruta_storage, $respuesta->getContent());
        $this->assertStringNotContainsString('ruta_storage', $respuesta->getContent());
        $this->assertStringNotContainsString('"disco"', $respuesta->getContent());
    }

    // CA1: la descarga autorizada pide a buscadores y cachés no guardar el documento
    public function test_la_descarga_no_es_indexable_ni_se_guarda_en_cache(): void
    {
        $respuesta = $this->actingAs($this->delegado)
            ->get(route('jugadores.documentos.show', [$this->jugador, $this->documento]))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $respuesta->headers->get('Cache-Control'));
    }

    // CA1: por petición directa, sin sesión o sin permiso sobre ese jugador, no se entrega el documento
    public function test_la_peticion_directa_al_documento_pide_autorizacion(): void
    {
        $url = route('jugadores.documentos.show', [$this->jugador, $this->documento]);

        $this->get($url)->assertRedirect(route('login'));

        $otroClub = Club::create(['nombre' => 'Otro club HU-022 '.str()->random(6)]);
        $this->actingAs($this->crearDelegado($otroClub, 'Delegado de otro club'))->get($url)->assertForbidden();

        // Un documento de otra persona no se puede pedir a través de un jugador propio
        $ajena = $this->crearPersona();
        $documentoAjeno = $ajena->documentos()->create([
            'tipo' => 'INE', 'disco' => 'private', 'ruta_storage' => 'personas/'.$ajena->id.'/ine.pdf',
            'mime_type' => 'application/pdf', 'tamano_bytes' => 10,
        ]);
        $this->actingAs($this->delegado)
            ->get(route('jugadores.documentos.show', [$this->jugador, $documentoAjeno]))
            ->assertNotFound();
    }

    // CA1: las pantallas internas y robots.txt piden no indexar las secciones con datos personales
    public function test_las_pantallas_con_datos_personales_no_son_indexables(): void
    {
        $this->actingAs($this->delegado)
            ->get(route('jugadores.gestion', $this->jugador))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow, noarchive">', false);

        $robots = (string) file_get_contents(public_path('robots.txt'));
        $this->assertStringContainsString('Disallow: /jugadores', $robots);
        $this->assertStringContainsString('Disallow: /personas', $robots);
        $this->assertStringContainsString('Disallow: /storage', $robots);
    }

    // CA2: un cambio sin motivo se rechaza y no cambia nada
    public function test_un_cambio_sin_motivo_se_rechaza(): void
    {
        $antes = AuditoriaLog::query()->count();

        $this->actingAs($this->delegado)
            ->putJson(route('jugadores.update', $this->jugador), ['dorsal' => 7])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo' => 'Escribe el motivo del cambio; quedará en el historial del jugador.']);

        $this->assertNull($this->jugador->fresh()?->dorsal);
        $this->assertSame($antes, AuditoriaLog::query()->count());
    }

    // CA2: el cambio queda en el historial con usuario, fecha y motivo, y se ve en la pantalla del historial
    public function test_un_cambio_queda_en_el_historial_con_usuario_fecha_y_motivo(): void
    {
        $this->actingAs($this->delegado)
            ->putJson(route('jugadores.update', $this->jugador), ['dorsal' => 7, 'motivo' => 'Cambió de número a petición del entrenador'])
            ->assertOk();

        $registro = AuditoriaLog::query()
            ->where('tabla_afectada', 'jugadores_equipos')
            ->where('registro_id', $this->jugador->id)
            ->where('accion', 'actualizar')
            ->sole();

        $this->assertSame($this->delegado->id, $registro->usuario_id);
        $this->assertNotNull($registro->created_at);
        $this->assertSame('Cambio de datos del jugador: Cambió de número a petición del entrenador', $registro->motivo);
        $this->assertSame(['dorsal' => null], $registro->valores_anteriores);
        $this->assertSame(['dorsal' => 7], $registro->valores_nuevos);

        $this->actingAs($this->delegado)
            ->get(route('jugadores.historial', $this->jugador))
            ->assertOk()
            ->assertSeeInOrder([
                $registro->created_at->timezone('America/Mexico_City')->format('d/m/Y H:i'),
                'Delegado HU-022',
                'Cambio de datos',
                'Dorsal: — → 7',
                'Cambió de número a petición del entrenador',
            ]);
    }

    // CA2: los cambios de estado (HU-018) y los documentos agregados también salen en el historial
    public function test_el_historial_junta_estado_documentos_y_datos(): void
    {
        $administrador = $this->crearUsuario('Administrador General', 'Administrador HU-022');

        $this->actingAs($administrador)
            ->putJson(route('jugadores.estado.update', $this->jugador), ['estado_elegibilidad' => 'Suspendido', 'motivo' => 'Acumuló tarjetas'])
            ->assertOk();
        AuditoriaLog::create([
            'usuario_id' => $this->delegado->id, 'accion' => 'documento_jugador_registrado',
            'tabla_afectada' => 'documentos_persona', 'registro_id' => $this->documento->id,
            'valores_nuevos' => ['tipo' => 'Comprobante'], 'motivo' => 'Inscripción de jugador en equipo',
        ]);

        $this->actingAs($this->delegado)
            ->get(route('jugadores.historial', $this->jugador))
            ->assertOk()
            ->assertSee('Estado: Activo → Suspendido')
            ->assertSee('Acumuló tarjetas')
            ->assertSee('Administrador HU-022')
            ->assertSee('Documento agregado')
            ->assertSee('Tipo de documento: — → Comprobante')
            ->assertDontSee('personas/'.$this->jugador->persona_id);
    }

    // CA2: el historial de un jugador no muestra cambios de otro, y solo lo ve quien puede ver al jugador
    public function test_el_historial_es_solo_del_jugador_y_pide_autorizacion(): void
    {
        $otro = $this->equipo->jugadores()->create(['persona_id' => $this->crearPersona()->id]);
        $this->actingAs($this->delegado)
            ->putJson(route('jugadores.update', $otro), ['dorsal' => 23, 'motivo' => 'Cambio de otro jugador'])
            ->assertOk();

        $this->actingAs($this->delegado)
            ->get(route('jugadores.historial', $this->jugador))
            ->assertOk()
            ->assertDontSee('Cambio de otro jugador');

        $otroClub = Club::create(['nombre' => 'Club ajeno HU-022 '.str()->random(6)]);
        $this->actingAs($this->crearDelegado($otroClub, 'Delegado ajeno'))
            ->get(route('jugadores.historial', $this->jugador))
            ->assertForbidden();
        $this->actingAs($this->crearUsuario('Tesorero', 'Tesorero HU-022'))
            ->get(route('jugadores.historial', $this->jugador))
            ->assertForbidden();
    }

    private function crearPersona(): Persona
    {
        return Persona::query()->create([
            'nombre' => 'Jugador',
            'apellidos' => 'HU-022',
            'fecha_nacimiento' => '2010-01-01',
            'curp' => fake()->unique()->regexify('[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[0-9A-Z][0-9]'),
        ]);
    }

    private function crearEquipo(Club $club, string $nombre): Equipo
    {
        $temporada = Temporada::factory()->activa()->create();
        $categoria = Categoria::factory()->create(['temporada_id' => $temporada->id]);
        $grupo = Grupo::factory()->create(['categoria_id' => $categoria->id, 'temporada_id' => $temporada->id]);

        return Equipo::query()->create([
            'club_id' => $club->id, 'temporada_id' => $temporada->id,
            'grupo_id' => $grupo->id, 'nombre' => $nombre,
        ]);
    }

    private function crearDelegado(Club $club, string $nombre): User
    {
        $usuario = User::query()->create([
            'name' => $nombre, 'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'), 'rol' => 'Directivo Club',
            'estatus' => 'activo', 'club_id' => $club->id,
        ]);
        $usuario->assignRole('Delegado del Club');

        return $usuario;
    }

    private function crearUsuario(string $rol, string $nombre): User
    {
        $usuario = User::query()->create([
            'name' => $nombre, 'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'), 'rol' => 'Consultor',
            'estatus' => 'activo',
        ]);
        $usuario->assignRole($rol);

        return $usuario;
    }
}
