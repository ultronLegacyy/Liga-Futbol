<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckSessionTimeout;
use App\Models\Cancha;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClubAndPlayerRolesAccessTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        // Registro de rutas de prueba para verificar autorizacion atomica de acciones reservadas (CA2 y CA3 de HU-073)
        // 1. Ruta de sancion disciplinaria (facultad exclusiva del Tribunal de Penas)
        Route::middleware(['web', 'auth', 'permission:disciplina.sancionar'])
            ->post('/test-clubes/sanciones/{id}', function (int $id) {
                return response()->json([
                    'modulo' => 'Regimen Disciplinario',
                    'mensaje' => 'Sancion disciplinaria dictaminada y aprobada con exito.',
                    'sancion_id' => $id,
                ]);
            });

        // 2. Ruta de programacion de partidos (logistica semanal del Programador de Partidos)
        Route::middleware(['web', 'auth', 'permission:partidos.programar'])
            ->post('/test-clubes/partidos/programar', function () {
                return response()->json([
                    'modulo' => 'Programacion de Partidos',
                    'mensaje' => 'Partido calendarizado y programado con exito.',
                ]);
            });

        // 3. Ruta de asignacion de arbitros (facultad de Comision Arbitral y Programador)
        Route::middleware(['web', 'auth', 'permission:arbitros.asignar'])
            ->post('/test-clubes/arbitros/asignar', function () {
                return response()->json([
                    'modulo' => 'Comision Arbitral',
                    'mensaje' => 'Terna arbitral asignada al encuentro programado.',
                ]);
            });

        // 4. Ruta de firma de cedula arbitral (facultad exclusiva de Arbitro en Campo)
        Route::middleware(['web', 'auth', 'permission:cedulas.firmar'])
            ->post('/test-clubes/cedulas/{id}/firmar', function (int $id) {
                return response()->json([
                    'modulo' => 'Cedulas Arbitrales',
                    'mensaje' => 'Cedula arbitral firmada y validada en terreno de juego.',
                    'cedula_id' => $id,
                ]);
            });

        // 5. Ruta de bloqueo de campos (facultad exclusiva del Programador de Partidos)
        Route::middleware(['web', 'auth', 'permission:campos.bloquear'])
            ->post('/test-clubes/campos/{id}/bloquear', function (int $id) {
                return response()->json([
                    'modulo' => 'Campos e Instalaciones',
                    'mensaje' => 'Cancha bloqueada con exito para mantenimiento o clima.',
                    'campo_id' => $id,
                ]);
            });
    }

    /**
     * CA1: Los 3 roles de clubes y jugadores poseen unicamente los permisos de su ambito conforme a la seccion 2.3 de la ERS.
     */
    public function test_roles_de_clubes_y_jugadores_poseen_unicamente_los_permisos_de_su_ambito_segun_ers_seccion_2_3(): void
    {
        // 1. Verificacion del rol Presidente del Club (Directivo de Club)
        $rolPresidente = Role::findByName('Presidente del Club', 'web');
        $this->assertTrue($rolPresidente->hasPermissionTo('temporadas.ver'));
        $this->assertTrue($rolPresidente->hasPermissionTo('convocatorias.ver'));
        $this->assertTrue($rolPresidente->hasPermissionTo('reglas_temporada.ver'));
        $this->assertTrue($rolPresidente->hasPermissionTo('categorias.ver'));
        $this->assertTrue($rolPresidente->hasPermissionTo('clubes.ver'));
        $this->assertTrue($rolPresidente->hasPermissionTo('equipos.ver'));
        $this->assertTrue($rolPresidente->hasPermissionTo('jugadores.ver'));
        $this->assertTrue($rolPresidente->hasPermissionTo('campos.ver'));

        // No debe tener administracion de cuentas, bitacora de auditoria, gobernanza, arbitraje, disciplina ni finanzas
        $this->assertFalse($rolPresidente->hasPermissionTo('usuarios.ver'));
        $this->assertFalse($rolPresidente->hasPermissionTo('usuarios.crear'));
        $this->assertFalse($rolPresidente->hasPermissionTo('usuarios.editar'));
        $this->assertFalse($rolPresidente->hasPermissionTo('usuarios.eliminar'));
        $this->assertFalse($rolPresidente->hasPermissionTo('usuarios.asignar_rol'));
        $this->assertFalse($rolPresidente->hasPermissionTo('roles.ver'));
        $this->assertFalse($rolPresidente->hasPermissionTo('roles.crear'));
        $this->assertFalse($rolPresidente->hasPermissionTo('auditoria.ver'));
        $this->assertFalse($rolPresidente->hasPermissionTo('gobernanza.panel'));
        $this->assertFalse($rolPresidente->hasPermissionTo('temporadas.crear'));
        $this->assertFalse($rolPresidente->hasPermissionTo('temporadas.editar'));
        $this->assertFalse($rolPresidente->hasPermissionTo('temporadas.cerrar'));
        $this->assertFalse($rolPresidente->hasPermissionTo('temporadas.eliminar'));
        $this->assertFalse($rolPresidente->hasPermissionTo('categorias.crear'));
        $this->assertFalse($rolPresidente->hasPermissionTo('categorias.editar'));
        $this->assertFalse($rolPresidente->hasPermissionTo('reglas_temporada.editar'));
        $this->assertFalse($rolPresidente->hasPermissionTo('campos.bloquear'));
        $this->assertFalse($rolPresidente->hasPermissionTo('campos.crear'));
        $this->assertFalse($rolPresidente->hasPermissionTo('campos.editar'));
        $this->assertFalse($rolPresidente->hasPermissionTo('arbitros.ver'));
        $this->assertFalse($rolPresidente->hasPermissionTo('arbitros.asignar'));
        $this->assertFalse($rolPresidente->hasPermissionTo('partidos.programar'));
        $this->assertFalse($rolPresidente->hasPermissionTo('cedulas.capturar'));
        $this->assertFalse($rolPresidente->hasPermissionTo('cedulas.firmar'));
        $this->assertFalse($rolPresidente->hasPermissionTo('disciplina.sancionar'));
        $this->assertFalse($rolPresidente->hasPermissionTo('finanzas.cargos'));

        // 2. Verificacion del rol Delegado del Club (Titular / Suplente)
        $rolDelegado = Role::findByName('Delegado del Club', 'web');
        $this->assertTrue($rolDelegado->hasPermissionTo('temporadas.ver'));
        $this->assertTrue($rolDelegado->hasPermissionTo('convocatorias.ver'));
        $this->assertTrue($rolDelegado->hasPermissionTo('reglas_temporada.ver'));
        $this->assertTrue($rolDelegado->hasPermissionTo('categorias.ver'));
        $this->assertTrue($rolDelegado->hasPermissionTo('clubes.ver'));
        $this->assertTrue($rolDelegado->hasPermissionTo('equipos.ver'));
        $this->assertTrue($rolDelegado->hasPermissionTo('jugadores.ver'));
        $this->assertTrue($rolDelegado->hasPermissionTo('campos.ver'));

        // No debe tener administracion de cuentas, bitacora de auditoria, temporadas, arbitraje ni disciplina
        $this->assertFalse($rolDelegado->hasPermissionTo('usuarios.ver'));
        $this->assertFalse($rolDelegado->hasPermissionTo('usuarios.crear'));
        $this->assertFalse($rolDelegado->hasPermissionTo('usuarios.editar'));
        $this->assertFalse($rolDelegado->hasPermissionTo('usuarios.eliminar'));
        $this->assertFalse($rolDelegado->hasPermissionTo('roles.ver'));
        $this->assertFalse($rolDelegado->hasPermissionTo('auditoria.ver'));
        $this->assertFalse($rolDelegado->hasPermissionTo('gobernanza.panel'));
        $this->assertFalse($rolDelegado->hasPermissionTo('temporadas.crear'));
        $this->assertFalse($rolDelegado->hasPermissionTo('temporadas.editar'));
        $this->assertFalse($rolDelegado->hasPermissionTo('temporadas.cerrar'));
        $this->assertFalse($rolDelegado->hasPermissionTo('categorias.crear'));
        $this->assertFalse($rolDelegado->hasPermissionTo('categorias.editar'));
        $this->assertFalse($rolDelegado->hasPermissionTo('reglas_temporada.editar'));
        $this->assertFalse($rolDelegado->hasPermissionTo('campos.bloquear'));
        $this->assertFalse($rolDelegado->hasPermissionTo('arbitros.asignar'));
        $this->assertFalse($rolDelegado->hasPermissionTo('partidos.programar'));
        $this->assertFalse($rolDelegado->hasPermissionTo('cedulas.capturar'));
        $this->assertFalse($rolDelegado->hasPermissionTo('cedulas.firmar'));
        $this->assertFalse($rolDelegado->hasPermissionTo('disciplina.sancionar'));
        $this->assertFalse($rolDelegado->hasPermissionTo('finanzas.cargos'));

        // 3. Verificacion del rol Jugador
        $rolJugador = Role::findByName('Jugador', 'web');
        $this->assertTrue($rolJugador->hasPermissionTo('temporadas.ver'));
        $this->assertTrue($rolJugador->hasPermissionTo('convocatorias.ver'));
        $this->assertTrue($rolJugador->hasPermissionTo('categorias.ver'));
        $this->assertTrue($rolJugador->hasPermissionTo('equipos.ver'));
        $this->assertTrue($rolJugador->hasPermissionTo('jugadores.ver'));
        $this->assertTrue($rolJugador->hasPermissionTo('campos.ver'));

        // Jugador NO tiene permiso de ver configuracion de reglas de temporada ni consulta global de clubes
        $this->assertFalse($rolJugador->hasPermissionTo('reglas_temporada.ver'));
        $this->assertFalse($rolJugador->hasPermissionTo('clubes.ver'));

        // Tampoco tiene gestion de cuentas, torneos, arbitraje ni disciplina
        $this->assertFalse($rolJugador->hasPermissionTo('usuarios.ver'));
        $this->assertFalse($rolJugador->hasPermissionTo('usuarios.crear'));
        $this->assertFalse($rolJugador->hasPermissionTo('roles.ver'));
        $this->assertFalse($rolJugador->hasPermissionTo('auditoria.ver'));
        $this->assertFalse($rolJugador->hasPermissionTo('temporadas.crear'));
        $this->assertFalse($rolJugador->hasPermissionTo('temporadas.editar'));
        $this->assertFalse($rolJugador->hasPermissionTo('categorias.crear'));
        $this->assertFalse($rolJugador->hasPermissionTo('reglas_temporada.editar'));
        $this->assertFalse($rolJugador->hasPermissionTo('campos.bloquear'));
        $this->assertFalse($rolJugador->hasPermissionTo('arbitros.asignar'));
        $this->assertFalse($rolJugador->hasPermissionTo('partidos.programar'));
        $this->assertFalse($rolJugador->hasPermissionTo('cedulas.capturar'));
        $this->assertFalse($rolJugador->hasPermissionTo('cedulas.firmar'));
        $this->assertFalse($rolJugador->hasPermissionTo('disciplina.sancionar'));
    }

    /**
     * CA2: Los permisos se guardan como datos en base de datos mediante seeders y no como condiciones en el codigo.
     */
    public function test_permisos_se_persisten_como_datos_en_seeder_sin_condicionales_en_codigo_ca2(): void
    {
        $rolesClubesYJugadores = [
            'Presidente del Club',
            'Delegado del Club',
            'Jugador',
        ];

        foreach ($rolesClubesYJugadores as $nombreRol) {
            $rol = Role::where('name', $nombreRol)->where('guard_name', 'web')->first();
            $this->assertNotNull($rol, "El rol '{$nombreRol}' debe existir en la base de datos.");
            $this->assertGreaterThan(
                0,
                $rol->permissions()->count(),
                "El rol '{$nombreRol}' debe tener permisos asignados en la tabla relacional de Spatie."
            );
        }
    }

    /**
     * CA3: Cuando un usuario con rol Presidente del Club, Delegado del Club o Jugador intenta abrir
     * la administracion de cuentas (HU-080), el sistema responde estrictamente con HTTP 403 Forbidden.
     */
    public function test_rechazo_403_al_intentar_acceder_a_administracion_de_cuentas_hu_080_ca3(): void
    {
        $rolesAProbar = [
            'Presidente del Club',
            'Delegado del Club',
            'Jugador',
        ];

        foreach ($rolesAProbar as $nombreRol) {
            $usuario = $this->crearUsuarioConRol($nombreRol, "usr_{$nombreRol}_cuentas@ligafutbol.com");

            // 1. Verificacion en UserPolicy: no debe tener habilidad viewAny ni create
            $this->assertFalse(
                Gate::forUser($usuario)->allows('viewAny', User::class),
                "El rol '{$nombreRol}' no debe tener autorizacion viewAny en UserPolicy."
            );
            $this->assertFalse(
                Gate::forUser($usuario)->allows('create', User::class),
                "El rol '{$nombreRol}' no debe tener autorizacion create en UserPolicy."
            );

            // 2. Verificacion en ruta /admin/gestion/usuarios (protegida por permission:usuarios.ver)
            $respuestaGestion = $this->actingAs($usuario)
                ->getJson('/admin/gestion/usuarios');

            $this->assertSame(
                403,
                $respuestaGestion->status(),
                "El rol '{$nombreRol}' debe recibir 403 en /admin/gestion/usuarios."
            );

            // 3. Verificacion en rutas administrativas /admin/usuarios (protegidas por role:Administrador General)
            $respuestaIndex = $this->actingAs($usuario)->get('/admin/usuarios');
            $this->assertSame(
                403,
                $respuestaIndex->status(),
                "El rol '{$nombreRol}' debe recibir 403 al listar cuentas de usuario."
            );

            $respuestaCreate = $this->actingAs($usuario)->get('/admin/usuarios/create');
            $this->assertSame(
                403,
                $respuestaCreate->status(),
                "El rol '{$nombreRol}' debe recibir 403 al intentar abrir el formulario de creacion de usuarios."
            );

            $respuestaStore = $this->actingAs($usuario)->post('/admin/usuarios', [
                'name' => 'Usuario Intruso',
                'email' => "intruso_{$nombreRol}@ligafutbol.com",
                'password' => 'PasswordSegura2026!',
                'rol' => 'Consultor',
            ]);
            $this->assertSame(
                403,
                $respuestaStore->status(),
                "El rol '{$nombreRol}' debe recibir 403 al intentar registrar usuarios."
            );
        }
    }

    /**
     * CA3: Cuando un usuario con estos roles intenta crear temporadas deportivas, el sistema responde con HTTP 403 Forbidden.
     */
    public function test_rechazo_403_al_intentar_crear_temporadas_deportivas_ca3(): void
    {
        $rolesAProbar = [
            'Presidente del Club',
            'Delegado del Club',
            'Jugador',
        ];

        foreach ($rolesAProbar as $nombreRol) {
            $usuario = $this->crearUsuarioConRol($nombreRol, "usr_{$nombreRol}_temporadas@ligafutbol.com");

            // 1. Verificacion en TemporadaPolicy: no debe tener habilidad create
            $this->assertFalse(
                Gate::forUser($usuario)->allows('create', Temporada::class),
                "El rol '{$nombreRol}' no debe tener autorizacion create en TemporadaPolicy."
            );

            // 2. Verificacion en ruta GET /temporadas/create
            $this->actingAs($usuario)
                ->get('/temporadas/create')
                ->assertStatus(403);

            // 3. Verificacion en ruta POST /temporadas
            $this->actingAs($usuario)
                ->post('/temporadas', [
                    'nombre' => 'Temporada No Autorizada 2026',
                    'fecha_inicio' => '2026-11-01',
                    'fecha_fin' => '2027-06-30',
                ])
                ->assertStatus(403);
        }
    }

    /**
     * CA3: Cuando un usuario con estos roles intenta crear categorias y grupos, el sistema responde con HTTP 403 Forbidden.
     */
    public function test_rechazo_403_al_intentar_crear_o_modificar_categorias_y_grupos_ca3(): void
    {
        $temporada = Temporada::factory()->activa()->create();

        $rolesAProbar = [
            'Presidente del Club',
            'Delegado del Club',
            'Jugador',
        ];

        foreach ($rolesAProbar as $nombreRol) {
            $usuario = $this->crearUsuarioConRol($nombreRol, "usr_{$nombreRol}_categorias@ligafutbol.com");

            // 1. Verificacion en CategoriaPolicy: no debe permitir create
            $this->assertFalse(
                Gate::forUser($usuario)->allows('create', [Categoria::class, $temporada]),
                "El rol '{$nombreRol}' no debe tener autorizacion create en CategoriaPolicy."
            );

            // 2. Verificacion en ruta GET /temporadas/{temporada}/categorias/create
            $this->actingAs($usuario)
                ->get("/temporadas/{$temporada->id}/categorias/create")
                ->assertStatus(403);

            // 3. Verificacion en ruta POST /temporadas/{temporada}/categorias
            $this->actingAs($usuario)
                ->post("/temporadas/{$temporada->id}/categorias", [
                    'nombre' => 'Categoria Intruso',
                    'edad_minima' => 18,
                    'edad_maxima' => 35,
                ])
                ->assertStatus(403);
        }
    }

    /**
     * CA3: Cuando un usuario con estos roles intenta configurar reglas oficiales de temporada, el sistema responde con HTTP 403 Forbidden.
     * Presidente y Delegado pueden consultar reglas (reglas_temporada.ver); Jugador recibe 403 incluso en lectura.
     */
    public function test_rechazo_403_al_intentar_configurar_reglas_oficiales_de_temporada_ca3(): void
    {
        $temporada = Temporada::factory()->activa()->create();

        $rolesAProbar = [
            'Presidente del Club',
            'Delegado del Club',
            'Jugador',
        ];

        foreach ($rolesAProbar as $nombreRol) {
            $usuario = $this->crearUsuarioConRol($nombreRol, "usr_{$nombreRol}_reglas@ligafutbol.com");

            // Todos los roles de clubes y jugadores reciben 403 al intentar guardar o editar reglas
            $this->actingAs($usuario)
                ->post("/temporadas/{$temporada->id}/reglas", [
                    'puntos_por_victoria' => 3,
                    'puntos_por_empate' => 1,
                    'puntos_por_derrota' => 0,
                    'criterio_desempate' => 'diferencia_goles',
                ])
                ->assertStatus(403);
        }

        // Jugador tampoco tiene permiso de lectura de reglas de temporada (reglas_temporada.ver = false)
        $jugador = $this->crearUsuarioConRol('Jugador', 'jugador_ver_reglas@ligafutbol.com');
        $this->actingAs($jugador)
            ->get("/temporadas/{$temporada->id}/reglas")
            ->assertStatus(403);

        // Por el contrario, Presidente y Delegado si pueden consultar las reglas oficiales (reglas_temporada.ver = true)
        $presidente = $this->crearUsuarioConRol('Presidente del Club', 'pte_ver_reglas@ligafutbol.com');
        $this->actingAs($presidente)
            ->get("/temporadas/{$temporada->id}/reglas")
            ->assertStatus(200);

        $delegado = $this->crearUsuarioConRol('Delegado del Club', 'del_ver_reglas@ligafutbol.com');
        $this->actingAs($delegado)
            ->get("/temporadas/{$temporada->id}/reglas")
            ->assertStatus(200);
    }

    /**
     * CA3: Bloqueo perimetral estricto con HTTP 403 Forbidden para operaciones arbitrales, disciplinarias y logisticas.
     */
    public function test_rechazo_403_en_operaciones_operativas_y_disciplinarias_no_autorizadas(): void
    {
        $cancha = Cancha::create([
            'nombre' => 'Cancha Test Clubes',
            'sede' => 'UDC',
            'tipo_superficie' => 'pasto_sintetico',
            'capacidad_partidos_dia' => 4,
            'estado' => 'disponible',
        ]);

        $rolesAProbar = [
            'Presidente del Club',
            'Delegado del Club',
            'Jugador',
        ];

        foreach ($rolesAProbar as $nombreRol) {
            $usuario = $this->crearUsuarioConRol($nombreRol, "usr_{$nombreRol}_op@ligafutbol.com");

            // 1. Bloqueo de campos (CanchaPolicy y ruta)
            $this->assertFalse(
                Gate::forUser($usuario)->allows('block', $cancha),
                "El rol '{$nombreRol}' no debe tener permiso de bloqueo en CanchaPolicy."
            );
            $this->actingAs($usuario)
                ->postJson("/test-clubes/campos/{$cancha->id}/bloquear")
                ->assertStatus(403);

            // 2. Programacion de partidos
            $this->actingAs($usuario)
                ->postJson('/test-clubes/partidos/programar')
                ->assertStatus(403);

            // 3. Asignacion de arbitros
            $this->actingAs($usuario)
                ->postJson('/test-clubes/arbitros/asignar')
                ->assertStatus(403);

            // 4. Firma de cedula arbitral
            $this->actingAs($usuario)
                ->postJson('/test-clubes/cedulas/1/firmar')
                ->assertStatus(403);

            // 5. Dictamen de sanciones disciplinarias
            $this->actingAs($usuario)
                ->postJson('/test-clubes/sanciones/1')
                ->assertStatus(403);
        }
    }

    /**
     * RNF-35: Verificacion de la politica de expiracion de sesion por inactividad de 8 horas (28800s) para roles de clubes y jugadores.
     */
    public function test_politica_de_expiracion_de_sesion_por_inactividad_8_horas_rnf_35(): void
    {
        $middleware = new CheckSessionTimeout();

        $rolesAProbar = [
            'Presidente del Club',
            'Delegado del Club',
            'Jugador',
        ];

        foreach ($rolesAProbar as $nombreRol) {
            $usuario = $this->crearUsuarioConRol($nombreRol, "timeout_{$nombreRol}@ligafutbol.com");

            // El umbral configurado debe ser exactamente 28800 segundos (8 horas)
            $limite = $middleware->obtenerLimiteInactividad($usuario);
            $this->assertSame(
                28800,
                $limite,
                "El rol '{$nombreRol}' debe aplicar el umbral de inactividad de 28800 segundos (8 horas)."
            );

            // Caso A: Inactividad de 7 horas (25200s) -> sesion se mantiene valida
            $this->actingAs($usuario)
                ->withSession(['last_activity_time' => now()->timestamp - 25200])
                ->get('/')
                ->assertStatus(200);

            // Caso B: Inactividad de mas de 8 horas (28801s) -> sesion expirada, redirige a login
            $respuestaExpirada = $this->actingAs($usuario)
                ->withSession(['last_activity_time' => now()->timestamp - 28801])
                ->get('/');

            $respuestaExpirada->assertRedirect(route('login'));
            $respuestaExpirada->assertSessionHas('status', 'Su sesion ha expirado por inactividad por motivos de seguridad.');

            // Caso C: Peticion AJAX/JSON expirada -> responde con 401 y session_expired = true
            $respuestaJsonExpirada = $this->actingAs($usuario)
                ->withSession(['last_activity_time' => now()->timestamp - 28801])
                ->getJson('/');

            $respuestaJsonExpirada->assertStatus(401);
            $respuestaJsonExpirada->assertJson([
                'session_expired' => true,
                'message' => 'Su sesion ha expirado por inactividad por motivos de seguridad.',
            ]);
        }
    }

    /**
     * Verificacion de que las cuentas demo sembradas en DatabaseSeeder poseen los roles Spatie asignados.
     */
    public function test_cuentas_demo_poseen_los_roles_spatie_correspondientes_desde_seeder(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Cuenta demo Presidente del Club
        $directivo = User::where('email', 'directivo@moreliafc.com')->first();
        $this->assertNotNull($directivo);
        $this->assertTrue($directivo->hasRole('Presidente del Club'));

        $presidenteClub = User::where('email', 'presidente.club@ligafutbol.com')->first();
        $this->assertNotNull($presidenteClub);
        $this->assertTrue($presidenteClub->hasRole('Presidente del Club'));

        // Cuenta demo Delegado del Club
        $delegado = User::where('email', 'delegado.club@ligafutbol.com')->first();
        $this->assertNotNull($delegado);
        $this->assertTrue($delegado->hasRole('Delegado del Club'));

        // Cuenta demo Jugador
        $jugador = User::where('email', 'jugador@ligafutbol.com')->first();
        $this->assertNotNull($jugador);
        $this->assertTrue($jugador->hasRole('Jugador'));
    }

    /**
     * Metodo auxiliar para crear usuarios autenticables con rol Spatie en transaccion aislada.
     */
    protected function crearUsuarioConRol(string $rol, string $email): User
    {
        $rolEnum = match ($rol) {
            'Presidente del Club' => 'Directivo Club',
            'Delegado del Club' => 'Consultor',
            'Jugador' => 'Jugador',
            default => 'Consultor',
        };

        $user = User::create([
            'name' => "Usuario Demo {$rol}",
            'email' => $email,
            'password' => Hash::make('password123'),
            'rol' => $rolEnum,
            'estatus' => 'activo',
            'requires_password_change' => false,
        ]);

        $user->assignRole($rol);

        return $user;
    }
}
