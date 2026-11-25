<?php

namespace Tests\Feature;

use App\Models\CedulaArbitral;
use App\Models\EventoPartido;
use App\Models\Jornada;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\TablaPosicion;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaPartidoConCedula;
use Tests\TestCase;

/**
 * HAB-S7 · S7-T03 (SCRUM-236): portada del portal con el prototipo deportivo aprobado y datos reales,
 * sin JavaScript (RNF-04) y solo con lo publicado (RF-12.3, RF-12.4).
 */
class PortalInicioTest extends TestCase
{
    use CreaPartidoConCedula;
    use DatabaseTransactions;

    private Partido $partido;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-12 09:00:00', 'America/Mexico_City'));
        $this->seed(RoleAndPermissionSeeder::class);
        $this->partido = $this->partidoConArbitro();
        Jornada::query()->whereKey($this->partido->jornada_id)->update(['estado' => Jornada::ESTADO_PUBLICADA]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_sin_cuenta_muestra_la_portada_con_los_partidos_publicados_de_la_semana(): void
    {
        $this->get(route('portal.inicio'))
            ->assertOk()
            ->assertSee('Sin límites.')
            ->assertSee('Toda la liga,')
            ->assertSeeInOrder(['Jornada', '1', 'Programación completa'])
            ->assertSee($this->partido->local->nombre)
            ->assertSee($this->partido->visitante->nombre)
            ->assertSee('Próximo:')
            ->assertSee('Lo que')
            ->assertSee('Los que');
    }

    public function test_no_muestra_partidos_de_jornadas_sin_publicar(): void
    {
        Jornada::query()->whereKey($this->partido->jornada_id)->update(['estado' => Jornada::ESTADO_BORRADOR]);

        $this->get(route('portal.inicio'))
            ->assertOk()
            ->assertSee('Todavía no hay partidos publicados.')
            ->assertDontSee($this->partido->local->nombre);
    }

    public function test_muestra_resultado_oficial_tabla_y_goleo(): void
    {
        $validador = $this->usuario('Programador de Partidos');
        $cedula = CedulaArbitral::create([
            'partido_id' => $this->partido->id,
            'estado' => CedulaArbitral::CERRADA,
            'goles_local' => 2,
            'goles_visitante' => 1,
            'cerrada_en' => now(),
            'validada_por_id' => $validador->id,
            'validada_en' => now(),
        ]);
        $goleador = Jugador::query()->where('equipo_id', $this->partido->equipo_local_id)->with('persona')->orderBy('id')->firstOrFail();
        EventoPartido::create(['cedula_id' => $cedula->id, 'jugador_id' => $goleador->id, 'tipo' => 'Gol', 'minuto' => 10]);
        EventoPartido::create(['cedula_id' => $cedula->id, 'jugador_id' => $goleador->id, 'tipo' => 'Gol', 'minuto' => 30]);
        TablaPosicion::create(['grupo_id' => $this->partido->jornada->grupo_id, 'equipo_id' => $this->partido->equipo_local_id, 'posicion' => 1, 'pj' => 1, 'pg' => 1, 'pe' => 0, 'pp' => 0, 'gf' => 2, 'gc' => 1, 'dg' => 1, 'puntos' => 3]);

        $html = $this->get(route('portal.inicio'))
            ->assertOk()
            ->assertSee('2–1')
            ->assertSeeInOrder(['Juega.', $this->partido->local->nombre, '+1', '3'])
            ->assertSeeInOrder(['Los que', '2', 'goles', trim($goleador->persona->nombre.' '.$goleador->persona->apellidos)])
            ->getContent();

        // RNF-31: del goleador solo nombre y equipo
        $persona = $goleador->persona()->firstOrFail();
        $this->assertStringNotContainsString((string) $persona->curp, $html);
    }

    public function test_es_accesible_sin_javascript_y_esta_en_el_menu(): void
    {
        $html = $this->get(route('portal.inicio'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringNotContainsString('<script', $html);
        $this->assertDoesNotMatchRegularExpression('/<link\b|\bsrc=/i', $html);
        $this->assertStringContainsString('type="radio"', $html);
        $this->assertStringContainsString('@supports selector(:has(*))', $html);
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('portal.inicio'), '/').'"\s+aria-current="page"/', $html);
        $this->get(route('portal.programacion'))->assertOk()->assertSee(route('portal.inicio'), false);
    }

    public function test_la_portada_limita_las_consultas_por_minuto(): void
    {
        for ($i = 0; $i < 300; $i++) {
            $this->get(route('portal.inicio'))->assertOk();
        }

        $this->get(route('portal.inicio'))->assertStatus(429);
    }
}
