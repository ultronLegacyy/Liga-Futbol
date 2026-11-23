<?php

namespace Tests\Feature;

use App\Events\ResultadoValidado;
use App\Models\AuditoriaLog;
use App\Models\CedulaArbitral;
use App\Models\Partido;
use App\Models\ReglaTemporada;
use App\Services\CedulaService;
use App\Services\ValidacionResultadoService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Concerns\CreaPartidoConCedula;
use Tests\TestCase;

// HU-040 (SCRUM-52, RF-08.4): un resultado capturado solo es oficial después de validarlo
class ValidacionResultadoTest extends TestCase
{
    use CreaPartidoConCedula;
    use DatabaseTransactions;

    private Partido $partido;

    private CedulaArbitral $cedula;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->partido = $this->partidoConArbitro();
        // Validar dispara el cálculo de la tabla (HU-041), que usa los puntos de las reglas de la temporada
        ReglaTemporada::create([
            'temporada_id' => $this->partido->jornada->grupo->temporada_id,
            'version' => 1,
            'reglas' => [
                'cuotas' => ['inscripcion' => 1000],
                'fechas_limite' => ['registro' => '2026-12-01'],
                'clasificacion' => ['puntos_victoria' => 3, 'puntos_empate' => 1, 'puntos_derrota' => 0],
                'desempates' => ['diferencia_goles'],
                'ascenso_descenso' => ['equipos_ascienden' => 1, 'equipos_descienden' => 1],
            ],
            'motivo' => 'Reglas de prueba',
        ]);
        $servicio = app(CedulaService::class);
        $this->cedula = $servicio->abrir($this->partido);
        $servicio->guardarMarcador($this->cedula, 2, 1, (string) Str::uuid());
    }

    // CA1: capturado y cerrado, pero sin validar, no es oficial ni dispara el cálculo de la tabla
    public function test_un_resultado_sin_validar_no_es_oficial(): void
    {
        Event::fake([ResultadoValidado::class]);
        $this->cerrar($this->cedula);

        $this->assertFalse($this->cedula->refresh()->esOficial());
        $this->assertFalse(CedulaArbitral::query()->oficiales()->whereKey($this->cedula->id)->exists());
        $this->assertTrue(CedulaArbitral::query()->porValidar()->whereKey($this->cedula->id)->exists());
        Event::assertNotDispatched(ResultadoValidado::class);
    }

    // CA2: al validar pasa a oficial, guarda quién y cuándo y dispara el cálculo de HU-041
    public function test_validar_lo_vuelve_oficial_y_dispara_el_calculo(): void
    {
        Event::fake([ResultadoValidado::class]);
        $this->cerrar($this->cedula);
        $programador = $this->usuario('Administrador General');

        $this->actingAs($programador)
            ->post(route('resultados.validar', $this->cedula->id))
            ->assertRedirect(route('resultados.index'))
            ->assertSessionHas('success');

        $this->cedula->refresh();
        $this->assertTrue($this->cedula->esOficial());
        $this->assertSame($programador->id, $this->cedula->validada_por_id);
        Event::assertDispatched(ResultadoValidado::class, fn (ResultadoValidado $evento) => $evento->cedula->is($this->cedula));
    }

    // CA2: la validación queda en la bitácora inmutable con su motivo
    public function test_la_validacion_queda_en_la_bitacora(): void
    {
        $this->cerrar($this->cedula);
        $ultimo = (int) AuditoriaLog::query()->max('id');

        app(ValidacionResultadoService::class)->validar($this->cedula, $this->usuario('Administrador General'));

        $log = AuditoriaLog::query()->where('id', '>', $ultimo)->where('tabla_afectada', 'cedulas_arbitrales')->sole();
        $this->assertSame('actualizar', $log->accion);
        $this->assertStringContainsString('Validación oficial del resultado del partido '.$this->partido->id, (string) $log->motivo);
    }

    // Solo se valida una cédula cerrada, y una sola vez
    public function test_no_se_valida_una_cedula_abierta_ni_dos_veces(): void
    {
        $servicio = app(ValidacionResultadoService::class);
        $usuario = $this->usuario('Administrador General');

        try {
            $servicio->validar($this->cedula, $usuario);
            $this->fail('Se validó una cédula abierta.');
        } catch (ValidationException $error) {
            $this->assertSame('Solo se valida un resultado con la cédula cerrada.', $error->getMessage());
        }
        $this->assertNull($this->cedula->refresh()->validada_en);

        $this->cerrar($this->cedula);
        $servicio->validar($this->cedula, $usuario);

        $this->expectException(ValidationException::class);
        $servicio->validar($this->cedula, $usuario);
    }

    // Una temporada cerrada solo se consulta (RF-01.4)
    public function test_no_se_valida_en_una_temporada_cerrada(): void
    {
        $this->cerrar($this->cedula);
        $this->partido->jornada->grupo->temporada->update(['estado' => 'cerrada']);

        $this->actingAs($this->usuario('Administrador General'))
            ->from(route('resultados.index'))
            ->post(route('resultados.validar', $this->cedula->id))
            ->assertRedirect(route('resultados.index'))
            ->assertSessionHasErrors('resultado');

        $this->assertNull($this->cedula->refresh()->validada_en);
    }

    // Si algo falla dentro de la transacción (por ejemplo, el cálculo de la tabla), el resultado sigue sin validar
    public function test_si_falla_algo_el_resultado_sigue_sin_validar(): void
    {
        $this->cerrar($this->cedula);
        Event::listen(ResultadoValidado::class, fn () => throw new RuntimeException('Falla simulada'));

        try {
            app(ValidacionResultadoService::class)->validar($this->cedula, $this->usuario('Administrador General'));
            $this->fail('La falla no se propagó.');
        } catch (RuntimeException) {
        }

        $this->assertNull($this->cedula->refresh()->validada_en);
    }

    // Decisión de Daniel (23/11): solo el Administrador General valida; los demás, el Programador incluido, reciben 403 en español
    public function test_solo_el_administrador_general_valida(): void
    {
        $this->cerrar($this->cedula);
        $aviso = 'Solo el Administrador General puede validar los resultados de los partidos.';

        foreach (['Programador de Partidos', 'Capturista', 'Árbitro en Campo', 'Tesorero', 'Secretario'] as $rol) {
            $usuario = $this->usuario($rol);
            $this->actingAs($usuario)->get(route('resultados.index'))->assertForbidden()->assertSee($aviso)->assertDontSee('This action is unauthorized');
            $this->actingAs($usuario)->post(route('resultados.validar', $this->cedula->id))->assertForbidden()->assertSee($aviso);
            $this->actingAs($usuario)->postJson(route('resultados.validar', $this->cedula->id))->assertForbidden()->assertJsonPath('mensaje', $aviso);
        }
        $this->assertNull($this->cedula->refresh()->validada_en);

        $this->actingAs($this->usuario('Administrador General'))->get(route('resultados.index'))->assertOk();
        $this->post(route('resultados.validar', 'abc'))->assertNotFound();
    }

    // Recomendado: una cédula validada sale en oficiales() y ya no en porValidar()
    public function test_la_cedula_validada_queda_entre_las_oficiales(): void
    {
        $this->cerrar($this->cedula);
        app(ValidacionResultadoService::class)->validar($this->cedula, $this->usuario('Administrador General'));

        $this->assertTrue(CedulaArbitral::query()->oficiales()->whereKey($this->cedula->id)->exists());
        $this->assertFalse(CedulaArbitral::query()->porValidar()->whereKey($this->cedula->id)->exists());
    }

    // Recomendado: los pendientes de una temporada cerrada no se listan, porque no se pueden validar
    public function test_los_pendientes_de_una_temporada_cerrada_no_se_listan(): void
    {
        $this->cerrar($this->cedula);
        $this->partido->jornada->grupo->temporada->update(['estado' => 'cerrada']);

        $this->assertFalse(app(ValidacionResultadoService::class)->pendientes()->contains('id', $this->cedula->id));
    }

    // La pantalla lista los pendientes con su marcador y el enlace del menú solo lo ve quien valida
    public function test_la_pantalla_muestra_los_pendientes(): void
    {
        $this->cerrar($this->cedula);
        $local = $this->partido->local->nombre;

        $this->actingAs($this->usuario('Administrador General'))
            ->get(route('resultados.index'))
            ->assertOk()
            ->assertSee('Resultados por validar')
            ->assertSee($local)
            ->assertSee('2 - 1')
            ->assertSee('action="'.route('resultados.validar', $this->cedula->id).'"', false);

        $this->actingAs($this->usuario('Programador de Partidos'))
            ->get(route('temporadas.index'))
            ->assertOk()
            ->assertDontSee('href="'.route('resultados.index').'"', false);
    }

    // Una cédula abierta todavía no aparece como pendiente de validar
    public function test_una_cedula_abierta_no_aparece_en_pendientes(): void
    {
        $this->actingAs($this->usuario('Administrador General'))
            ->get(route('resultados.index'))
            ->assertOk()
            ->assertSee('No hay resultados esperando validación.')
            ->assertDontSee('action="'.route('resultados.validar', $this->cedula->id).'"', false);
    }

    // El cierre con firmas es de HU-075; aquí se marca cerrada directamente
    private function cerrar(CedulaArbitral $cedula): void
    {
        $cedula->forceFill(['estado' => CedulaArbitral::CERRADA, 'cerrada_en' => now()])->save();
    }
}
