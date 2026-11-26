<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use PHPUnit\Framework\Attributes\DataProvider;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

// HU-082 (SCRUM-112): segundo factor (TOTP de Fortify) obligatorio para las cuentas con privilegios (decisión 2 de Daniel)
class SegundoFactorTest extends TestCase
{
    use DatabaseTransactions;

    // Estas pruebas corren con ExigirSegundoFactor (ver TestCase)
    protected bool $exigeSegundoFactor = true;

    private const CLAVE = 'Clave-De-Prueba-2026';

    private int $consecutivo = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    // Criterio 1: cada rol obligado, al iniciar sesión, va a activar el segundo factor
    #[DataProvider('rolesObligados')]
    public function test_cada_rol_obligado_va_a_activar_el_segundo_factor(string $rol): void
    {
        $usuario = $this->usuario($rol);

        $this->post(route('login'), ['email' => $usuario->email, 'password' => self::CLAVE]);
        $this->assertAuthenticatedAs($usuario);

        $this->get($usuario->rutaInicio())->assertRedirect(route('segundo-factor.show'));
        $this->get(route('segundo-factor.show'))->assertOk()
            ->assertSee('Tu cuenta tiene privilegios: para usar el sistema debes activar el segundo factor.')
            ->assertDontSee('Volver al sistema');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rolesObligados(): array
    {
        return array_combine(User::ROLES_CON_SEGUNDO_FACTOR, array_map(fn (string $rol): array => [$rol], User::ROLES_CON_SEGUNDO_FACTOR));
    }

    // Criterio 2: no se salta escribiendo otra URL ni con una petición POST directa, tampoco a las rutas de Fortify
    public function test_no_se_puede_saltar_la_activacion_por_url_ni_por_post(): void
    {
        $this->actingAs($this->usuario('Administrador General'));
        $temporadas = Temporada::query()->count();

        $this->get(route('admin.usuarios.index'))->assertRedirect(route('segundo-factor.show'));
        $this->get(route('correos.index'))->assertRedirect(route('segundo-factor.show'));
        $this->post(route('temporadas.store'), ['nombre' => 'Temporada saltada', 'fecha_inicio' => '2027-01-01', 'fecha_fin' => '2027-06-30'])
            ->assertRedirect(route('segundo-factor.show'));
        $this->put(route('user-password.update'), ['current_password' => self::CLAVE, 'password' => 'Otra-Clave-Larga-2026', 'password_confirmation' => 'Otra-Clave-Larga-2026'])
            ->assertRedirect(route('segundo-factor.show'));
        $this->getJson(route('temporadas.index'))->assertForbidden()
            ->assertJson(['message' => 'Debes activar el segundo factor de autenticación antes de continuar.']);

        $this->assertSame($temporadas, Temporada::query()->count());
    }

    // Un rol no obligado entra sin activarlo, y puede activarlo si quiere
    public function test_un_rol_no_obligado_entra_sin_activarlo(): void
    {
        $capturista = $this->usuario('Capturista');

        $this->post(route('login'), ['email' => $capturista->email, 'password' => self::CLAVE]);

        $this->get($capturista->rutaInicio())->assertOk();
        $this->get(route('segundo-factor.show'))->assertOk()->assertSee('Puedes proteger tu cuenta');
    }

    // Activación con el QR de Fortify: un código incorrecto se rechaza en español; el correcto lo activa y da los códigos
    public function test_activa_el_segundo_factor_con_un_codigo_correcto(): void
    {
        $tesorero = $this->usuario('Tesorero');
        $this->actingAs($tesorero)->withSession(['auth.password_confirmed_at' => time()]);

        $this->get(route('segundo-factor.show'))->assertOk()->assertSee('Activar el segundo factor');
        $this->post(route('two-factor.enable'))->assertRedirect();

        $tesorero->refresh();
        $this->get(route('segundo-factor.show'))->assertOk()
            ->assertSee('<svg', false)
            ->assertSee(decrypt((string) $tesorero->two_factor_secret));

        $this->from(route('segundo-factor.show'))->post(route('two-factor.confirm'), ['code' => '000000'])
            ->assertRedirect(route('segundo-factor.show'))
            ->assertSessionHasErrorsIn('confirmTwoFactorAuthentication', ['code' => 'El código del segundo factor no es válido. Revisa la hora de tu celular y escribe el código que muestra ahora tu app de autenticación.']);
        $this->assertNull($tesorero->refresh()->two_factor_confirmed_at);

        $this->from(route('segundo-factor.show'))->post(route('two-factor.confirm'), ['code' => $this->codigo($tesorero)])
            ->assertRedirect(route('segundo-factor.show'));
        $this->assertNotNull($tesorero->refresh()->two_factor_confirmed_at);

        $pantalla = $this->get(route('segundo-factor.show'))->assertOk()->assertSee('Listo: el segundo factor quedó activado.');
        foreach ($tesorero->recoveryCodes() as $codigo) {
            $pantalla->assertSee($codigo);
        }

        $this->get($tesorero->rutaInicio())->assertOk();
    }

    // El reto al iniciar sesión: el código incorrecto no entra, el correcto sí y va a la página de inicio de su rol
    public function test_el_reto_acepta_el_codigo_correcto_y_rechaza_el_incorrecto(): void
    {
        $programador = $this->conSegundoFactor($this->usuario('Programador de Partidos'));

        $this->post(route('login'), ['email' => $programador->email, 'password' => self::CLAVE])->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
        $this->get(route('two-factor.login'))->assertOk()->assertSee('Código del segundo factor');

        $this->from(route('two-factor.login'))->post(route('two-factor.login.store'), ['code' => '000000'])
            ->assertRedirect(route('two-factor.login'))
            ->assertSessionHasErrors(['code' => 'El código del segundo factor no es válido. Revisa la hora de tu celular y escribe el código que muestra ahora tu app de autenticación.']);
        $this->assertGuest();

        $this->post(route('two-factor.login.store'), ['code' => $this->codigo($programador)])->assertRedirect($programador->rutaInicio());
        $this->assertAuthenticatedAs($programador);
        $this->get($programador->rutaInicio())->assertOk();
    }

    // Criterio 4: cada código de recuperación sirve una sola vez
    public function test_un_codigo_de_recuperacion_sirve_una_sola_vez(): void
    {
        $secretario = $this->conSegundoFactor($this->usuario('Secretario'));
        $codigo = $secretario->recoveryCodes()[0];

        $this->post(route('login'), ['email' => $secretario->email, 'password' => self::CLAVE]);
        $this->post(route('two-factor.login.store'), ['recovery_code' => $codigo])->assertRedirect($secretario->rutaInicio());
        $this->assertAuthenticatedAs($secretario);
        $this->assertNotContains($codigo, $secretario->refresh()->recoveryCodes());

        $this->post(route('logout'));
        $this->post(route('login'), ['email' => $secretario->email, 'password' => self::CLAVE]);
        $this->from(route('two-factor.login'))->post(route('two-factor.login.store'), ['recovery_code' => $codigo])
            ->assertSessionHasErrors(['recovery_code' => 'El código de recuperación no es válido o ya se usó. Cada código sirve una sola vez.']);
        $this->assertGuest();
    }

    // Seguridad: el reto tiene límite de intentos, con el mensaje en español
    public function test_el_reto_limita_los_intentos_en_espanol(): void
    {
        $tribunal = $this->conSegundoFactor($this->usuario('Tribunal de Penas'));
        $this->post(route('login'), ['email' => $tribunal->email, 'password' => self::CLAVE]);

        foreach (range(1, 5) as $intento) {
            $this->post(route('two-factor.login.store'), ['code' => '000000'])->assertRedirect();
        }

        $this->post(route('two-factor.login.store'), ['code' => $this->codigo($tribunal)])
            ->assertStatus(429)
            ->assertSee('Demasiados intentos con el código del segundo factor. Espera un minuto e inténtalo de nuevo.');
        $this->assertGuest();
    }

    // Criterio 4: el Administrador General reinicia el segundo factor con motivo y su contraseña; queda auditado sin el secreto
    public function test_el_administrador_reinicia_el_segundo_factor_con_motivo_y_auditoria(): void
    {
        $admin = $this->conSegundoFactor($this->usuario('Administrador General'));
        $tesorero = $this->conSegundoFactor($this->usuario('Tesorero'));
        $secreto = decrypt((string) $tesorero->two_factor_secret);
        $codigos = $tesorero->recoveryCodes();

        $this->actingAs($admin)->from(route('admin.usuarios.edit', $tesorero))
            ->post(route('admin.usuarios.segundo-factor.reiniciar', $tesorero), ['motivo' => 'Perdió su celular', 'password' => 'Incorrecta-2026'])
            ->assertSessionHasErrorsIn('segundoFactor', ['password' => 'La contraseña no es correcta.']);
        $this->post(route('admin.usuarios.segundo-factor.reiniciar', $tesorero), ['motivo' => '', 'password' => self::CLAVE])
            ->assertSessionHasErrorsIn('segundoFactor', ['motivo' => 'Escribe el motivo del reinicio del segundo factor.']);
        $this->assertNotNull($tesorero->refresh()->two_factor_confirmed_at);

        $this->post(route('admin.usuarios.segundo-factor.reiniciar', $tesorero), ['motivo' => 'Perdió su celular y sus códigos', 'password' => self::CLAVE])
            ->assertRedirect(route('admin.usuarios.edit', $tesorero))
            ->assertSessionHas('success', 'Se reinició el segundo factor de la cuenta. Deberá activarlo de nuevo al entrar.');

        $tesorero->refresh();
        $this->assertSame([null, null, null], [$tesorero->two_factor_secret, $tesorero->two_factor_recovery_codes, $tesorero->two_factor_confirmed_at]);

        $bitacora = AuditoriaLog::query()->where('tabla_afectada', 'usuarios')->where('registro_id', $tesorero->id)->where('motivo', 'Perdió su celular y sus códigos')->sole();
        $fila = (string) json_encode($bitacora->getAttributes());
        $this->assertStringNotContainsString($secreto, $fila);
        foreach ($codigos as $codigo) {
            $this->assertStringNotContainsString($codigo, $fila);
        }

        // En su siguiente petición vuelve a la activación
        $this->actingAs($tesorero)->get($tesorero->rutaInicio())->assertRedirect(route('segundo-factor.show'));
    }

    // Criterio 4: los demás roles reciben 403 en español
    #[DataProvider('rolesSinReinicio')]
    public function test_los_demas_roles_no_pueden_reiniciar_el_segundo_factor(string $rol): void
    {
        $objetivo = $this->conSegundoFactor($this->usuario('Tesorero'));
        $usuario = $this->conSegundoFactor($this->usuario($rol));

        $this->actingAs($usuario)->post(route('admin.usuarios.segundo-factor.reiniciar', $objetivo), ['motivo' => 'Intento sin permiso', 'password' => self::CLAVE])
            ->assertForbidden()
            ->assertSee('No posee los permisos requeridos');
        $this->assertNotNull($objetivo->refresh()->two_factor_confirmed_at);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rolesSinReinicio(): array
    {
        $roles = array_values(array_diff(RoleAndPermissionSeeder::ROLES, ['Administrador General']));

        return array_combine($roles, array_map(fn (string $rol): array => [$rol], $roles));
    }

    // Criterio 5: el Administrador no reinicia su propio segundo factor
    public function test_el_administrador_no_reinicia_su_propio_segundo_factor(): void
    {
        $admin = $this->conSegundoFactor($this->usuario('Administrador General'));

        $this->actingAs($admin)->post(route('admin.usuarios.segundo-factor.reiniciar', $admin), ['motivo' => 'Quiero reiniciarlo', 'password' => self::CLAVE])
            ->assertForbidden()
            ->assertSee('No es posible reiniciar el segundo factor de la propia cuenta.');
        $this->assertNotNull($admin->refresh()->two_factor_confirmed_at);
        $this->get(route('admin.usuarios.edit', $admin))->assertOk()->assertSee('No puedes reiniciar el segundo factor de tu propia cuenta.');
    }

    // Criterio 5: el último Administrador General sin segundo factor no queda fuera: lo activa y sigue operando
    public function test_el_ultimo_administrador_activa_su_segundo_factor_y_opera(): void
    {
        $admin = $this->usuario('Administrador General');
        $this->assertSame(1, User::role('Administrador General')->where('estatus', 'activo')->count());

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
        $this->get(route('admin.dashboard'))->assertRedirect(route('segundo-factor.show'));
        $this->post(route('two-factor.enable'))->assertRedirect();
        $this->post(route('two-factor.confirm'), ['code' => $this->codigo($admin->refresh())])->assertRedirect();

        $this->get(route('admin.dashboard'))->assertOk();
    }

    // Una cuenta obligada no puede quitarse el segundo factor; una no obligada sí
    public function test_solo_una_cuenta_no_obligada_puede_desactivarlo(): void
    {
        $secretario = $this->conSegundoFactor($this->usuario('Secretario'));
        $this->actingAs($secretario)->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('two-factor.disable'))->assertForbidden();
        $this->assertNotNull($secretario->refresh()->two_factor_confirmed_at);
        $this->get(route('segundo-factor.show'))->assertDontSee('Desactivar el segundo factor');

        $capturista = $this->conSegundoFactor($this->usuario('Capturista'));
        $this->actingAs($capturista)->withSession(['auth.password_confirmed_at' => time()]);
        $this->get(route('segundo-factor.show'))->assertSee('Desactivar el segundo factor');
        $this->delete(route('two-factor.disable'))->assertRedirect();
        $this->assertNull($capturista->refresh()->two_factor_secret);
    }

    // Decisión de Daniel (25/11): sin passkeys, para que nadie entre sin el TOTP
    public function test_las_passkeys_estan_desactivadas(): void
    {
        $this->assertFalse(Route::has('passkey.store'));
        $this->assertFalse(Route::has('passkey.login'));
    }

    private function codigo(User $usuario): string
    {
        return app(Google2FA::class)->getCurrentOtp(decrypt((string) $usuario->two_factor_secret));
    }

    // Segundo factor activado y confirmado, con sus códigos de recuperación, como lo deja Fortify
    private function conSegundoFactor(User $usuario): User
    {
        app(EnableTwoFactorAuthentication::class)($usuario);
        $usuario->forceFill(['two_factor_confirmed_at' => now()])->save();

        return $usuario->refresh();
    }

    private function usuario(string $rol): User
    {
        $this->consecutivo++;
        $usuario = User::query()->create([
            'name' => 'Usuario '.$rol,
            'email' => 'segundo.factor'.$this->consecutivo.'@liga.test',
            'password' => Hash::make(self::CLAVE),
            'estatus' => 'activo',
            'requires_password_change' => false,
        ]);
        $usuario->assignRole($rol);

        return $usuario;
    }
}
