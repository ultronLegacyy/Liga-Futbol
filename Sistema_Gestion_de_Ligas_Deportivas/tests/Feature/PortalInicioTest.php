<?php

namespace Tests\Feature;

use App\Models\CatalogoArticulo;
use App\Models\Categoria;
use App\Models\CedulaArbitral;
use App\Models\Equipo;
use App\Models\EventoPartido;
use App\Models\ExpedienteDisciplinario;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Persona;
use App\Models\Sancion;
use App\Models\TablaPosicion;
use App\Models\Temporada;
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

    // Revisión del #112: la portada es de la temporada en curso; una temporada cerrada no entra en goleo, tablas ni cifras
    public function test_una_temporada_cerrada_no_entra_en_la_portada(): void
    {
        $vieja = Temporada::factory()->cerrada()->create(['nombre' => 'Temporada Vieja 2025']);
        $grupo = Grupo::factory()->create([
            'categoria_id' => Categoria::factory()->create(['temporada_id' => $vieja->id, 'nombre' => 'Categoría Vieja'])->id,
            'nombre' => 'Grupo Viejo',
        ]);
        $jornada = Jornada::create(['grupo_id' => $grupo->id, 'numero_jornada' => 1, 'fecha_inicio' => '2025-05-10', 'fecha_fin' => '2025-05-11', 'estado' => Jornada::ESTADO_PUBLICADA]);
        $local = $this->equipo($grupo);
        $partido = Partido::create([
            'jornada_id' => $jornada->id, 'equipo_local_id' => $local->id, 'equipo_visitante_id' => $this->equipo($grupo)->id,
            'arbitro_id' => $this->arbitro()->id, 'fecha_partido' => '2025-05-10', 'hora_partido' => '10:00:00', 'estado' => 'finalizado',
        ]);
        $cedula = CedulaArbitral::create([
            'partido_id' => $partido->id, 'estado' => CedulaArbitral::CERRADA, 'goles_local' => 20, 'goles_visitante' => 0,
            'cerrada_en' => now(), 'validada_por_id' => $this->usuario('Administrador General')->id, 'validada_en' => now(),
        ]);
        $goleador = Jugador::query()->where('equipo_id', $local->id)->orderBy('id')->firstOrFail();
        Persona::query()->whereKey($goleador->persona_id)->update(['nombre' => 'Goleador', 'apellidos' => 'Temporada Vieja']);
        for ($gol = 1; $gol <= 20; $gol++) {
            EventoPartido::create(['cedula_id' => $cedula->id, 'jugador_id' => $goleador->id, 'tipo' => 'Gol', 'minuto' => $gol]);
        }
        TablaPosicion::create(['grupo_id' => $grupo->id, 'equipo_id' => $local->id, 'posicion' => 1, 'pj' => 1, 'pg' => 1, 'pe' => 0, 'pp' => 0, 'gf' => 20, 'gc' => 0, 'dg' => 20, 'puntos' => 3]);

        $html = $this->get(route('portal.inicio'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Goleador Temporada Vieja', $html);
        $this->assertStringNotContainsString('Categoría Vieja · Grupo Viejo', $html);
        $this->assertStringNotContainsString('20–0', $html);
        // Cifras: la categoría y los equipos de la temporada cerrada no se cuentan
        $this->assertStringNotContainsString('Categoría Vieja', $html);
        $equiposEnCurso = Equipo::query()->whereHas('grupo.temporada', fn ($temporada) => $temporada->where('estado', 'activa'))->count();
        $this->assertLessThan(Equipo::query()->count(), $equiposEnCurso);
        $this->assertMatchesRegularExpression('/<dd>'.$equiposEnCurso.'<\/dd>\s*<dt>Equipos<\/dt>/', $html);
        $oficialesEnCurso = CedulaArbitral::query()->oficiales()
            ->whereHas('partido.jornada.grupo.temporada', fn ($temporada) => $temporada->where('estado', 'activa'))->count();
        $this->assertMatchesRegularExpression('/<dd>'.$oficialesEnCurso.'<\/dd>\s*<dt>Partidos oficiales<\/dt>/', $html);
    }

    // Revisión del #112: «Próximo» es el primer partido posterior a ahora en hora de México, no uno pendiente que ya pasó
    public function test_proximo_no_muestra_un_partido_que_ya_paso(): void
    {
        // Ahora es jueves 12/11 a las 09:00; el lunes 9 quedó un partido «programado» sin cédula
        Partido::create([
            'jornada_id' => $this->partido->jornada_id,
            'equipo_local_id' => $this->partido->equipo_visitante_id,
            'equipo_visitante_id' => $this->partido->equipo_local_id,
            'arbitro_id' => $this->partido->arbitro_id,
            'fecha_partido' => '2026-11-09',
            'hora_partido' => '08:00:00',
            'estado' => 'programado',
        ]);

        $this->get(route('portal.inicio'))
            ->assertOk()
            ->assertSee('Próximo: sáb')
            ->assertDontSee('Próximo: lun');
    }

    // Revisión 2 del #112: el marcador de la semana solo sale con resultado oficial (cédula validada)
    public function test_una_cedula_cerrada_sin_validar_no_muestra_marcador(): void
    {
        CedulaArbitral::create([
            'partido_id' => $this->partido->id, 'estado' => CedulaArbitral::CERRADA,
            'goles_local' => 4, 'goles_visitante' => 3, 'cerrada_en' => now(),
        ]);

        $this->get(route('portal.inicio'))->assertOk()->assertDontSee('4–3')->assertDontSee('4 a 3');
    }

    // Revisión 2 del #112: cada pestaña muestra el top 5 de la tabla
    public function test_la_tabla_del_banner_muestra_solo_cinco_equipos(): void
    {
        $grupo = $this->partido->jornada->grupo;
        $equipos = [$this->partido->local, $this->partido->visitante];
        for ($i = 0; $i < 4; $i++) {
            $equipos[] = $this->equipo($grupo);
        }
        foreach ($equipos as $posicion => $equipo) {
            TablaPosicion::create(['grupo_id' => $grupo->id, 'equipo_id' => $equipo->id, 'posicion' => $posicion + 1, 'pj' => 1, 'pg' => 0, 'pe' => 0, 'pp' => 0, 'gf' => 0, 'gc' => 0, 'dg' => 0, 'puntos' => 10 - $posicion]);
        }

        $this->get(route('portal.inicio'))->assertOk()
            ->assertSee($equipos[4]->nombre)
            ->assertDontSee($equipos[5]->nombre);
    }

    // Revisión 2 del #112: una persona con dos sanciones cuenta una vez, igual que /castigados
    public function test_los_castigados_cuentan_personas_distintas(): void
    {
        $jugador = Jugador::query()->where('equipo_id', $this->partido->equipo_local_id)->orderBy('id')->firstOrFail();
        $tribunal = $this->usuario('Tribunal de Penas');
        $articulo = CatalogoArticulo::query()->firstOrCreate(['codigo' => 'ART-PORTADA'], ['descripcion' => 'Conducta antideportiva', 'castigo_base_partidos' => 2]);
        foreach ([1, 2] as $numero) {
            $expediente = ExpedienteDisciplinario::create([
                'folio' => 'EXP-PORTADA-'.$numero, 'partido_id' => $this->partido->id, 'articulo_id' => $articulo->id,
                'titulo' => 'Expediente', 'descripcion_hechos' => 'Hechos', 'origen' => 'cedula_arbitral',
                'estado' => ExpedienteDisciplinario::ESTADO_ABIERTO, 'fecha_apertura' => now(), 'generado_automaticamente' => true,
            ]);
            Sancion::create([
                'expediente_id' => $expediente->id, 'persona_id' => $jugador->persona_id, 'articulo_id' => $articulo->id,
                'tipo_sancion' => Sancion::TIPO_PARTIDOS, 'descripcion_sancion' => 'Suspensión', 'partidos_suspendidos' => 2,
                'partidos_cumplidos' => 0, 'fecha_inicio' => '2026-11-10', 'estado' => Sancion::ESTADO_VIGENTE,
                'publicada_en_boletin' => true, 'validado_por_usuario_id' => $tribunal->id, 'fecha_validacion' => now(),
                'autorizado_por_usuario_id' => $tribunal->id, 'autorizado_por_cargo' => 'Tribunal de Penas', 'fecha_publicacion' => now(),
            ]);
        }

        $this->get(route('portal.inicio'))->assertOk()->assertSee('1 persona suspendida')->assertDontSee('2 personas suspendidas');
    }

    // Revisión 2 del #112: un partido sin hora es «próximo» solo si es de un día posterior, con «hora por confirmar»
    public function test_un_partido_sin_hora_de_hoy_no_es_proximo(): void
    {
        foreach (['2026-11-12', '2026-11-13'] as $fecha) {
            Partido::create([
                'jornada_id' => $this->partido->jornada_id,
                'equipo_local_id' => $this->partido->equipo_visitante_id,
                'equipo_visitante_id' => $this->partido->equipo_local_id,
                'arbitro_id' => $this->partido->arbitro_id,
                'fecha_partido' => $fecha,
                'hora_partido' => null,
                'estado' => 'programado',
            ]);
        }

        $this->get(route('portal.inicio'))->assertOk()
            ->assertSee('hora por confirmar')
            ->assertDontSee('Próximo: jue');
    }

    // Revisión 2 del #112: las pestañas van sobre el banner marino; su foco es un contorno blanco
    public function test_el_foco_de_las_pestanas_es_blanco(): void
    {
        $this->get(route('portal.inicio'))->assertOk()
            ->assertSee('.pestanas input:focus-visible + label { outline: 3px solid #fff; outline-offset: 2px; }', false);
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
