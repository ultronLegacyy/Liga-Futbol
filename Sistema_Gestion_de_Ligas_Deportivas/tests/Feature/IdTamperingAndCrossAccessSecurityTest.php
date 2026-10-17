<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Equipo;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IdTamperingAndCrossAccessSecurityTest extends TestCase
{
    // Manejo transaccional para revertir cambios tras cada prueba automatizada
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

        // Sembrado de roles y permisos del sistema
        $this->seed(RoleAndPermissionSeeder::class);

        // Creacion de clubes de prueba
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

        // Verificacion o creacion de grupo base para equipos
        $categoriaId = DB::table('categorias')->value('id');
        $temporadaId = DB::table('temporadas')->value('id');
        $grupoId = DB::table('grupos')->insertGetId([
            'categoria_id' => $categoriaId,
            'temporada_id' => $temporadaId,
            'nombre' => 'Grupo Unico Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Creacion de equipos para cada club
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

        // Creacion de usuario directivo para Club 1
        $this->directivoClub1 = User::create([
            'name' => 'Directivo Club Uno',
            'email' => 'directivo1@tampering.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Directivo Club',
            'club_id' => $this->club1->id,
        ]);
        $this->directivoClub1->assignRole('Directivo de Club');

        // Creacion de usuario directivo para Club 2
        $this->directivoClub2 = User::create([
            'name' => 'Directivo Club Dos',
            'email' => 'directivo2@tampering.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Directivo Club',
            'club_id' => $this->club2->id,
        ]);
        $this->directivoClub2->assignRole('Directivo de Club');

        // Creacion de usuario arbitro
        $this->arbitro = User::create([
            'name' => 'Arbitro Neutral Test',
            'email' => 'arbitro@tampering.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Arbitro',
            'club_id' => null,
        ]);
        $this->arbitro->assignRole('Arbitro');

        // Creacion de usuario Administrador General
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
        // Un directivo consulta la URL legitima de su propio club
        $respuesta = $this->actingAs($this->directivoClub1)->get("/clubes/{$this->club1->id}");

        $respuesta->assertStatus(200);
        $respuesta->assertJson([
            'modulo' => 'Club Deportivo',
            'club_id' => $this->club1->id,
            'acceso' => 'autorizado',
        ]);
    }

    public function test_prevencion_id_tampering_directivo_no_puede_consultar_club_ajeno_responde_403(): void
    {
        // Intento de ID Tampering: Directivo 1 altera el identificador en la URL para consultar el Club 2
        $respuesta = $this->actingAs($this->directivoClub1)->get("/clubes/{$this->club2->id}");

        // El sistema intercepta la alteracion de identificador y responde con HTTP 403 Forbidden
        $respuesta->assertStatus(403);
    }

    public function test_prevencion_id_tampering_directivo_no_puede_modificar_club_ajeno_responde_403(): void
    {
        // Intento de alteracion maliciosa: Directivo 1 intenta modificar datos del Club 2
        $respuesta = $this->actingAs($this->directivoClub1)->putJson("/clubes/{$this->club2->id}", [
            'nombre' => 'Nombre Alterado No Autorizado',
        ]);

        // Bloqueo estricto con codigo HTTP 403 Forbidden
        $respuesta->assertStatus(403);
        $respuesta->assertJson([
            'error' => 'Acceso denegado',
            'status' => 403,
        ]);
    }

    public function test_prevencion_id_tampering_directivo_no_puede_consultar_equipos_de_club_ajeno(): void
    {
        // Intento de navegacion a subrecursos: Directivo 1 solicita lista de equipos del Club 2
        $respuesta = $this->actingAs($this->directivoClub1)->get("/clubes/{$this->club2->id}/equipos");

        // Intercepcion inmediata y bloqueo con 403
        $respuesta->assertStatus(403);
    }

    public function test_prevencion_id_tampering_directivo_no_puede_consultar_equipo_de_otro_club(): void
    {
        // Intento de acceso directo: Directivo 1 consulta la URL del equipo perteneciente al Club 2
        $respuesta = $this->actingAs($this->directivoClub1)->get("/equipos/{$this->equipoClub2->id}");

        // Bloqueo de acceso cruzado con HTTP 403 Forbidden en el cien por ciento de los casos
        $respuesta->assertStatus(403);
    }

    public function test_prevencion_id_tampering_directivo_no_puede_modificar_equipo_de_otro_club(): void
    {
        // Intento de alteracion de datos: Directivo 1 envia peticion PUT sobre un equipo del Club 2
        $respuesta = $this->actingAs($this->directivoClub1)->putJson("/equipos/{$this->equipoClub2->id}", [
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
        // Directivo 1 consulta el detalle de su propio equipo
        $respuestaShow = $this->actingAs($this->directivoClub1)->get("/equipos/{$this->equipoClub1->id}");
        $respuestaShow->assertStatus(200);

        // Directivo 1 actualiza datos de su propio equipo de manera legitima
        $respuestaUpdate = $this->actingAs($this->directivoClub1)->putJson("/equipos/{$this->equipoClub1->id}", [
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
        // Intercepcion de modulo ajeno: un arbitro intenta ingresar al perfil del club
        $respuesta = $this->actingAs($this->arbitro)->get("/clubes/{$this->club1->id}");

        $respuesta->assertStatus(403);
    }

    public function test_arbitro_no_puede_acceder_a_rutas_de_gobernanza_administrativa_responde_403(): void
    {
        // Intercepcion de modulo ajeno: un arbitro intenta acceder al dashboard de administracion
        $respuesta = $this->actingAs($this->arbitro)->get('/admin/dashboard');

        $respuesta->assertStatus(403);
    }

    public function test_directivo_no_puede_acceder_a_rutas_de_gobernanza_administrativa_responde_403(): void
    {
        // Intercepcion de modulo ajeno: un directivo intenta ingresar a la configuracion general
        $respuesta = $this->actingAs($this->directivoClub1)->get('/admin/gobernanza');

        $respuesta->assertStatus(403);
    }

    public function test_administrador_general_puede_acceder_a_cualquier_club_sin_restriccion(): void
    {
        // El Administrador General cuenta con visibilidad y autorizacion total sobre todos los clubes
        $respuestaClub1 = $this->actingAs($this->admin)->get("/clubes/{$this->club1->id}");
        $respuestaClub1->assertStatus(200);

        $respuestaClub2 = $this->actingAs($this->admin)->get("/clubes/{$this->club2->id}");
        $respuestaClub2->assertStatus(200);
    }

    public function test_usuario_no_autenticado_es_redirigido_al_intentar_acceder_a_clubes(): void
    {
        // Un usuario no autenticado que intenta navegar a un club es enviado al formulario de login
        $respuesta = $this->get("/clubes/{$this->club1->id}");

        $respuesta->assertRedirect(route('login'));
    }
}
