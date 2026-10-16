<?php

namespace Tests\Feature;

use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacGovernanceAnd403Test extends TestCase
{
    // Manejo transaccional para evitar alteraciones permanentes en la base de datos
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Ejecutar seeder de roles y permisos para asegurar disponibilidad en el entorno de pruebas
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_rol_administrador_general_posee_todos_los_permisos_de_gobernanza(): void
    {
        $rolAdmin = Role::findByName('Administrador General', 'web');
        $totalPermisos = Permission::where('guard_name', 'web')->count();

        // Comprobacion de cobertura total de permisos del sistema
        $this->assertGreaterThan(0, $totalPermisos);
        $this->assertGreaterThanOrEqual(25, $rolAdmin->permissions()->count());

        // Verificacion de permisos especificos de gobernanza
        $permisosClave = [
            'gobernanza.panel',
            'ligas.ver',
            'ligas.crear',
            'ligas.editar',
            'temporadas.ver',
            'temporadas.crear',
            'temporadas.editar',
            'convocatorias.ver',
            'convocatorias.crear',
            'clubes.ver',
            'clubes.crear',
            'usuarios.ver',
            'usuarios.crear',
            'roles.ver',
            'roles.asignar_permiso',
            'auditoria.ver',
        ];

        foreach ($permisosClave as $permiso) {
            $this->assertTrue(
                $rolAdmin->hasPermissionTo($permiso),
                "El rol Administrador General debe poseer el permiso: {$permiso}"
            );
        }
    }

    public function test_administrador_general_puede_acceder_a_rutas_de_gobernanza(): void
    {
        $admin = User::create([
            'name' => 'Admin Gobernanza Test',
            'email' => 'admin_gob@liga.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Administrador General',
        ]);
        $admin->assignRole('Administrador General');

        // Peticion a dashboard de gobernanza
        $respuestaDashboard = $this->actingAs($admin)->get('/admin/dashboard');
        $respuestaDashboard->assertStatus(200);
        $respuestaDashboard->assertJson([
            'modulo' => 'Gobernanza y Configuracion',
            'estado' => 'autorizado',
        ]);

        // Peticion a seccion core de gobernanza
        $respuestaGobernanza = $this->actingAs($admin)->get('/admin/gobernanza');
        $respuestaGobernanza->assertStatus(200);
        $respuestaGobernanza->assertJson([
            'modulo' => 'Core de Gobernanza',
            'privilegio' => 'Administrador General',
        ]);
    }

    public function test_usuario_sin_rol_administrativo_es_rechazado_con_codigo_403_en_rutas_de_gobernanza(): void
    {
        $arbitro = User::create([
            'name' => 'Arbitro Test',
            'email' => 'arbitro_gob@liga.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Arbitro',
        ]);
        $arbitro->assignRole('Arbitro');

        // Intento de acceso a ruta administrativa por parte de un usuario con rol ajeno
        $respuesta = $this->actingAs($arbitro)->get('/admin/dashboard');

        // Comprobacion de bloqueo inmediato con codigo HTTP 403 Forbidden
        $respuesta->assertStatus(403);
        $respuesta->assertViewIs('errors.403');
        $respuesta->assertSee('403');
        $respuesta->assertSee('Acceso Restringido');
    }

    public function test_solicitud_ajax_o_json_sin_permiso_retorna_403_estructurado(): void
    {
        $directivo = User::create([
            'name' => 'Directivo Club Test',
            'email' => 'directivo_gob@liga.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Directivo Club',
        ]);
        $directivo->assignRole('Directivo de Club');

        // Peticion asincrona esperando respuesta en formato JSON
        $respuesta = $this->actingAs($directivo)->getJson('/admin/dashboard');

        // Verificacion de respuesta limpia sin exposicion de codigo interno
        $respuesta->assertStatus(403);
        $respuesta->assertJson([
            'error' => 'Acceso denegado',
            'status' => 403,
        ]);
    }

    public function test_usuario_no_autenticado_es_redirigido_a_login(): void
    {
        // Peticion de usuario invitado sin sesion
        $respuesta = $this->get('/admin/dashboard');

        // Debe ser interceptado y enviado al formulario de login
        $respuesta->assertRedirect(route('login'));
    }

    public function test_usuario_con_permiso_especifico_puede_acceder_a_su_modulo_correspondiente(): void
    {
        $presidente = User::create([
            'name' => 'Presidente Test',
            'email' => 'presidente_gob@liga.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Presidente',
        ]);
        $presidente->assignRole('Presidente');

        // El rol Presidente tiene asignado el permiso usuarios.ver
        $respuestaPresidente = $this->actingAs($presidente)->get('/admin/gestion/usuarios');
        $respuestaPresidente->assertStatus(200);
        $respuestaPresidente->assertJson([
            'modulo' => 'Gestion de Usuarios',
            'estado' => 'autorizado',
        ]);

        // Un usuario arbitro no posee dicho permiso y debe ser bloqueado con 403
        $arbitro = User::create([
            'name' => 'Arbitro Sin Permiso',
            'email' => 'arbitro_noperm@liga.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Arbitro',
        ]);
        $arbitro->assignRole('Arbitro');

        $respuestaArbitro = $this->actingAs($arbitro)->get('/admin/gestion/usuarios');
        $respuestaArbitro->assertStatus(403);
    }

    public function test_politica_temporada_policy_permite_creacion_al_administrador(): void
    {
        $admin = User::create([
            'name' => 'Admin Policy Test',
            'email' => 'admin_policy@liga.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Administrador General',
        ]);
        $admin->assignRole('Administrador General');

        $arbitro = User::create([
            'name' => 'Arbitro Policy Test',
            'email' => 'arbitro_policy@liga.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Arbitro',
        ]);
        $arbitro->assignRole('Arbitro');

        // Administrador General tiene autorizacion para crear temporadas
        $this->assertTrue(Gate::forUser($admin)->allows('create', Temporada::class));

        // Arbitro no tiene autorizacion para dar de alta temporadas
        $this->assertFalse(Gate::forUser($arbitro)->allows('create', Temporada::class));
    }

    public function test_politica_temporada_policy_bloquea_modificacion_de_temporada_cerrada(): void
    {
        $admin = User::create([
            'name' => 'Admin Cierre Test',
            'email' => 'admin_cierre@liga.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Administrador General',
        ]);
        $admin->assignRole('Administrador General');

        $temporadaCerrada = new Temporada([
            'nombre' => 'Temporada Clausurada 2025',
            'fecha_inicio' => '2025-01-01',
            'fecha_fin' => '2025-06-30',
            'estado' => 'cerrada',
        ]);

        // Verificacion de que la regla de negocio bloquea modificaciones en temporadas cerradas
        $inspeccionUpdate = Gate::forUser($admin)->inspect('update', $temporadaCerrada);
        $this->assertFalse($inspeccionUpdate->allowed());
        $this->assertStringContainsString('cerrada no puede modificarse', $inspeccionUpdate->message());

        $inspeccionDelete = Gate::forUser($admin)->inspect('delete', $temporadaCerrada);
        $this->assertFalse($inspeccionDelete->allowed());
        $this->assertStringContainsString('cerrada no puede eliminarse', $inspeccionDelete->message());
    }

    public function test_politica_user_policy_impide_que_un_usuario_se_elimine_a_si_mismo(): void
    {
        $admin = User::create([
            'name' => 'Admin Auto Delete',
            'email' => 'admin_autodel@liga.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Administrador General',
        ]);
        $admin->assignRole('Administrador General');

        // Intento de autoeliminacion bloqueado por politica de usuario
        $inspeccion = Gate::forUser($admin)->inspect('delete', $admin);
        $this->assertFalse($inspeccion->allowed());
        $this->assertStringContainsString('eliminar la propia cuenta', $inspeccion->message());
    }
}
