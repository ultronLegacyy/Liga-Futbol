<?php

namespace Tests\Feature;

use App\Models\CargoFinanciero;
use App\Models\Club;
use App\Models\Pago;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// HU-051 (SCRUM-182 y SCRUM-183): adeudos por club y filtro por estado en el listado de cargos (RF-13.2)
class AdeudoClubTest extends TestCase
{
    use DatabaseTransactions;

    private User $tesorero;

    private Temporada $temporada;

    private Club $deudor;

    private Club $alCorriente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        // Fecha fija: «hoy» es el 10/11/2026 a las 22:00 en Morelia, que en UTC ya es el 11/11
        $this->travelTo(now('America/Mexico_City')->setDate(2026, 11, 10)->setTime(22, 0));

        $this->tesorero = $this->crearUsuario('tesorero.adeudos@liga.test', 'Tesorero');
        $this->temporada = Temporada::factory()->create(['nombre' => 'Temporada Adeudos 2090', 'estado' => 'activa', 'fecha_inicio' => '2090-01-01', 'fecha_fin' => '2090-06-30']);
        $this->deudor = Club::create(['nombre' => 'Club Adeudos Deudor']);
        $this->alCorriente = Club::create(['nombre' => 'Club Adeudos Al Corriente']);
    }

    // Totales por club con pagos parciales: un club con adeudo y otro al corriente
    public function test_los_totales_por_club_cuentan_los_pagos_parciales_y_quien_esta_al_corriente(): void
    {
        $parcial = $this->cargo($this->deudor, '1000.00');
        $this->pagar($parcial, '400.00');
        $this->pagar($parcial, '100.50');
        $this->pagar($this->cargo($this->deudor, '500.00'), '500.00', true);
        $this->pagar($this->cargo($this->alCorriente, '350.00'), '350.00', true);
        // De otra temporada: no cuenta en esta
        $this->cargo($this->deudor, '9999.00', ['temporada_id' => Temporada::factory()->create(['fecha_inicio' => '2080-01-01'])->id]);

        $respuesta = $this->actingAs($this->tesorero)->get(route('adeudos.index', ['temporada_id' => $this->temporada->id]));

        $respuesta->assertOk()->assertViewHas('adeudos', function (array $adeudos): bool {
            $porClub = collect($adeudos)->mapWithKeys(fn (array $fila) => [$fila['club']->nombre => [$fila['cargado'], $fila['pagado'], $fila['saldo'], $fila['al_corriente']]]);

            return $porClub->all() === [
                'Club Adeudos Al Corriente' => [35000, 35000, 0, true],
                'Club Adeudos Deudor' => [150000, 100050, 49950, false],
            ];
        });
        $respuesta->assertSeeInOrder(['Club Adeudos Al Corriente', '$350.00', '$350.00', '$0.00', 'Al corriente'])
            ->assertSeeInOrder(['Club Adeudos Deudor', '$1,500.00', '$1,000.50', '$499.50', 'Con adeudo'])
            // El enlace lleva & escapado como &amp; en el HTML
            ->assertSee(route('cargos.index', ['temporada_id' => $this->temporada->id, 'club_id' => $this->deudor->id]));
    }

    // Sin elegir temporada se muestra la más reciente; una temporada sin cargos lo avisa
    public function test_por_defecto_muestra_la_temporada_mas_reciente(): void
    {
        $this->cargo($this->deudor, '100.00');
        $vacia = Temporada::factory()->create(['nombre' => 'Temporada Adeudos Vacía', 'fecha_inicio' => '2085-01-01']);

        $this->actingAs($this->tesorero)->get(route('adeudos.index'))
            ->assertOk()
            ->assertViewHas('temporada', fn (?Temporada $temporada): bool => $temporada?->id === $this->temporada->id)
            ->assertSee('Club Adeudos Deudor');

        $this->get(route('adeudos.index', ['temporada_id' => $vacia->id]))
            ->assertOk()
            ->assertSee('Esta temporada no tiene cargos registrados.')
            ->assertDontSee('Club Adeudos Deudor');
    }

    // El filtro por estado usa la misma regla que estadoMostrado(), incluido Vencido
    #[DataProvider('estados')]
    public function test_el_filtro_por_estado_coincide_con_el_estado_mostrado(string $estado, array $esperados): void
    {
        $cargos = [
            'pendiente a futuro' => $this->cargo($this->deudor, '100.00', ['vencimiento' => '2026-12-01']),
            'vence hoy' => $this->cargo($this->deudor, '100.00', ['vencimiento' => '2026-11-10']),
            'vencido sin pagos' => $this->cargo($this->deudor, '100.00', ['vencimiento' => '2026-11-09']),
            'vencido con pago parcial' => $this->cargo($this->deudor, '100.00', ['vencimiento' => '2026-10-01']),
            'pagado y vencido' => $this->cargo($this->deudor, '100.00', ['vencimiento' => '2026-10-01']),
            'vencido guardado' => $this->cargo($this->deudor, '100.00', ['vencimiento' => '2026-12-01', 'estado' => 'Vencido']),
            // Sin saldo no se muestra Vencido, aunque siga guardado como Pendiente
            'vencido ya cubierto' => $this->cargo($this->deudor, '100.00', ['vencimiento' => '2026-10-01']),
        ];
        $this->pagar($cargos['vencido con pago parcial'], '40.00');
        $this->pagar($cargos['pagado y vencido'], '100.00', true);
        $this->pagar($cargos['vencido ya cubierto'], '100.00');

        $respuesta = $this->actingAs($this->tesorero)
            ->get(route('cargos.index', ['temporada_id' => $this->temporada->id, 'club_id' => $this->deudor->id, 'estado' => $estado]));

        $respuesta->assertOk();
        $ids = collect($cargos)->only($esperados)->map->id->sort()->values()->all();
        $respuesta->assertViewHas('cargos', function (LengthAwarePaginator $pagina) use ($ids, $estado): bool {
            $mostrados = collect($pagina->items());

            return $mostrados->pluck('id')->sort()->values()->all() === $ids
                && $mostrados->every(fn (CargoFinanciero $cargo): bool => $cargo->estadoMostrado() === $estado);
        });
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function estados(): array
    {
        return [
            'Pendiente' => ['Pendiente', ['pendiente a futuro', 'vence hoy', 'vencido ya cubierto']],
            'Vencido' => ['Vencido', ['vencido sin pagos', 'vencido con pago parcial', 'vencido guardado']],
            'Pagado' => ['Pagado', ['pagado y vencido']],
        ];
    }

    // El listado muestra lo pagado, el saldo y el estado mostrado
    public function test_el_listado_muestra_pagado_saldo_y_estado_mostrado(): void
    {
        $this->pagar($this->cargo($this->deudor, '800.00', ['vencimiento' => '2026-10-01']), '300.00');

        $this->actingAs($this->tesorero)
            ->get(route('cargos.index', ['club_id' => $this->deudor->id]))
            ->assertOk()
            ->assertSeeInOrder(['Monto', 'Pagado', 'Saldo', 'Vencimiento', 'Estado'])
            ->assertSeeInOrder(['$800.00', '$300.00', '$500.00', '01/10/2026', 'Vencido'])
            // withSum: lo pagado llega en la misma consulta, sin una consulta de pagos por fila
            ->assertViewHas('cargos', fn (LengthAwarePaginator $pagina): bool => collect($pagina->items())->every(
                fn (CargoFinanciero $cargo): bool => array_key_exists('pagos_sum_monto', $cargo->getAttributes())
            ));
    }

    // Un estado que no existe se ignora
    public function test_un_estado_invalido_no_filtra(): void
    {
        $this->cargo($this->deudor, '100.00');

        $this->actingAs($this->tesorero)
            ->get(route('cargos.index', ['club_id' => $this->deudor->id, 'estado' => 'Parcial']))
            ->assertOk()
            ->assertViewHas('filtros', fn (array $filtros): bool => $filtros['estado'] === null)
            ->assertViewHas('cargos', fn (LengthAwarePaginator $pagina): bool => $pagina->total() === 1);
    }

    public function test_el_administrador_general_y_el_tesorero_abren_las_pantallas(): void
    {
        $admin = $this->crearUsuario('admin.adeudos@liga.test', 'Administrador General');

        foreach ([$this->tesorero, $admin] as $usuario) {
            $this->actingAs($usuario)->get(route('adeudos.index'))->assertOk();
            $this->get(route('cargos.index', ['estado' => 'Vencido']))->assertOk();
            $this->get(route('cargos.index'))->assertSee(route('adeudos.index'), false);
        }
    }

    #[DataProvider('rolesSinPermiso')]
    public function test_los_roles_sin_permiso_no_consultan_los_adeudos(string $rol): void
    {
        $this->cargo($this->deudor, '100.00');

        $this->actingAs($this->crearUsuario('sin.permiso.adeudos@liga.test', $rol))
            ->get(route('adeudos.index'))
            ->assertForbidden();
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

    public function test_sin_sesion_no_se_consultan_los_adeudos(): void
    {
        $this->get(route('adeudos.index'))->assertRedirect(route('login'));
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function cargo(Club $club, string $monto, array $cambios = []): CargoFinanciero
    {
        return CargoFinanciero::factory()->create(array_merge([
            'club_id' => $club->id,
            'temporada_id' => $this->temporada->id,
            'monto' => $monto,
            'vencimiento' => '2026-12-31',
        ], $cambios));
    }

    // Pago directo con la factory; el servicio y su comprobante ya se prueban en RegistroPagoServiceTest
    private function pagar(CargoFinanciero $cargo, string $monto, bool $completa = false): void
    {
        Pago::factory()->create(['cargo_id' => $cargo->id, 'monto' => $monto, 'validado_por_tesorero_id' => $this->tesorero->id]);

        if ($completa) {
            $cargo->update(['estado' => 'Pagado']);
        }
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
