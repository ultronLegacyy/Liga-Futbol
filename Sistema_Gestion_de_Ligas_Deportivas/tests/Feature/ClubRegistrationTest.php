<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Club;
use App\Models\Grupo;
use App\Models\HistorialVentaClub;
use App\Models\Persona;
use App\Models\Temporada;
use App\Models\User;
use App\Services\VentaClubService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use RuntimeException;
use Tests\TestCase;

class ClubRegistrationTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $directivo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $club = Club::firstOrCreate(
            ['nombre' => 'Club Base Registro Test'],
            [
                'colores' => 'Verde y blanco',
                'estado' => 'activo',
                'fecha_afiliacion' => '2024-01-01',
            ]
        );

        $this->admin = User::create([
            'name' => 'Administrador Registro Test',
            'email' => 'admin-registro-test@example.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Administrador General',
            'club_id' => null,
        ]);

        $this->admin->assignRole('Administrador General');

        $this->directivo = User::create([
            'name' => 'Directivo Registro Test',
            'email' => 'directivo-registro-test@example.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Directivo Club',
            'club_id' => $club->id,
        ]);

        $this->directivo->assignRole('Presidente del Club');
    }

    public function test_administrador_general_puede_registrar_club_directiva_y_auditarlo(): void
    {
        $respuesta = $this->actingAs($this->admin)->postJson('/clubes', [
            'nombre' => 'Club Nuevo Registro Test',
            'colores' => 'Azul y oro',
            'motivo' => 'Alta inicial para pruebas',
            'directivos' => [
                [
                    'nombre' => 'Juan Perez',
                    'cargo' => 'Presidente',
                ],
                [
                    'nombre' => 'Maria Lopez',
                    'cargo' => 'Secretaria',
                ],
            ],
        ]);

        $respuesta->assertCreated()
            ->assertJsonPath(
                'mensaje',
                'Club y directiva registrados exitosamente.'
            )
            ->assertJsonPath('club.nombre', 'Club Nuevo Registro Test')
            ->assertJsonPath('club.estado', 'activo')
            ->assertJsonCount(2, 'club.directivos');

        $clubId = $respuesta->json('club.id');

        $this->assertDatabaseHas('clubes', [
            'id' => $clubId,
            'nombre' => 'Club Nuevo Registro Test',
            'colores' => 'Azul y oro',
            'estado' => 'activo',
        ]);

        $this->assertDatabaseHas('directivos_club', [
            'club_id' => $clubId,
            'nombre' => 'Juan Perez',
            'cargo' => 'Presidente',
        ]);

        $this->assertDatabaseHas('directivos_club', [
            'club_id' => $clubId,
            'nombre' => 'Maria Lopez',
            'cargo' => 'Secretaria',
        ]);

        $this->assertDatabaseHas('auditoria_logs', [
            'accion' => 'crear',
            'tabla_afectada' => 'clubes',
            'registro_id' => $clubId,
            'motivo' => 'Alta inicial para pruebas',
        ]);

        $this->assertSame(1, DB::table('auditoria_logs')
            ->where('tabla_afectada', 'clubes')
            ->where('registro_id', $clubId)
            ->count());
    }

    public function test_directivo_no_puede_registrar_clubes(): void
    {
        $respuesta = $this->actingAs($this->directivo)->postJson('/clubes', [
            'nombre' => 'Club No Autorizado Test',
            'colores' => 'Rojo y negro',
            'directivos' => [
                [
                    'nombre' => 'Persona de prueba',
                    'cargo' => 'Presidente',
                ],
            ],
        ]);

        $respuesta->assertForbidden();

        $this->assertDatabaseMissing('clubes', [
            'nombre' => 'Club No Autorizado Test',
        ]);
    }

    public function test_registro_rechaza_nombre_duplicado(): void
    {
        Club::create([
            'nombre' => 'Club Duplicado Registro Test',
            'colores' => 'Negro',
            'fecha_afiliacion' => '2025-01-01',
            'estado' => 'activo',
        ]);

        $respuesta = $this->actingAs($this->admin)->postJson('/clubes', [
            'nombre' => 'Club Duplicado Registro Test',
            'directivos' => [
                [
                    'nombre' => 'Persona de prueba',
                    'cargo' => 'Presidente',
                ],
            ],
        ]);

        $respuesta->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre']);
    }

    public function test_administrador_puede_asignar_propietario_al_registrar_club(): void
    {
        // Crear usuario propietario
        $propietario = User::create([
            'name' => 'Propietario Club Test',
            'email' => 'propietario-club-test@example.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Consultor',
            'club_id' => null,
        ]);

        // Registrar club con propietario
        $respuesta = $this->actingAs($this->admin)->postJson('/clubes', [
            'nombre' => 'Club Propietario Test',
            'colores' => 'Verde y blanco',
            'propietario_usuario_id' => $propietario->id,
            'motivo' => 'Prueba de asignación de propietario',
            'directivos' => [
                [
                    'nombre' => 'Directivo Propietario Test',
                    'cargo' => 'Presidente',
                ],
            ],
        ]);

        $respuesta->assertCreated()
            ->assertJsonPath('club.nombre', 'Club Propietario Test')
            ->assertJsonPath(
                'club.propietario_usuario_id',
                $propietario->id
            )
            ->assertJsonPath(
                'club.propietario.id',
                $propietario->id
            );

        $clubId = $respuesta->json('club.id');

        // Comprobar que el propietario se guardó
        $this->assertDatabaseHas('clubes', [
            'id' => $clubId,
            'propietario_usuario_id' => $propietario->id,
        ]);

        // Comprobar la relación entre club y propietario
        $club = Club::with('propietario')->findOrFail($clubId);

        $this->assertNotNull($club->propietario);

        $this->assertEquals(
            $propietario->id,
            $club->propietario->id
        );
    }

    public function test_venta_sin_inscripciones_conserva_el_flujo_comercial(): void
    {
        $club = Club::findOrFail($this->directivo->club_id);
        $club->update(['propietario_usuario_id' => $this->directivo->id]);
        $nuevoPropietario = User::query()->create([
            'name' => 'Nuevo propietario sin plantilla',
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Consultor',
        ]);

        $venta = app(VentaClubService::class)->vender($club, (int) $nuevoPropietario->id, '2026-11-01');

        $this->assertDatabaseHas('clubes', [
            'id' => $club->id,
            'propietario_usuario_id' => $nuevoPropietario->id,
        ]);
        $this->assertDatabaseHas('historial_ventas_club', [
            'id' => $venta->id,
            'club_id' => $club->id,
        ]);
    }
    public function test_venta_desvincula_delegados_sin_revocar_su_rol_o_permisos(): void
    {
        $club = Club::findOrFail($this->directivo->club_id);
        $club->update(['propietario_usuario_id' => $this->directivo->id]);
        $delegado = User::query()->create([
            'name' => 'Delegado previo a venta',
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Directivo Club',
            'club_id' => $club->id,
        ]);
        $delegado->assignRole(Role::query()->firstOrCreate(['name' => 'Delegado del Club', 'guard_name' => 'web']));
        $clubOtro = Club::query()->create(['nombre' => 'Club no vendido '.str()->uuid(), 'estado' => 'activo']);
        $delegadoOtroClub = User::query()->create([
            'name' => 'Delegado de otro club',
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Directivo Club',
            'club_id' => $clubOtro->id,
        ]);
        $delegadoOtroClub->assignRole(Role::query()->firstOrCreate(['name' => 'Delegado del Club', 'guard_name' => 'web']));
        $directivaVendidaId = DB::table('directivos_club')->insertGetId([
            'club_id' => $club->id,
            'nombre' => 'Directiva existente del club vendido',
            'cargo' => 'Secretario',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $directivaOtroClubId = DB::table('directivos_club')->insertGetId([
            'club_id' => $clubOtro->id,
            'nombre' => 'Directiva de otro club',
            'cargo' => 'Secretario',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $temporada = Temporada::factory()->activa()->create();
        $categoria = Categoria::factory()->create(['temporada_id' => $temporada->id]);
        $grupo = Grupo::factory()->create(['temporada_id' => $temporada->id, 'categoria_id' => $categoria->id]);
        $equipo = $club->equipos()->create([
            'temporada_id' => $temporada->id,
            'grupo_id' => $grupo->id,
            'nombre' => 'Equipo tras venta',
        ]);
        $nuevoPropietario = User::query()->create([
            'name' => 'Comprador del club',
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Consultor',
        ]);

        app(VentaClubService::class)->vender($club, (int) $nuevoPropietario->id, '2026-11-01');

        $this->assertNull($delegado->fresh()->club_id);
        $this->assertTrue($delegado->fresh()->hasRole('Delegado del Club'));
        $this->assertTrue($delegado->fresh()->can('jugadores.crear'));
        $delegado->refresh();
        $this->assertDatabaseHas('usuarios', ['id' => $delegado->id, 'club_id' => null]);
        $this->assertDatabaseHas('directivos_club', ['id' => $directivaVendidaId, 'club_id' => $club->id]);
        $this->assertDatabaseHas('directivos_club', ['id' => $directivaOtroClubId, 'club_id' => $clubOtro->id]);
        $this->assertDatabaseHas('usuarios', ['id' => $delegadoOtroClub->id, 'club_id' => $clubOtro->id]);

        $persona = Persona::factory()->create();
        $this->actingAs($delegado)
            ->getJson(route('clubes.show', $club))
            ->assertForbidden();
        $this->actingAs($delegado)
            ->get(route('jugadores.create'))
            ->assertForbidden();
        $this->actingAs($delegado)
            ->postJson(route('equipos.jugadores.store', $equipo), ['persona_id' => $persona->id])
            ->assertForbidden();
        $this->assertDatabaseMissing('jugadores_equipos', ['persona_id' => $persona->id, 'equipo_id' => $equipo->id]);

        $jugadorGestion = $equipo->jugadores()->create([
            'persona_id' => Persona::factory()->create()->id,
        ]);
        $this->actingAs($delegado)
            ->get(route('jugadores.gestion', $jugadorGestion))
            ->assertForbidden();

        $nuevoDelegado = User::query()->create([
            'name' => 'Delegado asignado por el nuevo propietario',
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Directivo Club',
        ]);
        $nuevoDelegado->assignRole(Role::query()->firstOrCreate(['name' => 'Delegado del Club', 'guard_name' => 'web']));

        $this->actingAs($nuevoPropietario)
            ->postJson(route('clubes.delegados.store', $club), ['usuario_id' => $nuevoDelegado->id])
            ->assertCreated()
            ->assertJsonPath('delegado.club_id', $club->id);
        $this->assertDatabaseHas('usuarios', ['id' => $nuevoDelegado->id, 'club_id' => $club->id]);
        $this->actingAs($delegado)
            ->postJson(route('clubes.delegados.store', $club), ['usuario_id' => $delegado->id])
            ->assertForbidden();

        $this->actingAs($nuevoPropietario)
            ->postJson(route('clubes.delegados.store', $club), ['usuario_id' => $delegadoOtroClub->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('usuario_id');
        $this->assertDatabaseHas('usuarios', ['id' => $delegadoOtroClub->id, 'club_id' => $clubOtro->id]);
    }
    public function test_venta_fallida_revierte_la_desadscripcion_de_delegados(): void
    {
        $club = Club::query()->findOrFail($this->directivo->club_id);
        $club->update(['propietario_usuario_id' => $this->directivo->id]);
        $delegado = User::query()->create([
            'name' => 'Delegado venta rollback',
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Directivo Club',
            'club_id' => $club->id,
        ]);
        $delegado->assignRole(Role::query()->firstOrCreate(['name' => 'Delegado del Club', 'guard_name' => 'web']));
        $nuevoPropietario = User::query()->create([
            'name' => 'Comprador venta rollback',
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Consultor',
        ]);

        Event::listen('eloquent.creating: '.HistorialVentaClub::class, static function (): never {
            throw new RuntimeException('Fallo inducido al guardar historial de venta.');
        });

        try {
            app(VentaClubService::class)->vender($club, (int) $nuevoPropietario->id, '2026-11-01');
            $this->fail('La venta debe fallar durante el registro de su historial.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo inducido al guardar historial de venta.', $exception->getMessage());
        }

        $this->assertDatabaseHas('clubes', [
            'id' => $club->id,
            'propietario_usuario_id' => $this->directivo->id,
        ]);
        $this->assertDatabaseHas('usuarios', ['id' => $delegado->id, 'club_id' => $club->id]);
        $this->assertDatabaseCount('historial_ventas_club', 0);
    }

    public function test_venta_con_inscripciones_y_documentos_se_rechaza_y_conserva_la_plantilla(): void
    {
        $club = Club::findOrFail($this->directivo->club_id);
        $club->update(['propietario_usuario_id' => $this->directivo->id]);

        $nuevoPropietario = User::create([
            'name' => 'Nuevo Propietario Registro Test',
            'email' => 'nuevo-propietario-registro-test@example.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Consultor',
        ]);

        $ligaId = DB::table('ligas')->insertGetId([
            'nombre' => 'Liga Venta Test',
            'estado' => 'activa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $temporadaId = DB::table('temporadas')->insertGetId([
            'liga_id' => $ligaId,
            'nombre' => 'Temporada Venta Test',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'estado' => 'activa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $categoriaId = DB::table('categorias')->insertGetId([
            'nombre' => 'Categoría Venta Test',
            'edad_min' => 18,
            'edad_max' => 40,
            'jerarquia_salto' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $grupoId = DB::table('grupos')->insertGetId([
            'categoria_id' => $categoriaId,
            'temporada_id' => $temporadaId,
            'nombre' => 'Grupo Venta Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $equipoId = DB::table('equipos')->insertGetId([
            'club_id' => $club->id,
            'grupo_id' => $grupoId,
            'nombre' => 'Equipo Venta Test',
            'partidos_default_acumulados' => 9,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $personaId = DB::table('personas')->insertGetId([
            'nombre' => 'Jugador',
            'apellidos' => 'Venta Test',
            'fecha_nacimiento' => '2000-01-01',
            'curp' => 'VENTA'.str_pad((string) $club->id, 13, '0', STR_PAD_LEFT),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('documentos_persona')->insert([
            'persona_id' => $personaId,
            'tipo' => 'INE',
            'disco' => 'private',
            'ruta_storage' => 'personas/'.$personaId.'/ine.pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => 128,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $jugadorId = DB::table('jugadores_equipos')->insertGetId([
            'persona_id' => $personaId,
            'equipo_id' => $equipoId,
            'dorsal' => 10,
            'estado_elegibilidad' => 'Activo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $respuesta = $this->actingAs($this->directivo)->postJson("/clubes/{$club->id}/venta", [
            'propietario_usuario_id' => $nuevoPropietario->id,
            'carta_venta_pdf' => 'ventas/carta-venta.pdf',
            'fecha_venta' => '2026-11-01',
        ]);

        $respuesta->assertUnprocessable()->assertJsonValidationErrors(['club']);
        $this->assertDatabaseHas('clubes', [
            'id' => $club->id,
            'propietario_usuario_id' => $this->directivo->id,
        ]);
        $this->assertDatabaseCount('historial_ventas_club', 0);
        $this->assertDatabaseHas('equipos', ['id' => $equipoId, 'partidos_default_acumulados' => 9]);
        $this->assertDatabaseHas('jugadores_equipos', ['persona_id' => $personaId, 'equipo_id' => $equipoId]);
        $this->assertDatabaseHas('documentos_persona', [
            'persona_id' => $personaId,
            'tipo' => 'INE',
            'ruta_storage' => 'personas/'.$personaId.'/ine.pdf',
        ]);
    }

    public function test_directivo_recibe_403_al_consultar_o_modificar_club_ajeno(): void
    {
        $clubAjeno = Club::create([
            'nombre' => 'Club Ajeno Registro Test',
            'estado' => 'activo',
        ]);

        $this->actingAs($this->directivo)
            ->getJson("/clubes/{$clubAjeno->id}")
            ->assertForbidden();

        $this->actingAs($this->directivo)
            ->putJson("/clubes/{$clubAjeno->id}", ['nombre' => 'Nombre cambiado sin permiso'])
            ->assertForbidden();

        $this->assertDatabaseHas('clubes', [
            'id' => $clubAjeno->id,
            'nombre' => 'Club Ajeno Registro Test',
        ]);
    }
}
