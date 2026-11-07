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

class JugadorFormularioRegistroTest extends TestCase
{
    use DatabaseTransactions;

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

        $respuesta->assertRedirect(route('jugadores.create'))->assertSessionHas('success');
        $persona = Persona::query()->where('curp', 'TEST010101HDFXXX01')->firstOrFail();
        $jugador = Jugador::query()->where('persona_id', $persona->id)->firstOrFail();
        $this->assertSame($equipo->id, $jugador->equipo_id);
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

    public function test_acepta_fotografia_capturada_desde_camara_en_base64(): void
    {
        [$delegado, $equipo, $temporada, $categoria] = $this->crearContexto();
        $datos = $this->datosFormulario($equipo, $temporada, $categoria);
        $datos['fotografia_base64'] = 'data:image/png;base64,'.$this->pngBase64();

        $this->actingAs($delegado)->post(route('jugadores.store'), $datos)
            ->assertRedirect(route('jugadores.create'))
            ->assertSessionHas('success');

        $persona = Persona::query()->where('curp', 'TEST010101HDFXXX01')->firstOrFail();
        $foto = $persona->documentos()->where('tipo', 'Foto')->firstOrFail();
        $this->assertSame('private', $foto->disco);
        $this->assertSame('image/png', $foto->mime_type);
        Storage::disk('private')->assertExists($foto->ruta_storage);
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
            'fecha_nacimiento' => '2010-01-01',
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
