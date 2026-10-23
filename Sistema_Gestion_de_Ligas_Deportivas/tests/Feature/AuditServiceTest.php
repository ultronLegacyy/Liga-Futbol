<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
use App\Models\Temporada;
use App\Models\User;
use App\Services\AuditService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditServiceTest extends TestCase
{
    // Manejo transaccional: la auditoría no se puede borrar, así que nada debe confirmarse
    use DatabaseTransactions;

    private const IP_PRUEBA = '203.0.113.25';

    private AuditService $auditoria;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->auditoria = app(AuditService::class);

        // IP simulada de la petición en curso
        $this->app['request']->server->set('REMOTE_ADDR', self::IP_PRUEBA);
    }

    public function test_crear_una_temporada_con_motivo_registra_la_auditoria_completa(): void
    {
        $usuario = $this->crearUsuario('auditor_crear@liga.com');
        $this->actingAs($usuario);

        $temporada = $this->auditoria->conMotivo('Alta de la temporada de prueba', fn () => Temporada::create(
            $this->datosTemporada('Temporada Auditada')
        ));

        $log = $this->logsDe($temporada, 'crear')->sole();

        $this->assertSame('temporadas', $log->tabla_afectada);
        $this->assertSame($temporada->id, $log->registro_id);
        $this->assertSame($usuario->id, $log->usuario_id);
        $this->assertSame(self::IP_PRUEBA, $log->ip);
        $this->assertSame('Alta de la temporada de prueba', $log->motivo);
        $this->assertNull($log->valores_anteriores);
        $this->assertSame($temporada->id, $log->valores_nuevos['id']);
        $this->assertSame('Temporada Auditada', $log->valores_nuevos['nombre']);
        $this->assertSame('2027-01-10', $log->valores_nuevos['fecha_inicio']);
        $this->assertSame('borrador', $log->valores_nuevos['estado']);
        $this->assertNotNull($log->created_at);
        $this->assertTrue($log->created_at->between(now()->subMinute(), now()->addMinute()));
    }

    public function test_actualizar_una_temporada_registra_solo_los_valores_que_cambiaron(): void
    {
        $usuario = $this->crearUsuario('auditor_actualizar@liga.com');
        $this->actingAs($usuario);
        $temporada = Temporada::create($this->datosTemporada('Temporada Original'));

        $this->auditoria->conMotivo('Corrección del nombre y activación', fn () => $temporada->update([
            'nombre' => 'Temporada Corregida',
            'estado' => 'activa',
        ]));

        $log = $this->logsDe($temporada, 'actualizar')->sole();

        $this->assertSame(['nombre' => 'Temporada Original', 'estado' => 'borrador'], $log->valores_anteriores);
        $this->assertSame(['nombre' => 'Temporada Corregida', 'estado' => 'activa'], $log->valores_nuevos);
        $this->assertSame($usuario->id, $log->usuario_id);
        $this->assertSame(self::IP_PRUEBA, $log->ip);
        $this->assertSame('Corrección del nombre y activación', $log->motivo);
        $this->assertNotNull($log->created_at);

        // Si solo cambia updated_at no se registra nada
        $this->travel(5)->minutes();
        $temporada->touch();

        $this->assertSame(1, $this->logsDe($temporada, 'actualizar')->count());
    }

    public function test_eliminar_una_temporada_registra_los_valores_anteriores(): void
    {
        $usuario = $this->crearUsuario('auditor_eliminar@liga.com');
        $this->actingAs($usuario);
        $temporada = Temporada::create($this->datosTemporada('Temporada Duplicada'));
        $id = $temporada->id;

        $this->auditoria->conMotivo('Baja por captura duplicada', fn () => $temporada->delete());

        $log = $this->logsDe($temporada, 'eliminar')->sole();

        $this->assertDatabaseMissing('temporadas', ['id' => $id]);
        $this->assertSame($id, $log->registro_id);
        $this->assertSame('Temporada Duplicada', $log->valores_anteriores['nombre']);
        $this->assertNull($log->valores_nuevos);
        $this->assertSame($usuario->id, $log->usuario_id);
        $this->assertSame(self::IP_PRUEBA, $log->ip);
        $this->assertSame('Baja por captura duplicada', $log->motivo);
        $this->assertNotNull($log->created_at);
    }

    public function test_registrar_un_usuario_no_guarda_la_contrasena_ni_secretos(): void
    {
        $usuario = $this->crearUsuario('auditor_ocultos@liga.com');
        $usuario->forceFill(['two_factor_secret' => 'secreto-2fa', 'remember_token' => 'token-recuerdo'])->save();

        $log = $this->auditoria->registrar(
            'actualizar',
            $usuario,
            ['name' => 'Nombre Anterior', 'password' => 'hash-anterior'],
            $usuario->getAttributes(),
            'Prueba de atributos ocultos'
        );

        foreach (['password', 'password_hash', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'] as $oculto) {
            $this->assertArrayNotHasKey($oculto, $log->valores_nuevos);
            $this->assertArrayNotHasKey($oculto, $log->valores_anteriores);
        }

        $this->assertSame('auditor_ocultos@liga.com', $log->valores_nuevos['email']);
        $this->assertSame(['name' => 'Nombre Anterior'], $log->valores_anteriores);
        $this->assertSame('Prueba de atributos ocultos', $log->motivo);

        // Tampoco aparecen en el JSON guardado en la base
        $crudo = DB::table('auditoria_logs')->where('id', $log->id)->value('valores_nuevos');
        $this->assertStringNotContainsString('password', $crudo);
        $this->assertStringNotContainsString('secreto-2fa', $crudo);
    }

    public function test_asignar_y_retirar_un_rol_queda_registrado_una_sola_vez(): void
    {
        $admin = $this->crearUsuario('auditor_roles@liga.com');
        $usuario = $this->crearUsuario('arbitro_auditado@liga.com');
        $this->actingAs($admin);

        $usuario->assignRole('Árbitro en Campo');
        $usuario->removeRole('Árbitro en Campo');

        $asignado = $this->logsDe($usuario, 'rol_asignado')->sole();
        $this->assertSame(['roles' => ['Árbitro en Campo']], $asignado->valores_nuevos);
        $this->assertNull($asignado->valores_anteriores);
        $this->assertSame($admin->id, $asignado->usuario_id);
        $this->assertSame(self::IP_PRUEBA, $asignado->ip);

        $retirado = $this->logsDe($usuario, 'rol_retirado')->sole();
        $this->assertSame(['roles' => ['Árbitro en Campo']], $retirado->valores_anteriores);
        $this->assertNull($retirado->valores_nuevos);
        $this->assertSame($admin->id, $retirado->usuario_id);
    }

    public function test_asignar_y_retirar_un_permiso_queda_registrado_una_sola_vez(): void
    {
        $this->actingAs($this->crearUsuario('auditor_permisos@liga.com'));
        $rol = Role::create(['name' => 'Rol Auditado de Prueba', 'guard_name' => 'web']);

        $rol->givePermissionTo('temporadas.ver');
        $rol->revokePermissionTo('temporadas.ver');

        $this->assertSame(['permisos' => ['temporadas.ver']], $this->logsDe($rol, 'permiso_asignado')->sole()->valores_nuevos);
        $this->assertSame(['permisos' => ['temporadas.ver']], $this->logsDe($rol, 'permiso_retirado')->sole()->valores_anteriores);
    }

    public function test_un_motivo_vacio_lanza_una_excepcion_y_no_ejecuta_la_operacion(): void
    {
        $ejecutada = false;

        try {
            $this->auditoria->conMotivo('   ', function () use (&$ejecutada) {
                $ejecutada = true;
            });
            $this->fail('Un motivo vacío debió rechazarse.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('El motivo de la operación es obligatorio y no puede estar vacío.', $e->getMessage());
        }

        $this->assertFalse($ejecutada);
    }

    public function test_los_motivos_anidados_se_restauran_al_terminar(): void
    {
        $this->auditoria->conMotivo('Motivo externo', function () {
            $interna = $this->auditoria->conMotivo('Motivo interno', fn () => Temporada::create($this->datosTemporada('Temporada Interna')));
            $externa = Temporada::create($this->datosTemporada('Temporada Externa'));

            $this->assertSame('Motivo interno', $this->logsDe($interna, 'crear')->sole()->motivo);
            $this->assertSame('Motivo externo', $this->logsDe($externa, 'crear')->sole()->motivo);
        });

        // Fuera de conMotivo ya no hay motivo
        $sinMotivo = Temporada::create($this->datosTemporada('Temporada Sin Motivo'));
        $this->assertNull($this->logsDe($sinMotivo, 'crear')->sole()->motivo);
    }

    private function crearUsuario(string $email): User
    {
        return User::create([
            'name' => 'Usuario de Auditoría',
            'email' => $email,
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Administrador General',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosTemporada(string $nombre): array
    {
        $ligaId = DB::table('ligas')->insertGetId([
            'nombre' => 'Liga de Prueba de Auditoría',
            'estado' => 'activa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'liga_id' => $ligaId,
            'nombre' => $nombre,
            'fecha_inicio' => '2027-01-10',
            'fecha_fin' => '2027-06-30',
            'estado' => 'borrador',
        ];
    }

    /**
     * @return Builder<AuditoriaLog>
     */
    private function logsDe(Model $modelo, string $accion): Builder
    {
        return AuditoriaLog::query()
            ->where('tabla_afectada', $modelo->getTable())
            ->where('registro_id', $modelo->getKey())
            ->where('accion', $accion);
    }
}
