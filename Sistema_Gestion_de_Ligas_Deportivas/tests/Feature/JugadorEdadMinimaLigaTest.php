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
use App\Services\ReglaEdadCategoriaService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\Concerns\DatosPrivacidadJugador;
use Tests\TestCase;

/** Regla de la Liga: solo se inscriben jugadores mayores de edad (config/jugadores.php). */
class JugadorEdadMinimaLigaTest extends TestCase
{
    use DatabaseTransactions;
    use DatosPrivacidadJugador;

    private const MENSAJE = 'La Liga solo inscribe jugadores mayores de edad: el jugador debe tener 18 años cumplidos.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Storage::fake('private');
    }

    public function test_la_regla_esta_activa_por_defecto_con_18_anios(): void
    {
        $this->assertSame(18, app(ReglaEdadCategoriaService::class)->edadMinimaLiga());
    }

    public function test_el_formulario_no_registra_a_un_menor_de_edad_aunque_traiga_consentimiento_del_tutor(): void
    {
        [$delegado, $equipo, $temporada, $categoria] = $this->contexto('Libre', 'Primera Fuerza');
        $menor = now()->subYears(18)->addDay()->toDateString();

        $this->actingAs($delegado)->postJson(route('jugadores.store'), $this->datos($equipo, $temporada, $categoria, $menor))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_nacimiento' => self::MENSAJE]);

        $this->assertDatabaseCount('jugadores_equipos', 0);
        $this->assertDatabaseMissing('personas', ['curp' => 'EDAD010101HDFXXX01']);
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_un_menor_tampoco_entra_por_la_rama_infantil_ni_por_juvenil(): void
    {
        foreach ([['Infantil', 'Infantil A'], ['Libre', 'Juvenil A']] as [$rama, $nombre]) {
            [$delegado, $equipo, $temporada, $categoria] = $this->contexto($rama, $nombre);

            $this->actingAs($delegado)->postJson(route('jugadores.store'), $this->datos($equipo, $temporada, $categoria, '2012-03-15'))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['fecha_nacimiento' => self::MENSAJE]);
        }

        $this->assertDatabaseCount('jugadores_equipos', 0);
    }

    public function test_quien_cumple_18_anios_hoy_si_se_registra(): void
    {
        [$delegado, $equipo, $temporada, $categoria] = $this->contexto('Libre', 'Primera Fuerza');

        $this->actingAs($delegado)->post(route('jugadores.store'), $this->datos($equipo, $temporada, $categoria, now()->subYears(18)->toDateString()))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Jugador::query()->count());
    }

    public function test_tampoco_se_inscribe_a_una_persona_existente_menor_de_edad(): void
    {
        [$delegado, $equipo] = $this->contexto('Libre', 'Primera Fuerza');
        $persona = Persona::factory()->create(['fecha_nacimiento' => now()->subYears(17)->toDateString()]);

        $this->actingAs($delegado)->postJson(route('equipos.jugadores.store', $equipo), ['persona_id' => $persona->id] + $this->privacidad())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['persona_id' => self::MENSAJE]);

        $this->assertDatabaseMissing('jugadores_equipos', ['persona_id' => $persona->id]);
    }

    public function test_la_regla_compartida_con_renovacion_y_transferencia_rechaza_menores(): void
    {
        $servicio = app(ReglaEdadCategoriaService::class);
        $temporada = Temporada::factory()->activa()->create();
        $categoria = Categoria::factory()->create(['temporada_id' => $temporada->id, 'rama' => 'Libre', 'nombre' => 'Primera Fuerza']);
        $menor = Persona::factory()->make(['fecha_nacimiento' => now()->subYears(16)->toDateString()]);
        $adulto = Persona::factory()->make(['fecha_nacimiento' => now()->subYears(25)->toDateString()]);

        $servicio->validar($adulto, $categoria, $temporada);

        try {
            $servicio->validar($menor, $categoria, $temporada);
            $this->fail('La renovación y la transferencia deben rechazar a un menor de edad.');
        } catch (ValidationException $exception) {
            $this->assertSame([self::MENSAJE], $exception->errors()['equipo_id']);
        }
    }

    public function test_la_renovacion_de_un_menor_se_rechaza(): void
    {
        [$delegado, $equipoActual, , , $club] = $this->contexto('Libre', 'Primera Fuerza');
        $persona = Persona::factory()->create(['fecha_nacimiento' => now()->subYears(17)->toDateString()]);
        $anterior = $equipoActual->jugadores()->create(['persona_id' => $persona->id]);
        $temporadaNueva = Temporada::factory()->activa()->create(['fecha_inicio' => now()->addMonth()->toDateString()]);
        $categoriaNueva = Categoria::factory()->create(['temporada_id' => $temporadaNueva->id, 'rama' => 'Libre', 'nombre' => 'Primera Fuerza']);
        $grupoNuevo = Grupo::factory()->create(['categoria_id' => $categoriaNueva->id, 'temporada_id' => $temporadaNueva->id]);
        $equipoNuevo = Equipo::query()->create([
            'club_id' => $club->id,
            'temporada_id' => $temporadaNueva->id,
            'grupo_id' => $grupoNuevo->id,
            'nombre' => 'Equipo temporada nueva',
        ]);

        $this->actingAs($delegado)->postJson(route('jugadores.renovar', $anterior), ['equipo_id' => $equipoNuevo->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['equipo_id' => self::MENSAJE]);

        $this->assertDatabaseMissing('jugadores_equipos', ['persona_id' => $persona->id, 'equipo_id' => $equipoNuevo->id]);
    }

    public function test_el_formulario_avisa_la_edad_minima_y_limita_la_fecha_de_nacimiento(): void
    {
        [$delegado] = $this->contexto('Libre', 'Primera Fuerza');

        $this->actingAs($delegado)->get(route('jugadores.create'))
            ->assertOk()
            ->assertSee('La Liga solo inscribe jugadores de 18 años cumplidos o más.')
            ->assertSee('max="'.now()->subYears(18)->toDateString().'"', false);
    }

    public function test_si_la_liga_desactiva_la_regla_un_menor_se_registra_con_el_consentimiento_del_tutor(): void
    {
        config(['jugadores.edad_minima' => null]);
        [$delegado, $equipo, $temporada, $categoria] = $this->contexto('Infantil', 'Infantil A');

        $this->actingAs($delegado)->post(route('jugadores.store'), $this->datos($equipo, $temporada, $categoria, '2012-03-15'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(Jugador::query()->sole()->consentimientoPrivacidad?->requiere_tutor);
    }

    /** @return array{User, Equipo, Temporada, Categoria, Club} */
    private function contexto(string $rama, string $nombreCategoria): array
    {
        $club = Club::query()->create(['nombre' => 'Club Edad '.str()->random(5)]);
        $delegado = User::query()->create([
            'name' => 'Delegado edad',
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
            'nombre' => 'Equipo Edad',
        ]);

        return [$delegado, $equipo, $temporada, $categoria, $club];
    }

    /** @return array<string, mixed> */
    private function datos(Equipo $equipo, Temporada $temporada, Categoria $categoria, string $nacimiento): array
    {
        return [
            'nombre' => 'Jugador',
            'apellidos' => 'Edad',
            'fecha_nacimiento' => $nacimiento,
            'curp' => 'EDAD010101HDFXXX01',
            'temporada_id' => $temporada->id,
            'categoria_id' => $categoria->id,
            'equipo_id' => $equipo->id,
            'documentos' => [
                'curp' => UploadedFile::fake()->create('curp.pdf', 80, 'application/pdf'),
                'fotografia' => UploadedFile::fake()->createWithContent('foto.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/kXcAAAAASUVORK5CYII=') ?: 'png'),
                'comprobante' => UploadedFile::fake()->create('domicilio.pdf', 90, 'application/pdf'),
            ],
        ] + $this->privacidad();
    }
}
