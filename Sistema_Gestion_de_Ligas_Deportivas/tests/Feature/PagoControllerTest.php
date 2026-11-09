<?php

namespace Tests\Feature;

use App\Models\CargoFinanciero;
use App\Models\Club;
use App\Models\Pago;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// HU-051 (SCRUM-181 y SCRUM-182): registrar pagos y descargar comprobantes desde el detalle del cargo (RF-13.2)
class PagoControllerTest extends TestCase
{
    use DatabaseTransactions;

    private User $tesorero;

    private Temporada $temporada;

    private CargoFinanciero $cargo;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(RoleAndPermissionSeeder::class);

        $this->tesorero = $this->crearUsuario('tesorero.comprobantes@liga.test', 'Tesorero');
        $this->temporada = Temporada::factory()->create(['estado' => 'activa', 'nombre' => 'Temporada Pagos HU-051']);
        $this->cargo = CargoFinanciero::factory()->create([
            'club_id' => Club::create(['nombre' => 'Club Pagos HU-051'])->id,
            'temporada_id' => $this->temporada->id,
            'monto' => '1000.00',
            'vencimiento' => '2099-12-31',
        ]);
    }

    // Criterio 1, de principio a fin: el pago parcial deja el saldo y el que lo completa deja el cargo pagado
    public function test_un_pago_parcial_y_luego_el_total_actualizan_el_estado_del_adeudo(): void
    {
        $this->actingAs($this->tesorero)
            ->post(route('cargos.pagos.store', $this->cargo), $this->datos('400.00'))
            ->assertRedirect(route('cargos.show', $this->cargo))
            ->assertSessionHas('success', 'Pago de $400.00 registrado. Saldo pendiente: $600.00.');

        $this->assertSame('Pendiente', $this->cargo->refresh()->estado);
        $this->get(route('cargos.show', $this->cargo))
            ->assertOk()
            ->assertSeeInOrder(['Pagado', '$400.00 MXN', 'Saldo pendiente', '$600.00 MXN', 'Estado', 'Pendiente'])
            ->assertSee('Registrar pago');

        $this->post(route('cargos.pagos.store', $this->cargo), $this->datos('600.00'))
            ->assertRedirect(route('cargos.show', $this->cargo))
            ->assertSessionHas('success', 'Pago de $600.00 registrado. El cargo quedó pagado.');

        $this->assertSame('Pagado', $this->cargo->refresh()->estado);
        $this->get(route('cargos.show', $this->cargo))
            ->assertOk()
            ->assertSeeInOrder(['Saldo pendiente', '$0.00 MXN', 'Estado', 'Pagado'])
            ->assertDontSee('Registrar pago');
    }

    // Criterio 2, de principio a fin: el comprobante queda ligado al cargo y se descarga del disco privado
    public function test_el_comprobante_subido_queda_ligado_al_cargo_y_se_descarga(): void
    {
        $this->actingAs($this->tesorero)
            ->post(route('cargos.pagos.store', $this->cargo), $this->datos('250.00', ['referencia' => 'SPEI-777']))
            ->assertRedirect(route('cargos.show', $this->cargo));

        $pago = $this->cargo->pagos()->sole();
        $ruta = (string) $pago->recibo_url;
        $this->assertStringStartsWith('comprobantes/', $ruta);
        Storage::disk('local')->assertExists($ruta);
        $this->assertSame($this->tesorero->id, $pago->validado_por_tesorero_id);

        $this->get(route('cargos.show', $this->cargo))
            ->assertOk()
            ->assertSeeInOrder(['$250.00', 'Transferencia', 'SPEI-777', $this->tesorero->name])
            ->assertSee(route('pagos.comprobante', $pago), false);

        $descarga = $this->get(route('pagos.comprobante', $pago));
        $descarga->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertDownload('comprobante-cargo-'.$this->cargo->id.'-pago-'.$pago->id.'.pdf');
        $this->assertSame(Storage::disk('local')->get($ruta), $descarga->streamedContent());
    }

    // El Administrador General entra por el before() de la política
    public function test_el_administrador_general_registra_y_descarga(): void
    {
        $admin = $this->crearUsuario('admin.pagos@liga.test', 'Administrador General');

        $this->actingAs($admin)
            ->post(route('cargos.pagos.store', $this->cargo), $this->datos('100.00'))
            ->assertRedirect(route('cargos.show', $this->cargo));

        $this->get(route('pagos.comprobante', $this->cargo->pagos()->sole()))->assertOk();
    }

    #[DataProvider('rolesSinPermiso')]
    public function test_los_roles_sin_permiso_no_registran_ni_descargan(string $rol): void
    {
        $pago = $this->pagoConComprobante();
        $usuario = $this->crearUsuario('sin.permiso.pagos@liga.test', $rol);

        $this->actingAs($usuario)
            ->post(route('cargos.pagos.store', $this->cargo), $this->datos('100.00'))
            ->assertForbidden();
        $this->get(route('pagos.comprobante', $pago))->assertForbidden();

        $this->assertSame(1, $this->cargo->pagos()->count());
        $this->assertCount(1, Storage::disk('local')->allFiles('comprobantes'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rolesSinPermiso(): array
    {
        return [
            'Secretario' => ['Secretario'],
            'Programador de Partidos' => ['Programador de Partidos'],
            'Jugador' => ['Jugador'],
        ];
    }

    // Sin sesión no se registra un pago ni se descarga un comprobante
    public function test_sin_sesion_no_se_registra_ni_se_descarga(): void
    {
        $pago = $this->pagoConComprobante();

        $this->post(route('cargos.pagos.store', $this->cargo), $this->datos('100.00'))->assertRedirect(route('login'));
        $this->get(route('pagos.comprobante', $pago))->assertRedirect(route('login'));

        $this->assertSame(1, $this->cargo->pagos()->count());
    }

    public function test_la_validacion_se_muestra_en_espanol_en_el_detalle(): void
    {
        $this->actingAs($this->tesorero)
            ->from(route('cargos.show', $this->cargo))
            ->followingRedirects()
            ->post(route('cargos.pagos.store', $this->cargo), ['monto' => '', 'metodo_pago' => ''])
            ->assertOk()
            ->assertSee('No se registró el pago. Revisa los campos marcados.')
            ->assertSee('El monto del pago es obligatorio.')
            ->assertSee('El método de pago es obligatorio.')
            ->assertSee('El comprobante de pago es obligatorio.');

        $this->post(route('cargos.pagos.store', $this->cargo), $this->datos('1500.00'))
            ->assertSessionHasErrors(['monto' => 'El pago supera el saldo pendiente de $1,000.00.']);

        $this->assertSame(0, $this->cargo->pagos()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('comprobantes'));
    }

    // Los rechazos del campo cargo se ven arriba, aunque el formulario ya no aparezca
    public function test_el_rechazo_de_un_cargo_ya_pagado_se_muestra_en_el_detalle(): void
    {
        $this->cargo->update(['estado' => 'Pagado']);

        $this->actingAs($this->tesorero)
            ->from(route('cargos.show', $this->cargo))
            ->followingRedirects()
            ->post(route('cargos.pagos.store', $this->cargo), $this->datos('100.00'))
            ->assertOk()
            ->assertSee('El cargo ya está pagado.');
    }

    public function test_con_la_temporada_cerrada_se_rechaza_y_no_aparece_el_formulario(): void
    {
        $this->temporada->update(['estado' => 'cerrada']);

        $this->actingAs($this->tesorero)
            ->from(route('cargos.show', $this->cargo))
            ->followingRedirects()
            ->post(route('cargos.pagos.store', $this->cargo), $this->datos('100.00'))
            ->assertOk()
            ->assertSee('La temporada del cargo está cerrada: solo se consulta (RF-01.4).')
            ->assertDontSee('Registrar pago');

        $this->assertSame(0, $this->cargo->pagos()->count());
    }

    // Quien solo consulta finanzas ve los pagos y descarga, pero no tiene el formulario
    public function test_sin_permiso_de_registrar_no_aparece_el_formulario(): void
    {
        $pago = $this->pagoConComprobante();
        $consulta = $this->crearUsuario('consulta.finanzas@liga.test', 'Jugador');
        $consulta->givePermissionTo('finanzas.ver');

        $this->actingAs($consulta)
            ->get(route('cargos.show', $this->cargo))
            ->assertOk()
            ->assertSee(route('pagos.comprobante', $pago), false)
            ->assertDontSee('Registrar pago');
    }

    // Vencido se calcula al mostrar: en la base sigue Pendiente
    public function test_el_detalle_muestra_vencido_con_saldo_y_fecha_pasada(): void
    {
        $this->cargo->update(['vencimiento' => '2020-01-15']);

        $this->actingAs($this->tesorero)
            ->get(route('cargos.show', $this->cargo))
            ->assertOk()
            ->assertSeeInOrder(['Estado', 'Vencido']);
        $this->assertSame('Pendiente', $this->cargo->refresh()->estado);
    }

    // 404 si el archivo no existe o si la ruta guardada no es de comprobantes/
    public function test_responde_404_si_el_comprobante_no_esta_disponible(): void
    {
        $faltante = Pago::factory()->create(['cargo_id' => $this->cargo->id, 'monto' => '10.00', 'recibo_url' => 'comprobantes/no-existe.pdf']);
        Storage::disk('local')->put('convocatorias/ajena.pdf', '%PDF');
        $ajeno = Pago::factory()->create(['cargo_id' => $this->cargo->id, 'monto' => '10.00', 'recibo_url' => 'convocatorias/ajena.pdf']);
        $sinArchivo = Pago::factory()->create(['cargo_id' => $this->cargo->id, 'monto' => '10.00', 'recibo_url' => null]);

        $this->actingAs($this->tesorero);
        $this->get(route('pagos.comprobante', $faltante))->assertNotFound();
        $this->get(route('pagos.comprobante', $ajeno))->assertNotFound();
        $this->get(route('pagos.comprobante', $sinArchivo))->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datos(string $monto, array $cambios = []): array
    {
        return array_merge([
            'monto' => $monto,
            'metodo_pago' => 'Transferencia',
            'referencia' => '',
            'comprobante' => UploadedFile::fake()->create('recibo.pdf', 100, 'application/pdf'),
        ], $cambios);
    }

    // Un pago registrado por el tesorero, con su comprobante guardado
    private function pagoConComprobante(): Pago
    {
        $this->actingAs($this->tesorero)->post(route('cargos.pagos.store', $this->cargo), $this->datos('100.00'));
        auth()->logout();

        return $this->cargo->pagos()->sole();
    }

    private function crearUsuario(string $email, string $rol): User
    {
        $usuario = User::create([
            'name' => 'Usuario '.$rol,
            'email' => $email,
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Consultor',
        ]);
        $usuario->assignRole($rol);

        return $usuario;
    }
}
