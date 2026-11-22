<?php

namespace Tests\Feature;

use App\Models\AlineacionPartido;
use App\Models\AuditoriaLog;
use App\Models\CedulaArbitral;
use App\Models\EnvioCedula;
use App\Models\EventoPartido;
use App\Models\IncidenciaPartido;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\User;
use App\Services\CapturaCedulaFisicaService;
use App\Services\CedulaService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
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
            ->assertRedirect(route('cedulas.fisica.create', $this->partido))
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

        // Después de guardar, el Capturista revisa lo transcrito y desde ahí puede corregirlo o cerrarlo
        $this->actingAs($this->capturista)->get(route('cedulas.fisica.create', $this->partido))->assertOk()->assertSee('Cerrar la cédula en papel');
        $this->actingAs($this->capturista)->get(route('cedulas.show', $this->partido))->assertOk()->assertSee('Corregir o cerrar la cédula en papel');
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
        $this->actingAs($this->capturista)->post(route('cedulas.fisica.store', $this->partido), $datos)
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('success')
            ->assertSessionHas('warning');

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
            ->assertSessionHasErrors(['estado' => 'La cédula solo se abre y se captura cuando el partido está confirmado o en juego.']);
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

        foreach ([$arbitro, $this->usuario('Tesorero'), $this->usuario('Delegado del Club'), $this->usuario('Jugador')] as $usuario) {
            $this->actingAs($usuario)->get(route('cedulas.fisicas.index'))->assertForbidden();
            $this->actingAs($usuario)->get(route('cedulas.fisica.create', $this->partido))->assertForbidden();
            $this->actingAs($usuario)->post(route('cedulas.fisica.store', $this->partido), $this->formulario([]))
                ->assertForbidden()
                ->assertSee('Solo el Capturista puede transcribir cédulas en papel.');
            $this->actingAs($usuario)->post(route('cedulas.fisica.cerrar', $this->partido), ['confirmar' => '1'])->assertForbidden();
        }

        $this->assertDatabaseCount('cedulas_arbitrales', 0);

        // El Administrador General también transcribe
        $administrador = $this->usuario('Administrador General');
        $this->actingAs($administrador)->get(route('cedulas.fisica.create', $this->partido))->assertOk();
        $this->actingAs($administrador)->post(route('cedulas.fisica.store', $this->partido), $this->formulario(['goles_local' => '2']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('cedulas.fisica.create', $this->partido));
        $this->assertSame(2, CedulaArbitral::query()->where('partido_id', $this->partido->id)->sole()->goles_local);

        auth()->logout();
        $this->get(route('cedulas.fisicas.index'))->assertRedirect();
    }

    // Obligatorio 1 (#89): el mismo formulario con otros datos (por ejemplo, con «Atrás») no se aplica ni dice que se guardó
    public function test_reenviar_la_misma_clave_con_otros_datos_no_cambia_nada_ni_informa_exito(): void
    {
        [$goleador] = $this->jugadores('local', 1);
        $datos = $this->formulario([
            'goles_local' => '2',
            'goles_visitante' => '1',
            'alineacion' => [$goleador->id => ['asistio' => '1']],
            'eventos' => [['jugador_id' => $goleador->id, 'tipo' => 'Gol', 'minuto' => '12']],
            'incidencias' => [['descripcion' => 'Invasión de cancha']],
        ]);
        $this->actingAs($this->capturista)->post(route('cedulas.fisica.store', $this->partido), $datos)->assertSessionHas('success');

        $cambiados = array_replace($datos, [
            'goles_local' => '3',
            'eventos' => [['jugador_id' => $goleador->id, 'tipo' => 'Gol', 'minuto' => '15']],
            'incidencias' => [['descripcion' => 'Otra cosa']],
        ]);
        $this->actingAs($this->capturista)->post(route('cedulas.fisica.store', $this->partido), $cambiados)
            ->assertRedirect(route('cedulas.show', $this->partido))
            ->assertSessionMissing('success')
            ->assertSessionHas('warning', fn (string $aviso): bool => str_contains($aviso, 'ya se había guardado'));

        $cedula = CedulaArbitral::query()->sole();
        $this->assertSame([2, 1], [$cedula->goles_local, $cedula->goles_visitante]);
        $this->assertSame([12], EventoPartido::query()->pluck('minuto')->all());
        $this->assertSame(['Invasión de cancha'], IncidenciaPartido::query()->pluck('descripcion')->all());

        // El aviso se ve en la cédula
        $this->actingAs($this->capturista)->post(route('cedulas.fisica.store', $this->partido), $cambiados);
        $this->actingAs($this->capturista)->get(route('cedulas.show', $this->partido))->assertSee('ya se había guardado');
    }

    // Obligatorio 2 (#89): volver a guardar el formulario sin cambios no vuelve a guardar la alineación
    public function test_guardar_otra_vez_sin_cambios_no_toca_la_alineacion(): void
    {
        [$goleador, $companero] = $this->jugadores('local', 2);
        $alineacion = [$goleador->id => ['asistio' => '1', 'numero_camiseta' => '9'], $companero->id => ['asistio' => '1', 'numero_camiseta' => '5']];
        $capitanes = [$this->partido->equipo_local_id => (string) $goleador->id];

        $this->actingAs($this->capturista)->post(route('cedulas.fisica.store', $this->partido), $this->formulario(['alineacion' => $alineacion, 'capitanes' => $capitanes]))
            ->assertSessionHasNoErrors();
        $envios = EnvioCedula::query()->where('tipo', EnvioCedula::ALINEACION)->count();
        $auditorias = AuditoriaLog::query()->where('tabla_afectada', 'alineaciones_partido')->count();

        // El formulario se vuelve a abrir (clave nueva) y se envía con lo mismo
        $this->actingAs($this->capturista)->post(route('cedulas.fisica.store', $this->partido), $this->formulario(['alineacion' => $alineacion, 'capitanes' => $capitanes]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame($envios, EnvioCedula::query()->where('tipo', EnvioCedula::ALINEACION)->count());
        $this->assertSame($auditorias, AuditoriaLog::query()->where('tabla_afectada', 'alineaciones_partido')->count());

        // Un cambio real sí se guarda
        $alineacion[$companero->id]['numero_camiseta'] = '7';
        $this->actingAs($this->capturista)->post(route('cedulas.fisica.store', $this->partido), $this->formulario(['alineacion' => $alineacion, 'capitanes' => $capitanes]))
            ->assertSessionHasNoErrors();
        $this->assertSame(7, AlineacionPartido::query()->where('jugador_id', $companero->id)->sole()->numero_camiseta);
        $this->assertSame($envios + 1, EnvioCedula::query()->where('tipo', EnvioCedula::ALINEACION)->count());
    }

    // Respuesta 51 del PO: no se transcribe sobre una cédula que el árbitro ya capturó en campo; sus firmas se conservan
    public function test_no_transcribe_sobre_una_cedula_capturada_y_firmada_en_campo(): void
    {
        Storage::fake('local');
        [$goleador] = $this->jugadores('local', 1);
        $cedulas = app(CedulaService::class);
        $cedula = $cedulas->abrir($this->partido);
        $cedulas->guardarMarcador($cedula, 1, 0, (string) Str::uuid());
        foreach (['local', 'visitante'] as $lado) {
            Storage::disk('local')->put('cedulas/'.$cedula->id.'/firma-'.$lado.'.png', 'png');
        }
        $cedula->update(['firma_capitan_local' => 'cedulas/'.$cedula->id.'/firma-local.png', 'firma_capitan_visitante' => 'cedulas/'.$cedula->id.'/firma-visitante.png']);

        $this->actingAs($this->capturista)->get(route('cedulas.fisicas.index'))->assertOk()->assertDontSee(route('cedulas.fisica.create', $this->partido), false);
        $this->actingAs($this->capturista)->get(route('cedulas.fisica.create', $this->partido))
            ->assertOk()
            ->assertSee('El árbitro ya capturó esta cédula en campo')
            ->assertDontSee('name="clave_envio"', false);
        $this->actingAs($this->capturista)->get(route('cedulas.show', $this->partido))->assertOk()->assertDontSee('cédula en papel</a>', false);

        // El mismo formulario precargado que antes borraba las firmas
        $this->actingAs($this->capturista)->post(route('cedulas.fisica.store', $this->partido), $this->formulario([
            'goles_local' => '1',
            'alineacion' => [$goleador->id => ['asistio' => '0']],
        ]))->assertSessionHasErrors(['cedula' => CapturaCedulaFisicaService::CAPTURADA_EN_CAMPO]);

        $cedula->refresh();
        $this->assertSame(CedulaArbitral::ORIGEN_CAMPO, $cedula->origen);
        $this->assertNull($cedula->capturada_por_id);
        $this->assertNotNull($cedula->firma_capitan_local);
        $this->assertNotNull($cedula->firma_capitan_visitante);
        Storage::disk('local')->assertExists('cedulas/'.$cedula->id.'/firma-local.png');
    }

    // Respuesta 51: si el árbitro solo abrió la cédula y no capturó nada, el Capturista sí transcribe
    public function test_transcribe_sobre_una_cedula_abierta_sin_captura(): void
    {
        app(CedulaService::class)->abrir($this->partido);

        $this->actingAs($this->capturista)->get(route('cedulas.fisicas.index'))->assertSee(route('cedulas.fisica.create', $this->partido), false);
        $this->actingAs($this->capturista)->post(route('cedulas.fisica.store', $this->partido), $this->formulario(['goles_local' => '4']))
            ->assertSessionHasNoErrors();

        $cedula = CedulaArbitral::query()->sole();
        $this->assertSame(CedulaArbitral::ORIGEN_PAPEL, $cedula->origen);
        $this->assertSame(4, $cedula->goles_local);
    }

    public function test_un_jugador_de_otro_partido_en_la_alineacion_se_rechaza(): void
    {
        $ajeno = Jugador::query()->where('equipo_id', $this->partidoConArbitro()->equipo_local_id)->firstOrFail();

        $this->actingAs($this->capturista)->post(route('cedulas.fisica.store', $this->partido), $this->formulario([
            'alineacion' => [$ajeno->id => ['asistio' => '1']],
        ]))->assertSessionHasErrors(['alineacion.'.$ajeno->id => 'El jugador no pertenece a los equipos de este partido.']);

        $this->assertSame(0, AlineacionPartido::query()->where('jugador_id', $ajeno->id)->where('asistio', true)->count());
    }

    public function test_la_lista_muestra_las_cedulas_abiertas_y_oculta_las_cerradas(): void
    {
        $cerrado = $this->partidoConArbitro();
        app(CedulaService::class)->abrir($this->partido);
        app(CedulaService::class)->abrir($cerrado)->update(['estado' => CedulaArbitral::CERRADA]);

        $this->actingAs($this->capturista)->get(route('cedulas.fisicas.index'))
            ->assertOk()
            ->assertSee(route('cedulas.fisica.create', $this->partido), false)
            ->assertDontSee(route('cedulas.fisica.create', $cerrado), false);
    }

    // Recomendado (#89): en un partido donde no se puede guardar, un aviso en lugar del formulario y sin enlace
    public function test_un_partido_fuera_de_captura_muestra_un_aviso_sin_formulario(): void
    {
        foreach (['borrador', 'programado', 'finalizado', 'cancelado'] as $estado) {
            $this->partido->forceFill(['estado' => $estado])->save();

            $this->actingAs($this->capturista)->get(route('cedulas.fisica.create', $this->partido))
                ->assertOk()
                ->assertSee('La cédula solo se captura cuando el partido está confirmado o en juego')
                ->assertDontSee('name="clave_envio"', false);
            $this->actingAs($this->capturista)->get(route('cedulas.show', $this->partido))->assertOk()->assertDontSee('Capturar desde la cédula en papel');
        }
    }

    // Respuesta 50 del PO: el Capturista cierra la cédula en papel con el escaneo firmado; el partido queda finalizado
    public function test_el_capturista_cierra_la_cedula_en_papel_con_su_escaneo(): void
    {
        Storage::fake('local');
        [$goleador] = $this->jugadores('local', 1);
        $this->actingAs($this->capturista)->post(route('cedulas.fisica.store', $this->partido), $this->formulario([
            'goles_local' => '1',
            'alineacion' => [$goleador->id => ['asistio' => '1']],
            'eventos' => [['jugador_id' => $goleador->id, 'tipo' => 'Gol', 'minuto' => '30']],
        ]))->assertSessionHasNoErrors();

        // Sin escaneo ni confirmación no se cierra, y el error sale en la sección del cierre
        $this->actingAs($this->capturista)->from(route('cedulas.fisica.create', $this->partido))
            ->post(route('cedulas.fisica.cerrar', $this->partido), [])
            ->assertRedirect(route('cedulas.fisica.create', $this->partido))
            ->assertSessionHasErrorsIn('cierre', ['cedula_escaneada', 'confirmar']);
        $this->actingAs($this->capturista)->from(route('cedulas.fisica.create', $this->partido))->followingRedirects()
            ->post(route('cedulas.fisica.cerrar', $this->partido), ['confirmar' => '1'])
            ->assertOk()
            ->assertSee('No se cerró la cédula:')
            ->assertSee('Adjunta la foto o el escaneo de la cédula en papel');
        $this->actingAs($this->capturista)->post(route('cedulas.fisica.cerrar', $this->partido), [
            'cedula_escaneada' => UploadedFile::fake()->create('cedula.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            'confirmar' => '1',
        ])->assertSessionHasErrorsIn('cierre', ['cedula_escaneada' => 'El escaneo de la cédula debe ser una imagen JPG o PNG, o un PDF.']);
        $this->assertSame(CedulaArbitral::ABIERTA, CedulaArbitral::query()->sole()->estado);

        $this->actingAs($this->capturista)->post(route('cedulas.fisica.cerrar', $this->partido), [
            'cedula_escaneada' => UploadedFile::fake()->image('cedula.jpg', 800, 1100),
            'confirmar' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect(route('cedulas.show', $this->partido));

        $cedula = CedulaArbitral::query()->sole();
        $this->assertSame(CedulaArbitral::CERRADA, $cedula->estado);
        $this->assertSame($this->capturista->id, $cedula->cerrada_por_id);
        $this->assertNotNull($cedula->cedula_papel_ruta);
        Storage::disk('local')->assertExists((string) $cedula->cedula_papel_ruta);
        $this->assertSame(Partido::ESTADO_FINALIZADO, $this->partido->refresh()->estado);
        $this->assertDatabaseHas('auditoria_logs', ['tabla_afectada' => 'cedulas_arbitrales', 'registro_id' => $cedula->id, 'motivo' => 'Cierre de la cédula en papel del partido '.$this->partido->id]);

        // Se consulta el escaneo; ya no se captura ni se cierra otra vez
        $this->actingAs($this->capturista)->get(route('cedulas.show', $this->partido))->assertOk()->assertSee(route('cedulas.fisica.escaneo', $this->partido), false);
        $this->actingAs($this->capturista)->get(route('cedulas.fisica.escaneo', $this->partido))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->actingAs($this->usuario('Tesorero'))->get(route('cedulas.fisica.escaneo', $this->partido))->assertForbidden();
        $this->actingAs($this->capturista)->post(route('cedulas.fisica.cerrar', $this->partido), [
            'cedula_escaneada' => UploadedFile::fake()->image('otra.jpg'),
            'confirmar' => '1',
        ])->assertSessionHasErrorsIn('cierre', ['cedula' => 'La cédula está cerrada: ya no se puede modificar.']);
    }

    // La cédula capturada en campo la cierra el árbitro con las firmas, no el Capturista con un escaneo
    public function test_no_cierra_con_escaneo_una_cedula_de_campo(): void
    {
        Storage::fake('local');
        app(CedulaService::class)->abrir($this->partido);

        $this->actingAs($this->capturista)->post(route('cedulas.fisica.cerrar', $this->partido), [
            'cedula_escaneada' => UploadedFile::fake()->image('cedula.png'),
            'confirmar' => '1',
        ])->assertSessionHasErrorsIn('cierre', 'cedula');

        $this->assertSame(CedulaArbitral::ABIERTA, CedulaArbitral::query()->sole()->estado);
        $this->assertSame([], Storage::disk('local')->allFiles());
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
