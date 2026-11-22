<?php

namespace Tests\Feature;

use App\Mail\AvisoProgramacion;
use App\Models\RegistroCorreo;
use App\Models\User;
use App\Services\BruteForceProtectionService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;
use Throwable;

// HU-067 (SCRUM-77, RF-15.4): cada correo del sistema queda registrado con fecha, destinatarios y estado
class RegistroCorreoTest extends TestCase
{
    use DatabaseTransactions;

    private const CORREO = 'registro.correo@liga.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    // Criterio 1: la recuperación de contraseña queda enviada, sin el enlace ni el token
    public function test_la_recuperacion_de_contrasena_queda_enviada_sin_el_enlace(): void
    {
        $this->usuario(self::CORREO, 'Delegado del Club');
        $this->freezeSecond();

        $this->post(route('password.email'), ['email' => self::CORREO])->assertRedirect();

        $registro = $this->ultimo();
        $this->assertSame(['recuperacion', 'Recupera tu contraseña del SGLD', [self::CORREO], RegistroCorreo::ENVIADO], [$registro->tipo, $registro->asunto, $registro->destinatarios, $registro->estado]);
        $this->assertSame(now()->format('Y-m-d H:i:s'), $registro->enviado_en?->format('Y-m-d H:i:s'));

        $fila = (string) json_encode(RegistroCorreo::query()->whereKey($registro->id)->toBase()->first());
        $this->assertStringNotContainsString('reset-password', $fila);
        $this->assertStringNotContainsString('token', $fila);
        $this->assertSame(1, count(Mail::mailer('array')->getSymfonyTransport()->messages()));
    }

    public function test_el_aviso_de_bloqueo_y_el_correo_de_prueba_quedan_enviados(): void
    {
        $usuario = $this->usuario(self::CORREO, 'Delegado del Club');

        app(BruteForceProtectionService::class)->sendLockoutNotification($usuario, '203.0.113.7');
        $this->assertSame(['bloqueo', [self::CORREO], RegistroCorreo::ENVIADO], [$this->ultimo()->tipo, $this->ultimo()->destinatarios, $this->ultimo()->estado]);

        $this->artisan('sgld:probar-correo', ['destinatario' => 'pruebas@liga.test'])->assertSuccessful();
        $this->assertSame(['prueba', ['pruebas@liga.test'], RegistroCorreo::ENVIADO], [$this->ultimo()->tipo, $this->ultimo()->destinatarios, $this->ultimo()->estado]);
    }

    // Un fallo de SMTP queda «fallido» y no tumba la operación que lo originó
    public function test_un_fallo_de_smtp_queda_fallido_sin_tumbar_la_operacion(): void
    {
        $usuario = $this->usuario(self::CORREO, 'Delegado del Club');
        $this->smtpCaido();

        $this->post(route('password.email'), ['email' => self::CORREO])
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->assertSame(['recuperacion', RegistroCorreo::FALLIDO, 'TransportException'], [$this->ultimo()->tipo, $this->ultimo()->estado, $this->ultimo()->error]);

        app(BruteForceProtectionService::class)->sendLockoutNotification($usuario, '203.0.113.7');
        $this->assertSame(['bloqueo', RegistroCorreo::FALLIDO], [$this->ultimo()->tipo, $this->ultimo()->estado]);

        $this->artisan('sgld:probar-correo', ['destinatario' => 'pruebas@liga.test'])->assertFailed();
        $this->assertSame(['prueba', RegistroCorreo::FALLIDO], [$this->ultimo()->tipo, $this->ultimo()->estado]);

        // El detalle del error, que puede traer el usuario SMTP, no se guarda
        $this->assertSame(0, RegistroCorreo::query()->where('error', 'like', '%127.0.0.1%')->count());
    }

    // El aviso por la cola que agota sus intentos queda «fallido»
    public function test_un_aviso_por_la_cola_que_falla_queda_fallido(): void
    {
        $this->smtpCaido();

        try {
            Mail::to('delegado@liga.test')->queue(new AvisoProgramacion('Cambio de programación', 'Datos', 'prueba:fallo'));
            $this->fail('Se esperaba el fallo del transporte.');
        } catch (Throwable $error) {
            $this->assertInstanceOf(TransportException::class, $error);
        }

        $registro = RegistroCorreo::query()->where('clave', 'prueba:fallo')->sole();
        $this->assertSame(['aviso_programacion', ['delegado@liga.test'], RegistroCorreo::FALLIDO], [$registro->tipo, $registro->destinatarios, $registro->estado]);
    }

    // Un aviso con la misma clave no sale dos veces (reenvío o reintento de la cola)
    public function test_un_aviso_ya_enviado_no_se_manda_otra_vez(): void
    {
        foreach ([1, 2] as $intento) {
            Mail::to('delegado@liga.test')->send(new AvisoProgramacion('Programación publicada', 'Datos '.$intento, 'publicacion:1:20261120:7'));
        }

        $this->assertCount(1, Mail::mailer('array')->getSymfonyTransport()->messages());
        $this->assertSame(RegistroCorreo::ENVIADO, RegistroCorreo::query()->where('clave', 'publicacion:1:20261120:7')->sole()->estado);
    }

    // Dos procesos con el mismo aviso: si otro lo está enviando no sale; un «pendiente» abandonado o un «fallido» sí se reintentan
    public function test_un_aviso_en_curso_en_otro_proceso_no_sale_dos_veces(): void
    {
        RegistroCorreo::query()->create(['tipo' => 'aviso_programacion', 'asunto' => 'Programación publicada', 'destinatarios' => ['delegado@liga.test'], 'estado' => RegistroCorreo::PENDIENTE, 'clave' => 'publicacion:9:1:7']);
        RegistroCorreo::query()->create(['tipo' => 'aviso_programacion', 'asunto' => 'Cambio de programación', 'destinatarios' => ['delegado@liga.test'], 'estado' => RegistroCorreo::FALLIDO, 'error' => 'TransportException', 'clave' => 'reprogramacion:3:7']);
        $enviar = fn (string $clave) => Mail::to('delegado@liga.test')->send(new AvisoProgramacion('Aviso', 'Datos', $clave));
        $enviados = fn (): int => count(Mail::mailer('array')->getSymfonyTransport()->messages());

        $enviar('publicacion:9:1:7');
        $this->assertSame(0, $enviados());

        $this->travel(31)->seconds();
        $enviar('publicacion:9:1:7');
        $enviar('reprogramacion:3:7');

        $this->assertSame(2, $enviados());
        $this->assertSame([[RegistroCorreo::ENVIADO, null]], RegistroCorreo::query()->whereIn('clave', ['publicacion:9:1:7', 'reprogramacion:3:7'])->get()->map(fn (RegistroCorreo $registro): array => [$registro->estado, $registro->error])->unique()->values()->all());
    }

    // Un fallo antes de llegar al transporte también queda registrado
    public function test_un_fallo_antes_del_envio_tambien_queda_registrado(): void
    {
        $aviso = new AvisoProgramacion('Cambio de programación', 'Datos', 'prueba:previo');
        $aviso->to('delegado@liga.test');

        $aviso->failed(new RuntimeException('Falla simulada al preparar el correo.'));

        $this->assertSame([RegistroCorreo::FALLIDO, 'RuntimeException', ['delegado@liga.test']], [$this->ultimo()->estado, $this->ultimo()->error, $this->ultimo()->destinatarios]);
    }

    // Pantalla del registro: el Administrador General la consulta y filtra por estado
    public function test_el_administrador_general_consulta_el_registro(): void
    {
        RegistroCorreo::query()->create(['tipo' => 'aviso_programacion', 'asunto' => 'Programación publicada: Jornada 1', 'destinatarios' => ['uno@liga.test'], 'estado' => RegistroCorreo::ENVIADO, 'clave' => 'a', 'enviado_en' => now()]);
        RegistroCorreo::query()->create(['tipo' => 'bloqueo', 'asunto' => 'Aviso de seguridad', 'destinatarios' => ['dos@liga.test'], 'estado' => RegistroCorreo::FALLIDO, 'error' => 'TransportException', 'clave' => 'b']);

        $this->actingAs($this->usuario('admin.correos@liga.test', 'Administrador General'))
            ->get(route('correos.index'))
            ->assertOk()
            ->assertSee('Registro de correos')
            ->assertSee('Programación publicada: Jornada 1')
            ->assertSee('uno@liga.test')
            ->assertSee('Aviso de bloqueo')
            ->assertSee('TransportException');

        $this->get(route('correos.index', ['estado' => 'fallido']))
            ->assertOk()
            ->assertSee('dos@liga.test')
            ->assertDontSee('uno@liga.test');
    }

    // Los demás roles reciben el 403 en español y no ven el menú
    #[DataProvider('rolesSinPermiso')]
    public function test_los_demas_roles_reciben_403_en_espanol(string $rol): void
    {
        $this->actingAs($this->usuario('sin.permiso@liga.test', $rol))
            ->get(route('correos.index'))
            ->assertForbidden()
            ->assertSee('No posee los permisos requeridos para acceder a este recurso administrativo.');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rolesSinPermiso(): array
    {
        $roles = array_values(array_diff(RoleAndPermissionSeeder::ROLES, ['Administrador General']));

        return array_combine($roles, array_map(fn (string $rol): array => [$rol], $roles));
    }

    public function test_solo_el_administrador_general_tiene_correos_ver(): void
    {
        $this->assertSame(['Administrador General'], \Spatie\Permission\Models\Role::query()->whereHas('permissions', fn ($consulta) => $consulta->where('name', 'correos.ver'))->pluck('name')->all());
        $this->actingAs($this->usuario('admin.menu@liga.test', 'Administrador General'))->get(route('admin.dashboard'))->assertSee(route('correos.index'));
        $this->actingAs($this->usuario('secretario.menu@liga.test', 'Secretario'))->get(route('asambleas.index'))->assertOk()->assertSee('Cerrar Sesión')->assertDontSee(route('correos.index'));
    }

    private function ultimo(): RegistroCorreo
    {
        return RegistroCorreo::query()->latest('id')->firstOrFail();
    }

    // Transporte que simula un servidor de correo caído, como en RecuperacionContrasenaTest
    private function smtpCaido(): void
    {
        Mail::extend('caido', fn () => new class () extends AbstractTransport {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Connection could not be established with host "127.0.0.1:1025".');
            }

            public function __toString(): string
            {
                return 'caido://';
            }
        });
        config(['mail.mailers.caido' => ['transport' => 'caido'], 'mail.default' => 'caido']);
    }

    private function usuario(string $correo, string $rol): User
    {
        $usuario = User::query()->create([
            'name' => 'Usuario '.$rol,
            'email' => $correo,
            'password' => Hash::make('Clave-De-Prueba-2026'),
            'estatus' => 'activo',
            'requires_password_change' => false,
        ]);
        $usuario->assignRole($rol);

        return $usuario;
    }
}
