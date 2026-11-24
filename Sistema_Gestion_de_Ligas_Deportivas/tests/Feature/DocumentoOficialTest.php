<?php

namespace Tests\Feature;

use App\Models\AlineacionPartido;
use App\Models\CargoFinanciero;
use App\Models\CedulaArbitral;
use App\Models\Club;
use App\Models\EventoPartido;
use App\Models\IncidenciaPartido;
use App\Models\Jugador;
use App\Models\Pago;
use App\Models\Partido;
use App\Models\Temporada;
use App\Models\User;
use App\Services\DocumentoOficialService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Concerns\CreaPartidoConCedula;
use Tests\TestCase;

/**
 * HU-069 (SCRUM-79, RF-16.2, IH-03): cédula oficial, estado de cuenta por club y constancia de registro en PDF.
 */
class DocumentoOficialTest extends TestCase
{
    use CreaPartidoConCedula;
    use DatabaseTransactions;

    private User $tesorero;

    private Club $club;

    private Temporada $temporada;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->tesorero = $this->usuario('Tesorero');
        $this->temporada = Temporada::factory()->create(['nombre' => 'Temporada Documentos 2093', 'fecha_inicio' => '2093-01-01', 'fecha_fin' => '2093-06-30']);
        $this->club = Club::create(['nombre' => 'Club Documentos']);
    }

    // Criterio 2: el estado de cuenta refleja los cargos y los pagos del club
    public function test_el_estado_de_cuenta_refleja_cargos_y_pagos_del_club(): void
    {
        $inscripcion = $this->cargo($this->club, 'Inscripcion', '1000.00', '2093-02-01');
        Pago::factory()->create(['cargo_id' => $inscripcion->id, 'monto' => '400.00', 'metodo_pago' => 'Transferencia', 'referencia' => 'REF-ABC', 'validado_por_tesorero_id' => $this->tesorero->id]);
        Pago::factory()->create(['cargo_id' => $inscripcion->id, 'monto' => '100.50', 'metodo_pago' => 'Efectivo', 'referencia' => 'REF-XYZ']);
        $this->cargo($this->club, 'Arbitraje', '300.00', '2093-03-01');
        $this->cargo(Club::create(['nombre' => 'Otro Club']), 'Cancha', '9999.00', '2093-03-01');

        $estado = app(DocumentoOficialService::class)->estadoDeCuenta($this->club, $this->temporada);

        $this->assertSame(['cargado' => 130000, 'pagado' => 50050, 'saldo' => 79950], [
            'cargado' => $estado['cargado'], 'pagado' => $estado['pagado'], 'saldo' => $estado['saldo'],
        ]);
        $this->assertCount(2, $estado['cargos']);
        $this->assertCount(2, $estado['pagos']);

        $html = view('documentos.estado-cuenta', [
            'club' => $this->club, 'temporada' => $this->temporada, 'estado' => $estado,
            'folio' => 'EC-00001-20930101', 'generado' => '01/01/2093 10:00',
        ])->render();

        $this->assertStringContainsString('Estado de cuenta', $html);
        $this->assertStringContainsString('Temporada Documentos 2093', $html);
        foreach (['$1,300.00', '$500.50', '$799.50', 'Inscripción', 'Arbitraje', 'REF-ABC', 'REF-XYZ', 'Pendiente', 'EC-00001-20930101'] as $texto) {
            $this->assertStringContainsString($texto, $html);
        }
        $this->assertStringNotContainsString('$9,999.00', $html);
        $this->assertStringContainsString('@page', $html);
    }

    public function test_el_tesorero_descarga_el_estado_de_cuenta_en_pdf(): void
    {
        $this->cargo($this->club, 'Inscripcion', '500.00', '2093-02-01');

        $respuesta = $this->actingAs($this->tesorero)
            ->get(route('documentos.estado-cuenta', ['club' => $this->club, 'temporada_id' => $this->temporada->id]));

        $respuesta->assertOk()->assertDownload('estado-de-cuenta-club-documentos-temporada-documentos-2093.pdf');
        $this->assertSame('application/pdf', $respuesta->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', (string) $respuesta->getContent());
    }

    public function test_solo_quien_ve_reportes_financieros_descarga_el_estado_de_cuenta(): void
    {
        $ruta = route('documentos.estado-cuenta', $this->club);

        $this->actingAs($this->usuario('Presidente'))->get($ruta)->assertOk();

        foreach (['Capturista', 'Secretario', 'Delegado del Club'] as $rol) {
            $this->actingAs($this->usuario($rol))->get($ruta)->assertForbidden();
        }

        auth()->logout();
        $this->get($ruta)->assertRedirect(route('login'));
    }

    // Criterio 1: la cédula oficial de un partido cerrado trae marcador, alineaciones e incidencias
    public function test_la_cedula_oficial_incluye_marcador_alineaciones_eventos_e_incidencias(): void
    {
        $partido = $this->partidoCerrado();
        $local = Jugador::query()->where('equipo_id', $partido->equipo_local_id)->with('persona')->orderBy('id')->firstOrFail();

        $datos = app(DocumentoOficialService::class)->cedulaOficial($partido);
        $html = view('documentos.cedula', [
            'partido' => $partido, 'cedula' => $datos['cedula'], 'firmas' => $datos['firmas'],
            'folio' => 'CED-00001-20261114', 'generado' => '14/11/2026 12:00',
        ])->render();

        foreach ([
            'Cédula arbitral oficial',
            $partido->local->nombre,
            $partido->visitante->nombre,
            '>3<',
            '>1<',
            trim($local->persona->nombre.' '.$local->persona->apellidos).' (C)',
            'Tarjeta amarilla',
            e("27'"),
            'Invasión de cancha',
            'Partido sin novedades graves.',
        ] as $texto) {
            $this->assertStringContainsString($texto, $html);
        }
        $this->assertNotEmpty($local->persona->curp);
        $this->assertStringNotContainsString((string) $local->persona->curp, $html);
    }

    public function test_la_cedula_oficial_se_descarga_solo_con_la_cedula_cerrada(): void
    {
        $partido = $this->partidoCerrado();

        $respuesta = $this->actingAs($this->tesorero)->get(route('documentos.cedula', $partido));
        $respuesta->assertOk()->assertDownload('cedula-oficial-partido-'.$partido->id.'.pdf');
        $this->assertStringStartsWith('%PDF', (string) $respuesta->getContent());

        $this->actingAs($this->usuario('Programador de Partidos'))->get(route('documentos.cedula', $partido))->assertOk();
        $this->actingAs($this->usuario('Delegado del Club'))->get(route('documentos.cedula', $partido))->assertForbidden();

        $abierto = $this->partidoConArbitro();
        CedulaArbitral::create(['partido_id' => $abierto->id, 'estado' => CedulaArbitral::ABIERTA, 'goles_local' => 0, 'goles_visitante' => 0]);
        $this->actingAs($this->tesorero)->get(route('documentos.cedula', $abierto))
            ->assertRedirect(route('cedulas.show', $abierto))
            ->assertSessionHasErrors(['cedula' => 'La cédula oficial solo se genera cuando la cédula del partido está cerrada.']);

        $sinCedula = $this->partidoConArbitro();
        $this->actingAs($this->tesorero)->get(route('documentos.cedula', $sinCedula))->assertSessionHasErrors('cedula');
    }

    public function test_la_constancia_de_registro_no_lleva_datos_sensibles(): void
    {
        $partido = $this->partidoConArbitro();
        $jugador = Jugador::query()->where('equipo_id', $partido->equipo_local_id)->with('persona')->orderBy('id')->firstOrFail();

        // La persona completa se lee aparte: la constancia carga solo nombre y apellidos
        $persona = $jugador->persona()->firstOrFail();

        $html = view('documentos.constancia', [
            'jugador' => app(DocumentoOficialService::class)->constancia($jugador),
            'folio' => 'CON-00001-20261124', 'generado' => '24/11/2026 12:00',
        ])->render();

        $this->assertStringContainsString('Constancia de registro', $html);
        $this->assertStringContainsString(trim($jugador->persona->nombre.' '.$jugador->persona->apellidos), $html);
        $this->assertStringContainsString($partido->local->nombre, $html);
        $this->assertStringContainsString('Activo', $html);
        $this->assertNotEmpty($persona->curp);
        $this->assertStringNotContainsString((string) $persona->curp, $html);
        $this->assertStringNotContainsString($persona->fecha_nacimiento->format('d/m/Y'), $html);

        $ruta = route('documentos.constancia', $jugador);
        $respuesta = $this->actingAs($this->tesorero)->get($ruta);
        $respuesta->assertOk();
        $this->assertSame('application/pdf', $respuesta->headers->get('Content-Type'));
        $this->actingAs($this->usuario('Secretario'))->get($ruta)->assertOk();
        $this->actingAs($this->usuario('Capturista'))->get($ruta)->assertForbidden();
    }

    private function partidoCerrado(): Partido
    {
        $partido = $this->partidoConArbitro();
        $cedula = CedulaArbitral::create([
            'partido_id' => $partido->id,
            'estado' => CedulaArbitral::CERRADA,
            'goles_local' => 3,
            'goles_visitante' => 1,
            'informe_arbitral' => 'Partido sin novedades graves.',
            'cerrada_en' => now(),
        ]);

        $jugadores = Jugador::query()->where('equipo_id', $partido->equipo_local_id)->orderBy('id')->get();
        foreach ($jugadores->take(3) as $indice => $jugador) {
            AlineacionPartido::create([
                'cedula_id' => $cedula->id,
                'jugador_id' => $jugador->id,
                'equipo_id' => $partido->equipo_local_id,
                'asistio' => true,
                'numero_camiseta' => $indice + 1,
                'es_capitan' => $indice === 0,
            ]);
        }

        EventoPartido::create(['cedula_id' => $cedula->id, 'jugador_id' => $jugadores->first()->id, 'tipo' => 'Gol', 'minuto' => 12]);
        EventoPartido::create(['cedula_id' => $cedula->id, 'jugador_id' => $jugadores->first()->id, 'tipo' => 'Amarilla', 'minuto' => 27]);
        IncidenciaPartido::create([
            'cedula_id' => $cedula->id,
            'equipo_id' => $partido->equipo_visitante_id,
            'minuto' => 80,
            'descripcion' => 'Invasión de cancha',
            'clave_envio' => (string) Str::uuid(),
        ]);

        return $partido->fresh();
    }

    private function cargo(Club $club, string $concepto, string $monto, string $vencimiento): CargoFinanciero
    {
        return CargoFinanciero::factory()->create([
            'club_id' => $club->id,
            'temporada_id' => $this->temporada->id,
            'concepto' => $concepto,
            'monto' => $monto,
            'vencimiento' => $vencimiento,
            'estado' => 'Pendiente',
        ]);
    }
}
