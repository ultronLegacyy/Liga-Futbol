<?php

namespace Tests\Feature;

use App\Mail\CorreoComunicado;
use App\Models\AuditoriaLog;
use App\Models\Categoria;
use App\Models\Club;
use App\Models\Comunicado;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\RegistroCorreo;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Queue\SyncQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

// HU-066 (SCRUM-76, RF-15.3): el Secretario manda comunicados por correo a grupos de usuarios
class ComunicadoTest extends TestCase
{
    use DatabaseTransactions;

    private User $secretario;

    private Categoria $libre;

    /** @var array<string, Club> */
    private array $clubes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->secretario = $this->usuario('secretario.comunicados@liga.test', 'Secretario');

        $temporada = Temporada::factory()->activa()->create(['fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31']);
        $this->libre = Categoria::factory()->create(['temporada_id' => $temporada->id, 'nombre' => 'Libre']);
        $veteranos = Categoria::factory()->create(['temporada_id' => $temporada->id, 'nombre' => 'Veteranos']);
        $grupoLibre = Grupo::factory()->create(['categoria_id' => $this->libre->id]);
        $grupoVeteranos = Grupo::factory()->create(['categoria_id' => $veteranos->id]);

        // Uno y Tres juegan en Libre; Dos, en Veteranos
        foreach (['Uno' => $grupoLibre, 'Dos' => $grupoVeteranos, 'Tres' => $grupoLibre] as $nombre => $grupo) {
            $this->clubes[$nombre] = Club::create(['nombre' => 'Club Comunicado '.$nombre]);
            Equipo::create(['club_id' => $this->clubes[$nombre]->id, 'grupo_id' => $grupo->id, 'nombre' => 'Equipo '.$nombre]);
        }

        $this->usuario('delegado.uno@liga.test', 'Delegado del Club', $this->clubes['Uno']);
        $this->usuario('delegado.uno.baja@liga.test', 'Delegado del Club', $this->clubes['Uno'], 'inactivo');
        $this->usuario('delegado.dos@liga.test', 'Delegado del Club', $this->clubes['Dos']);
        $this->usuario('delegado.tres@liga.test', 'Delegado del Club', $this->clubes['Tres']);
        $this->usuario('presidente.uno@liga.test', 'Presidente del Club', $this->clubes['Uno']);
        $this->usuario('arbitro.comunicados@liga.test', 'Árbitro en Campo');
    }

    // Criterio 1: cada grupo recibe solo a sus usuarios, sin cuentas inactivas (decisión 5: roles, categoría y clubes)
    public function test_cada_grupo_recibe_solo_a_sus_usuarios(): void
    {
        $casos = [
            [['roles' => ['Delegado del Club']], ['delegado.dos@liga.test', 'delegado.tres@liga.test', 'delegado.uno@liga.test']],
            [['roles' => ['Delegado del Club'], 'categoria_id' => $this->libre->id], ['delegado.tres@liga.test', 'delegado.uno@liga.test']],
            [['roles' => ['Delegado del Club', 'Presidente del Club'], 'clubes' => [$this->clubes['Uno']->id]], ['delegado.uno@liga.test', 'presidente.uno@liga.test']],
            [['roles' => ['Delegado del Club'], 'categoria_id' => $this->libre->id, 'clubes' => [$this->clubes['Tres']->id, $this->clubes['Dos']->id]], ['delegado.tres@liga.test']],
            [['roles' => ['Árbitro en Campo', 'Presidente del Club']], ['arbitro.comunicados@liga.test', 'presidente.uno@liga.test']],
        ];

        foreach ($casos as [$grupo, $esperados]) {
            Mail::fake();

            $this->actingAs($this->secretario)->post(route('comunicados.store'), $this->comunicado($grupo))
                ->assertRedirect(route('comunicados.create'))
                ->assertSessionHas('status', 'Comunicado enviado: '.count($esperados).(count($esperados) === 1 ? ' destinatario' : ' destinatarios').'. Los correos salen por la cola en los próximos minutos.');

            $recibieron = [];
            Mail::assertQueued(CorreoComunicado::class, function (CorreoComunicado $correo) use (&$recibieron): bool {
                $recibieron[] = $correo->to[0]['address'];

                return $correo->asunto === 'Asamblea extraordinaria' && $correo->mensaje === "Se convoca a asamblea.\nAsistan puntuales.";
            });
            sort($recibieron);
            $this->assertSame($esperados, $recibieron, json_encode($grupo) ?: '');
        }
    }

    // Antes de enviar, el Secretario ve cuántos destinatarios tiene el grupo; la revisión no manda nada
    public function test_la_revision_muestra_cuantos_destinatarios_tiene_el_grupo(): void
    {
        Mail::fake();

        $this->actingAs($this->secretario)->post(route('comunicados.revisar'), $this->comunicado(['roles' => ['Delegado del Club'], 'categoria_id' => $this->libre->id]))
            ->assertOk()
            ->assertSee('El grupo tiene 2 destinatarios')
            ->assertSee('Enviar a 2 destinatarios')
            ->assertSee('value="'.$this->libre->id.'"', false);

        Mail::assertNothingQueued();
        $this->assertSame(0, Comunicado::query()->count());
    }

    // Un grupo vacío se rechaza con mensaje, en la revisión y en el envío
    public function test_un_grupo_vacio_se_rechaza_con_mensaje(): void
    {
        Mail::fake();
        $vacio = $this->comunicado(['roles' => ['Árbitro en Campo'], 'categoria_id' => $this->libre->id]);
        $mensaje = 'El grupo elegido no tiene destinatarios: ninguna cuenta activa con correo cumple esos roles, categoría y clubes.';

        $this->actingAs($this->secretario)->from(route('comunicados.create'))->post(route('comunicados.store'), $vacio)
            ->assertRedirect(route('comunicados.create'))->assertSessionHasErrors(['roles' => $mensaje]);

        // La revisión vuelve al formulario con el mensaje y lo ya escrito
        $this->from(route('comunicados.create'))->followingRedirects()->post(route('comunicados.revisar'), $vacio)
            ->assertOk()->assertSee($mensaje)->assertSee('value="Asamblea extraordinaria"', false);
        Mail::assertNothingQueued();
        $this->assertSame(0, Comunicado::query()->count());
    }

    public function test_los_datos_invalidos_se_rechazan_en_espanol(): void
    {
        $this->actingAs($this->secretario)->post(route('comunicados.revisar'), ['asunto' => '', 'mensaje' => ['x'], 'categoria_id' => 999999])
            ->assertSessionHasErrors([
                'asunto' => 'El asunto del comunicado es obligatorio.',
                'mensaje' => 'El mensaje del comunicado es obligatorio.',
                'roles' => 'Elige al menos un rol destinatario.',
                'categoria_id' => 'La categoría elegida no existe.',
            ]);

        $this->post(route('comunicados.revisar'), $this->comunicado(['roles' => ['Rey'], 'clubes' => [999999]]))
            ->assertSessionHasErrors(['roles.0' => 'Uno de los roles elegidos no existe.', 'clubes.0' => 'Uno de los clubes elegidos no existe.']);
    }

    // Obligatorio 1 de la revisión: un doble clic o «Atrás» y reenviar no manda el comunicado dos veces
    public function test_un_segundo_envio_de_la_misma_revision_se_rechaza(): void
    {
        Mail::fake();
        $datos = $this->comunicado(['roles' => ['Delegado del Club'], 'categoria_id' => $this->libre->id]);
        $mensaje = 'Este comunicado ya se envió. Para mandarlo otra vez, revisa de nuevo los destinatarios.';

        $this->actingAs($this->secretario)->post(route('comunicados.store'), $datos)->assertSessionHasNoErrors();
        $this->from(route('comunicados.create'))->post(route('comunicados.store'), $datos)
            ->assertRedirect(route('comunicados.create'))->assertSessionHasErrors(['token' => $mensaje]);

        $this->assertSame(1, Comunicado::query()->count());
        Mail::assertQueuedCount(2);

        // Una revisión nueva da otro token y sí permite mandarlo de nuevo
        $this->post(route('comunicados.store'), ['token' => (string) Str::uuid()] + $datos)->assertSessionHasNoErrors();
        $this->assertSame(2, Comunicado::query()->count());
    }

    // La revisión pone su token en el formulario de confirmación; sin token no se envía
    public function test_el_envio_exige_el_token_de_la_revision(): void
    {
        Mail::fake();
        $datos = $this->comunicado(['roles' => ['Delegado del Club']]);

        $html = $this->actingAs($this->secretario)->post(route('comunicados.revisar'), $datos)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/name="token" value="[0-9a-f-]{36}"/', (string) $html);
        $this->assertStringNotContainsString($datos['token'], (string) $html);

        $this->post(route('comunicados.store'), array_diff_key($datos, ['token' => true]))
            ->assertSessionHasErrors(['token' => 'Falta la confirmación del envío: revisa los destinatarios antes de enviar.']);
        $this->post(route('comunicados.store'), ['token' => 'no-es-un-token'] + $datos)
            ->assertSessionHasErrors(['token' => 'La confirmación del envío no es válida: revisa los destinatarios otra vez.']);

        Mail::assertNothingQueued();
        $this->assertSame(0, Comunicado::query()->count());
    }

    // Si la cola falla a la mitad, no queda el comunicado ni una parte de los correos, y se puede reenviar
    public function test_si_falla_la_cola_no_queda_un_envio_a_medias(): void
    {
        // Una cola sync que falla al segundo trabajo; hereda de SyncQueue para respetar ShouldQueueAfterCommit
        Queue::extend('caida', fn () => new class () implements ConnectorInterface {
            public function connect(array $config): SyncQueue
            {
                return new class () extends SyncQueue {
                    private int $encolados = 0;

                    protected function executeJob($job, $data = '', $queue = null): int
                    {
                        if (++$this->encolados > 1) {
                            throw new RuntimeException('Cola caída.');
                        }

                        return 0;
                    }
                };
            }
        });
        config(['queue.connections.caida' => ['driver' => 'caida'], 'queue.default' => 'caida']);
        $datos = $this->comunicado(['roles' => ['Delegado del Club']]);

        $this->withoutExceptionHandling()->actingAs($this->secretario);
        $this->assertThrows(fn () => $this->post(route('comunicados.store'), $datos), RuntimeException::class, 'Cola caída.');
        $this->assertSame(0, Comunicado::query()->count());
        $this->assertSame(0, AuditoriaLog::query()->where('tabla_afectada', 'comunicados')->count());

        config(['queue.default' => 'sync']);
        Mail::fake();
        $this->post(route('comunicados.store'), $datos)->assertRedirect(route('comunicados.create'));
        Mail::assertQueuedCount(3);
    }

    // Recomendado 2: authorize() también niega, en español, aunque la ruta llegue sin el middleware del permiso
    public function test_la_form_request_niega_sin_el_middleware_del_permiso(): void
    {
        $this->withoutMiddleware(PermissionMiddleware::class)
            ->actingAs($this->usuario('delegado.sin.permiso@liga.test', 'Delegado del Club'))
            ->post(route('comunicados.revisar'), $this->comunicado(['roles' => ['Delegado del Club']]))
            ->assertForbidden();
    }

    // Recomendado 3 y 4: una categoría en arreglo se rechaza (no amplía el grupo) y un club repetido avisa una sola vez
    public function test_una_categoria_en_arreglo_se_rechaza_y_el_club_repetido_avisa_una_vez(): void
    {
        $this->actingAs($this->secretario)->post(route('comunicados.revisar'), $this->comunicado(['roles' => ['Delegado del Club'], 'categoria_id' => [$this->libre->id]]))
            ->assertSessionHasErrors(['categoria_id' => 'La categoría elegida no es válida.']);

        $html = (string) $this->from(route('comunicados.create'))->followingRedirects()
            ->post(route('comunicados.revisar'), $this->comunicado(['roles' => ['Delegado del Club'], 'categoria_id' => [$this->libre->id], 'clubes' => [$this->clubes['Uno']->id, $this->clubes['Uno']->id]]))
            ->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'Un club está repetido.'));
        $this->assertStringContainsString('La categoría elegida no es válida.', $html);
    }

    // Envío en lotes: un grupo más grande que un lote (200) llega completo
    public function test_un_grupo_mayor_que_un_lote_se_envia_completo(): void
    {
        Mail::fake();
        $clave = Hash::make('Clave-De-Prueba-2026');
        $rol = Role::findByName('Delegado del Club');
        $usuarios = array_map(fn (int $numero): array => [
            'name' => 'Delegado lote '.$numero, 'email' => 'lote'.$numero.'@liga.test', 'password' => $clave, 'estatus' => 'activo',
            'club_id' => $this->clubes['Dos']->id, 'requires_password_change' => false, 'created_at' => now(), 'updated_at' => now(),
        ], range(1, 205));
        DB::table('usuarios')->insert($usuarios);
        $ids = User::query()->where('email', 'like', 'lote%@liga.test')->pluck('id');
        DB::table('model_has_roles')->insert($ids->map(fn (int $id): array => ['role_id' => $rol->id, 'model_type' => User::class, 'model_id' => $id])->all());

        $this->actingAs($this->secretario)->post(route('comunicados.store'), $this->comunicado(['roles' => ['Delegado del Club'], 'clubes' => [$this->clubes['Dos']->id]]))
            ->assertSessionHas('status', 'Comunicado enviado: 206 destinatarios. Los correos salen por la cola en los próximos minutos.');

        Mail::assertQueuedCount(206);
        Mail::assertQueued(CorreoComunicado::class, fn (CorreoComunicado $correo): bool => $correo->hasTo('lote205@liga.test'));
    }

    // HU-067: cada envío queda en el registro de correos; el comunicado queda guardado y auditado
    public function test_cada_envio_queda_en_el_registro_y_el_comunicado_se_guarda(): void
    {
        $this->actingAs($this->secretario)->post(route('comunicados.store'), $this->comunicado(['roles' => ['Delegado del Club'], 'clubes' => [$this->clubes['Uno']->id, $this->clubes['Dos']->id]]))
            ->assertRedirect(route('comunicados.create'));

        $comunicado = Comunicado::query()->sole();
        $this->assertSame(
            [$this->secretario->id, 'Asamblea extraordinaria', 2, ['roles' => ['Delegado del Club'], 'categoria_id' => null, 'clubes' => [$this->clubes['Uno']->id, $this->clubes['Dos']->id]]],
            [$comunicado->enviado_por_usuario_id, $comunicado->asunto, $comunicado->total_destinatarios, $comunicado->filtros]
        );
        $this->assertTrue(AuditoriaLog::query()->where('tabla_afectada', 'comunicados')->where('registro_id', $comunicado->id)->where('motivo', 'Envío de comunicado por correo')->exists());

        $registros = RegistroCorreo::query()->where('tipo', 'comunicado')->orderBy('id')->get();
        $this->assertSame([['delegado.uno@liga.test'], ['delegado.dos@liga.test']], $registros->pluck('destinatarios')->all());
        $this->assertSame([RegistroCorreo::ENVIADO], $registros->pluck('estado')->unique()->values()->all());
        $this->assertSame('Comunicado', $registros->first()?->nombreTipo());
    }

    // El Secretario y el Administrador General abren la pantalla y la ven en el menú
    public function test_el_secretario_y_el_administrador_general_tienen_el_permiso(): void
    {
        $this->assertSame(['Administrador General', 'Secretario'], Role::query()->whereHas('permissions', fn ($consulta) => $consulta->where('name', 'comunicados.enviar'))->orderBy('name')->pluck('name')->all());

        $this->actingAs($this->secretario)->get(route('comunicados.create'))
            ->assertOk()->assertSee('Comunicados por correo')->assertSee(route('comunicados.create'))->assertSee('Club Comunicado Tres')->assertSee('Libre');
        $this->actingAs($this->usuario('admin.comunicados@liga.test', 'Administrador General'))->get(route('comunicados.create'))->assertOk();
    }

    // Los demás roles reciben el 403 en español y no pueden enviar
    #[DataProvider('rolesSinPermiso')]
    public function test_los_demas_roles_reciben_403_en_espanol(string $rol): void
    {
        Mail::fake();
        $this->actingAs($this->usuario('sin.permiso@liga.test', $rol));

        $this->get(route('comunicados.create'))->assertForbidden()->assertSee('No posee los permisos requeridos para acceder a este recurso administrativo.');
        $this->post(route('comunicados.revisar'), $this->comunicado(['roles' => ['Delegado del Club']]))->assertForbidden();
        $this->post(route('comunicados.store'), $this->comunicado(['roles' => ['Delegado del Club']]))->assertForbidden();

        Mail::assertNothingQueued();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rolesSinPermiso(): array
    {
        $roles = array_values(array_diff(RoleAndPermissionSeeder::ROLES, ['Administrador General', 'Secretario']));

        return array_combine($roles, array_map(fn (string $rol): array => [$rol], $roles));
    }

    /**
     * @param  array<string, mixed>  $grupo
     * @return array<string, mixed>
     */
    private function comunicado(array $grupo): array
    {
        // El token es el que da la revisión; cada llamada trae uno nuevo, como una revisión nueva
        return ['asunto' => 'Asamblea extraordinaria', 'mensaje' => "Se convoca a asamblea.\nAsistan puntuales.", 'token' => (string) Str::uuid()] + $grupo;
    }

    private function usuario(string $correo, string $rol, ?Club $club = null, string $estatus = 'activo'): User
    {
        $usuario = User::query()->create([
            'name' => 'Usuario '.$correo,
            'email' => $correo,
            'password' => Hash::make('Clave-De-Prueba-2026'),
            'estatus' => $estatus,
            'requires_password_change' => false,
        ]);
        $usuario->forceFill(['club_id' => $club?->id])->save();
        $usuario->assignRole($rol);

        return $usuario;
    }
}
