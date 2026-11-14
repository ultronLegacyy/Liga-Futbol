<?php

namespace Tests\Feature;

use App\Models\CedulaArbitral;
use App\Models\Jugador;
use App\Models\Partido;
use App\Services\CedulaService;
use App\Services\ValidacionCedulaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreaPartidoConCedula;
use Tests\TestCase;

// HU-075 (SCRUM-223): validaciones antes de cerrar la cédula; un caso por cada una del criterio 4 (RNF-16)
class ValidacionCedulaServiceTest extends TestCase
{
    use CreaPartidoConCedula;
    use DatabaseTransactions;

    private CedulaService $cedulas;

    private ValidacionCedulaService $validacion;

    private Partido $partido;

    private CedulaArbitral $cedula;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cedulas = app(CedulaService::class);
        $this->validacion = app(ValidacionCedulaService::class);
        $this->partido = $this->partidoConArbitro();
        $this->cedula = $this->cedulas->abrir($this->partido);
    }

    // Una cédula correcta no tiene hallazgos: asistentes activos, goles que suman y tarjetas a alineados
    public function test_una_cedula_correcta_no_tiene_hallazgos(): void
    {
        [$local, $visitante] = [$this->activo('local'), $this->activo('visitante')];
        $this->asistio($local);
        $this->asistio($visitante);
        $this->evento($local, 'Gol');
        $this->evento($visitante, 'Gol', autogol: true);
        $this->evento($visitante, 'Amarilla');
        $this->cedulas->guardarMarcador($this->cedula, 2, 0, (string) Str::uuid());

        $this->assertSame([], $this->validacion->validar($this->cedula->refresh()));
    }

    // Caso 1: jugador no registrado (dado de baja o que ya no está en la plantilla de su equipo)
    public function test_detecta_un_jugador_no_registrado(): void
    {
        $jugador = $this->activo('local');
        $this->asistio($jugador);
        $jugador->update(['estado_elegibilidad' => 'Baja']);

        $this->assertSame(
            [['tipo' => ValidacionCedulaService::NO_REGISTRADO, 'mensaje' => 'Jugador 1 Equipo 1 (Equipo Cédula 1) no está registrado en la plantilla del equipo.']],
            $this->validacion->validar($this->cedula)
        );
    }

    // Caso 1 (variante): la venta del club deja vacío al jugador; su gol queda sin jugador registrado
    public function test_detecta_un_evento_sin_jugador_registrado(): void
    {
        $jugador = $this->activo('local');
        $this->asistio($jugador);
        $this->evento($jugador, 'Gol', minuto: 30);
        $this->cedulas->guardarMarcador($this->cedula, 1, 0, (string) Str::uuid());
        DB::table('jugadores_equipos')->where('id', $jugador->id)->delete();

        $tipos = array_column($this->validacion->validar($this->cedula->refresh()), 'mensaje', 'tipo');

        $this->assertSame('El gol del minuto 30 no tiene un jugador registrado en la alineación.', $tipos[ValidacionCedulaService::NO_REGISTRADO]);
    }

    // Caso 2: jugador suspendido (HU-018) que asistió
    public function test_detecta_un_jugador_suspendido(): void
    {
        $suspendido = Jugador::query()->where('equipo_id', $this->partido->equipo_visitante_id)->where('estado_elegibilidad', 'Suspendido')->firstOrFail();
        $this->asistio($suspendido);

        $this->assertSame(
            [['tipo' => ValidacionCedulaService::SUSPENDIDO, 'mensaje' => 'Jugador 4 Equipo 2 (Equipo Cédula 2) está suspendido y aparece en la cédula.']],
            $this->validacion->validar($this->cedula)
        );
    }

    // Caso 3: los goles no suman el marcador (el autogol cuenta para el rival)
    public function test_detecta_goles_que_no_suman_el_marcador(): void
    {
        $local = $this->activo('local');
        $this->asistio($local);
        $this->evento($local, 'Gol', autogol: true);
        $this->cedulas->guardarMarcador($this->cedula, 1, 0, (string) Str::uuid());

        $this->assertSame([
            ['tipo' => ValidacionCedulaService::MARCADOR, 'mensaje' => 'Los goles registrados de Equipo Cédula 1 (0) no suman su marcador (1).'],
            ['tipo' => ValidacionCedulaService::MARCADOR, 'mensaje' => 'Los goles registrados de Equipo Cédula 2 (1) no suman su marcador (0).'],
        ], $this->validacion->validar($this->cedula->refresh()));
    }

    // Caso 4: tarjeta a un jugador que no está marcado como asistente
    public function test_detecta_una_tarjeta_a_un_jugador_no_alineado(): void
    {
        $this->evento($this->activo('visitante'), 'Roja', minuto: 80);

        $this->assertSame(
            [['tipo' => ValidacionCedulaService::TARJETA_NO_ALINEADO, 'mensaje' => 'La tarjeta roja del minuto 80 es para Jugador 1 Equipo 2 (Equipo Cédula 2), que no está marcado como asistente.']],
            $this->validacion->validar($this->cedula)
        );
    }

    // Detecta los cuatro casos a la vez (100 %), sin repetir al mismo jugador
    public function test_detecta_los_cuatro_casos_juntos(): void
    {
        $baja = $this->activo('local');
        $this->asistio($baja);
        $this->evento($baja, 'Gol');
        $baja->update(['estado_elegibilidad' => 'Baja']);
        $suspendido = Jugador::query()->where('equipo_id', $this->partido->equipo_local_id)->where('estado_elegibilidad', 'Suspendido')->firstOrFail();
        $this->asistio($suspendido);
        $this->evento($this->activo('visitante'), 'Amarilla');

        $tipos = array_count_values(array_column($this->validacion->validar($this->cedula->refresh()), 'tipo'));

        $this->assertSame([
            ValidacionCedulaService::NO_REGISTRADO => 1,
            ValidacionCedulaService::SUSPENDIDO => 1,
            ValidacionCedulaService::TARJETA_NO_ALINEADO => 1,
            ValidacionCedulaService::MARCADOR => 1,
        ], $tipos);
    }

    private function activo(string $lado): Jugador
    {
        return Jugador::query()
            ->where('equipo_id', $lado === 'local' ? $this->partido->equipo_local_id : $this->partido->equipo_visitante_id)
            ->where('estado_elegibilidad', 'Activo')
            ->orderBy('id')
            ->firstOrFail();
    }

    private function asistio(Jugador $jugador): void
    {
        $this->cedulas->guardarAlineacion($this->cedula, $jugador->id, ['asistio' => true], (string) Str::uuid());
    }

    private function evento(Jugador $jugador, string $tipo, bool $autogol = false, ?int $minuto = null): void
    {
        $this->cedulas->registrarEvento($this->cedula, ['jugador_id' => $jugador->id, 'tipo' => $tipo, 'autogol' => $autogol, 'minuto' => $minuto], (string) Str::uuid());
    }
}
