<?php

namespace Tests\Feature;

use App\Events\ResultadoValidado;
use App\Models\AuditoriaLog;
use App\Models\CedulaArbitral;
use App\Models\Partido;
use App\Services\CedulaService;
use App\Services\ValidacionResultadoService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Concerns\CreaGrupoConResultados;
use Tests\Concerns\CreaPartidoConCedula;
use Tests\TestCase;

// HU-040 (SCRUM-52, RF-08.4): un resultado capturado solo es oficial después de validarlo
class ValidacionResultadoTest extends TestCase
{
    use CreaGrupoConResultados;
    use CreaPartidoConCedula;
    use DatabaseTransactions;

    private Partido $partido;

    private CedulaArbitral $cedula;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->partido = $this->partidoConArbitro();
        // Desde HU-041, validar recalcula la tabla con los puntos de las reglas de la temporada
        $this->reglas($this->partido->jornada->grupo->temporada, [3, 1, 0], ['diferencia_goles']);
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
        $programador = $this->usuario('Programador de Partidos');

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

        app(ValidacionResultadoService::class)->validar($this->cedula, $this->usuario('Programador de Partidos'));

        $log = AuditoriaLog::query()->where('id', '>', $ultimo)->where('tabla_afectada', 'cedulas_arbitrales')->sole();
        $this->assertSame('actualizar', $log->accion);
        $this->assertStringContainsString('Validación oficial del resultado del partido '.$this->partido->id, (string) $log->motivo);
    }

    // Solo se valida una cédula cerrada, y una sola vez
    public function test_no_se_valida_una_cedula_abierta_ni_dos_veces(): void
    {
        $servicio = app(ValidacionResultadoService::class);
        $usuario = $this->usuario('Programador de Partidos');

        try {
            $servicio->validar($this->cedula, $usuario);
            $this->fail('Se validó una cédula abierta.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('cédula cerrada', $error->getMessage());
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

        $this->actingAs($this->usuario('Programador de Partidos'))
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
            app(ValidacionResultadoService::class)->validar($this->cedula, $this->usuario('Programador de Partidos'));
            $this->fail('La falla no se propagó.');
        } catch (RuntimeException) {
        }

        $this->assertNull($this->cedula->refresh()->validada_en);
    }

    // Solo quien tiene resultados.validar (Programador de Partidos y Administrador General) entra y valida
    public function test_solo_quien_tiene_el_permiso_valida(): void
    {
        $this->cerrar($this->cedula);

        foreach (['Capturista', 'Árbitro en Campo', 'Tesorero', 'Secretario'] as $rol) {
            $usuario = $this->usuario($rol);
            $this->actingAs($usuario)->get(route('resultados.index'))->assertForbidden();
            $this->actingAs($usuario)->post(route('resultados.validar', $this->cedula->id))->assertForbidden();
        }
        $this->assertNull($this->cedula->refresh()->validada_en);

        $this->actingAs($this->usuario('Administrador General'))->get(route('resultados.index'))->assertOk();
        $this->post(route('resultados.validar', 'abc'))->assertNotFound();
    }

    // La pantalla lista los pendientes con su marcador y el enlace del menú solo lo ve quien valida
    public function test_la_pantalla_muestra_los_pendientes(): void
    {
        $this->cerrar($this->cedula);
        $local = $this->partido->local->nombre;

        $this->actingAs($this->usuario('Programador de Partidos'))
            ->get(route('resultados.index'))
            ->assertOk()
            ->assertSee('Resultados por validar')
            ->assertSee($local)
            ->assertSee('2 - 1')
            ->assertSee('action="'.route('resultados.validar', $this->cedula->id).'"', false);

        $this->actingAs($this->usuario('Capturista'))
            ->get(route('temporadas.index'))
            ->assertOk()
            ->assertDontSee('href="'.route('resultados.index').'"', false);
    }

    // Una cédula abierta todavía no aparece como pendiente de validar
    public function test_una_cedula_abierta_no_aparece_en_pendientes(): void
    {
        $this->actingAs($this->usuario('Programador de Partidos'))
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
