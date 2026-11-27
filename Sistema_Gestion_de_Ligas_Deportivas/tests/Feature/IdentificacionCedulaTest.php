<?php

namespace Tests\Feature;

use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Persona;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreaPartidoConCedula;
use Tests\TestCase;

/**
 * HU-038 (SCRUM-50, RF-08.2): al abrir la cédula, el sistema ya muestra los equipos, la plantilla de cada uno
 * y el árbitro asignado, en la consulta, la captura desde el celular y la captura en papel.
 */
class IdentificacionCedulaTest extends TestCase
{
    use CreaPartidoConCedula;
    use DatabaseTransactions;

    private Partido $partido;

    private User $arbitroUsuario;

    private User $capturista;

    protected function setUp(): void
    {
        parent::setUp();

        // El partido del ayudante es el 14/11/2026; hoy es martes 17
        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00', 'America/Mexico_City'));
        $this->seed(RoleAndPermissionSeeder::class);
        $this->partido = $this->partidoConArbitro();
        $this->arbitroUsuario = $this->usuario('Árbitro en Campo', $this->partido->arbitro);
        $this->capturista = $this->usuario('Capturista');

        // Un jugador de otro equipo, de otro partido: no debe aparecer en esta cédula
        Jugador::create([
            'persona_id' => Persona::factory()->create(['nombre' => 'Intruso', 'apellidos' => 'Ajeno'])->id,
            'equipo_id' => $this->partidoConArbitro()->equipo_local_id,
            'dorsal' => 7,
            'estado_elegibilidad' => 'Activo',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // Criterio 1, consulta: antes de abrir la cédula, el árbitro ya ve los equipos, sus plantillas y el árbitro
    public function test_la_consulta_muestra_equipos_plantillas_y_arbitro(): void
    {
        $this->assertIdentificaElPartido($this->actingAs($this->arbitroUsuario)->get(route('cedulas.show', $this->partido)));
    }

    // Criterio 1, captura desde el celular: al abrir la cédula, la alineación ya trae la plantilla y se ve el árbitro
    public function test_la_captura_digital_muestra_equipos_plantillas_y_arbitro(): void
    {
        $this->actingAs($this->arbitroUsuario)->post(route('cedulas.abrir', $this->partido))->assertSessionHasNoErrors();

        $respuesta = $this->get(route('cedulas.show', $this->partido))->assertSee('id="captura-cedula"', false);
        $this->assertIdentificaElPartido($respuesta);
    }

    // Criterio 1, captura en papel: el Capturista ve lo mismo sin capturarlo a mano
    public function test_la_captura_en_papel_muestra_equipos_plantillas_y_arbitro(): void
    {
        $respuesta = $this->actingAs($this->capturista)->get(route('cedulas.fisica.create', $this->partido))->assertSee('name="clave_envio"', false);
        $this->assertIdentificaElPartido($respuesta);
    }

    // Un partido sin árbitro muestra «Sin asignar» en las tres pantallas
    public function test_un_partido_sin_arbitro_muestra_sin_asignar(): void
    {
        $admin = $this->usuario('Administrador General');
        $sinArbitro = $this->partidoConArbitro();
        $sinArbitro->update(['arbitro_id' => null]);

        $this->actingAs($admin)->get(route('cedulas.show', $sinArbitro))->assertOk()->assertSee('Árbitro: Sin asignar');
        $this->actingAs($this->capturista)->get(route('cedulas.fisica.create', $sinArbitro))->assertOk()->assertSee('Árbitro: Sin asignar');

        // Solo el árbitro asignado abre la cédula: si después se le quita el árbitro, la captura muestra «Sin asignar»
        $this->actingAs($this->arbitroUsuario)->post(route('cedulas.abrir', $this->partido))->assertSessionHasNoErrors();
        $this->partido->update(['arbitro_id' => null]);
        $this->actingAs($admin)->get(route('cedulas.show', $this->partido))->assertSee('id="captura-cedula"', false)->assertSee('Árbitro: Sin asignar');
    }

    // Solo los jugadores de los dos equipos del partido, sin las bajas, y el suspendido marcado (HU-036 y HU-047)
    private function assertIdentificaElPartido(TestResponse $respuesta): void
    {
        $this->partido->load(['local', 'visitante']);

        $respuesta->assertOk()
            ->assertSee($this->partido->local->nombre)
            ->assertSee($this->partido->visitante->nombre)
            ->assertSee('Árbitro: Árbitro Cédula')
            ->assertSee('Suspendido')
            ->assertDontSee('Intruso')
            ->assertDontSee('Ajeno');

        $plantilla = Jugador::query()->with('persona')->whereIn('equipo_id', [$this->partido->equipo_local_id, $this->partido->equipo_visitante_id])->get();
        $this->assertCount(10, $plantilla);

        foreach ($plantilla as $jugador) {
            // Nunca la CURP (HU-022)
            $respuesta->assertDontSee($jugador->persona->curp);

            if ($jugador->estado_elegibilidad === 'Baja') {
                $respuesta->assertDontSee($jugador->persona->nombre.' '.$jugador->persona->apellidos)
                    ->assertDontSee($jugador->persona->apellidos.', '.$jugador->persona->nombre);
            } else {
                $this->assertTrue(
                    str_contains((string) $respuesta->getContent(), e($jugador->persona->nombre.' '.$jugador->persona->apellidos))
                        || str_contains((string) $respuesta->getContent(), e($jugador->persona->apellidos.', '.$jugador->persona->nombre)),
                    'Falta '.$jugador->persona->nombre.' '.$jugador->persona->apellidos.' en la plantilla.'
                );
            }
        }
    }
}
