<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckSessionTimeout;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GovernanceRolesAccessTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    /**
     * CA1: Los roles de gobernanza ven y poseen unicamente los modulos y acciones de su responsabilidad (ERS 2.3).
     */
    public function test_roles_de_gobernanza_poseen_unicamente_los_permisos_de_su_ambito_segun_ers_seccion_2_3(): void
    {
        // 1. Verificacion de permisos de Presidente
        $rolPresidente = Role::findByName('Presidente', 'web');
        $this->assertTrue($rolPresidente->hasPermissionTo('gobernanza.panel'));
        $this->assertTrue($rolPresidente->hasPermissionTo('temporadas.ver'));
        $this->assertTrue($rolPresidente->hasPermissionTo('temporadas.crear'));
        $this->assertTrue($rolPresidente->hasPermissionTo('temporadas.editar'));
        $this->assertTrue($rolPresidente->hasPermissionTo('temporadas.cerrar'));
        $this->assertTrue($rolPresidente->hasPermissionTo('convocatorias.ver'));
        $this->assertTrue($rolPresidente->hasPermissionTo('convocatorias.crear'));
        $this->assertTrue($rolPresidente->hasPermissionTo('reglas.configurar'));
        $this->assertTrue($rolPresidente->hasPermissionTo('auditoria.ver'));
        $this->assertTrue($rolPresidente->hasPermissionTo('finanzas.reportes'));

        // No debe tener administracion de cuentas ni dictamen disciplinario
        $this->assertFalse($rolPresidente->hasPermissionTo('usuarios.crear'));
        $this->assertFalse($rolPresidente->hasPermissionTo('usuarios.eliminar'));
        $this->assertFalse($rolPresidente->hasPermissionTo('roles.crear'));
        $this->assertFalse($rolPresidente->hasPermissionTo('disciplina.expedientes'));
        $this->assertFalse($rolPresidente->hasPermissionTo('disciplina.sancionar'));
        $this->assertFalse($rolPresidente->hasPermissionTo('finanzas.cargos'));

        // 2. Verificacion de permisos de Secretario
        $rolSecretario = Role::findByName('Secretario', 'web');
        $this->assertTrue($rolSecretario->hasPermissionTo('gobernanza.panel'));
        $this->assertTrue($rolSecretario->hasPermissionTo('convocatorias.ver'));
        $this->assertTrue($rolSecretario->hasPermissionTo('convocatorias.crear'));
        $this->assertTrue($rolSecretario->hasPermissionTo('convocatorias.editar'));
        $this->assertTrue($rolSecretario->hasPermissionTo('clubes.crear'));
        $this->assertTrue($rolSecretario->hasPermissionTo('equipos.crear'));
        $this->assertTrue($rolSecretario->hasPermissionTo('jugadores.crear'));
        $this->assertTrue($rolSecretario->hasPermissionTo('jugadores.validar_edad'));
        $this->assertTrue($rolSecretario->hasPermissionTo('arbitros.crear'));

        // No debe crear temporadas, ni ver auditoria, ni gestionar finanzas ni expedientes
        $this->assertFalse($rolSecretario->hasPermissionTo('temporadas.crear'));
        $this->assertFalse($rolSecretario->hasPermissionTo('temporadas.cerrar'));
        $this->assertFalse($rolSecretario->hasPermissionTo('auditoria.ver'));
        $this->assertFalse($rolSecretario->hasPermissionTo('finanzas.ver'));
        $this->assertFalse($rolSecretario->hasPermissionTo('disciplina.expedientes'));

        // 3. Verificacion de permisos de Tesorero (Aislamiento total en finanzas)
        $rolTesorero = Role::findByName('Tesorero', 'web');
        $this->assertTrue($rolTesorero->hasPermissionTo('gobernanza.panel'));
        $this->assertTrue($rolTesorero->hasPermissionTo('finanzas.ver'));
        $this->assertTrue($rolTesorero->hasPermissionTo('finanzas.cargos'));
        $this->assertTrue($rolTesorero->hasPermissionTo('finanzas.pagos_validar'));
        $this->assertTrue($rolTesorero->hasPermissionTo('finanzas.reportes'));

        // No debe tener acceso disciplinario, ni deportivo de creacion ni consulta de canchas/temporadas, ni auditoria, ni usuarios
        $this->assertFalse($rolTesorero->hasPermissionTo('disciplina.ver'));
        $this->assertFalse($rolTesorero->hasPermissionTo('disciplina.expedientes'));
        $this->assertFalse($rolTesorero->hasPermissionTo('disciplina.sancionar'));
        $this->assertFalse($rolTesorero->hasPermissionTo('temporadas.ver'));
        $this->assertFalse($rolTesorero->hasPermissionTo('temporadas.crear'));
        $this->assertFalse($rolTesorero->hasPermissionTo('convocatorias.ver'));
        $this->assertFalse($rolTesorero->hasPermissionTo('convocatorias.crear'));
        $this->assertFalse($rolTesorero->hasPermissionTo('campos.ver'));
        $this->assertFalse($rolTesorero->hasPermissionTo('usuarios.ver'));
        $this->assertFalse($rolTesorero->hasPermissionTo('auditoria.ver'));

        // 4. Verificacion de permisos de Comision de la Rama Infantil
        $rolInfantil = Role::findByName('Comision de la Rama Infantil', 'web');
        $this->assertTrue($rolInfantil->hasPermissionTo('gobernanza.panel'));
        $this->assertTrue($rolInfantil->hasPermissionTo('categorias.ver'));
        $this->assertTrue($rolInfantil->hasPermissionTo('categorias.crear'));
        $this->assertTrue($rolInfantil->hasPermissionTo('jugadores.crear'));
        $this->assertTrue($rolInfantil->hasPermissionTo('jugadores.validar_edad'));

        // No debe tener finanzas, creacion de temporadas, ni cuentas de usuarios
        $this->assertFalse($rolInfantil->hasPermissionTo('finanzas.ver'));
        $this->assertFalse($rolInfantil->hasPermissionTo('finanzas.cargos'));
        $this->assertFalse($rolInfantil->hasPermissionTo('temporadas.crear'));
        $this->assertFalse($rolInfantil->hasPermissionTo('usuarios.ver'));
        $this->assertFalse($rolInfantil->hasPermissionTo('auditoria.ver'));
        $this->assertFalse($rolInfantil->hasPermissionTo('disciplina.expedientes'));
    }

    /**
     * CA2: Tesorero editando un expediente disciplinario es rechazado con HTTP 403 Forbidden.
     */
    public function test_tesorero_es_rechazado_con_403_al_intentar_editar_expediente_disciplinario(): void
    {
        $tesorero = User::create([
            'name' => 'Tesorero Prueba CA2',
            'email' => 'tesorero_ca2@ligafutbol.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Tesorero',
        ]);
        $tesorero->assignRole('Tesorero');

        $respuesta = $this->actingAs($tesorero)
            ->putJson('/disciplina/expedientes/1');

        $respuesta->assertStatus(403);
    }

    /**
     * CA2: Un rol autorizado para disciplina si puede actualizar expedientes.
     */
    public function test_rol_disciplinario_puede_actualizar_expediente_disciplinario(): void
    {
        $tribunal = User::create([
            'name' => 'Tribunal Penas Test',
            'email' => 'tribunal_ca2@ligafutbol.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Tribunal de Penas',
        ]);
        $tribunal->assignRole('Tribunal de Penas');

        $respuesta = $this->actingAs($tribunal)
            ->putJson('/disciplina/expedientes/1');

        $respuesta->assertStatus(200);
        $respuesta->assertJson([
            'modulo' => 'Regimen Disciplinario',
            'mensaje' => 'Expediente disciplinario actualizado con exito.',
            'expediente_id' => 1,
        ]);
    }

    /**
     * CA2: Acciones operativas fuera del ambito de cada rol de gobernanza son rechazadas por politica o ruta.
     */
    public function test_roles_de_gobernanza_no_pueden_crear_temporadas_salvo_presidente_y_administrador(): void
    {
        $secretario = User::create([
            'name' => 'Secretario Test CA2',
            'email' => 'secretario_ca2@ligafutbol.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Secretario',
        ]);
        $secretario->assignRole('Secretario');

        $tesorero = User::create([
            'name' => 'Tesorero Test CA2',
            'email' => 'tesorero_no_temp@ligafutbol.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Tesorero',
        ]);
        $tesorero->assignRole('Tesorero');

        $infantil = User::create([
            'name' => 'Infantil Test CA2',
            'email' => 'infantil_no_temp@ligafutbol.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
        ]);
        $infantil->assignRole('Comision de la Rama Infantil');

        $presidente = User::create([
            'name' => 'Presidente Test CA2',
            'email' => 'presidente_temp@ligafutbol.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Presidente',
        ]);
        $presidente->assignRole('Presidente');

        // Secretario, Tesorero e Infantil no tienen permiso de crear temporadas
        $this->assertFalse(Gate::forUser($secretario)->allows('create', Temporada::class));
        $this->assertFalse(Gate::forUser($tesorero)->allows('create', Temporada::class));
        $this->assertFalse(Gate::forUser($infantil)->allows('create', Temporada::class));

        // Presidente si tiene la facultad de crear temporadas
        $this->assertTrue(Gate::forUser($presidente)->allows('create', Temporada::class));
    }

    /**
     * CA3: Los permisos estan persistidos como datos en la base de datos (seeder), no como if/else hardcodeados.
     */
    public function test_permisos_y_roles_se_encuentran_persistidos_como_datos_en_la_base_de_datos(): void
    {
        // Verificacion en base de datos de los 13 roles
        $rolesRequeridos = [
            'Administrador General',
            'Presidente',
            'Secretario',
            'Tesorero',
            'Comision de la Rama Infantil',
            'Programador de Partidos',
            'Capturista',
            'Arbitro',
            'Comision Arbitral',
            'Comision Disciplinaria',
            'Directivo de Club',
            'Delegado del Club',
            'Jugador',
        ];

        foreach ($rolesRequeridos as $rol) {
            $this->assertDatabaseHas('roles', [
                'name' => $rol,
                'guard_name' => 'web',
            ]);
        }

        // Verificacion de que existen permisos persistidos en la tabla permissions
        $totalPermisos = Permission::where('guard_name', 'web')->count();
        $this->assertGreaterThanOrEqual(35, $totalPermisos);

        // Verificacion de que las asignaciones estan en la tabla role_has_permissions
        $rolTesorero = Role::findByName('Tesorero', 'web');
        $this->assertGreaterThan(0, $rolTesorero->permissions()->count());

        $rolPresidente = Role::findByName('Presidente', 'web');
        $this->assertGreaterThan(0, $rolPresidente->permissions()->count());

        $rolSecretario = Role::findByName('Secretario', 'web');
        $this->assertGreaterThan(0, $rolSecretario->permissions()->count());

        $rolInfantil = Role::findByName('Comision de la Rama Infantil', 'web');
        $this->assertGreaterThan(0, $rolInfantil->permissions()->count());
    }

    /**
     * CA4: Cuando un rol de gobernanza intenta abrir la administracion de cuentas, el sistema responde 403.
     */
    public function test_roles_de_gobernanza_reciben_403_al_intentar_acceder_a_la_administracion_de_cuentas(): void
    {
        $rolesGobernanza = [
            'Presidente',
            'Secretario',
            'Tesorero',
            'Comision de la Rama Infantil',
        ];

        foreach ($rolesGobernanza as $nombreRol) {
            $email = strtolower(str_replace(' ', '_', $nombreRol)) . '_cuentas@ligafutbol.com';
            $usuario = User::create([
                'name' => "Usuario {$nombreRol}",
                'email' => $email,
                'password' => Hash::make('secreto123'),
                'estatus' => 'activo',
            ]);
            $usuario->assignRole($nombreRol);

            $respuesta = $this->actingAs($usuario)
                ->getJson('/admin/usuarios');

            $this->assertSame(
                403,
                $respuesta->status(),
                "El rol '{$nombreRol}' debe ser rechazado con 403 al abrir administracion de cuentas."
            );
        }
    }

    /**
     * CA4: El Administrador General si tiene acceso permitido a la administracion de cuentas.
     */
    public function test_administrador_general_puede_acceder_a_la_administracion_de_cuentas(): void
    {
        $admin = User::create([
            'name' => 'Admin Cuentas Permitido',
            'email' => 'admin_cuentas@ligafutbol.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Administrador General',
        ]);
        $admin->assignRole('Administrador General');

        $respuesta = $this->actingAs($admin)
            ->getJson('/admin/usuarios');

        $respuesta->assertStatus(200);
        $respuesta->assertJson([
            'modulo' => 'Administracion de Cuentas',
            'privilegio' => 'Administrador General',
            'acceso' => 'total',
        ]);
    }

    /**
     * Verificacion de sesion: Comision de la Rama Infantil aplica la politica administrativa de 30 minutos.
     */
    public function test_comision_de_la_rama_infantil_aplica_expiracion_de_inactividad_de_30_minutos(): void
    {
        $middleware = new CheckSessionTimeout();

        $infantil = User::create([
            'name' => 'Infantil Timeout Test',
            'email' => 'infantil_timeout@ligafutbol.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
        ]);
        $infantil->assignRole('Comision de la Rama Infantil');

        $limite = $middleware->obtenerLimiteInactividad($infantil);

        $this->assertSame(1800, $limite, 'Comision de la Rama Infantil debe tener tiempo limite de inactividad de 1800s (30 min).');
    }
}
