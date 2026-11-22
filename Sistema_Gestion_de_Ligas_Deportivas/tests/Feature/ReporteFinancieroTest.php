<?php

namespace Tests\Feature;

use App\Models\CargoFinanciero;
use App\Models\Club;
use App\Models\Pago;
use App\Models\Temporada;
use App\Models\User;
use App\Services\ReporteFinancieroService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// HU-052 (SCRUM-230 a SCRUM-232): reporte financiero por periodo, club, temporada y concepto (RF-13.3)
class ReporteFinancieroTest extends TestCase
{
    use DatabaseTransactions;

    private User $tesorero;

    private Temporada $temporada;

    private Temporada $otraTemporada;

    private Club $club;

    private Club $otroClub;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->tesorero = $this->crearUsuario('tesorero.reporte@liga.test', 'Tesorero');
        $this->temporada = Temporada::factory()->create(['nombre' => 'Temporada Reporte 2091', 'fecha_inicio' => '2091-01-01', 'fecha_fin' => '2091-06-30']);
        $this->otraTemporada = Temporada::factory()->create(['nombre' => 'Temporada Reporte 2092', 'fecha_inicio' => '2092-01-01', 'fecha_fin' => '2092-06-30']);
        $this->club = Club::create(['nombre' => 'Club Reporte Uno']);
        $this->otroClub = Club::create(['nombre' => 'Club Reporte Dos']);

        // Del club, dentro del periodo de marzo de 2091 (los bordes cuentan)
        $inscripcion = $this->cargo($this->club, 'Inscripcion', '1000.00', '2091-03-01');
        $this->pagar($inscripcion, '400.00');
        $this->pagar($inscripcion, '100.50');
        $this->pagar($this->cargo($this->club, 'Arbitraje', '300.00', '2091-03-31'), '300.00');
        $this->cargo($this->club, 'Arbitraje', '250.00', '2091-03-15', $this->otraTemporada);

        // No entran en marzo de 2091 o son de otro club
        $this->cargo($this->club, 'Inscripcion', '7777.00', '2091-02-28');
        $this->cargo($this->club, 'Cancha', '8888.00', '2091-04-01');
        $this->cargo($this->otroClub, 'Inscripcion', '9999.00', '2091-03-10');
    }

    // Criterio 1, filtro de club: montos por concepto con pagos parciales; fuera del periodo y otros clubes no cuentan
    public function test_el_reporte_del_club_muestra_los_montos_por_concepto_del_periodo(): void
    {
        $respuesta = $this->actingAs($this->tesorero)->get($this->ruta());

        $respuesta->assertOk()->assertViewHas('reporte', fn (array $reporte): bool => $reporte === [
            'filas' => [
                ['concepto' => 'Inscripción', 'cargos' => 1, 'cargado' => 100000, 'cobrado' => 50050, 'saldo' => 49950],
                ['concepto' => 'Arbitraje', 'cargos' => 2, 'cargado' => 55000, 'cobrado' => 30000, 'saldo' => 25000],
            ],
            'totales' => ['cargos' => 3, 'cargado' => 155000, 'cobrado' => 80050, 'saldo' => 74950],
        ]);
        $respuesta->assertSeeInOrder(['Club Reporte Uno', 'Descargar PDF', 'Descargar CSV', 'Inscripción', '$1,000.00', '$500.50', '$499.50'])
            ->assertSeeInOrder(['Total', '3', '$1,550.00', '$800.50', '$749.50'])
            ->assertDontSee('$9,999.00')
            ->assertDontSee('$7,777.00')
            ->assertDontSee('$8,888.00');
    }

    // Criterio 1, filtro de temporada
    public function test_el_filtro_de_temporada_deja_solo_sus_cargos(): void
    {
        $this->actingAs($this->tesorero)->get($this->ruta(['temporada_id' => $this->otraTemporada->id]))
            ->assertOk()
            ->assertViewHas('reporte', fn (array $reporte): bool => $reporte['filas'] === [
                ['concepto' => 'Arbitraje', 'cargos' => 1, 'cargado' => 25000, 'cobrado' => 0, 'saldo' => 25000],
            ] && $reporte['totales']['cargado'] === 25000)
            ->assertSee('Temporada Reporte 2092');
    }

    // Criterio 1, filtro de concepto, y combinado con la temporada
    public function test_el_filtro_de_concepto_solo_y_combinado_con_la_temporada(): void
    {
        $this->actingAs($this->tesorero)->get($this->ruta(['concepto' => 'Arbitraje']))
            ->assertOk()
            ->assertViewHas('reporte', fn (array $reporte): bool => $reporte['totales'] === ['cargos' => 2, 'cargado' => 55000, 'cobrado' => 30000, 'saldo' => 25000]);

        $this->get($this->ruta(['concepto' => 'Arbitraje', 'temporada_id' => $this->temporada->id]))
            ->assertOk()
            ->assertViewHas('reporte', fn (array $reporte): bool => $reporte['totales'] === ['cargos' => 1, 'cargado' => 30000, 'cobrado' => 30000, 'saldo' => 0]);
    }

    public function test_sin_movimientos_lo_avisa_y_no_ofrece_descarga(): void
    {
        $this->actingAs($this->tesorero)->get($this->ruta(['concepto' => 'Multa']))
            ->assertOk()
            ->assertSee('No hay cargos de este club con vencimiento en ese periodo para los filtros elegidos.')
            ->assertDontSee('Descargar CSV')
            ->assertDontSee('Descargar PDF');
    }

    // Sin filtros solo se muestra el formulario
    public function test_sin_filtros_solo_muestra_el_formulario(): void
    {
        $this->actingAs($this->tesorero)->get(route('reportes.financiero'))
            ->assertOk()
            ->assertViewHas('reporte', fn (?array $reporte): bool => $reporte === null)
            ->assertSee('Elija el periodo y el club, y después genere el reporte.');
    }

    public function test_el_periodo_invertido_se_rechaza_con_un_mensaje_claro(): void
    {
        $this->actingAs($this->tesorero)->get($this->ruta(['desde' => '2091-03-31', 'hasta' => '2091-03-01']))
            ->assertRedirect(route('reportes.financiero'))
            ->assertSessionHasErrors(['hasta' => 'La fecha final no puede ser anterior a la fecha de inicio.']);

        $this->followingRedirects()->get($this->ruta(['desde' => '2091-03-31', 'hasta' => '2091-03-01']))
            ->assertOk()
            ->assertSee('La fecha final no puede ser anterior a la fecha de inicio.')
            ->assertSee('value="2091-03-31"', false);
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    #[DataProvider('filtrosInvalidos')]
    public function test_los_filtros_invalidos_regresan_mensajes_en_espanol_sin_error_500(array $cambios, string $campo, string $mensaje): void
    {
        $this->actingAs($this->tesorero)->get($this->ruta($cambios))
            ->assertRedirect(route('reportes.financiero'))
            ->assertSessionHasErrors([$campo => $mensaje]);

        // Al volver al formulario con lo enviado (incluidos arreglos) la pantalla abre
        $this->followingRedirects()->get($this->ruta($cambios))->assertOk()->assertSee($mensaje);
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function filtrosInvalidos(): array
    {
        return [
            'sin club' => [['club_id' => ''], 'club_id', 'Elija un club.'],
            'club inexistente' => [['club_id' => 999999999], 'club_id', 'El club elegido no existe.'],
            'club como arreglo' => [['club_id' => ['1']], 'club_id', 'El club elegido no es válido.'],
            'sin fecha final' => [['hasta' => ''], 'hasta', 'Indique la fecha final del periodo.'],
            'fecha con otro formato' => [['desde' => '01/03/2091'], 'desde', 'La fecha de inicio debe tener el formato AAAA-MM-DD.'],
            'fecha como arreglo' => [['desde' => ['2091-03-01']], 'desde', 'La fecha de inicio no es válida.'],
            'temporada como arreglo' => [['temporada_id' => ['1']], 'temporada_id', 'La temporada elegida no es válida.'],
            'concepto inexistente' => [['concepto' => 'Donativo'], 'concepto', 'El concepto elegido no es válido.'],
            'concepto como arreglo' => [['concepto' => ['Multa']], 'concepto', 'El concepto elegido no es válido.'],
        ];
    }

    // El servicio hace una sola consulta aunque haya muchos cargos y pagos (sin N+1)
    public function test_el_reporte_se_agrupa_en_una_sola_consulta(): void
    {
        foreach (range(1, 15) as $numero) {
            $this->pagar($this->cargo($this->club, 'Cancha', '10.00', '2091-03-20'), '5.00');
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $reporte = app(ReporteFinancieroService::class)->generar('2091-03-01', '2091-03-31', $this->club->id);
        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(1, $consultas);
        $this->assertSame(['cargos' => 18, 'cargado' => 170000, 'cobrado' => 87550, 'saldo' => 82450], $reporte['totales']);
    }

    public function test_el_csv_trae_los_mismos_montos_y_neutraliza_formulas(): void
    {
        $this->club->update(['nombre' => '=HYPERLINK("http://x")']);

        $respuesta = $this->actingAs($this->tesorero)->get(route('reportes.financiero.csv', $this->filtros()));

        $respuesta->assertOk()->assertDownload();
        $this->assertStringContainsString('text/csv', (string) $respuesta->headers->get('Content-Type'));
        $contenido = $respuesta->streamedContent();
        $this->assertStringStartsWith("\u{FEFF}\"Reporte financiero\"", $contenido);
        $this->assertStringContainsString("Club,\"'=HYPERLINK(\"\"http://x\"\")\"", $contenido);
        $this->assertStringContainsString('Inscripción,1,1000.00,500.50,499.50', $contenido);
        $this->assertStringContainsString('Total,3,1550.00,800.50,749.50', $contenido);
    }

    public function test_el_pdf_se_descarga_con_su_nombre(): void
    {
        $respuesta = $this->actingAs($this->tesorero)->get(route('reportes.financiero.pdf', $this->filtros()));

        $respuesta->assertOk()->assertDownload('reporte-financiero-club-reporte-uno-2091-03-01-a-2091-03-31.pdf');
        $this->assertSame('application/pdf', $respuesta->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', (string) $respuesta->getContent());
    }

    // El contenido del PDF sale de la misma vista: montos, filtros y el nombre del club escapado
    public function test_la_vista_del_pdf_muestra_los_montos_y_los_filtros(): void
    {
        $this->club->update(['nombre' => 'Club <b>Uno</b>']);

        $html = view('reportes.financiero-pdf', [
            'encabezado' => ['club' => $this->club, 'periodo' => '2091-03-01 a 2091-03-31', 'temporada' => 'Todas', 'concepto' => 'Todos'],
            'reporte' => app(ReporteFinancieroService::class)->generar('2091-03-01', '2091-03-31', $this->club->id),
            'generado' => '15/11/2026 12:00',
        ])->render();

        $this->assertStringContainsString('Club &lt;b&gt;Uno&lt;/b&gt;', $html);
        $this->assertMatchesRegularExpression('/Inscripción.*\$1,000\.00.*\$500\.50.*\$499\.50/s', $html);
        $this->assertMatchesRegularExpression('/Total.*\$1,550\.00.*\$800\.50.*\$749\.50/s', $html);
        $this->assertStringContainsString('2091-03-01 a 2091-03-31', $html);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function descargas(): array
    {
        return ['csv' => ['reportes.financiero.csv'], 'pdf' => ['reportes.financiero.pdf']];
    }

    #[DataProvider('descargas')]
    public function test_las_descargas_exigen_los_filtros(string $ruta): void
    {
        $this->actingAs($this->tesorero)->get(route($ruta))
            ->assertRedirect(route('reportes.financiero'))
            ->assertSessionHasErrors(['desde', 'hasta', 'club_id']);

        $this->get(route($ruta, $this->filtros(['desde' => '2091-03-31', 'hasta' => '2091-03-01'])))
            ->assertRedirect(route('reportes.financiero'))
            ->assertSessionHasErrors(['hasta' => 'La fecha final no puede ser anterior a la fecha de inicio.']);
    }

    // El Tesorero, el Presidente y el Administrador General consultan el reporte y ven el enlace en el menú
    public function test_los_roles_con_permiso_abren_el_reporte(): void
    {
        $usuarios = [
            $this->tesorero,
            $this->crearUsuario('presidente.reporte@liga.test', 'Presidente'),
            $this->crearUsuario('admin.reporte@liga.test', 'Administrador General'),
        ];

        foreach ($usuarios as $usuario) {
            $this->actingAs($usuario)->get($this->ruta())->assertOk()->assertSee(route('reportes.financiero'), false);
            $this->get(route('reportes.financiero.csv', $this->filtros()))->assertOk();
            $this->get(route('reportes.financiero.pdf', $this->filtros()))->assertOk();
        }
    }

    #[DataProvider('rolesSinPermiso')]
    public function test_los_roles_sin_permiso_reciben_403_en_espanol(string $rol): void
    {
        $this->actingAs($this->crearUsuario('sin.permiso.reporte@liga.test', $rol));

        $this->get($this->ruta())
            ->assertForbidden()
            ->assertSee('No cuenta con privilegios para consultar el reporte financiero.');
        $this->get(route('reportes.financiero.csv', $this->filtros()))->assertForbidden();
        $this->get(route('reportes.financiero.pdf', $this->filtros()))->assertForbidden();
        // Ni con filtros inválidos: primero se revisa el permiso
        $this->get($this->ruta(['desde' => 'x']))->assertForbidden();
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

    public function test_sin_sesion_no_se_consulta_el_reporte(): void
    {
        $this->get($this->ruta())->assertRedirect(route('login'));
        $this->get(route('reportes.financiero.csv', $this->filtros()))->assertRedirect(route('login'));
        $this->get(route('reportes.financiero.pdf', $this->filtros()))->assertRedirect(route('login'));
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function filtros(array $cambios = []): array
    {
        return array_merge(['desde' => '2091-03-01', 'hasta' => '2091-03-31', 'club_id' => $this->club->id], $cambios);
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function ruta(array $cambios = []): string
    {
        return route('reportes.financiero', $this->filtros($cambios));
    }

    private function cargo(Club $club, string $concepto, string $monto, string $vencimiento, ?Temporada $temporada = null): CargoFinanciero
    {
        return CargoFinanciero::factory()->create([
            'club_id' => $club->id,
            'temporada_id' => ($temporada ?? $this->temporada)->id,
            'concepto' => $concepto,
            'monto' => $monto,
            'vencimiento' => $vencimiento,
        ]);
    }

    // Pago directo con la factory; el servicio y su comprobante ya se prueban en RegistroPagoServiceTest
    private function pagar(CargoFinanciero $cargo, string $monto): void
    {
        Pago::factory()->create(['cargo_id' => $cargo->id, 'monto' => $monto, 'validado_por_tesorero_id' => $this->tesorero->id]);
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
