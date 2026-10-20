<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Equipo;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IdTamperingAndCrossAccessSecurityTest extends TestCase
{
    use DatabaseTransactions;

    private User $directivoClub1;
    private User $directivoClub2;
    private User $arbitro;
    private User $admin;

    private Club $club1;
    private Club $club2;

    private Equipo $equipoClub1;
    private Equipo $equipoClub2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        // Crear o recuperar los clubes de prueba.
        $this->club1 = Club::firstOrCreate(
            ['nombre' => 'Deportivo Morelia Test'],
            [
                'colores' => 'Rojo y Amarillo',
                'estado' => 'activo',
                'fecha_afiliacion' => '2020-01-01',
            ]
        );

        $this->club2 = Club::firstOrCreate(
            ['nombre' => 'Halcones Morelia Test'],
            [
                'colores' => 'Verde y Blanco',
                'estado' => 'activo',
                'fecha_afiliacion' => '2021-06-01',
            ]
        );

        // Garantizar que exista una categoría válida.
        $categoriaId = DB::table('categorias')->value('id');

        if ($categoriaId === null) {
            $categoriaId = DB::table('categorias')->insertGetId([
                'nombre' => 'Categoría de Prueba',
                'edad_min' => 18,
                'edad_max' => 35,
                'jerarquia_salto' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // La fábrica de temporadas crea una liga si no existe.
        $temporadaId = DB::table('temporadas')->value('id');

        if ($temporadaId === null) {
            $temporadaId = Temporada::factory()->create()->id;
        }

        // Crear un grupo con sus dos llaves foráneas válidas.
        $grupoId = DB::table('grupos')->insertGetId([
            'categoria_id' => $categoriaId,
            'temporada_id' => $temporadaId,
            'nombre' => 'Grupo Unico Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Crear los equipos asociados a sus respectivos clubes.
        $this->equipoClub1 = Equipo::create([
            'club_id' => $this->club1->id,
            'grupo_id' => $grupoId,
            'nombre' => 'Morelia Premier A',
        ]);

        $this->equipoClub2 = Equipo::create([
            'club_id' => $this->club2->id,
            'grupo_id' => $grupoId,
            'nombre' => 'Halcones Juvenil B',
        ]);

        // Crear el directivo del primer club.
        $this->directivoClub1 = User::create([
            'name' => 'Directivo Club Uno',
            'email' => 'directivo1@tampering.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Directivo Club',
            'club_id' => $this->club1->id,
        ]);

        $this->directivoClub1->assignRole('Directivo de Club');

        // Crear el directivo del segundo club.
        $this->directivoClub2 = User::create([
            'name' => 'Directivo Club Dos',
            'email' => 'directivo2@tampering.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Directivo Club',
            'club_id' => $this->club2->id,
        ]);

        $this->directivoClub2->assignRole('Directivo de Club');

        // Crear el árbitro.
        $this->arbitro = User::create([
            'name' => 'Arbitro Neutral Test',
            'email' => 'arbitro@tampering.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Arbitro',
            'club_id' => null,
        ]);

        $this->arbitro->assignRole('Arbitro');

        // Crear el administrador general.
        $this->admin = User::create([
            'name' => 'Super Admin Test',
            'email' => 'admin@tampering.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Administrador General',
            'club_id' => null,
        ]);

        $this->admin->assignRole('Administrador General');
    }

    public function test_directivo_puede_consultar_su_propio_club(): void
    {
        $respuesta = $this->actingAs($this->directivoClub1)
            ->get("/clubes/{$this->club1->id}");

        $respuesta->assertStatus(200);
        $respuesta->assertJson([
            'modulo' => 'Club Deportivo',
            'club_id' => $this->club1->id,
            'acceso' => 'autorizado',
        ]);
    }

    public function test_prevencion_id_tampering_directivo_no_puede_consultar_club_ajeno_responde_403(): void
    {
        $respuesta = $this->actingAs($this->directivoClub1)
            ->get("/clubes/{$this->club2->id}");

        $respuesta->assertStatus(403);
    }

    public function test_prevencion_id_tampering_directivo_no_puede_modificar_club_ajeno_responde_403(): void
    {
        $respuesta = $this->actingAs($this->directivoClub1)
            ->putJson("/clubes/{$this->club2->id}", [
                'nombre' => 'Nombre Alterado No Autorizado',
            ]);

        $respuesta->assertStatus(403);
        $respuesta->assertJson([
            'error' => 'Acceso denegado',
            'status' => 403,
        ]);
    }

    public function test_prevencion_id_tampering_directivo_no_puede_consultar_equipos_de_club_ajeno(): void
    {
        $respuesta = $this->actingAs($this->directivoClub1)
            ->get("/clubes/{$this->club2->id}/equipos");

        $respuesta->assertStatus(403);
    }

    public function test_prevencion_id_tampering_directivo_no_puede_consultar_equipo_de_otro_club(): void
    {
        $respuesta = $this->actingAs($this->directivoClub1)
            ->get("/equipos/{$this->equipoClub2->id}");

        $respuesta->assertStatus(403);
    }

    public function test_prevencion_id_tampering_directivo_no_puede_modificar_equipo_de_otro_club(): void
    {
        $respuesta = $this->actingAs($this->directivoClub1)
            ->putJson("/equipos/{$this->equipoClub2->id}", [
                'nombre' => 'Nombre Equipo Alterado',
            ]);

        $respuesta->assertStatus(403);
        $respuesta->assertJson([
            'error' => 'Acceso denegado',
            'status' => 403,
        ]);
    }

    public function test_directivo_puede_gestionar_equipos_de_su_propio_club(): void
    {
        $respuestaShow = $this->actingAs($this->directivoClub1)
            ->get("/equipos/{$this->equipoClub1->id}");

        $respuestaShow->assertStatus(200);

        $respuestaUpdate = $this->actingAs($this->directivoClub1)
            ->putJson("/equipos/{$this->equipoClub1->id}", [
                'nombre' => 'Morelia Premier Modificado',
            ]);

        $respuestaUpdate->assertStatus(200);
        $respuestaUpdate->assertJson([
            'mensaje' => 'Equipo actualizado exitosamente.',
            'equipo_id' => $this->equipoClub1->id,
        ]);
    }

    public function test_arbitro_no_puede_acceder_a_rutas_de_clubes_responde_403(): void
    {
        $respuesta = $this->actingAs($this->arbitro)
            ->get("/clubes/{$this->club1->id}");

        $respuesta->assertStatus(403);
    }

    public function test_arbitro_no_puede_acceder_a_rutas_de_gobernanza_administrativa_responde_403(): void
    {
        $respuesta = $this->actingAs($this->arbitro)
            ->get('/admin/dashboard');

        $respuesta->assertStatus(403);
    }

    public function test_directivo_no_puede_acceder_a_rutas_de_gobernanza_administrativa_responde_403(): void
    {
        $respuesta = $this->actingAs($this->directivoClub1)
            ->get('/admin/gobernanza');

        $respuesta->assertStatus(403);
    }

    public function test_administrador_general_puede_acceder_a_cualquier_club_sin_restriccion(): void
    {
        $respuestaClub1 = $this->actingAs($this->admin)
            ->get("/clubes/{$this->club1->id}");

        $respuestaClub1->assertStatus(200);

        $respuestaClub2 = $this->actingAs($this->admin)
            ->get("/clubes/{$this->club2->id}");

        $respuestaClub2->assertStatus(200);
    }

    public function test_usuario_no_autenticado_es_redirigido_al_intentar_acceder_a_clubes(): void
    {
        $respuesta = $this->get("/clubes/{$this->club1->id}");

        $respuesta->assertRedirect(route('login'));
    }
}
