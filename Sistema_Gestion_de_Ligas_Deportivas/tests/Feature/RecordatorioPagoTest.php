<?php

namespace Tests\Feature;

use App\Mail\RecordatorioPago;
use App\Models\CargoFinanciero;
use App\Models\Club;
use App\Models\Pago;
use App\Models\RegistroCorreo;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

// HU-065 (SCRUM-75, RF-15.2): recordatorios de pago a los delegados de los clubes con adeudos vencidos o próximos a vencer
class RecordatorioPagoTest extends TestCase
{
    use DatabaseTransactions;

    private Temporada $temporada;

    private Club $deudor;

    private Club $alCorriente;

    private Club $sinDelegado;

    /** @var array<string, CargoFinanciero> */
    private array $cargos = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        // «Hoy» es el 10/11/2026 a las 22:00 en Morelia, que en UTC ya es el 11/11
        $this->travelTo(now('America/Mexico_City')->setDate(2026, 11, 10)->setTime(22, 0));

        $this->temporada = Temporada::factory()->create(['fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31']);
        $this->deudor = Club::create(['nombre' => 'Club Recordatorio Deudor']);
        $this->alCorriente = Club::create(['nombre' => 'Club Recordatorio Al Corriente']);
        $this->sinDelegado = Club::create(['nombre' => 'Club Recordatorio Sin Delegado']);

        $this->delegado('deudor@liga.test', $this->deudor);
        $this->delegado('deudor2@liga.test', $this->deudor);
        $this->delegado('deudor.baja@liga.test', $this->deudor, 'inactivo');
        $this->delegado('corriente@liga.test', $this->alCorriente);

        $this->cargos = [
            // Vencido hace semanas: también recibe su aviso (decisión 8)
            'vencido' => $this->cargo($this->deudor, 'Inscripcion', '1500.00', '2026-10-01'),
            // Vence hoy en México (en UTC ya es mañana): todavía es «próximo»
            'hoy' => $this->cargo($this->deudor, 'Arbitraje', '300.00', '2026-11-10'),
            'enTresDias' => $this->cargo($this->deudor, 'Cancha', '250.00', '2026-11-13'),
            'enCuatroDias' => $this->cargo($this->deudor, 'Arbitraje', '300.00', '2026-11-14'),
            'pagado' => $this->cargo($this->deudor, 'Arbitraje', '300.00', '2026-11-01', 'Pagado'),
            'saldado' => $this->cargo($this->alCorriente, 'Inscripcion', '1500.00', '2026-11-11'),
            'sinDelegado' => $this->cargo($this->sinDelegado, 'Inscripcion', '1500.00', '2026-11-01'),
        ];
        $this->pagar($this->cargos['vencido'], '1000.00');
        $this->pagar($this->cargos['saldado'], '1500.00');
    }

    // Criterio 1: vencido y próximo a vencer en un solo correo por delegado; al corriente y sin delegado no reciben nada
    public function test_el_delegado_recibe_sus_cargos_vencidos_y_proximos_a_vencer(): void
    {
        Mail::fake();

        $this->artisan('sgld:recordatorios-pago')->expectsOutput('Recordatorios de pago encolados: 2.')->assertSuccessful();

        Mail::assertQueuedCount(2);
        foreach (['deudor@liga.test', 'deudor2@liga.test'] as $correo) {
            Mail::assertQueued(RecordatorioPago::class, fn (RecordatorioPago $aviso): bool => $aviso->hasTo($correo)
                && $aviso->asunto === 'Recordatorio de pago: Club Recordatorio Deudor'
                && str_contains($aviso->mensaje, "- Inscripción: saldo de $500.00 · venció el 01/10/2026\n- Arbitraje: saldo de $300.00 · vence el 10/11/2026\n- Cancha: saldo de $250.00 · vence el 13/11/2026")
                && ! str_contains($aviso->mensaje, '14/11/2026'));
        }

        $this->assertSame([
            [$this->cargos['vencido']->id, 'vencido'],
            [$this->cargos['hoy']->id, 'proximo'],
            [$this->cargos['enTresDias']->id, 'proximo'],
        ], $this->avisados());
    }

    // Decisión 4: no se repite; al día siguiente solo sale lo que entra a una etapa nueva
    public function test_el_mismo_recordatorio_no_se_repite(): void
    {
        Mail::fake();
        $this->artisan('sgld:recordatorios-pago')->assertSuccessful();
        $this->artisan('sgld:recordatorios-pago')->expectsOutput('Recordatorios de pago encolados: 0.')->assertSuccessful();
        Mail::assertQueuedCount(2);

        // 11/11: el cargo de ayer ya venció y el del 14/11 entra a «próximo»
        $this->travelTo(now('America/Mexico_City')->setDate(2026, 11, 11)->setTime(9, 0));
        $this->artisan('sgld:recordatorios-pago')->assertSuccessful();

        Mail::assertQueuedCount(4);
        Mail::assertQueued(RecordatorioPago::class, fn (RecordatorioPago $aviso): bool => $aviso->hasTo('deudor@liga.test')
            && $aviso->mensaje === "Hola, Usuario deudor@liga.test:\n\nEste es un recordatorio de los adeudos del club Club Recordatorio Deudor con la liga:\n\n- Arbitraje: saldo de $300.00 · venció el 10/11/2026\n- Arbitraje: saldo de $300.00 · vence el 14/11/2026");

        // 20/11: vencen los del 13/11 y el 14/11; después, ya nada se repite
        $this->travelTo(now('America/Mexico_City')->setDate(2026, 11, 20)->setTime(9, 0));
        $this->artisan('sgld:recordatorios-pago')->assertSuccessful();
        $this->artisan('sgld:recordatorios-pago')->assertSuccessful();
        $this->assertSame(7, DB::table('recordatorios_pago')->count());
        Mail::assertQueuedCount(6);
    }

    // Un club sin delegado no recibe nada ni queda marcado; el aviso sale cuando tenga delegado
    public function test_un_club_sin_delegado_recibe_el_aviso_cuando_tiene_delegado(): void
    {
        Mail::fake();
        $this->artisan('sgld:recordatorios-pago')->assertSuccessful();
        $this->assertSame(0, DB::table('recordatorios_pago')->where('cargo_id', $this->cargos['sinDelegado']->id)->count());

        $this->delegado('nuevo@liga.test', $this->sinDelegado);
        $this->artisan('sgld:recordatorios-pago')->expectsOutput('Recordatorios de pago encolados: 1.')->assertSuccessful();

        Mail::assertQueued(RecordatorioPago::class, fn (RecordatorioPago $aviso): bool => $aviso->hasTo('nuevo@liga.test') && str_contains($aviso->mensaje, 'venció el 01/11/2026'));
    }

    // HU-067: cada recordatorio queda en el registro de correos
    public function test_los_recordatorios_quedan_en_el_registro_de_correos(): void
    {
        $this->artisan('sgld:recordatorios-pago')->assertSuccessful();

        $registros = RegistroCorreo::query()->where('tipo', 'recordatorio_pago')->orderBy('id')->get();
        $this->assertSame([['deudor@liga.test'], ['deudor2@liga.test']], $registros->pluck('destinatarios')->all());
        $this->assertSame([RegistroCorreo::ENVIADO], $registros->pluck('estado')->unique()->values()->all());
        $this->assertSame('Recordatorio de pago', $registros->first()?->nombreTipo());
    }

    // Decisión 4: corre diario a las 09:00, hora de México, desde el schedule
    public function test_el_comando_corre_diario_a_las_nueve_hora_de_mexico(): void
    {
        $evento = collect(app(Schedule::class)->events())->first(fn (Event $evento): bool => str_contains((string) $evento->command, 'sgld:recordatorios-pago'));

        $this->assertNotNull($evento);
        $this->assertSame(['0 9 * * *', 'America/Mexico_City'], [$evento->expression, $evento->timezone]);
    }

    /**
     * @return list<array{int, string}>
     */
    private function avisados(): array
    {
        return DB::table('recordatorios_pago')->orderBy('id')->get(['cargo_id', 'etapa'])
            ->map(fn (object $fila): array => [(int) $fila->cargo_id, (string) $fila->etapa])->all();
    }

    private function cargo(Club $club, string $concepto, string $monto, string $vencimiento, string $estado = 'Pendiente'): CargoFinanciero
    {
        return CargoFinanciero::factory()->create([
            'club_id' => $club->id,
            'temporada_id' => $this->temporada->id,
            'concepto' => $concepto,
            'monto' => $monto,
            'vencimiento' => $vencimiento,
            'estado' => $estado,
        ]);
    }

    private function pagar(CargoFinanciero $cargo, string $monto): void
    {
        Pago::factory()->create(['cargo_id' => $cargo->id, 'monto' => $monto]);
    }

    private function delegado(string $correo, Club $club, string $estatus = 'activo'): User
    {
        $usuario = User::query()->create([
            'name' => 'Usuario '.$correo,
            'email' => $correo,
            'password' => Hash::make('Clave-De-Prueba-2026'),
            'estatus' => $estatus,
            'requires_password_change' => false,
        ]);
        $usuario->forceFill(['club_id' => $club->id])->save();
        $usuario->assignRole('Delegado del Club');

        return $usuario;
    }
}
