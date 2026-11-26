<?php

namespace Tests\Feature;

use App\Models\CargoFinanciero;
use App\Models\Categoria;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Jugador;
use App\Models\Partido;
use App\Services\CedulaService;
use App\Services\ControlSancionesDeportivasService;
use App\Services\ElegibilidadJugadorService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Concerns\CreaPartidoConCedula;
use Tests\TestCase;

// HU-019 (SCRUM-34, RF-04.4, RNF-02, RNF-43): elegibilidad del jugador antes de participar
class ElegibilidadJugadorTest extends TestCase
{
    use CreaPartidoConCedula;
    use DatabaseTransactions;

    private Partido $partido;

    private ElegibilidadJugadorService $elegibilidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->partido = $this->partidoConArbitro();
        $this->elegibilidad = app(ElegibilidadJugadorService::class);
    }

    // CA2 (RNF-02): los cuatro casos de la historia, y el habilitado, dan el resultado correcto
    public function test_cada_caso_da_el_resultado_correcto(): void
    {
        $this->assertSame(ElegibilidadJugadorService::HABILITADO, $this->elegibilidad->evaluar($this->activo())['estado']);

        $casos = [
            'suspendido' => [$this->suspendido(), 'HU-018 · estado del jugador'],
            'cuota pendiente' => [$this->conCuota('Pendiente'), 'RF-13.1 · HU-050/HU-051 cuota cubierta'],
            'registro incompleto' => [$this->sinComprobante(), 'HU-016 · documentos obligatorios del registro'],
            'categoría superior' => [$this->enCategoriaSuperior(), 'RF-04.4 · salto de una categoría'],
        ];

        foreach ($casos as $caso => [$jugador, $regla]) {
            $resultado = $this->elegibilidad->evaluar($jugador);
            $this->assertSame(ElegibilidadJugadorService::BLOQUEADO, $resultado['estado'], "Caso {$caso}");
            $this->assertContains($regla, array_column($resultado['motivos'], 'regla'), "Caso {$caso}");
        }
    }

    // CA2: con la cuota ya pagada el jugador sí está habilitado
    public function test_con_la_cuota_cubierta_esta_habilitado(): void
    {
        $this->assertSame(ElegibilidadJugadorService::HABILITADO, $this->elegibilidad->evaluar($this->conCuota('Pagado'))['estado']);
    }

    // CA1: al intentar alinearlo se bloquea y se muestra el motivo y la regla que lo origina
    public function test_al_alinearlo_se_bloquea_con_motivo_y_regla(): void
    {
        $cedula = app(CedulaService::class)->abrir($this->partido);
        $jugador = $this->sinComprobante();

        try {
            app(CedulaService::class)->guardarAlineacion($cedula, $jugador->id, ['asistio' => true], (string) Str::uuid());
            $this->fail('Se alineó a un jugador con el registro incompleto.');
        } catch (ValidationException $error) {
            $mensaje = (string) collect($error->errors())->flatten()->first();
            $this->assertStringContainsString('no se puede alinear', $mensaje);
            $this->assertStringContainsString('Registro incompleto: falta el comprobante de domicilio.', $mensaje);
            $this->assertStringContainsString('(regla: HU-016 · documentos obligatorios del registro)', $mensaje);
        }

        $this->assertFalse((bool) $cedula->alineaciones()->where('jugador_id', $jugador->id)->value('asistio'));
    }

    // CA3 (RNF-43): con datos incompletos queda «no verificado» y nunca se habilita
    public function test_sin_datos_para_decidir_queda_no_verificado(): void
    {
        $jugador = $this->activo();
        $jugador->persona->forceFill(['curp' => ''])->save();

        $resultado = $this->elegibilidad->evaluar($jugador);

        $this->assertSame(ElegibilidadJugadorService::NO_VERIFICADO, $resultado['estado']);
        $this->assertStringContainsString('la CURP', $resultado['motivos'][0]['motivo']);
        $this->assertSame('RNF-43 · no verificado', $resultado['motivos'][0]['regla']);

        $this->expectException(ValidationException::class);
        $this->elegibilidad->exigirHabilitado($jugador);
    }

    // CA3 (RNF-43): si una revisión falla por un error interno, tampoco se habilita
    public function test_un_error_interno_no_habilita(): void
    {
        $falla = $this->createMock(ControlSancionesDeportivasService::class);
        $falla->method('obtenerSancionVigenteJugador')->willThrowException(new RuntimeException('Base de datos no disponible'));
        $this->app->instance(ControlSancionesDeportivasService::class, $falla);

        $resultado = app(ElegibilidadJugadorService::class)->evaluar($this->activo());

        $this->assertSame(ElegibilidadJugadorService::NO_VERIFICADO, $resultado['estado']);
        $this->assertStringContainsString('error interno', $resultado['motivos'][0]['motivo']);
    }

    // CA3: el árbitro ve en la cédula quién no es elegible y por qué, antes de marcar su asistencia
    public function test_el_arbitro_ve_el_aviso_en_la_cedula(): void
    {
        $arbitro = $this->arbitro();
        $partido = $this->partidoConArbitro($arbitro, '12:00:00');
        $partido->update(['estado' => 'confirmado']);
        app(CedulaService::class)->abrir($partido);
        $jugador = Jugador::query()->where('equipo_id', $partido->equipo_local_id)->where('estado_elegibilidad', 'Activo')->orderBy('id')->firstOrFail();
        $jugador->persona->documentos()->where('tipo', 'Comprobante')->delete();

        $this->actingAs($this->usuario('Árbitro en Campo', $arbitro))
            ->get(route('cedulas.show', $partido))
            ->assertOk()
            ->assertSee('data-elegibilidad="bloqueado"', false)
            ->assertSee('Registro incompleto: falta el comprobante de domicilio.');
    }

    // CA3: el Delegado de su club y el Secretario ven la elegibilidad del equipo; el de otro club no
    public function test_el_delegado_y_el_secretario_ven_la_elegibilidad_del_equipo(): void
    {
        $equipo = Equipo::query()->findOrFail($this->partido->equipo_local_id);
        $this->suspendido();

        $delegado = $this->usuario('Delegado del Club');
        $delegado->forceFill(['club_id' => $equipo->club_id])->save();
        $this->actingAs($delegado)
            ->get(route('equipos.elegibilidad', $equipo))
            ->assertOk()
            ->assertSee('no se pueden alinear todavía')
            ->assertSee('El jugador está suspendido en el padrón.')
            ->assertSee('Habilitado');

        $this->actingAs($this->usuario('Secretario'))->get(route('equipos.elegibilidad', $equipo))->assertOk();

        $otro = $this->usuario('Delegado del Club');
        $otro->forceFill(['club_id' => Club::create(['nombre' => 'Club ajeno '.Str::random(6)])->id])->save();
        $this->actingAs($otro)
            ->get(route('equipos.elegibilidad', $equipo))
            ->assertForbidden()
            ->assertSee('Solo el Secretario o la directiva del club consultan la elegibilidad de sus jugadores.');
    }

    private function activo(): Jugador
    {
        return Jugador::query()->where('equipo_id', $this->partido->equipo_local_id)->where('estado_elegibilidad', 'Activo')->orderBy('id')->firstOrFail();
    }

    private function suspendido(): Jugador
    {
        return Jugador::query()->where('equipo_id', $this->partido->equipo_local_id)->where('estado_elegibilidad', 'Suspendido')->firstOrFail();
    }

    private function conCuota(string $estado): Jugador
    {
        $jugador = $this->activo();
        $equipo = $jugador->equipo;

        CargoFinanciero::create([
            'club_id' => $equipo->club_id,
            'persona_id' => $jugador->persona_id,
            'temporada_id' => $equipo->grupo->temporada_id,
            'concepto' => 'Inscripcion',
            'monto' => 500,
            'vencimiento' => '2026-12-01',
            'estado' => $estado,
        ]);

        return $jugador;
    }

    private function sinComprobante(): Jugador
    {
        $jugador = $this->activo();
        $jugador->persona->documentos()->where('tipo', 'Comprobante')->delete();

        return $jugador;
    }

    // Inscrito en una categoría de nivel 1 y también en el equipo del partido, de nivel 3: sube dos categorías
    private function enCategoriaSuperior(): Jugador
    {
        $jugador = $this->activo();
        $grupoPartido = $jugador->equipo->grupo;
        $grupoPartido->categoria->update(['jerarquia_salto' => 3]);

        $categoriaBaja = Categoria::factory()->create(['temporada_id' => $grupoPartido->temporada_id, 'jerarquia_salto' => 1]);
        $grupoBajo = Grupo::factory()->create(['categoria_id' => $categoriaBaja->id]);
        $equipoBajo = Equipo::create(['club_id' => $jugador->equipo->club_id, 'grupo_id' => $grupoBajo->id, 'nombre' => 'Equipo de categoría baja']);
        Jugador::create(['persona_id' => $jugador->persona_id, 'equipo_id' => $equipoBajo->id, 'dorsal' => 30, 'estado_elegibilidad' => 'Activo']);

        return $jugador;
    }
}
