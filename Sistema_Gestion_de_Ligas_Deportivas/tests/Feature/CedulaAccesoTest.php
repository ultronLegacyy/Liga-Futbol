<?php

namespace Tests\Feature;

use App\Models\AlineacionPartido;
use App\Models\CedulaArbitral;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Persona;
use App\Services\CedulaService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreaPartidoConCedula;
use Tests\TestCase;

// HU-036 (SCRUM-217 y SCRUM-220): acceso a la cédula; el Árbitro en Campo solo ve sus partidos (criterio 5, RNF-29)
class CedulaAccesoTest extends TestCase
{
    use CreaPartidoConCedula;
    use DatabaseTransactions;

    private Partido $partido;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->partido = $this->partidoConArbitro();
    }

    // Un GET no crea nada: el árbitro ve la plantilla vigente y el botón para abrir la cédula
    public function test_un_get_no_crea_la_cedula_y_muestra_la_plantilla_vigente(): void
    {
        $this->actingAs($this->usuario('Árbitro en Campo', $this->partido->arbitro))
            ->get(route('cedulas.show', $this->partido))
            ->assertOk()
            ->assertSee('Equipo Cédula 1 vs. Equipo Cédula 2')
            ->assertSee('Plantilla vigente')
            ->assertSee('Abrir cédula')
            ->assertSee('Equipo 1, Jugador 4')
            // La baja no aparece en la plantilla
            ->assertDontSee('Equipo 1, Jugador 5');

        $this->assertFalse(CedulaArbitral::query()->where('partido_id', $this->partido->id)->exists());
    }

    // Criterio 3: el árbitro asignado abre la cédula por POST y la alineación sale precargada de las dos plantillas
    public function test_el_arbitro_asignado_abre_la_cedula_por_post_con_la_alineacion_precargada(): void
    {
        $this->actingAs($this->usuario('Árbitro en Campo', $this->partido->arbitro))
            ->post(route('cedulas.abrir', $this->partido))
            ->assertRedirect(route('cedulas.show', $this->partido))
            ->assertSessionHas('success', 'Cédula abierta: ya puedes capturar el partido.');

        $cedula = CedulaArbitral::query()->where('partido_id', $this->partido->id)->sole();
        $this->assertSame(CedulaArbitral::ABIERTA, $cedula->estado);
        // 4 por equipo: 3 activos y 1 suspendido; la baja no entra
        $this->assertSame(
            [$this->partido->equipo_local_id => 4, $this->partido->equipo_visitante_id => 4],
            $cedula->alineaciones()->get()->countBy('equipo_id')->all()
        );
        $this->assertSame([1, 2, 3, 4], $cedula->alineaciones()->where('equipo_id', $this->partido->equipo_local_id)->orderBy('numero_camiseta')->pluck('numero_camiseta')->all());
        $this->assertFalse($cedula->alineaciones()->where('asistio', true)->exists());

        // Ya abierta, el GET muestra la pantalla de captura
        $this->get(route('cedulas.show', $this->partido))->assertOk()->assertSee('Registrar evento')->assertSee('Todo guardado');

        // Abrirla otra vez no crea otra cédula ni duplica la alineación
        $this->post(route('cedulas.abrir', $this->partido))->assertRedirect();
        $this->assertSame(1, CedulaArbitral::query()->where('partido_id', $this->partido->id)->count());
        $this->assertSame(8, AlineacionPartido::query()->where('cedula_id', $cedula->id)->count());
    }

    /**
     * Solo el árbitro asignado abre la cédula; el Administrador tampoco (decisión del 11/11).
     */
    #[DataProvider('quienesNoAbren')]
    public function test_solo_el_arbitro_asignado_abre_la_cedula(string $rol, bool $esOtroArbitro, string $mensaje): void
    {
        $usuario = $this->usuario($rol, $esOtroArbitro ? $this->arbitro() : null);

        $this->actingAs($usuario)
            ->post(route('cedulas.abrir', $this->partido))
            ->assertForbidden()
            ->assertSee($mensaje);

        $this->assertFalse(CedulaArbitral::query()->where('partido_id', $this->partido->id)->exists());
    }

    /**
     * @return array<string, array{string, bool, string}>
     */
    public static function quienesNoAbren(): array
    {
        return [
            'otro árbitro' => ['Árbitro en Campo', true, 'Solo el árbitro asignado a este partido puede acceder a su cédula.'],
            'Administrador General' => ['Administrador General', false, 'Solo el árbitro asignado a este partido puede abrir su cédula.'],
            'Capturista' => ['Capturista', false, 'Solo el árbitro asignado a este partido puede abrir su cédula.'],
            'Programador de Partidos' => ['Programador de Partidos', false, 'Solo el árbitro asignado a este partido puede abrir su cédula.'],
        ];
    }

    // Solo se abre en los estados con sentido: programado, confirmado o en juego
    #[DataProvider('estadosNoPermitidos')]
    public function test_no_se_abre_en_un_estado_no_permitido(string $estado): void
    {
        $this->partido->update(['estado' => $estado]);
        $arbitro = $this->usuario('Árbitro en Campo', $this->partido->arbitro);

        $this->actingAs($arbitro)
            ->from(route('cedulas.show', $this->partido))
            ->post(route('cedulas.abrir', $this->partido))
            ->assertRedirect(route('cedulas.show', $this->partido))
            ->assertSessionHasErrors(['estado' => 'La cédula solo se abre y se captura cuando el partido está programado, confirmado o en juego.']);

        $this->assertFalse(CedulaArbitral::query()->where('partido_id', $this->partido->id)->exists());
        $this->get(route('cedulas.show', $this->partido))
            ->assertOk()
            ->assertDontSee('Abrir cédula')
            ->assertSee('La cédula solo se abre cuando el partido está programado, confirmado o en juego.');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function estadosNoPermitidos(): array
    {
        return ['borrador' => ['borrador'], 'finalizado' => ['finalizado'], 'suspendido' => ['suspendido']];
    }

    #[DataProvider('estadosPermitidos')]
    public function test_se_abre_en_los_estados_permitidos(string $estado): void
    {
        $this->partido->update(['estado' => $estado]);

        $this->actingAs($this->usuario('Árbitro en Campo', $this->partido->arbitro))
            ->post(route('cedulas.abrir', $this->partido))
            ->assertSessionHasNoErrors();

        $this->assertTrue(CedulaArbitral::query()->where('partido_id', $this->partido->id)->exists());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function estadosPermitidos(): array
    {
        return ['programado' => ['programado'], 'confirmado' => ['confirmado'], 'en juego' => ['en juego']];
    }

    // Un jugador registrado antes de abrir aparece; uno registrado después se suma sin perder lo capturado
    public function test_los_jugadores_nuevos_de_la_plantilla_entran_a_la_alineacion(): void
    {
        $antes = $this->jugadorNuevo($this->partido->equipo_local_id, 20);
        $arbitro = $this->usuario('Árbitro en Campo', $this->partido->arbitro);
        $this->actingAs($arbitro)->post(route('cedulas.abrir', $this->partido));

        $cedula = CedulaArbitral::query()->where('partido_id', $this->partido->id)->sole();
        $this->assertTrue($cedula->alineaciones()->where('jugador_id', $antes->id)->exists());

        $capturado = $cedula->alineaciones()->where('jugador_id', '!=', $antes->id)->firstOrFail();
        app(CedulaService::class)->guardarAlineacion($cedula, (int) $capturado->jugador_id, ['asistio' => true, 'numero_camiseta' => 77], (string) Str::uuid());
        $despues = $this->jugadorNuevo($this->partido->equipo_visitante_id, 21);

        $this->get(route('cedulas.show', $this->partido))->assertSee('Hay 1 jugador nuevo');
        $this->post(route('cedulas.abrir', $this->partido))->assertSessionHas('success', 'La alineación quedó al día con la plantilla.');

        $this->assertSame(10, $cedula->alineaciones()->count());
        $this->assertSame(21, $cedula->alineaciones()->where('jugador_id', $despues->id)->value('numero_camiseta'));
        $this->assertTrue($capturado->refresh()->asistio);
        $this->assertSame(77, $capturado->numero_camiseta);
        $this->get(route('cedulas.show', $this->partido))->assertDontSee('jugador nuevo');
    }

    // Criterio 5: otro árbitro que cambia el identificador en la URL recibe 403 en español
    public function test_otro_arbitro_recibe_403(): void
    {
        $otro = $this->usuario('Árbitro en Campo', $this->arbitro());

        $this->actingAs($otro)
            ->get(route('cedulas.show', $this->partido))
            ->assertForbidden()
            ->assertSee('Solo el árbitro asignado a este partido puede acceder a su cédula.');
    }

    // Un usuario con el rol de árbitro pero sin persona enlazada tampoco entra
    public function test_un_arbitro_sin_persona_enlazada_recibe_403(): void
    {
        $this->actingAs($this->usuario('Árbitro en Campo'))
            ->get(route('cedulas.show', $this->partido))
            ->assertForbidden()
            ->assertSee('Solo el árbitro asignado a este partido puede acceder a su cédula.');
    }

    // Un partido sin árbitro asignado: ningún árbitro entra, y quien consulta lo ve sin asignar
    public function test_un_partido_sin_arbitro(): void
    {
        $this->partido->update(['arbitro_id' => null]);
        $arbitro = $this->usuario('Árbitro en Campo', $this->arbitro());

        $this->actingAs($arbitro)
            ->get(route('cedulas.show', $this->partido))
            ->assertForbidden()
            ->assertSee('Solo el árbitro asignado a este partido puede acceder a su cédula.');

        $this->actingAs($this->usuario('Programador de Partidos'))
            ->get(route('cedulas.show', $this->partido))
            ->assertOk()
            ->assertSee('Árbitro: Sin asignar');
    }

    #[DataProvider('rolesSinPermiso')]
    public function test_un_rol_sin_cedulas_ver_recibe_403(string $rol): void
    {
        $this->actingAs($this->usuario($rol))
            ->get(route('cedulas.show', $this->partido))
            ->assertForbidden()
            ->assertSee('No cuenta con privilegios para consultar cédulas arbitrales.');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rolesSinPermiso(): array
    {
        return [
            'Tesorero' => ['Tesorero'],
            'Jugador' => ['Jugador'],
            'Delegado del Club' => ['Delegado del Club'],
        ];
    }

    // Quien solo consulta ve la cédula abierta sin la pantalla de captura
    #[DataProvider('rolesDeConsulta')]
    public function test_los_roles_de_consulta_ven_la_cedula_sin_capturar(string $rol): void
    {
        app(CedulaService::class)->abrir($this->partido);

        $this->actingAs($this->usuario($rol))
            ->get(route('cedulas.show', $this->partido))
            ->assertOk()
            ->assertSee('Alineaciones')
            ->assertDontSee('Registrar evento')
            ->assertDontSee('Abrir cédula');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rolesDeConsulta(): array
    {
        return [
            'Comisión Arbitral' => ['Comisión Arbitral'],
            'Tribunal de Penas' => ['Tribunal de Penas'],
            'Programador de Partidos' => ['Programador de Partidos'],
        ];
    }

    public function test_un_identificador_no_numerico_da_404(): void
    {
        $this->actingAs($this->usuario('Árbitro en Campo', $this->partido->arbitro))
            ->get('/partidos/abc/cedula')
            ->assertNotFound();
    }

    public function test_sin_sesion_redirige_al_inicio_de_sesion(): void
    {
        $this->get(route('cedulas.show', $this->partido))->assertRedirect(route('login'));
        $this->post(route('cedulas.abrir', $this->partido))->assertRedirect(route('login'));
    }

    // El Administrador General consulta cualquier cédula, pero un GET no la crea
    public function test_el_administrador_general_consulta_sin_crear_la_cedula(): void
    {
        $this->actingAs($this->usuario('Administrador General'))
            ->get(route('cedulas.show', $this->partido))
            ->assertOk()
            ->assertSee('El árbitro todavía no abre la cédula de este partido.')
            ->assertDontSee('Abrir cédula');

        $this->assertFalse(CedulaArbitral::query()->where('partido_id', $this->partido->id)->exists());
    }

    // Lista del árbitro: solo sus partidos, sin los que están en borrador
    public function test_mis_partidos_muestra_solo_los_del_arbitro(): void
    {
        $ajeno = $this->partidoConArbitro();
        $borrador = $this->partidoConArbitro($this->partido->arbitro, '12:00:00');
        $borrador->update(['estado' => 'borrador']);

        $this->actingAs($this->usuario('Árbitro en Campo', $this->partido->arbitro))
            ->get(route('cedulas.mis-partidos'))
            ->assertOk()
            ->assertSee(route('cedulas.show', $this->partido))
            ->assertDontSee(route('cedulas.show', $ajeno))
            ->assertDontSee(route('cedulas.show', $borrador))
            ->assertSee('Cédula sin abrir');

        $this->actingAs($this->usuario('Árbitro en Campo'))
            ->get(route('cedulas.mis-partidos'))
            ->assertOk()
            ->assertSee('No tienes partidos asignados.');
    }

    public function test_mis_partidos_es_solo_para_el_arbitro(): void
    {
        $this->actingAs($this->usuario('Programador de Partidos'))
            ->get(route('cedulas.mis-partidos'))
            ->assertForbidden()
            ->assertSee('Esta lista es solo para el Árbitro en Campo.');
    }

    private function jugadorNuevo(int $equipoId, int $dorsal): Jugador
    {
        return Jugador::create([
            'persona_id' => Persona::factory()->create(['nombre' => 'Nuevo '.$dorsal, 'apellidos' => 'Refuerzo'])->id,
            'equipo_id' => $equipoId,
            'dorsal' => $dorsal,
            'estado_elegibilidad' => 'Activo',
        ]);
    }
}
