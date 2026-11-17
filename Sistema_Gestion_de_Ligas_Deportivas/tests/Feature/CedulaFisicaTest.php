<?php

namespace Tests\Feature;

use App\Models\AlineacionPartido;
use App\Models\CedulaArbitral;
use App\Models\EventoPartido;
use App\Models\IncidenciaPartido;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\User;
use App\Services\CedulaService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreaPartidoConCedula;
use Tests\TestCase;

/** HU-037 (SCRUM-49, RF-08.1): el Capturista transcribe una cédula en papel con las mismas validaciones que la captura móvil. */
class CedulaFisicaTest extends TestCase
{
    use CreaPartidoConCedula;
    use DatabaseTransactions;

    private Partido $partido;

    private User $capturista;

    protected function setUp(): void
    {
        parent::setUp();
        // El partido del ayudante es el 14/11/2026; hoy es martes 17
        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00', 'America/Mexico_City'));

        $this->seed(RoleAndPermissionSeeder::class);
        $this->partido = $this->partidoConArbitro();
        $this->capturista = $this->usuario('Capturista');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_el_capturista_ve_los_partidos_pendientes_y_el_formulario_sin_abrir_nada(): void
    {
        $futuro = $this->partidoConArbitro();
        $futuro->update(['fecha_partido' => '2026-11-28']);

        $this->actingAs($this->capturista)->get(route('cedulas.fisicas.index'))
            ->assertOk()
            ->assertSee($this->partido->local->nombre)
            ->assertSee(route('cedulas.fisica.create', $this->partido), false)
            ->assertDontSee(route('cedulas.fisica.create', $futuro), false);

        $this->actingAs($this->capturista)->get(route('cedulas.fisica.create', $this->partido))
            ->assertOk()
            ->assertSee('Marcador')
            ->assertSee('Goles, amonestaciones y expulsiones')
            ->assertSee('Jugador 1')
            ->assertSee('name="clave_envio"', false);

        $this->assertDatabaseCount('cedulas_arbitrales', 0);
        // El menú lleva al Capturista a la lista
        $this->actingAs($this->capturista)->get(route('cedulas.fisicas.index'))->assertSee('href="'.route('cedulas.fisicas.index').'"', false);
    }

    // Criterio 1: marcador, alineaciones, amonestaciones, expulsiones e incidencias
    public function test_transcribe_la_cedula_completa(): void
    {
        [$goleador, $amonestado] = $this->jugadores('local', 2);
        [$expulsado] = $this->jugadores('visitante', 1);

        $this->actingAs($this->capturista)
            ->post(route('cedulas.fisica.store', $this->partido), $this->formulario([
                'goles_local' => 1,
                'goles_visitante' => 0,
                'alineacion' => [
                    $goleador->id => ['asistio' => '1', 'numero_camiseta' => '9'],
                    $amonestado->id => ['asistio' => '1', 'numero_camiseta' => '5'],
                    $expulsado->id => ['asistio' => '1', 'numero_camiseta' => '4'],
                ],
                'capitanes' => [$this->partido->equipo_local_id => (string) $goleador->id],
                'eventos' => [
                    ['jugador_id' => $goleador->id, 'tipo' => 'Gol', 'minuto' => '23'],
                    ['jugador_id' => $amonestado->id, 'tipo' => 'Amarilla', 'minuto' => '40'],
                    ['jugador_id' => $expulsado->id, 'tipo' => 'Roja', 'minuto' => '78'],
                    ['jugador_id' => '', 'tipo' => '', 'minuto' => ''],
                ],
                'incidencias' => [
                    ['descripcion' => 'Retraso de 10 minutos por lluvia', 'minuto' => '', 'equipo_id' => ''],
                    ['descripcion' => '', 'minuto' => '', 'equipo_id' => ''],
                ],
                'informe_arbitral' => 'Partido sin más novedades.',
            ]))
            ->assertRedirect(route('cedulas.show', $this->partido))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $cedula = CedulaArbitral::query()->where('partido_id', $this->partido->id)->sole();
        $this->assertSame(CedulaArbitral::ORIGEN_PAPEL, $cedula->origen);
        $this->assertSame($this->capturista->id, $cedula->capturada_por_id);
        $this->assertNotNull($cedula->capturada_en);
        $this->assertSame([1, 0], [$cedula->goles_local, $cedula->goles_visitante]);
        $this->assertSame('Partido sin más novedades.', $cedula->informe_arbitral);
        $this->assertSame(CedulaArbitral::ABIERTA, $cedula->estado);

        $capitan = AlineacionPartido::query()->where('cedula_id', $cedula->id)->where('jugador_id', $goleador->id)->sole();
        $this->assertTrue($capitan->asistio);
        $this->assertTrue($capitan->es_capitan);
        $this->assertSame(9, $capitan->numero_camiseta);
        $this->assertSame(3, AlineacionPartido::query()->where('cedula_id', $cedula->id)->where('asistio', true)->count());

        $this->assertSame(['Gol', 'Amarilla', 'Roja'], EventoPartido::query()->where('cedula_id', $cedula->id)->orderBy('minuto')->pluck('tipo')->all());
        $this->assertSame(['Retraso de 10 minutos por lluvia'], IncidenciaPartido::query()->where('cedula_id', $cedula->id)->pluck('descripcion')->all());
        $this->assertDatabaseHas('auditoria_logs', ['tabla_afectada' => 'cedulas_arbitrales', 'registro_id' => $cedula->id]);

        // Después de guardar, el Capturista revisa la cédula en la pantalla de captura
        $this->actingAs($this->capturista)->get(route('cedulas.show', $this->partido))->assertOk()->assertSee('Capturar desde la cédula en papel');
    }

    public function test_reenviar_el_mismo_formulario_no_duplica_nada(): void
    {
        [$goleador] = $this->jugadores('local', 1);
        $datos = $this->formulario([
            'goles_local' => 1,
            'alineacion' => [$goleador->id => ['asistio' => '1']],
            'eventos' => [['jugador_id' => $goleador->id, 'tipo' => 'Gol', 'minuto' => '10']],
            'incidencias' => [['descripcion' => 'Incidencia única']],
        ]);

        $this->actingAs($this->capturista)->post(route('cedulas.fisica.store', $this->partido), $datos)->assertSessionHasNoErrors();
        $this->actingAs($this->capturista)->post(route('cedulas.fisica.store', $this->partido), $datos)->assertSessionHasNoErrors();

        $this->assertSame(1, EventoPartido::query()->count());
        $this->assertSame(1, IncidenciaPartido::query()->count());
        $this->assertSame(1, CedulaArbitral::query()->count());
    }

    /** @return array<string, array{array<string, mixed>, string, string}> */
    public static function datosInvalidos(): array
    {
        return [
            'minuto fuera de rango' => [['eventos' => [['jugador_id' => 'LOCAL', 'tipo' => 'Gol', 'minuto' => '200']]], 'eventos.0.minuto', 'El minuto debe ser un número entero entre 0 y 150.'],
            'evento sin tipo' => [['eventos' => [['jugador_id' => 'LOCAL', 'tipo' => '', 'minuto' => '10']]], 'eventos.0.tipo', 'Evento 1: elige si es gol, amarilla o roja.'],
            'evento sin jugador' => [['eventos' => [['jugador_id' => '', 'tipo' => 'Roja', 'minuto' => '10']]], 'eventos.0.jugador_id', 'Evento 1: elige al jugador.'],
            'autogol en una tarjeta' => [['eventos' => [['jugador_id' => 'LOCAL', 'tipo' => 'Amarilla', 'autogol' => '1']]], 'eventos.0.autogol', 'Evento 1: Solo un gol puede marcarse como autogol.'],
            'jugador de otro partido' => [['eventos' => [['jugador_id' => 'AJENO', 'tipo' => 'Gol']]], 'eventos.0.jugador_id', 'Evento 1: El jugador no pertenece a los equipos de este partido.'],
            'marcador negativo' => [['goles_local' => '-1'], 'goles_local', 'Los goles de cada equipo deben estar entre 0 y 99.'],
            'sin marcador' => [['goles_visitante' => ''], 'goles_visitante', 'Escribe los goles del equipo visitante; si no anotó, escribe 0.'],
            'incidencia sin descripción' => [['incidencias' => [['descripcion' => '', 'minuto' => '30']]], 'incidencias.0.descripcion', 'Incidencia 1: describe lo que pasó.'],
            'incidencia de otro equipo' => [['incidencias' => [['descripcion' => 'Riña', 'equipo_id' => '999999']]], 'incidencias.0.equipo_id', 'Incidencia 1: El equipo no juega este partido.'],
            'camiseta fuera de rango' => [['alineacion' => ['LOCAL' => ['asistio' => '1', 'numero_camiseta' => '1000']]], 'alineacion.LOCAL.numero_camiseta', 'El número de camiseta debe estar entre 0 y 999.'],
            'capitán de otro equipo' => [['capitanes' => ['LOCAL_EQUIPO' => 'VISITANTE']], 'capitanes.LOCAL_EQUIPO', 'El capitán debe ser un jugador de su equipo.'],
        ];
    }

    // Criterio 2: un dato inválido o incompleto muestra un error claro y no guarda nada
    #[DataProvider('datosInvalidos')]
    public function test_un_dato_invalido_muestra_un_error_claro_y_no_guarda_nada(array $cambios, string $campo, string $mensaje): void
    {
        [$local] = $this->jugadores('local', 1);
        [$visitante] = $this->jugadores('visitante', 1);
        $ajeno = Jugador::query()->where('equipo_id', $this->partidoConArbitro()->equipo_local_id)->firstOrFail();
        $sustituir = ['LOCAL_EQUIPO' => (string) $this->partido->equipo_local_id, 'LOCAL' => (string) $local->id, 'VISITANTE' => (string) $visitante->id, 'AJENO' => (string) $ajeno->id];
        $reemplazar = function (mixed $valor) use (&$reemplazar, $sustituir): mixed {
            if (! is_array($valor)) {
                return is_string($valor) ? ($sustituir[$valor] ?? $valor) : $valor;
            }

            $resultado = [];
            foreach ($valor as $clave => $interno) {
                $resultado[is_string($clave) ? ($sustituir[$clave] ?? $clave) : $clave] = $reemplazar($interno);
            }

            return $resultado;
        };
        $campo = strtr($campo, $sustituir);

        $datos = $this->formulario($reemplazar($cambios));

        $this->actingAs($this->capturista)->from(route('cedulas.fisica.create', $this->partido))
            ->post(route('cedulas.fisica.store', $this->partido), $datos)
            ->assertRedirect(route('cedulas.fisica.create', $this->partido))
            ->assertSessionHasErrors([$campo => $mensaje]);

        $this->assertDatabaseCount('cedulas_arbitrales', 0);
        $this->assertDatabaseCount('eventos_partido', 0);

        // La página que ve el Capturista: el formulario con el error y lo que había escrito, sin un 500
        $this->actingAs($this->capturista)->from(route('cedulas.fisica.create', $this->partido))->followingRedirects()
            ->post(route('cedulas.fisica.store', $this->partido), $datos)
            ->assertOk()
            ->assertSee('No se guardó la cédula. Corrige lo siguiente:')
            ->assertSee($mensaje)
            ->assertSee('value="'.$datos['clave_envio'].'"', false);
    }

    public function test_un_error_en_una_fila_no_deja_la_cedula_a_medias_si_ya_estaba_abierta(): void
    {
        app(CedulaService::class)->abrir($this->partido);
        [$goleador] = $this->jugadores('local', 1);

        $this->actingAs($this->capturista)->post(route('cedulas.fisica.store', $this->partido), $this->formulario([
            'goles_local' => 3,
            'eventos' => [
                ['jugador_id' => $goleador->id, 'tipo' => 'Gol', 'minuto' => '5'],
                ['jugador_id' => $goleador->id, 'tipo' => 'Amarilla', 'autogol' => '1'],
            ],
        ]))->assertSessionHasErrors('eventos.1.autogol');

        $cedula = CedulaArbitral::query()->sole();
        $this->assertSame(0, $cedula->goles_local);
        $this->assertSame(CedulaArbitral::ORIGEN_CAMPO, $cedula->origen);
        $this->assertSame(0, EventoPartido::query()->count());
    }

    // Lo que el cierre revisaría (HU-075) se avisa al guardar, sin bloquear
    public function test_avisa_si_los_goles_no_suman_el_marcador(): void
    {
        [$goleador] = $this->jugadores('local', 1);

        $this->actingAs($this->capturista)->post(route('cedulas.fisica.store', $this->partido), $this->formulario([
            'goles_local' => 2,
            'alineacion' => [$goleador->id => ['asistio' => '1']],
            'eventos' => [['jugador_id' => $goleador->id, 'tipo' => 'Gol']],
        ]))->assertSessionHasNoErrors()->assertSessionHas('hallazgos', fn (array $hallazgos): bool => collect($hallazgos)->contains('tipo', 'marcador'));

        $this->actingAs($this->capturista)->get(route('cedulas.show', $this->partido))->assertOk()->assertSee('Revisa antes de cerrar la cédula:');
    }

    public function test_no_se_captura_en_una_cedula_cerrada_ni_en_un_partido_cancelado(): void
    {
        $cedula = app(CedulaService::class)->abrir($this->partido);
        $cedula->update(['estado' => CedulaArbitral::CERRADA]);

        $this->actingAs($this->capturista)->get(route('cedulas.fisica.create', $this->partido))->assertOk()->assertSee('ya está cerrada');
        $this->actingAs($this->capturista)->post(route('cedulas.fisica.store', $this->partido), $this->formulario([]))
            ->assertSessionHasErrors(['cedula' => 'La cédula está cerrada: ya no se puede modificar.']);

        $cancelado = $this->partidoConArbitro();
        $cancelado->forceFill(['estado' => 'cancelado'])->save();
        $this->actingAs($this->capturista)->post(route('cedulas.fisica.store', $cancelado), $this->formulario([]))
            ->assertSessionHasErrors(['estado' => 'La cédula solo se abre y se captura cuando el partido está programado, confirmado o en juego.']);
        $this->assertSame(0, CedulaArbitral::query()->where('partido_id', $cancelado->id)->count());
    }

    public function test_un_arreglo_en_un_campo_de_texto_no_provoca_un_error_500(): void
    {
        $this->actingAs($this->capturista)->from(route('cedulas.fisica.create', $this->partido))
            ->post(route('cedulas.fisica.store', $this->partido), $this->formulario(['informe_arbitral' => ['x'], 'goles_local' => ['x'], 'incidencias' => [['descripcion' => ['x'], 'minuto' => '5']]]))
            ->assertSessionHasErrors(['goles_local', 'incidencias.0.descripcion']);

        $this->actingAs($this->capturista)->from(route('cedulas.fisica.create', $this->partido))->followingRedirects()
            ->post(route('cedulas.fisica.store', $this->partido), ['clave_envio' => (string) Str::uuid(), 'informe_arbitral' => ['x'], 'goles_local' => ['x']])
            ->assertOk();
    }

    // Solo el Capturista (y el Administrador General); el árbitro captura desde el celular (RNF-29)
    public function test_solo_quien_captura_en_la_sede_transcribe_cedulas_en_papel(): void
    {
        $arbitro = $this->usuario('Árbitro en Campo', $this->partido->arbitro);
        $tesorero = $this->usuario('Tesorero');

        foreach ([$arbitro, $tesorero] as $usuario) {
            $this->actingAs($usuario)->get(route('cedulas.fisicas.index'))->assertForbidden();
            $this->actingAs($usuario)->get(route('cedulas.fisica.create', $this->partido))->assertForbidden();
            $this->actingAs($usuario)->post(route('cedulas.fisica.store', $this->partido), $this->formulario([]))->assertForbidden();
        }

        $this->actingAs($this->usuario('Administrador General'))->get(route('cedulas.fisica.create', $this->partido))->assertOk();
        $this->assertDatabaseCount('cedulas_arbitrales', 0);

        auth()->logout();
        $this->get(route('cedulas.fisicas.index'))->assertRedirect();
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function formulario(array $cambios): array
    {
        return array_replace(['clave_envio' => (string) Str::uuid(), 'goles_local' => '0', 'goles_visitante' => '0'], $cambios);
    }

    /** @return list<Jugador> */
    private function jugadores(string $lado, int $cuantos): array
    {
        $equipoId = $lado === 'local' ? $this->partido->equipo_local_id : $this->partido->equipo_visitante_id;

        return Jugador::query()->where('equipo_id', $equipoId)->where('estado_elegibilidad', 'Activo')->orderBy('dorsal')->limit($cuantos)->get()->all();
    }
}
