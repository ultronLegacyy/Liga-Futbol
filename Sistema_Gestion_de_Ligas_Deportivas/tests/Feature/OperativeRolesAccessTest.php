<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckSessionTimeout;
use App\Models\Cancha;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperativeRolesAccessTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        // Registro de rutas de prueba para verificar autorizacion atomica de acciones operativas (CA2 de HU-072)
        // 1. Ruta de sancion disciplinaria (simula aprobacion de sanciones del Tribunal de Penas - Sprint 7)
        Route::middleware(['web', 'auth', 'permission:disciplina.sancionar'])
            ->post('/test-operativo/sanciones/{id}', function (int $id) {
                return response()->json([
                    'modulo' => 'Regimen Disciplinario',
                    'mensaje' => 'Sancion disciplinaria dictaminada y aprobada con exito.',
                    'sancion_id' => $id,
                ]);
            });

        // 2. Ruta de programacion de partidos (logistica semanal del Programador de Partidos)
        Route::middleware(['web', 'auth', 'permission:partidos.programar'])
            ->post('/test-operativo/partidos/programar', function () {
                return response()->json([
                    'modulo' => 'Programacion de Partidos',
                    'mensaje' => 'Partido calendarizado y programado con exito.',
                ]);
            });

        // 3. Ruta de asignacion de arbitros (facultad de Comision Arbitral y Programador)
        Route::middleware(['web', 'auth', 'permission:arbitros.asignar'])
            ->post('/test-operativo/arbitros/asignar', function () {
                return response()->json([
                    'modulo' => 'Comision Arbitral',
                    'mensaje' => 'Terna arbitral asignada al encuentro programado.',
                ]);
            });

        // 4. Ruta de firma de cedula arbitral (facultad exclusiva de Arbitro en Campo)
        Route::middleware(['web', 'auth', 'permission:cedulas.firmar'])
            ->post('/test-operativo/cedulas/{id}/firmar', function (int $id) {
                return response()->json([
                    'modulo' => 'Cedulas Arbitrales',
                    'mensaje' => 'Cedula arbitral firmada y validada en terreno de juego.',
                    'cedula_id' => $id,
                ]);
            });
    }

    /**
     * CA1: Los 5 roles operativos deportivos poseen unicamente los permisos de su ambito conforme a la seccion 2.3 de la ERS.
     */
    public function test_roles_operativos_poseen_unicamente_los_permisos_de_su_ambito_segun_ers_seccion_2_3(): void
    {
        // 1. Verificacion del rol Programador de Partidos
        $rolProgramador = Role::findByName('Programador de Partidos', 'web');
        $this->assertTrue($rolProgramador->hasPermissionTo('temporadas.ver'));
        $this->assertTrue($rolProgramador->hasPermissionTo('convocatorias.ver'));
        $this->assertTrue($rolProgramador->hasPermissionTo('reglas_temporada.ver'));
        $this->assertTrue($rolProgramador->hasPermissionTo('categorias.ver'));
        $this->assertTrue($rolProgramador->hasPermissionTo('clubes.ver'));
        $this->assertTrue($rolProgramador->hasPermissionTo('equipos.ver'));
        $this->assertTrue($rolProgramador->hasPermissionTo('jugadores.ver'));
        $this->assertTrue($rolProgramador->hasPermissionTo('campos.ver'));
        $this->assertTrue($rolProgramador->hasPermissionTo('campos.crear'));
        $this->assertTrue($rolProgramador->hasPermissionTo('campos.editar'));
        $this->assertTrue($rolProgramador->hasPermissionTo('campos.bloquear'));
        $this->assertTrue($rolProgramador->hasPermissionTo('arbitros.ver'));
        $this->assertTrue($rolProgramador->hasPermissionTo('arbitros.asignar'));
        $this->assertTrue($rolProgramador->hasPermissionTo('partidos.ver'));
        $this->assertTrue($rolProgramador->hasPermissionTo('partidos.programar'));
        $this->assertTrue($rolProgramador->hasPermissionTo('partidos.reprogramar'));
        $this->assertTrue($rolProgramador->hasPermissionTo('cedulas.ver'));
        $this->assertTrue($rolProgramador->hasPermissionTo('resultados.validar'));

        // No debe tener administracion de cuentas, bitacora de auditoria, disciplina sancionatoria ni finanzas
        $this->assertFalse($rolProgramador->hasPermissionTo('usuarios.ver'));
        $this->assertFalse($rolProgramador->hasPermissionTo('usuarios.crear'));
        $this->assertFalse($rolProgramador->hasPermissionTo('usuarios.eliminar'));
        $this->assertFalse($rolProgramador->hasPermissionTo('roles.ver'));
        $this->assertFalse($rolProgramador->hasPermissionTo('auditoria.ver'));
        $this->assertFalse($rolProgramador->hasPermissionTo('gobernanza.panel'));
        $this->assertFalse($rolProgramador->hasPermissionTo('disciplina.sancionar'));
        $this->assertFalse($rolProgramador->hasPermissionTo('disciplina.expedientes'));
        $this->assertFalse($rolProgramador->hasPermissionTo('finanzas.ver'));

        // 2. Verificacion del rol Capturista
        $rolCapturista = Role::findByName('Capturista', 'web');
        $this->assertTrue($rolCapturista->hasPermissionTo('temporadas.ver'));
        $this->assertTrue($rolCapturista->hasPermissionTo('convocatorias.ver'));
        $this->assertTrue($rolCapturista->hasPermissionTo('reglas_temporada.ver'));
        $this->assertTrue($rolCapturista->hasPermissionTo('categorias.ver'));
        $this->assertTrue($rolCapturista->hasPermissionTo('clubes.ver'));
        $this->assertTrue($rolCapturista->hasPermissionTo('equipos.ver'));
        $this->assertTrue($rolCapturista->hasPermissionTo('jugadores.ver'));
        $this->assertTrue($rolCapturista->hasPermissionTo('campos.ver'));
        $this->assertTrue($rolCapturista->hasPermissionTo('partidos.ver'));
        $this->assertTrue($rolCapturista->hasPermissionTo('cedulas.ver'));
        $this->assertTrue($rolCapturista->hasPermissionTo('cedulas.capturar'));

        // No debe tener disciplina sancionatoria (ejemplo expreso de CA2), programacion de partidos ni bloqueo de campos
        $this->assertFalse($rolCapturista->hasPermissionTo('disciplina.sancionar'));
        $this->assertFalse($rolCapturista->hasPermissionTo('disciplina.expedientes'));
        $this->assertFalse($rolCapturista->hasPermissionTo('partidos.programar'));
        $this->assertFalse($rolCapturista->hasPermissionTo('partidos.reprogramar'));
        $this->assertFalse($rolCapturista->hasPermissionTo('campos.bloquear'));
        $this->assertFalse($rolCapturista->hasPermissionTo('arbitros.asignar'));
        $this->assertFalse($rolCapturista->hasPermissionTo('usuarios.ver'));
        $this->assertFalse($rolCapturista->hasPermissionTo('roles.ver'));
        $this->assertFalse($rolCapturista->hasPermissionTo('finanzas.ver'));

        // 3. Verificacion del rol Arbitro en Campo
        $rolArbitro = Role::findByName('Árbitro en Campo', 'web');
        $this->assertTrue($rolArbitro->hasPermissionTo('temporadas.ver'));
        $this->assertTrue($rolArbitro->hasPermissionTo('convocatorias.ver'));
        $this->assertTrue($rolArbitro->hasPermissionTo('categorias.ver'));
        $this->assertTrue($rolArbitro->hasPermissionTo('equipos.ver'));
        $this->assertTrue($rolArbitro->hasPermissionTo('jugadores.ver'));
        $this->assertTrue($rolArbitro->hasPermissionTo('campos.ver'));
        $this->assertTrue($rolArbitro->hasPermissionTo('arbitros.ver'));
        $this->assertTrue($rolArbitro->hasPermissionTo('partidos.ver'));
        $this->assertTrue($rolArbitro->hasPermissionTo('cedulas.ver'));
        $this->assertTrue($rolArbitro->hasPermissionTo('cedulas.capturar'));
        $this->assertTrue($rolArbitro->hasPermissionTo('cedulas.firmar'));
        $this->assertTrue($rolArbitro->hasPermissionTo('disciplina.ver'));

        // No debe dictaminar sanciones (facultad del Tribunal), ni programar partidos ni bloquear campos
        $this->assertFalse($rolArbitro->hasPermissionTo('disciplina.sancionar'));
        $this->assertFalse($rolArbitro->hasPermissionTo('disciplina.expedientes'));
        $this->assertFalse($rolArbitro->hasPermissionTo('partidos.programar'));
        $this->assertFalse($rolArbitro->hasPermissionTo('campos.bloquear'));
        $this->assertFalse($rolArbitro->hasPermissionTo('arbitros.asignar'));
        $this->assertFalse($rolArbitro->hasPermissionTo('usuarios.ver'));
        $this->assertFalse($rolArbitro->hasPermissionTo('finanzas.ver'));

        // 4. Verificacion del rol Comision Arbitral
        $rolComisionArbitral = Role::findByName('Comisión Arbitral', 'web');
        $this->assertTrue($rolComisionArbitral->hasPermissionTo('temporadas.ver'));
        $this->assertTrue($rolComisionArbitral->hasPermissionTo('convocatorias.ver'));
        $this->assertTrue($rolComisionArbitral->hasPermissionTo('categorias.ver'));
        $this->assertTrue($rolComisionArbitral->hasPermissionTo('equipos.ver'));
        $this->assertTrue($rolComisionArbitral->hasPermissionTo('jugadores.ver'));
        $this->assertTrue($rolComisionArbitral->hasPermissionTo('campos.ver'));
        $this->assertTrue($rolComisionArbitral->hasPermissionTo('arbitros.ver'));
        $this->assertTrue($rolComisionArbitral->hasPermissionTo('arbitros.crear'));
        $this->assertTrue($rolComisionArbitral->hasPermissionTo('arbitros.editar'));
        $this->assertTrue($rolComisionArbitral->hasPermissionTo('arbitros.asignar'));
        $this->assertTrue($rolComisionArbitral->hasPermissionTo('partidos.ver'));
        $this->assertTrue($rolComisionArbitral->hasPermissionTo('cedulas.ver'));

        // No debe sancionar, programar partidos ni administrar usuarios
        $this->assertFalse($rolComisionArbitral->hasPermissionTo('disciplina.sancionar'));
        $this->assertFalse($rolComisionArbitral->hasPermissionTo('partidos.programar'));
        $this->assertFalse($rolComisionArbitral->hasPermissionTo('campos.bloquear'));
        $this->assertFalse($rolComisionArbitral->hasPermissionTo('usuarios.ver'));
        $this->assertFalse($rolComisionArbitral->hasPermissionTo('finanzas.ver'));

        // 5. Verificacion del rol Tribunal de Penas
        $rolTribunal = Role::findByName('Tribunal de Penas', 'web');
        $this->assertTrue($rolTribunal->hasPermissionTo('temporadas.ver'));
        $this->assertTrue($rolTribunal->hasPermissionTo('convocatorias.ver'));
        $this->assertTrue($rolTribunal->hasPermissionTo('categorias.ver'));
        $this->assertTrue($rolTribunal->hasPermissionTo('equipos.ver'));
        $this->assertTrue($rolTribunal->hasPermissionTo('jugadores.ver'));
        $this->assertTrue($rolTribunal->hasPermissionTo('campos.ver'));
        $this->assertTrue($rolTribunal->hasPermissionTo('arbitros.ver'));
        $this->assertTrue($rolTribunal->hasPermissionTo('partidos.ver'));
        $this->assertTrue($rolTribunal->hasPermissionTo('cedulas.ver'));
        $this->assertTrue($rolTribunal->hasPermissionTo('disciplina.ver'));
        $this->assertTrue($rolTribunal->hasPermissionTo('disciplina.expedientes'));
        $this->assertTrue($rolTribunal->hasPermissionTo('disciplina.sancionar'));

        // No debe programar partidos, bloquear campos, asignar arbitros ni administrar usuarios
        $this->assertFalse($rolTribunal->hasPermissionTo('partidos.programar'));
        $this->assertFalse($rolTribunal->hasPermissionTo('partidos.reprogramar'));
        $this->assertFalse($rolTribunal->hasPermissionTo('campos.bloquear'));
        $this->assertFalse($rolTribunal->hasPermissionTo('arbitros.asignar'));
        $this->assertFalse($rolTribunal->hasPermissionTo('usuarios.ver'));
        $this->assertFalse($rolTribunal->hasPermissionTo('finanzas.ver'));
    }

    /**
     * CA2: Rechazo con HTTP 403 Forbidden cuando un rol operativo intenta una accion fuera de su ambito.
     * Caso especifico de Jira: Capturista intentando aprobar o emitir una sancion disciplinaria.
     */
    public function test_capturista_intentando_aprobar_sancion_es_rechazado_con_403_forbidden(): void
    {
        $capturista = $this->crearUsuarioConRol('Capturista', 'capturista_test@ligafutbol.com');

        // Intento de aprobar o emitir una sancion
        $respuesta = $this->actingAs($capturista)
            ->postJson('/test-operativo/sanciones/99');

        $respuesta->assertStatus(403);

        // Demas roles no autorizados para dictaminar sanciones tambien deben recibir 403
        $programador = $this->crearUsuarioConRol('Programador de Partidos', 'prog_sancion@ligafutbol.com');
        $this->actingAs($programador)->postJson('/test-operativo/sanciones/99')->assertStatus(403);

        $arbitro = $this->crearUsuarioConRol('Árbitro en Campo', 'arbitro_sancion@ligafutbol.com');
        $this->actingAs($arbitro)->postJson('/test-operativo/sanciones/99')->assertStatus(403);

        $comision = $this->crearUsuarioConRol('Comisión Arbitral', 'comision_sancion@ligafutbol.com');
        $this->actingAs($comision)->postJson('/test-operativo/sanciones/99')->assertStatus(403);

        // Por el contrario, el Tribunal de Penas si tiene autorizacion
        $tribunal = $this->crearUsuarioConRol('Tribunal de Penas', 'tribunal_autorizado@ligafutbol.com');
        $respuestaTribunal = $this->actingAs($tribunal)
            ->postJson('/test-operativo/sanciones/99');

        $respuestaTribunal->assertStatus(200);
        $respuestaTribunal->assertJson([
            'modulo' => 'Regimen Disciplinario',
            'mensaje' => 'Sancion disciplinaria dictaminada y aprobada con exito.',
            'sancion_id' => 99,
        ]);
    }

    /**
     * CA2: Programacion de partidos es exclusiva del Programador de Partidos; demas roles reciben 403.
     */
    public function test_programacion_de_partidos_es_exclusiva_de_programador_y_rechaza_a_demas_con_403(): void
    {
        $programador = $this->crearUsuarioConRol('Programador de Partidos', 'prog_partido@ligafutbol.com');

        $this->actingAs($programador)
            ->postJson('/test-operativo/partidos/programar')
            ->assertStatus(200)
            ->assertJson([
                'modulo' => 'Programacion de Partidos',
                'mensaje' => 'Partido calendarizado y programado con exito.',
            ]);

        // Rechazo 403 para los demas perfiles operativos
        $rolesRechazados = ['Capturista', 'Árbitro en Campo', 'Comisión Arbitral', 'Tribunal de Penas'];
        foreach ($rolesRechazados as $rol) {
            $usuario = $this->crearUsuarioConRol($rol, "usr_{$rol}_prog@ligafutbol.com");
            $this->actingAs($usuario)
                ->postJson('/test-operativo/partidos/programar')
                ->assertStatus(403);
        }
    }

    /**
     * CA2: Asignacion de arbitros es permitida para Comision Arbitral y Programador de Partidos; rechazada para otros con 403.
     */
    public function test_asignacion_de_arbitros_permitida_para_comision_y_programador_y_rechazada_para_otros(): void
    {
        $comision = $this->crearUsuarioConRol('Comisión Arbitral', 'comision_asig@ligafutbol.com');
        $this->actingAs($comision)
            ->postJson('/test-operativo/arbitros/asignar')
            ->assertStatus(200);

        $programador = $this->crearUsuarioConRol('Programador de Partidos', 'prog_asig@ligafutbol.com');
        $this->actingAs($programador)
            ->postJson('/test-operativo/arbitros/asignar')
            ->assertStatus(200);

        // Rechazo 403 para roles sin permiso de asignacion arbitral
        $capturista = $this->crearUsuarioConRol('Capturista', 'capt_asig@ligafutbol.com');
        $this->actingAs($capturista)->postJson('/test-operativo/arbitros/asignar')->assertStatus(403);

        $tribunal = $this->crearUsuarioConRol('Tribunal de Penas', 'trib_asig@ligafutbol.com');
        $this->actingAs($tribunal)->postJson('/test-operativo/arbitros/asignar')->assertStatus(403);
    }

    /**
     * CA2: Firma de cedulas arbitrales es exclusiva de Arbitro en Campo; otros roles reciben 403.
     */
    public function test_firma_de_cedulas_es_exclusiva_de_arbitro_en_campo_y_rechaza_a_otros_con_403(): void
    {
        $arbitro = $this->crearUsuarioConRol('Árbitro en Campo', 'arbitro_firma@ligafutbol.com');
        $this->actingAs($arbitro)
            ->postJson('/test-operativo/cedulas/10/firmar')
            ->assertStatus(200)
            ->assertJson([
                'modulo' => 'Cedulas Arbitrales',
                'mensaje' => 'Cedula arbitral firmada y validada en terreno de juego.',
                'cedula_id' => 10,
            ]);

        $capturista = $this->crearUsuarioConRol('Capturista', 'capt_firma@ligafutbol.com');
        $this->actingAs($capturista)->postJson('/test-operativo/cedulas/10/firmar')->assertStatus(403);

        $programador = $this->crearUsuarioConRol('Programador de Partidos', 'prog_firma@ligafutbol.com');
        $this->actingAs($programador)->postJson('/test-operativo/cedulas/10/firmar')->assertStatus(403);

        $tribunal = $this->crearUsuarioConRol('Tribunal de Penas', 'trib_firma@ligafutbol.com');
        $this->actingAs($tribunal)->postJson('/test-operativo/cedulas/10/firmar')->assertStatus(403);
    }

    /**
     * CA2: Gestion y bloqueo de canchas (HU-024) es permitida para Programador de Partidos y rechaza con 403 a los demas.
     */
    public function test_bloqueo_de_canchas_es_permitido_para_programador_y_rechazado_con_403_para_otros(): void
    {
        $cancha = Cancha::create([
            'nombre' => 'Cancha Prueba Operativos',
            'sede' => 'UDC',
            'tipo_superficie' => 'pasto_sintetico',
            'capacidad_partidos_dia' => 4,
            'estado' => 'disponible',
        ]);

        $programador = $this->crearUsuarioConRol('Programador de Partidos', 'prog_canchas@ligafutbol.com');

        // CanchaPolicy permite block al Programador
        $this->assertTrue(Gate::forUser($programador)->allows('block', $cancha));

        // Demas roles operativos no deben tener autorizacion block en CanchaPolicy
        $rolesSinPermiso = ['Capturista', 'Árbitro en Campo', 'Comisión Arbitral', 'Tribunal de Penas'];
        foreach ($rolesSinPermiso as $rol) {
            $usuario = $this->crearUsuarioConRol($rol, "usr_{$rol}_bloqueo@ligafutbol.com");
            $this->assertFalse(
                Gate::forUser($usuario)->allows('block', $cancha),
                "El rol '{$rol}' no debe tener autorizacion para bloquear canchas."
            );
        }
    }

    /**
     * CA3: Los permisos se guardan como datos en base de datos mediante seeders y no como condiciones cableadas en el codigo.
     */
    public function test_permisos_se_persisten_como_datos_en_seeder_sin_condicionales_en_codigo(): void
    {
        $rolesOperativos = [
            'Programador de Partidos',
            'Capturista',
            'Árbitro en Campo',
            'Comisión Arbitral',
            'Tribunal de Penas',
        ];

        foreach ($rolesOperativos as $nombreRol) {
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
     * CA4: Cuando un usuario con cualquiera de los 5 roles operativos intenta abrir la administracion de cuentas (HU-080),
     * el sistema responde estrictamente con HTTP 403 Forbidden.
     */
    public function test_roles_operativos_reciben_403_al_intentar_acceder_a_la_administracion_de_cuentas_ca4(): void
    {
        $rolesOperativos = [
            'Programador de Partidos',
            'Capturista',
            'Árbitro en Campo',
            'Comisión Arbitral',
            'Tribunal de Penas',
        ];

        foreach ($rolesOperativos as $nombreRol) {
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
                'email' => 'intruso@ligafutbol.com',
                'password' => 'PasswordSegura2026!',
                'rol' => 'Capturista',
            ]);
            $this->assertSame(
                403,
                $respuestaStore->status(),
                "El rol '{$nombreRol}' debe recibir 403 al intentar registrar usuarios."
            );
        }
    }

    /**
     * RNF-35: Verificacion de la politica de expiracion de sesion diferenciada por inactividad.
     * Roles operativos aplican 8 horas (28800s); Tribunal de Penas aplica 30 minutos (1800s) por jerarquia directiva/disciplinaria.
     */
    public function test_politica_de_expiracion_de_sesion_diferenciada_para_roles_operativos(): void
    {
        $middleware = new CheckSessionTimeout();

        // Roles operativos deportivos aplican 8 horas (28800 segundos)
        $rolesOperativos8Horas = [
            'Programador de Partidos',
            'Capturista',
            'Árbitro en Campo',
            'Comisión Arbitral',
        ];

        foreach ($rolesOperativos8Horas as $rol) {
            $usuario = $this->crearUsuarioConRol($rol, "timeout_{$rol}@ligafutbol.com");
            $limite = $middleware->obtenerLimiteInactividad($usuario);

            $this->assertSame(
                28800,
                $limite,
                "El rol operativo '{$rol}' debe tener limite de inactividad de 28800s (8 horas)."
            );
        }

        // Tribunal de Penas es un organo directivo-disciplinario y aplica la politica de 30 minutos (1800 segundos)
        $tribunal = $this->crearUsuarioConRol('Tribunal de Penas', 'timeout_tribunal@ligafutbol.com');
        $this->assertSame(
            1800,
            $middleware->obtenerLimiteInactividad($tribunal),
            "El rol 'Tribunal de Penas' debe tener limite de inactividad de 1800s (30 minutos)."
        );
    }

    /**
     * Metodo auxiliar para crear usuarios autenticables con rol Spatie en transaccion aislada.
     */
    protected function crearUsuarioConRol(string $rol, string $email): User
    {
        $user = User::create([
            'name' => "Usuario Demo {$rol}",
            'email' => $email,
            'password' => Hash::make('password123'),
            'estatus' => 'activo',
            'requires_password_change' => false,
        ]);

        $user->assignRole($rol);

        return $user;
    }
}
