<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
use App\Models\CargoFinanciero;
use App\Models\Pago;
use App\Models\Temporada;
use App\Models\User;
use App\Services\RegistroPagoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// HU-051 (SCRUM-180): pagos parciales o totales con su comprobante y estado del adeudo (RF-13.2)
class RegistroPagoServiceTest extends TestCase
{
    use DatabaseTransactions;

    private RegistroPagoService $servicio;

    private Temporada $temporada;

    private CargoFinanciero $cargo;

    private User $tesorero;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->servicio = app(RegistroPagoService::class);
        $this->temporada = Temporada::factory()->create(['estado' => 'activa']);
        $this->cargo = CargoFinanciero::factory()->create([
            'temporada_id' => $this->temporada->id,
            'monto' => '1000.00',
            'vencimiento' => '2099-12-31',
        ]);
        $this->tesorero = User::create([
            'name' => 'Tesorero Pagos HU-051',
            'email' => 'tesorero.pagos@liga.test',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Consultor',
        ]);
    }

    // Criterio 1: un pago parcial deja el cargo Pendiente y con su saldo
    public function test_un_pago_parcial_deja_el_cargo_pendiente_con_su_saldo(): void
    {
        $this->pagar('400.50');

        $this->cargo->refresh();
        $this->assertSame('Pendiente', $this->cargo->estado);
        $this->assertSame('400.50', $this->cargo->montoPagado());
        $this->assertSame('599.50', $this->cargo->saldo());
    }

    public function test_el_pago_que_completa_el_saldo_deja_el_cargo_pagado(): void
    {
        $this->pagar('1000.00');

        $this->cargo->refresh();
        $this->assertSame('Pagado', $this->cargo->estado);
        $this->assertSame('0.00', $this->cargo->saldo());
    }

    public function test_dos_pagos_parciales_que_suman_el_total_dejan_el_cargo_pagado(): void
    {
        $this->pagar('333.33');
        $this->assertSame('Pendiente', $this->cargo->fresh()?->estado);

        $this->pagar('666.67');

        $this->cargo->refresh();
        $this->assertSame('Pagado', $this->cargo->estado);
        $this->assertSame('1000.00', $this->cargo->montoPagado());
        $this->assertSame('0.00', $this->cargo->saldo());
    }

    // Criterio 2: el comprobante queda en el disco privado, ligado al pago y, por él, al cargo
    #[DataProvider('comprobantesValidos')]
    public function test_el_comprobante_queda_en_el_disco_privado_ligado_al_pago_y_al_cargo(string $nombre, string $mime): void
    {
        $pago = $this->pagar('200.00', ['metodo_pago' => 'Transferencia', 'referencia' => 'SPEI-123'], UploadedFile::fake()->create($nombre, 120, $mime));

        $this->assertStringStartsWith('comprobantes/', (string) $pago->recibo_url);
        Storage::disk('local')->assertExists((string) $pago->recibo_url);
        Storage::disk('public')->assertMissing((string) $pago->recibo_url);
        $this->assertSame($this->cargo->id, $pago->cargo_id);
        $this->assertSame($this->tesorero->id, $pago->validado_por_tesorero_id);
        $this->assertSame('Transferencia', $pago->metodo_pago);
        $this->assertSame('SPEI-123', $pago->referencia);
        $this->assertSame([$pago->id], $this->cargo->pagos()->pluck('id')->all());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function comprobantesValidos(): array
    {
        return [
            'PDF' => ['comprobante.pdf', 'application/pdf'],
            'JPG' => ['comprobante.jpg', 'image/jpeg'],
            'PNG' => ['comprobante.png', 'image/png'],
        ];
    }

    // Sobrepago: un pago no puede superar el saldo, y el mensaje lo informa
    public function test_rechaza_un_pago_que_supera_el_saldo(): void
    {
        $this->pagar('700.00');

        $this->assertRechazo('300.01', [], 'monto', 'El pago supera el saldo pendiente de $300.00.');
        $this->assertSame('Pendiente', $this->cargo->fresh()?->estado);
    }

    public function test_rechaza_un_pago_de_un_cargo_ya_pagado(): void
    {
        $this->pagar('1000.00');

        $this->assertRechazo('1.00', [], 'cargo', 'El cargo ya está pagado.');
    }

    public function test_rechaza_un_pago_si_la_temporada_esta_cerrada(): void
    {
        $this->temporada->update(['estado' => 'cerrada']);

        $this->assertRechazo('100.00', [], 'cargo', 'La temporada del cargo está cerrada: solo se consulta (RF-01.4).');
    }

    #[DataProvider('montosInvalidos')]
    public function test_rechaza_montos_invalidos(string $monto, string $mensaje): void
    {
        $this->assertRechazo($monto, [], 'monto', $mensaje);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function montosInvalidos(): array
    {
        return [
            'cero' => ['0', 'El monto del pago debe ser mayor que cero.'],
            'negativo' => ['-50.00', 'El monto del pago debe ser mayor que cero.'],
            'tres decimales' => ['10.555', 'El monto del pago admite como máximo dos decimales.'],
            'vacío' => ['', 'El monto del pago es obligatorio.'],
        ];
    }

    public function test_rechaza_un_metodo_de_pago_vacio_o_largo_y_una_referencia_larga(): void
    {
        $this->assertRechazo('100.00', ['metodo_pago' => ''], 'metodo_pago', 'El método de pago es obligatorio.');
        $this->assertRechazo('100.00', ['metodo_pago' => str_repeat('a', 51)], 'metodo_pago', 'El método de pago no debe tener más de 50 caracteres.');
        $this->assertRechazo('100.00', ['referencia' => str_repeat('r', 101)], 'referencia', 'La referencia no debe tener más de 100 caracteres.');
    }

    public function test_rechaza_un_pago_sin_comprobante(): void
    {
        $this->assertRechazo('100.00', [], 'comprobante', 'El comprobante de pago es obligatorio.', null);
    }

    public function test_rechaza_un_comprobante_ejecutable(): void
    {
        $this->assertRechazo('100.00', [], 'comprobante', 'El comprobante debe ser un PDF, JPG o PNG.', UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'));
    }

    public function test_rechaza_un_comprobante_de_mas_de_5_mb(): void
    {
        $this->assertRechazo('100.00', [], 'comprobante', 'El comprobante no debe pesar más de 5 MB.', UploadedFile::fake()->create('grande.pdf', 5121, 'application/pdf'));
    }

    // Si algo falla después de guardar el archivo, se borra y no queda suelto
    public function test_si_falla_al_guardar_el_pago_se_borra_el_comprobante(): void
    {
        Pago::creating(function (): void {
            throw new \RuntimeException('Falla simulada al guardar el pago.');
        });

        try {
            $this->pagar('100.00');
            $this->fail('Se esperaba que el pago fallara.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Falla simulada al guardar el pago.', $e->getMessage());
        } finally {
            // Solo el escucha de esta prueba: los de Auditable (created, updated, deleted) siguen registrados
            Pago::getEventDispatcher()?->forget('eloquent.creating: '.Pago::class);
        }

        $this->assertSame([], Storage::disk('local')->allFiles('comprobantes'));
        $this->assertSame(0, $this->cargo->pagos()->count());
    }

    // Vencido se calcula al mostrar: en la base sigue Pendiente
    public function test_con_saldo_y_vencimiento_pasado_se_muestra_vencido(): void
    {
        $this->cargo->update(['vencimiento' => '2020-01-15']);
        $this->pagar('100.00');

        $this->cargo->refresh();
        $this->assertSame('Vencido', $this->cargo->estadoMostrado());
        $this->assertSame('Pendiente', $this->cargo->estado);
    }

    public function test_con_vencimiento_futuro_se_muestra_pendiente(): void
    {
        $this->assertSame('Pendiente', $this->cargo->estadoMostrado());
    }

    public function test_un_cargo_pagado_se_muestra_pagado_aunque_haya_vencido(): void
    {
        $this->pagar('1000.00');
        $this->cargo->refresh()->update(['vencimiento' => '2020-01-15']);

        $this->assertSame('Pagado', $this->cargo->refresh()->estadoMostrado());
    }

    // «Hoy» es el de America/Mexico_City: a las 21:00 del día de vencimiento en Morelia ya es el día siguiente en UTC
    public function test_el_vencimiento_se_compara_con_la_fecha_de_mexico(): void
    {
        $this->cargo->update(['vencimiento' => '2026-11-06']);

        $this->travelTo(now('UTC')->setDate(2026, 11, 7)->setTime(3, 0));
        $this->assertSame('Pendiente', $this->cargo->refresh()->estadoMostrado());

        $this->travelTo(now('UTC')->setDate(2026, 11, 7)->setTime(7, 0));
        $this->assertSame('Vencido', $this->cargo->refresh()->estadoMostrado());
    }

    // En un listado, withSum evita una consulta de pagos por cargo
    public function test_con_with_sum_no_se_consultan_los_pagos_por_cargo(): void
    {
        $this->pagar('250.00');
        $cargo = CargoFinanciero::query()->withSum('pagos', 'monto')->findOrFail($this->cargo->id);

        DB::enableQueryLog();
        $resultado = [$cargo->montoPagado(), $cargo->saldo(), $cargo->estadoMostrado()];

        $this->assertSame(['250.00', '750.00', 'Pendiente'], $resultado);
        $this->assertSame([], DB::getQueryLog());
    }

    // RNF-33: una fila por pago y una por el cambio de estado del cargo
    public function test_la_auditoria_registra_el_pago_y_el_cambio_de_estado(): void
    {
        $ultimoId = (int) AuditoriaLog::query()->max('id');
        $this->pagar('400.00');

        $parcial = AuditoriaLog::query()->where('id', '>', $ultimoId)->get();
        $this->assertSame([['pagos', 'crear']], $parcial->map(fn ($log) => [$log->tabla_afectada, $log->accion])->all());

        $ultimoId = (int) AuditoriaLog::query()->max('id');
        $this->pagar('600.00');

        $total = AuditoriaLog::query()->where('id', '>', $ultimoId)->orderBy('id')->get();
        $this->assertSame([['pagos', 'crear'], ['cargos_financieros', 'actualizar']], $total->map(fn ($log) => [$log->tabla_afectada, $log->accion])->all());
        $this->assertSame('Pagado', $total->last()?->valores_nuevos['estado']);
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function pagar(string $monto, array $cambios = [], ?UploadedFile $comprobante = null): Pago
    {
        return $this->servicio->registrar(
            $this->cargo,
            array_merge(['monto' => $monto, 'metodo_pago' => 'Efectivo'], $cambios),
            $comprobante ?? UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf'),
            $this->tesorero
        );
    }

    /**
     * Rechaza el pago con el mensaje en el campo, sin guardar pago ni archivo.
     *
     * @param  array<string, mixed>  $cambios
     */
    private function assertRechazo(string $monto, array $cambios, string $campo, string $mensaje, UploadedFile|false|null $comprobante = false): void
    {
        $pagos = Pago::query()->count();
        $archivos = Storage::disk('local')->allFiles('comprobantes');

        try {
            $this->servicio->registrar(
                $this->cargo,
                array_merge(['monto' => $monto, 'metodo_pago' => 'Efectivo'], $cambios),
                $comprobante === false ? UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf') : $comprobante,
                $this->tesorero
            );
            $this->fail('Se esperaba que el pago se rechazara.');
        } catch (ValidationException $e) {
            $this->assertSame($mensaje, $e->errors()[$campo][0] ?? null, json_encode($e->errors(), JSON_UNESCAPED_UNICODE) ?: '');
        }

        $this->assertSame($pagos, Pago::query()->count());
        $this->assertSame($archivos, Storage::disk('local')->allFiles('comprobantes'));
    }
}
