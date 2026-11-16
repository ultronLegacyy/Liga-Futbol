<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
use App\Models\CedulaArbitral;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\User;
use App\Services\CedulaService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\CreaPartidoConCedula;
use Tests\TestCase;

// HU-075 (SCRUM-222 y SCRUM-225): reporte arbitral, firmas de los capitanes, anulaciones y cierre de la cédula
class CedulaCierreTest extends TestCase
{
    use CreaPartidoConCedula;
    use DatabaseTransactions;

    private CedulaService $cedulas;

    private Partido $partido;

    private CedulaArbitral $cedula;

    private User $arbitro;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(RoleAndPermissionSeeder::class);
        $this->cedulas = app(CedulaService::class);
        $this->partido = $this->partidoConArbitro();
        $this->cedula = $this->cedulas->abrir($this->partido);
        $this->arbitro = $this->usuario('Árbitro en Campo', $this->partido->arbitro);
    }

    // Criterio 1: la firma del capitán va al disco privado; la cédula guarda su ruta y la auditoría la registra una vez
    public function test_el_arbitro_recaba_la_firma_del_capitan(): void
    {
        $this->capitanes();
        $ultimoId = (int) AuditoriaLog::query()->max('id');

        $this->actingAs($this->arbitro)
            ->post(route('cedulas.firmar', [$this->partido, 'local']), ['firma' => $this->firma()])
            ->assertRedirect(route('cedulas.cierre', $this->partido))
            ->assertSessionHas('success', 'Firma guardada.');

        $ruta = (string) $this->cedula->refresh()->firma_capitan_local;
        $this->assertStringStartsWith('cedulas/'.$this->cedula->id.'/firma-local-', $ruta);
        Storage::disk('local')->assertExists($ruta);
        $this->assertSame(1, AuditoriaLog::query()->where('id', '>', $ultimoId)->count());

        $this->get(route('cedulas.firma', [$this->partido, 'local']))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('cedulas.firma', [$this->partido, 'visitante']))->assertNotFound();

        // Volver a firmar reemplaza el archivo anterior
        $this->post(route('cedulas.firmar', [$this->partido, 'local']), ['firma' => $this->firma()]);
        Storage::disk('local')->assertMissing($ruta);
        Storage::disk('local')->assertExists((string) $this->cedula->refresh()->firma_capitan_local);
    }

    public function test_no_se_firma_sin_capitan_marcado(): void
    {
        $this->actingAs($this->arbitro)
            ->from(route('cedulas.cierre', $this->partido))
            ->post(route('cedulas.firmar', [$this->partido, 'visitante']), ['firma' => $this->firma()])
            ->assertSessionHasErrors(['firma' => 'Marca al capitán de Equipo Cédula 2 en la alineación antes de su firma.']);

        $this->assertNull($this->cedula->refresh()->firma_capitan_visitante);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    #[DataProvider('firmasInvalidas')]
    public function test_rechaza_una_firma_que_no_es_un_png_valido(string $firma, string $mensaje): void
    {
        $this->capitanes();

        $this->actingAs($this->arbitro)
            ->post(route('cedulas.firmar', [$this->partido, 'local']), ['firma' => $firma])
            ->assertSessionHasErrors(['firma' => $mensaje]);

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function firmasInvalidas(): array
    {
        return [
            'texto en base64' => ['data:image/png;base64,'.base64_encode('no es una imagen'), 'La firma no es una imagen PNG válida de hasta 300 KB.'],
            'otro formato' => ['data:image/jpeg;base64,'.base64_encode('jpeg'), 'La firma debe ser una imagen PNG.'],
            'png diminuto' => ['data:image/png;base64,'.base64_encode(self::png(10, 10)), 'La firma no es una imagen PNG válida de hasta 300 KB.'],
            'vacía' => ['', 'Falta la firma del capitán.'],
        ];
    }

    // Criterio 2: no se cierra sin las dos firmas
    public function test_no_se_cierra_sin_las_dos_firmas(): void
    {
        $this->cedulaValida();
        $this->actingAs($this->arbitro);

        $this->post(route('cedulas.cerrar', $this->partido), ['confirmar' => '1'])
            ->assertSessionHasErrors(['firmas' => 'No se puede cerrar la cédula sin la firma de los dos capitanes. Falta: el capitán de Equipo Cédula 1 y el capitán de Equipo Cédula 2.']);

        $this->post(route('cedulas.firmar', [$this->partido, 'local']), ['firma' => $this->firma()]);
        $this->post(route('cedulas.cerrar', $this->partido), ['confirmar' => '1'])
            ->assertSessionHasErrors(['firmas' => 'No se puede cerrar la cédula sin la firma de los dos capitanes. Falta: el capitán de Equipo Cédula 2.']);

        $this->assertSame(CedulaArbitral::ABIERTA, $this->cedula->refresh()->estado);
    }

    // Criterio 4: con algún hallazgo de la validación no se cierra, aunque estén las dos firmas
    public function test_no_se_cierra_si_la_validacion_encuentra_algo(): void
    {
        $this->cedulaValida();
        $this->firmarAmbos();
        // Un gol de más en el marcador: los goles ya no suman, y las firmas se borran por el cambio
        $this->cedulas->guardarMarcador($this->cedula, 2, 0, (string) Str::uuid());
        $this->firmarAmbos();

        $this->actingAs($this->arbitro)
            ->post(route('cedulas.cerrar', $this->partido), ['confirmar' => '1'])
            ->assertSessionHasErrors(['validacion' => 'Los goles registrados de Equipo Cédula 1 (1) no suman su marcador (2).']);

        $this->assertSame(CedulaArbitral::ABIERTA, $this->cedula->refresh()->estado);
    }

    // Criterio 4: el resumen para confirmar muestra los datos, la revisión y las firmas
    public function test_el_resumen_muestra_lo_que_se_va_a_cerrar(): void
    {
        $this->cedulaValida();
        $this->cedulas->guardarInforme($this->cedula, 'Partido sin novedades.', (string) Str::uuid());

        $this->actingAs($this->arbitro)
            ->get(route('cedulas.cierre', $this->partido))
            ->assertOk()
            ->assertSee('Resumen')
            ->assertSee('Equipo Cédula 1: 1 asistente')
            ->assertSee('Gol · Equipo 1, Jugador 1')
            ->assertSee('Partido sin novedades.')
            ->assertSee('No se encontraron problemas.')
            ->assertSee('Faltan firmas: no se puede cerrar.');

        $this->firmarAmbos();
        $this->get(route('cedulas.cierre', $this->partido))
            ->assertDontSee('Faltan firmas')
            ->assertSee('Firmada.')
            ->assertSee(route('cedulas.firma', [$this->partido, 'visitante']));
    }

    // El cierre válido queda cerrado, con quién y cuándo, y una sola entrada en la auditoría
    public function test_el_cierre_valido_guarda_todo_y_la_cedula_ya_no_se_edita(): void
    {
        $this->cedulaValida();
        $this->firmarAmbos();
        $ultimoId = (int) AuditoriaLog::query()->max('id');
        $ahora = $this->freezeSecond();

        $this->actingAs($this->arbitro)
            ->post(route('cedulas.cerrar', $this->partido), ['confirmar' => '1'])
            ->assertRedirect(route('cedulas.show', $this->partido))
            ->assertSessionHas('success', 'Cédula cerrada: ya no se puede modificar.');

        $cedula = $this->cedula->refresh();
        $this->assertSame(CedulaArbitral::CERRADA, $cedula->estado);
        $this->assertSame($this->arbitro->id, (int) $cedula->cerrada_por_id);
        $this->assertSame($ahora->format('Y-m-d H:i:s'), $cedula->cerrada_en?->format('Y-m-d H:i:s'));
        $this->assertSame(['cedulas_arbitrales actualizar'], AuditoriaLog::query()->where('id', '>', $ultimoId)->get()->map(fn ($log) => $log->tabla_afectada.' '.$log->accion)->all());
        $this->assertStringStartsWith('Cierre de la cédula arbitral', (string) AuditoriaLog::query()->latest('id')->value('motivo'));

        // Ya cerrada: ni captura, ni firmas, ni anulaciones, ni otro cierre
        $evento = $cedula->eventos()->firstOrFail();
        $this->putJson(route('cedulas.marcador', $this->partido), ['clave_envio' => (string) Str::uuid(), 'goles_local' => 3, 'goles_visitante' => 0])
            ->assertJsonValidationErrors(['cedula' => 'La cédula está cerrada: ya no se puede modificar.']);
        $this->post(route('cedulas.firmar', [$this->partido, 'local']), ['firma' => $this->firma()])->assertSessionHasErrors('cedula');
        $this->delete(route('cedulas.eventos.anular', [$this->partido, $evento]), ['motivo' => 'Error de captura'])->assertSessionHasErrors('cedula');
        $this->post(route('cedulas.cerrar', $this->partido), ['confirmar' => '1'])->assertSessionHasErrors('cedula');
        $this->get(route('cedulas.cierre', $this->partido))->assertRedirect(route('cedulas.show', $this->partido));
        $this->get(route('cedulas.show', $this->partido))->assertSee('La cédula está cerrada: solo se consulta.')->assertSee(route('cedulas.firma', [$this->partido, 'local']));
        $this->assertSame(1, $cedula->refresh()->goles_local);
    }

    // RNF-22: si algo falla a mitad del cierre, no se guarda nada
    public function test_el_cierre_es_atomico(): void
    {
        $this->cedulaValida();
        $this->firmarAmbos();
        $ultimoId = (int) AuditoriaLog::query()->max('id');
        CedulaArbitral::updated(function (CedulaArbitral $cedula): void {
            if ($cedula->estado === CedulaArbitral::CERRADA) {
                throw new RuntimeException('Falla simulada después de guardar el estado.');
            }
        });

        try {
            $this->actingAs($this->arbitro)->withoutExceptionHandling()->post(route('cedulas.cerrar', $this->partido), ['confirmar' => '1']);
            $this->fail('Se esperaba la falla simulada.');
        } catch (RuntimeException $error) {
            $this->assertSame('Falla simulada después de guardar el estado.', $error->getMessage());
        }

        $cedula = $this->cedula->refresh();
        $this->assertSame(CedulaArbitral::ABIERTA, $cedula->estado);
        $this->assertNull($cedula->cerrada_por_id);
        $this->assertNull($cedula->cerrada_en);
        $this->assertSame(0, AuditoriaLog::query()->where('id', '>', $ultimoId)->count());
    }

    public function test_cerrar_exige_confirmar_el_resumen(): void
    {
        $this->cedulaValida();
        $this->firmarAmbos();

        $this->actingAs($this->arbitro)
            ->post(route('cedulas.cerrar', $this->partido))
            ->assertSessionHasErrors(['confirmar' => 'Confirma que revisaste el resumen antes de cerrar la cédula.']);

        $this->assertSame(CedulaArbitral::ABIERTA, $this->cedula->refresh()->estado);
    }

    // Decisión de Daniel: cualquier cambio después de firmar borra las dos firmas y sus archivos
    #[DataProvider('cambios')]
    public function test_un_cambio_despues_de_firmar_borra_las_dos_firmas(string $cambio): void
    {
        $this->cedulaValida();
        $this->firmarAmbos();
        $rutas = [(string) $this->cedula->refresh()->firma_capitan_local, (string) $this->cedula->firma_capitan_visitante];
        $jugador = $this->activo('local');
        $ultimoId = (int) AuditoriaLog::query()->max('id');

        match ($cambio) {
            'marcador' => $this->cedulas->guardarMarcador($this->cedula, 3, 0, (string) Str::uuid()),
            'alineacion' => $this->cedulas->guardarAlineacion($this->cedula, $jugador->id, ['numero_camiseta' => 77], (string) Str::uuid()),
            'evento' => $this->cedulas->registrarEvento($this->cedula, ['jugador_id' => $jugador->id, 'tipo' => 'Amarilla'], (string) Str::uuid()),
            'incidencia' => $this->cedulas->registrarIncidencia($this->cedula, ['descripcion' => 'Lluvia'], (string) Str::uuid()),
            'informe' => $this->cedulas->guardarInforme($this->cedula, 'Reporte nuevo', (string) Str::uuid()),
            default => $this->cedulas->anularEvento($this->cedula, (int) $this->cedula->eventos()->value('id'), 'Gol mal anotado'),
        };

        $cedula = $this->cedula->refresh();
        $this->assertNull($cedula->firma_capitan_local);
        $this->assertNull($cedula->firma_capitan_visitante);
        Storage::disk('local')->assertMissing($rutas);
        // La operación sigue dejando a lo más una entrada en la auditoría
        $this->assertLessThanOrEqual(1, AuditoriaLog::query()->where('id', '>', $ultimoId)->count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function cambios(): array
    {
        return ['marcador' => ['marcador'], 'alineación' => ['alineacion'], 'evento' => ['evento'], 'incidencia' => ['incidencia'], 'reporte' => ['informe'], 'anulación' => ['anulacion']];
    }

    // Guardar el mismo marcador no es un cambio: las firmas se conservan
    public function test_repetir_el_mismo_marcador_no_borra_las_firmas(): void
    {
        $this->cedulaValida();
        $this->firmarAmbos();

        $this->cedulas->guardarMarcador($this->cedula, 1, 0, (string) Str::uuid());

        $this->assertNotNull($this->cedula->refresh()->firma_capitan_local);
    }

    // Anular un gol mal capturado, con motivo; queda en la auditoría como eliminación
    public function test_anular_un_evento_y_una_incidencia_con_motivo(): void
    {
        $this->cedulaValida();
        $evento = $this->cedula->eventos()->firstOrFail();
        $incidencia = $this->cedulas->registrarIncidencia($this->cedula, ['descripcion' => 'Riña'], (string) Str::uuid());
        $this->actingAs($this->arbitro);

        $this->delete(route('cedulas.eventos.anular', [$this->partido, $evento]), ['motivo' => ''])
            ->assertSessionHasErrors(['motivo' => 'Escribe el motivo de la anulación.']);
        $this->assertModelExists($evento);

        $this->delete(route('cedulas.eventos.anular', [$this->partido, $evento]), ['motivo' => 'Gol anotado al jugador equivocado'])
            ->assertRedirect(route('cedulas.show', $this->partido))
            ->assertSessionHas('success', 'Evento anulado.');
        $this->assertModelMissing($evento);
        $log = AuditoriaLog::query()->where('tabla_afectada', 'eventos_partido')->where('accion', 'eliminar')->sole();
        $this->assertStringEndsWith('Gol anotado al jugador equivocado', (string) $log->motivo);

        $this->delete(route('cedulas.incidencias.anular', [$this->partido, $incidencia]), ['motivo' => 'Duplicada por error'])
            ->assertSessionHas('success', 'Incidencia anulada.');
        $this->assertModelMissing($incidencia);
    }

    // Un evento de otra cédula no se anula cambiando el identificador
    public function test_no_se_anula_un_evento_de_otro_partido(): void
    {
        $otro = $this->partidoConArbitro($this->partido->arbitro, '12:00:00');
        $otraCedula = $this->cedulas->abrir($otro);
        $ajeno = $this->cedulas->registrarEvento($otraCedula, ['jugador_id' => (int) $otraCedula->alineaciones()->value('jugador_id'), 'tipo' => 'Gol'], (string) Str::uuid());

        $this->actingAs($this->arbitro)
            ->delete(route('cedulas.eventos.anular', [$this->partido, $ajeno]), ['motivo' => 'Intento con otro ID'])
            ->assertNotFound();

        $this->assertModelExists($ajeno);
    }

    // Criterio 1: reporte arbitral por la cola, con clave de envío
    public function test_guarda_el_reporte_arbitral_sin_duplicar(): void
    {
        $datos = ['clave_envio' => (string) Str::uuid(), 'informe_arbitral' => 'Se expulsó al 7 por agresión.'];
        $this->actingAs($this->arbitro);

        $this->putJson(route('cedulas.informe', $this->partido), $datos)->assertOk()->assertExactJson(['informe_arbitral' => 'Se expulsó al 7 por agresión.']);
        $this->putJson(route('cedulas.informe', $this->partido), ['clave_envio' => (string) Str::uuid(), 'informe_arbitral' => 'Otro texto'])->assertOk();
        // Un reenvío viejo no pisa el reporte más nuevo
        $this->putJson(route('cedulas.informe', $this->partido), $datos)->assertOk()->assertExactJson(['informe_arbitral' => 'Otro texto']);

        $this->putJson(route('cedulas.informe', $this->partido), ['clave_envio' => (string) Str::uuid(), 'informe_arbitral' => str_repeat('a', 5001)])
            ->assertJsonValidationErrors(['informe_arbitral' => 'El reporte arbitral admite hasta 5000 caracteres.']);
        $this->get(route('cedulas.show', $this->partido))->assertSee('Reporte arbitral')->assertSee('Firmas y cierre');
    }

    // 403 en las rutas nuevas: otro árbitro, el Administrador (no tiene cedulas.firmar) y quien solo consulta
    #[DataProvider('quienesNoFirman')]
    public function test_solo_el_arbitro_asignado_firma_y_cierra(string $rol, bool $otroArbitro, string $mensaje): void
    {
        $this->capitanes();
        $usuario = $this->usuario($rol, $otroArbitro ? $this->arbitro() : null);
        $this->actingAs($usuario);

        $this->get(route('cedulas.cierre', $this->partido))->assertForbidden()->assertSee($mensaje);
        $this->post(route('cedulas.firmar', [$this->partido, 'local']), ['firma' => $this->firma()])->assertForbidden();
        $this->post(route('cedulas.cerrar', $this->partido), ['confirmar' => '1'])->assertForbidden();

        $this->assertNull($this->cedula->refresh()->firma_capitan_local);
        $this->assertSame(CedulaArbitral::ABIERTA, $this->cedula->estado);
    }

    /**
     * @return array<string, array{string, bool, string}>
     */
    public static function quienesNoFirman(): array
    {
        return [
            'otro árbitro' => ['Árbitro en Campo', true, 'Solo el árbitro asignado a este partido puede acceder a su cédula.'],
            'Administrador General' => ['Administrador General', false, 'No cuenta con privilegios para recabar firmas ni cerrar cédulas arbitrales.'],
            'Capturista' => ['Capturista', false, 'No cuenta con privilegios para recabar firmas ni cerrar cédulas arbitrales.'],
            'Tribunal de Penas' => ['Tribunal de Penas', false, 'No cuenta con privilegios para recabar firmas ni cerrar cédulas arbitrales.'],
        ];
    }

    // Quien solo consulta ve la firma, pero no anula; otro árbitro no ve la imagen
    public function test_permisos_de_la_imagen_y_de_las_anulaciones(): void
    {
        $this->cedulaValida();
        $this->firmarAmbos();
        $evento = $this->cedula->eventos()->firstOrFail();

        $this->actingAs($this->usuario('Tribunal de Penas'));
        $this->get(route('cedulas.firma', [$this->partido, 'local']))->assertOk();
        $this->delete(route('cedulas.eventos.anular', [$this->partido, $evento]), ['motivo' => 'Sin permiso'])
            ->assertForbidden()
            ->assertSee('No cuenta con privilegios para capturar cédulas arbitrales.');

        $this->actingAs($this->usuario('Árbitro en Campo', $this->arbitro()));
        $this->get(route('cedulas.firma', [$this->partido, 'local']))->assertForbidden();
        $this->get('/partidos/'.$this->partido->id.'/cedula/firmas/otro')->assertNotFound();

        $this->assertModelExists($evento);
    }

    // Capitán y asistencia de los dos equipos, un gol local y el marcador 1-0: la validación no encuentra nada
    private function cedulaValida(): void
    {
        $this->capitanes();
        $this->cedulas->registrarEvento($this->cedula, ['jugador_id' => $this->activo('local')->id, 'tipo' => 'Gol', 'minuto' => 10], (string) Str::uuid());
        $this->cedulas->guardarMarcador($this->cedula, 1, 0, (string) Str::uuid());
    }

    private function capitanes(): void
    {
        foreach (['local', 'visitante'] as $lado) {
            $this->cedulas->guardarAlineacion($this->cedula, $this->activo($lado)->id, ['asistio' => true, 'es_capitan' => true], (string) Str::uuid());
        }
    }

    private function firmarAmbos(): void
    {
        foreach (['local', 'visitante'] as $lado) {
            $this->actingAs($this->arbitro)
                ->post(route('cedulas.firmar', [$this->partido, $lado]), ['firma' => $this->firma()])
                ->assertSessionHasNoErrors();
        }
    }

    private function activo(string $lado): Jugador
    {
        return Jugador::query()
            ->where('equipo_id', $lado === 'local' ? $this->partido->equipo_local_id : $this->partido->equipo_visitante_id)
            ->where('estado_elegibilidad', 'Activo')
            ->orderBy('id')
            ->firstOrFail();
    }

    private function firma(): string
    {
        return 'data:image/png;base64,'.base64_encode(self::png(320, 160));
    }

    // PNG blanco hecho a mano (sin GD), como el que produce el recuadro de firma del navegador
    private static function png(int $ancho, int $alto): string
    {
        $bloque = fn (string $tipo, string $datos): string => pack('N', strlen($datos)).$tipo.$datos.pack('N', crc32($tipo.$datos));
        $filas = str_repeat("\x00".str_repeat("\xff\xff\xff", $ancho), $alto);

        return "\x89PNG\r\n\x1a\n"
            .$bloque('IHDR', pack('NNCCCCC', $ancho, $alto, 8, 2, 0, 0, 0))
            .$bloque('IDAT', (string) gzcompress($filas))
            .$bloque('IEND', '');
    }
}
