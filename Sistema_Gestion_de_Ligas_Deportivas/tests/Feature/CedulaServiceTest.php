<?php

namespace Tests\Feature;

use App\Models\AlineacionPartido;
use App\Models\AuditoriaLog;
use App\Models\CedulaArbitral;
use App\Models\EventoPartido;
use App\Models\IncidenciaPartido;
use App\Models\Jugador;
use App\Models\Partido;
use App\Services\CedulaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreaPartidoConCedula;
use Tests\TestCase;

// HU-036 (SCRUM-217): servicio de la cédula. Un reenvío con la misma clave no duplica nada (RNF-21)
class CedulaServiceTest extends TestCase
{
    use CreaPartidoConCedula;
    use DatabaseTransactions;

    private CedulaService $servicio;

    private Partido $partido;

    private CedulaArbitral $cedula;

    protected function setUp(): void
    {
        parent::setUp();

        $this->servicio = app(CedulaService::class);
        $this->partido = $this->partidoConArbitro();
        $this->cedula = $this->servicio->abrir($this->partido);
    }

    public function test_abrir_dos_veces_devuelve_la_misma_cedula(): void
    {
        $this->assertSame($this->cedula->id, $this->servicio->abrir($this->partido)->id);
        $this->assertSame(1, CedulaArbitral::query()->where('partido_id', $this->partido->id)->count());
        $this->assertSame(8, $this->cedula->alineaciones()->count());
    }

    // RNF-21: el mismo evento enviado dos veces se guarda una sola vez y deja una sola entrada en la auditoría
    public function test_un_evento_con_la_misma_clave_no_se_duplica(): void
    {
        $clave = (string) Str::uuid();
        $ultimoId = (int) AuditoriaLog::query()->max('id');
        $datos = ['jugador_id' => $this->jugador('local')->id, 'tipo' => 'Gol', 'minuto' => 23];

        $primero = $this->servicio->registrarEvento($this->cedula, $datos, $clave);
        $segundo = $this->servicio->registrarEvento($this->cedula, $datos, strtoupper($clave));

        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(1, $this->cedula->eventos()->count());
        $this->assertSame(
            [['eventos_partido', 'crear']],
            AuditoriaLog::query()->where('id', '>', $ultimoId)->get()->map(fn ($log) => [$log->tabla_afectada, $log->accion])->all()
        );
    }

    public function test_una_incidencia_con_la_misma_clave_no_se_duplica(): void
    {
        $clave = (string) Str::uuid();
        $datos = ['descripcion' => 'Se suspendió 5 minutos por lluvia.', 'minuto' => 40, 'equipo_id' => null];

        $this->servicio->registrarIncidencia($this->cedula, $datos, $clave);
        $this->servicio->registrarIncidencia($this->cedula, $datos, $clave);

        $this->assertSame(1, $this->cedula->incidencias()->count());
        $this->assertSame('Se suspendió 5 minutos por lluvia.', IncidenciaPartido::query()->where('clave_envio', $clave)->value('descripcion'));
    }

    // Un reenvío de un cambio de alineación ya aplicado no vuelve a cambiar nada
    public function test_la_alineacion_con_la_misma_clave_no_se_aplica_dos_veces(): void
    {
        $jugador = $this->jugador('local');
        $clave = (string) Str::uuid();

        $this->servicio->guardarAlineacion($this->cedula, $jugador->id, ['asistio' => true, 'numero_camiseta' => 10], $clave);
        $this->servicio->guardarAlineacion($this->cedula, $jugador->id, ['asistio' => false, 'numero_camiseta' => 99], $clave);

        $fila = AlineacionPartido::query()->where('cedula_id', $this->cedula->id)->where('jugador_id', $jugador->id)->sole();
        $this->assertTrue($fila->asistio);
        $this->assertSame(10, $fila->numero_camiseta);
    }

    // Decisión de Daniel: el árbitro anota al capitán; uno por equipo
    public function test_hay_un_solo_capitan_por_equipo(): void
    {
        [$primero, $segundo] = Jugador::query()->where('equipo_id', $this->partido->equipo_local_id)->where('estado_elegibilidad', 'Activo')->orderBy('id')->take(2)->get()->all();
        $visitante = $this->jugador('visitante');

        $this->servicio->guardarAlineacion($this->cedula, $primero->id, ['es_capitan' => true], (string) Str::uuid());
        $this->servicio->guardarAlineacion($this->cedula, $visitante->id, ['es_capitan' => true], (string) Str::uuid());
        $this->servicio->guardarAlineacion($this->cedula, $segundo->id, ['es_capitan' => true], (string) Str::uuid());

        $this->assertSame([$segundo->id, $visitante->id], $this->cedula->alineaciones()->where('es_capitan', true)->orderBy('jugador_id')->pluck('jugador_id')->all());
    }

    // Un autogol es un Gol del jugador que lo anotó; solo un gol puede ser autogol
    public function test_el_autogol_se_registra_como_gol_marcado(): void
    {
        $evento = $this->servicio->registrarEvento($this->cedula, ['jugador_id' => $this->jugador('local')->id, 'tipo' => 'Gol', 'autogol' => true], (string) Str::uuid());

        $this->assertTrue($evento->refresh()->autogol);
        $this->assertSame('Gol', $evento->tipo);
        $this->assertRechazo('autogol', 'Solo un gol puede marcarse como autogol.', fn () => $this->servicio->registrarEvento(
            $this->cedula,
            ['jugador_id' => $this->jugador('local')->id, 'tipo' => 'Amarilla', 'autogol' => true],
            (string) Str::uuid()
        ));
    }

    // RNF-29: un jugador de otro partido no entra en esta cédula
    public function test_rechaza_un_jugador_de_otro_partido(): void
    {
        $otro = $this->partidoConArbitro();
        $ajeno = Jugador::query()->where('equipo_id', $otro->equipo_local_id)->firstOrFail();

        $this->assertRechazo('jugador_id', 'El jugador no pertenece a los equipos de este partido.', fn () => $this->servicio->registrarEvento(
            $this->cedula,
            ['jugador_id' => $ajeno->id, 'tipo' => 'Roja'],
            (string) Str::uuid()
        ));
        $this->assertRechazo('jugador_id', 'El jugador no está en la alineación de este partido.', fn () => $this->servicio->guardarAlineacion(
            $this->cedula,
            $ajeno->id,
            ['asistio' => true],
            (string) Str::uuid()
        ));
        $this->assertRechazo('equipo_id', 'El equipo no juega este partido.', fn () => $this->servicio->registrarIncidencia(
            $this->cedula,
            ['descripcion' => 'Riña', 'equipo_id' => $otro->equipo_local_id],
            (string) Str::uuid()
        ));
        $this->assertSame(0, $this->cedula->eventos()->count());
    }

    // La misma clave usada en otra cédula no devuelve el registro ajeno
    public function test_una_clave_de_otra_cedula_se_rechaza(): void
    {
        $clave = (string) Str::uuid();
        $this->servicio->registrarEvento($this->cedula, ['jugador_id' => $this->jugador('local')->id, 'tipo' => 'Gol'], $clave);
        $otra = $this->servicio->abrir($this->partidoConArbitro());

        $this->assertRechazo('clave_envio', 'La clave de envío ya se usó en otra cédula.', fn () => $this->servicio->registrarEvento(
            $otra,
            ['jugador_id' => $otra->alineaciones()->value('jugador_id'), 'tipo' => 'Gol'],
            $clave
        ));
    }

    public function test_rechaza_datos_invalidos(): void
    {
        $jugador = $this->jugador('local')->id;

        $this->assertRechazo('clave_envio', 'La clave de envío no es válida.', fn () => $this->servicio->registrarEvento($this->cedula, ['jugador_id' => $jugador, 'tipo' => 'Gol'], 'no-es-uuid'));
        $this->assertRechazo('tipo', 'El evento debe ser Gol, Amarilla o Roja.', fn () => $this->servicio->registrarEvento($this->cedula, ['jugador_id' => $jugador, 'tipo' => 'Azul'], (string) Str::uuid()));
        $this->assertRechazo('minuto', 'El minuto debe ser un número entero entre 0 y 150.', fn () => $this->servicio->registrarEvento($this->cedula, ['jugador_id' => $jugador, 'tipo' => 'Gol', 'minuto' => 151], (string) Str::uuid()));
        $this->assertRechazo('descripcion', 'La descripción de la incidencia es obligatoria y admite hasta 1000 caracteres.', fn () => $this->servicio->registrarIncidencia($this->cedula, ['descripcion' => '   '], (string) Str::uuid()));
        $this->assertRechazo('marcador', 'Los goles de cada equipo deben estar entre 0 y 99.', fn () => $this->servicio->guardarMarcador($this->cedula, -1, 0));
        $this->assertSame(0, $this->cedula->eventos()->count());
    }

    // El marcador queda en la auditoría una sola vez; guardar el mismo marcador otra vez no deja otra entrada
    public function test_el_marcador_se_audita_una_sola_vez(): void
    {
        $ultimoId = (int) AuditoriaLog::query()->max('id');

        $this->servicio->guardarMarcador($this->cedula, 2, 1);
        $this->servicio->guardarMarcador($this->cedula, 2, 1);

        $registros = AuditoriaLog::query()->where('id', '>', $ultimoId)->get();
        $this->assertCount(1, $registros);
        $this->assertSame(['goles_local' => 2, 'goles_visitante' => 1], $registros->first()?->valores_nuevos);
        $this->assertStringStartsWith('Marcador de la cédula arbitral', (string) $registros->first()?->motivo);
    }

    // Una cédula cerrada ya no se edita (HU-075)
    public function test_una_cedula_cerrada_no_se_modifica(): void
    {
        $this->cedula->update(['estado' => CedulaArbitral::CERRADA]);
        $jugador = $this->jugador('local')->id;

        $this->assertRechazo('cedula', 'La cédula está cerrada: ya no se puede modificar.', fn () => $this->servicio->registrarEvento($this->cedula, ['jugador_id' => $jugador, 'tipo' => 'Gol'], (string) Str::uuid()));
        $this->assertRechazo('cedula', 'La cédula está cerrada: ya no se puede modificar.', fn () => $this->servicio->guardarMarcador($this->cedula, 1, 0));
        $this->assertRechazo('cedula', 'La cédula está cerrada: ya no se puede modificar.', fn () => $this->servicio->guardarAlineacion($this->cedula, $jugador, ['asistio' => true], (string) Str::uuid()));
        $this->assertSame(0, EventoPartido::query()->where('cedula_id', $this->cedula->id)->count());
    }

    private function jugador(string $lado): Jugador
    {
        return Jugador::query()
            ->where('equipo_id', $lado === 'local' ? $this->partido->equipo_local_id : $this->partido->equipo_visitante_id)
            ->where('estado_elegibilidad', 'Activo')
            ->orderBy('id')
            ->firstOrFail();
    }

    private function assertRechazo(string $campo, string $mensaje, callable $operacion): void
    {
        try {
            $operacion();
            $this->fail('Se esperaba un rechazo en '.$campo.'.');
        } catch (ValidationException $error) {
            $this->assertSame([$campo => [$mensaje]], $error->errors());
        }
    }
}
