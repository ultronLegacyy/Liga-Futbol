<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
use App\Models\Cancha;
use App\Models\User;
use App\Services\AuditService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

// HU-023: catálogo de canchas y sedes con sus características y su capacidad (RF-05.1)
class CanchaTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = $this->crearUsuario('admin_canchas@liga.com', 'Administrador General');
    }

    // CA1: la cancha queda registrada con sus características y su capacidad
    public function test_el_administrador_registra_una_cancha_con_su_capacidad(): void
    {
        $respuesta = $this->actingAs($this->admin)->post(route('canchas.store'), [
            'nombre' => 'Cancha 12 U.D.C.',
            'direccion_ubicacion' => 'Unidad Deportiva Ejército de la Revolución, Morelia',
            'capacidad_partidos_consecutivos' => 3,
        ]);

        $respuesta->assertRedirect();
        $respuesta->assertSessionHasNoErrors();
        $respuesta->assertSessionHas('success', 'Cancha registrada correctamente.');

        $this->assertDatabaseHas('canchas', [
            'nombre' => 'Cancha 12 U.D.C.',
            'direccion_ubicacion' => 'Unidad Deportiva Ejército de la Revolución, Morelia',
            'capacidad_partidos_consecutivos' => 3,
            'estado' => 'Disponible',
        ]);
    }

    // CA1: la dirección o ubicación es opcional
    public function test_la_cancha_se_registra_sin_direccion(): void
    {
        $this->actingAs($this->admin)->post(route('canchas.store'), [
            'nombre' => 'Campo particular San Juan',
            'capacidad_partidos_consecutivos' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('canchas', [
            'nombre' => 'Campo particular San Juan',
            'direccion_ubicacion' => null,
        ]);
    }

    // CA2: los cambios de características y capacidad se guardan y se ven al consultarla
    public function test_los_cambios_de_la_cancha_se_guardan_y_se_ven_al_consultarla(): void
    {
        $cancha = Cancha::factory()->create([
            'nombre' => 'Cancha 5',
            'capacidad_partidos_consecutivos' => 2,
        ]);

        $respuesta = $this->actingAs($this->admin)->put(route('canchas.update', $cancha), [
            'nombre' => 'Cancha 5 (empastada)',
            'direccion_ubicacion' => 'Campo 5, U.D.C.',
            'capacidad_partidos_consecutivos' => 4,
        ]);

        $respuesta->assertRedirect();
        $respuesta->assertSessionHasNoErrors();
        $respuesta->assertSessionHas('success', 'Cancha actualizada correctamente.');

        $this->actingAs($this->admin)->getJson(route('canchas.show', $cancha))
            ->assertOk()
            ->assertJson([
                'id' => $cancha->id,
                'nombre' => 'Cancha 5 (empastada)',
                'direccion_ubicacion' => 'Campo 5, U.D.C.',
                'capacidad_partidos_consecutivos' => 4,
                'estado' => 'Disponible',
            ]);
    }

    // CA2: el catálogo lista las canchas registradas
    public function test_el_catalogo_lista_las_canchas_registradas(): void
    {
        $cancha = Cancha::factory()->create(['nombre' => 'Cancha del catálogo']);

        $this->actingAs($this->admin)->getJson(route('canchas.index'))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $cancha->id,
                'nombre' => 'Cancha del catálogo',
            ]);
    }

    // CA3: sin nombre no se guarda y el mensaje está en español
    public function test_sin_nombre_no_se_guarda_y_el_mensaje_esta_en_espanol(): void
    {
        $respuesta = $this->actingAs($this->admin)->post(route('canchas.store'), [
            'nombre' => '',
            'capacidad_partidos_consecutivos' => 2,
        ]);

        $respuesta->assertSessionHasErrors(['nombre' => 'El nombre de la cancha es obligatorio.']);
        $this->assertSame(0, Cancha::count());
    }

    // CA3: una capacidad que no es un número entero válido no se guarda
    public function test_una_capacidad_invalida_no_se_guarda_y_el_mensaje_esta_en_espanol(): void
    {
        $casos = [
            '' => 'La capacidad de partidos consecutivos es obligatoria.',
            'abc' => 'La capacidad de partidos consecutivos debe ser un número entero.',
            '1.5' => 'La capacidad de partidos consecutivos debe ser un número entero.',
            '0' => 'La capacidad de partidos consecutivos debe ser de al menos 1.',
            '-1' => 'La capacidad de partidos consecutivos debe ser de al menos 1.',
            '4294967296' => 'La capacidad de partidos consecutivos no puede ser mayor que 4294967295.',
        ];

        foreach ($casos as $capacidad => $mensaje) {
            $this->actingAs($this->admin)->post(route('canchas.store'), [
                'nombre' => 'Cancha con capacidad '.$capacidad,
                'capacidad_partidos_consecutivos' => (string) $capacidad,
            ])->assertSessionHasErrors(['capacidad_partidos_consecutivos' => $mensaje]);
        }

        $this->assertSame(0, Cancha::count());
    }

    // CA3: la edición valida igual que el alta y no cambia nada si hay errores
    public function test_la_edicion_con_datos_invalidos_no_cambia_la_cancha(): void
    {
        $cancha = Cancha::factory()->create([
            'nombre' => 'Cancha intacta',
            'capacidad_partidos_consecutivos' => 2,
        ]);

        $this->actingAs($this->admin)->put(route('canchas.update', $cancha), [
            'nombre' => '',
            'capacidad_partidos_consecutivos' => 'muchos',
        ])->assertSessionHasErrors(['nombre', 'capacidad_partidos_consecutivos']);

        $this->assertDatabaseHas('canchas', [
            'id' => $cancha->id,
            'nombre' => 'Cancha intacta',
            'capacidad_partidos_consecutivos' => 2,
        ]);
    }

    // CA3: un campo enviado con un tipo inesperado se rechaza con un mensaje, no con un error 500
    public function test_un_nombre_enviado_como_lista_se_rechaza_sin_error_del_servidor(): void
    {
        $this->actingAs($this->admin)->post(route('canchas.store'), [
            'nombre' => ['Cancha', 'duplicada'],
            'capacidad_partidos_consecutivos' => 1,
        ])->assertSessionHasErrors(['nombre' => 'El nombre de la cancha debe ser texto.']);

        $this->assertSame(0, Cancha::count());
    }

    // El estado no se captura en el catálogo: los bloqueos por mantenimiento son de HU-024
    public function test_el_estado_no_se_cambia_desde_el_alta_ni_la_edicion(): void
    {
        $this->actingAs($this->admin)->post(route('canchas.store'), [
            'nombre' => 'Cancha con estado enviado',
            'capacidad_partidos_consecutivos' => 1,
            'estado' => 'Inactiva',
        ])->assertSessionHasNoErrors();

        $cancha = Cancha::where('nombre', 'Cancha con estado enviado')->sole();
        $this->assertSame('Disponible', $cancha->fresh()?->estado);

        $this->actingAs($this->admin)->put(route('canchas.update', $cancha), [
            'nombre' => 'Cancha con estado enviado',
            'capacidad_partidos_consecutivos' => 1,
            'estado' => 'Mantenimiento',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Disponible', $cancha->fresh()?->estado);
    }

    // CA4: un rol que no es Administrador General recibe 403 al registrar o editar
    public function test_otro_rol_recibe_403_al_registrar_o_editar_una_cancha(): void
    {
        $arbitro = $this->crearUsuario('arbitro_canchas@liga.com', 'Arbitro');
        $cancha = Cancha::factory()->create([
            'nombre' => 'Cancha protegida',
            'capacidad_partidos_consecutivos' => 2,
        ]);

        $this->actingAs($arbitro)->post(route('canchas.store'), [
            'nombre' => 'Cancha no autorizada',
            'capacidad_partidos_consecutivos' => 1,
        ])->assertForbidden()->assertSee('No cuenta con privilegios para registrar canchas.');

        $this->actingAs($arbitro)->put(route('canchas.update', $cancha), [
            'nombre' => 'Cancha alterada',
            'capacidad_partidos_consecutivos' => 9,
        ])->assertForbidden()->assertSee('No cuenta con privilegios para modificar canchas.');

        $this->assertDatabaseMissing('canchas', ['nombre' => 'Cancha no autorizada']);
        $this->assertDatabaseHas('canchas', [
            'id' => $cancha->id,
            'nombre' => 'Cancha protegida',
            'capacidad_partidos_consecutivos' => 2,
        ]);
    }

    // Consultar requiere permiso: el Árbitro tiene campos.ver y el Tesorero no
    public function test_consultar_el_catalogo_requiere_permiso(): void
    {
        $arbitro = $this->crearUsuario('arbitro_consulta@liga.com', 'Arbitro');
        $tesorero = $this->crearUsuario('tesorero_canchas@liga.com', 'Tesorero');
        $cancha = Cancha::factory()->create();

        $this->actingAs($arbitro)->getJson(route('canchas.index'))->assertOk();
        $this->actingAs($arbitro)->getJson(route('canchas.show', $cancha))->assertOk();

        $this->actingAs($tesorero)->getJson(route('canchas.index'))
            ->assertForbidden()
            ->assertJsonPath('mensaje', 'No cuenta con privilegios para consultar las canchas.');
        $this->actingAs($tesorero)->getJson(route('canchas.show', $cancha))->assertForbidden();
    }

    // Sin sesión no se puede consultar ni registrar
    public function test_sin_sesion_se_redirige_al_inicio_de_sesion(): void
    {
        $this->get(route('canchas.index'))->assertRedirect(route('login'));
        $this->post(route('canchas.store'), [
            'nombre' => 'Cancha anónima',
            'capacidad_partidos_consecutivos' => 1,
        ])->assertRedirect(route('login'));

        $this->assertSame(0, Cancha::count());
    }

    // El alta y los cambios quedan en la auditoría con el usuario que los hizo
    public function test_el_alta_y_los_cambios_quedan_en_la_auditoria(): void
    {
        $this->actingAs($this->admin)->post(route('canchas.store'), [
            'nombre' => 'Cancha auditada',
            'capacidad_partidos_consecutivos' => 2,
        ])->assertSessionHasNoErrors();

        $cancha = Cancha::where('nombre', 'Cancha auditada')->sole();

        $this->actingAs($this->admin)->put(route('canchas.update', $cancha), [
            'nombre' => 'Cancha auditada',
            'capacidad_partidos_consecutivos' => 3,
        ])->assertSessionHasNoErrors();

        $alta = $this->logsDe($cancha, 'crear')->sole();
        $this->assertSame($this->admin->id, $alta->usuario_id);
        $this->assertSame('Cancha auditada', $alta->valores_nuevos['nombre']);

        $cambio = $this->logsDe($cancha, 'actualizar')->sole();
        $this->assertSame($this->admin->id, $cambio->usuario_id);
        $this->assertSame(['capacidad_partidos_consecutivos' => 2], $cambio->valores_anteriores);
        $this->assertSame(['capacidad_partidos_consecutivos' => 3], $cambio->valores_nuevos);
    }

    // RNF-22: si la auditoría falla, la cancha no queda registrada
    public function test_si_falla_la_auditoria_la_cancha_no_se_guarda(): void
    {
        $this->mock(AuditService::class, function (MockInterface $simulado): void {
            $simulado->shouldReceive('registrar')->andThrow(new RuntimeException('Falla simulada de la auditoría'));
        });

        $this->actingAs($this->admin)->post(route('canchas.store'), [
            'nombre' => 'Cancha sin auditoría',
            'capacidad_partidos_consecutivos' => 1,
        ])->assertServerError();

        $this->assertDatabaseMissing('canchas', ['nombre' => 'Cancha sin auditoría']);
    }

    private function crearUsuario(string $email, string $rol): User
    {
        $usuario = User::create([
            'name' => 'Usuario '.$rol,
            'email' => $email,
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => $rol,
        ]);

        $usuario->assignRole($rol);

        return $usuario;
    }

    /**
     * @return Builder<AuditoriaLog>
     */
    private function logsDe(Cancha $cancha, string $accion): Builder
    {
        return AuditoriaLog::query()
            ->where('tabla_afectada', 'canchas')
            ->where('registro_id', $cancha->id)
            ->where('accion', $accion);
    }
}
