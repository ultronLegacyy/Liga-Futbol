<?php

namespace Tests\Feature;

use App\Models\Asamblea;
use App\Models\CatalogoArticulo;
use App\Models\CedulaArbitral;
use App\Models\ComunicadoAsamblea;
use App\Models\Equipo;
use App\Models\ExpedienteDisciplinario;
use App\Models\Jornada;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Sancion;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreaPartidoConCedula;
use Tests\TestCase;

/**
 * HU-063 (SCRUM-73, RF-12.4): buscador del portal por fecha, categoría y equipo, solo sobre lo publicado.
 */
class PortalBusquedaTest extends TestCase
{
    use CreaPartidoConCedula;
    use DatabaseTransactions;

    private Partido $primero;

    private Partido $segundo;

    private Partido $borrador;

    private User $programador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->programador = $this->usuario('Programador de Partidos');

        $this->primero = $this->publicado('Halcones Búsqueda', 'Águilas Búsqueda', '2026-11-14');
        $this->segundo = $this->publicado('Tigres Búsqueda', 'Lobos Búsqueda', '2026-11-21');
        $this->borrador = $this->publicado('Oculto Búsqueda', 'Secreto Búsqueda', '2026-11-14', Jornada::ESTADO_BORRADOR);
    }

    public function test_sin_filtros_explica_como_buscar(): void
    {
        $this->get(route('portal.buscar'))
            ->assertOk()
            ->assertSee('Buscar en la liga')
            ->assertSee('Escribe el nombre de un equipo, elige una categoría o una fecha para empezar.')
            ->assertDontSee('Halcones Búsqueda');
    }

    // Criterio 1: los filtros ajustan los resultados
    public function test_filtra_por_parte_del_nombre_del_equipo_sin_importar_mayusculas(): void
    {
        $this->get(route('portal.buscar', ['equipo' => 'halcon']))
            ->assertOk()
            ->assertSee('1 resultado')
            ->assertSeeInOrder(['Partidos', 'Halcones Búsqueda', 'Águilas Búsqueda', '14/11/2026', 'Programado'])
            ->assertDontSee('Tigres Búsqueda');
    }

    public function test_filtra_por_categoria_y_por_fecha(): void
    {
        $categoria = $this->segundo->jornada->grupo->categoria_id;

        $this->get(route('portal.buscar', ['categoria' => $categoria]))
            ->assertOk()->assertSee('Tigres Búsqueda')->assertDontSee('Halcones Búsqueda');

        $this->get(route('portal.buscar', ['fecha' => '2026-11-14']))
            ->assertOk()->assertSee('Halcones Búsqueda')->assertDontSee('Tigres Búsqueda');

        $this->get(route('portal.buscar', ['fecha' => '2026-11-14', 'categoria' => $categoria]))
            ->assertOk()->assertSee('No encontramos nada con esos filtros.');
    }

    // Criterio 2: solo lo publicado es visible
    public function test_no_encuentra_partidos_de_jornadas_sin_publicar(): void
    {
        $this->get(route('portal.buscar', ['equipo' => 'Oculto']))
            ->assertOk()
            ->assertSee('No encontramos nada con esos filtros.')
            ->assertDontSee('Oculto Búsqueda');

        $this->get(route('portal.buscar', ['fecha' => '2026-11-14']))->assertOk()->assertDontSee('Secreto Búsqueda');
    }

    public function test_muestra_el_marcador_solo_si_el_resultado_es_oficial(): void
    {
        CedulaArbitral::create([
            'partido_id' => $this->primero->id,
            'estado' => CedulaArbitral::CERRADA,
            'goles_local' => 4,
            'goles_visitante' => 2,
            'cerrada_en' => now(),
            'validada_por_id' => $this->programador->id,
            'validada_en' => now(),
        ]);
        CedulaArbitral::create([
            'partido_id' => $this->segundo->id,
            'estado' => CedulaArbitral::CERRADA,
            'goles_local' => 9,
            'goles_visitante' => 9,
            'cerrada_en' => now(),
        ]);

        $this->get(route('portal.buscar', ['equipo' => 'Búsqueda']))
            ->assertOk()
            ->assertSeeInOrder(['Halcones Búsqueda', '4 – 2', 'Águilas Búsqueda', 'Resultado oficial'])
            ->assertDontSee('9 – 9');
    }

    public function test_encuentra_comunicados_publicados_por_texto_y_no_los_internos(): void
    {
        $asamblea = Asamblea::query()->create([
            'tipo' => 'Ordinaria', 'fecha' => '2026-11-20', 'hora' => '18:00', 'lugar' => 'Sala',
            'orden_del_dia' => 'Asuntos', 'estado' => Asamblea::ESTADO_CELEBRADA,
        ]);
        $publicado = ComunicadoAsamblea::query()->create(['asamblea_id' => $asamblea->id, 'titulo' => 'Final de Halcones Búsqueda', 'contenido' => 'Se juega en sede alterna.', 'registrado_por_usuario_id' => $this->programador->id]);
        $publicado->forceFill(['publicado_en_portal' => true, 'publicado_por_usuario_id' => $this->programador->id, 'publicado_en' => now()])->save();
        ComunicadoAsamblea::query()->create(['asamblea_id' => $asamblea->id, 'titulo' => 'Halcones Búsqueda interno', 'contenido' => 'x', 'registrado_por_usuario_id' => $this->programador->id]);

        $this->get(route('portal.buscar', ['equipo' => 'Halcones']))
            ->assertOk()
            ->assertSeeInOrder(['Comunicados', 'Final de Halcones Búsqueda', 'Se juega en sede alterna.'])
            ->assertDontSee('Halcones Búsqueda interno');
    }

    public function test_los_filtros_invalidos_se_ignoran_y_los_comodines_no_traen_todo(): void
    {
        $this->get(route('portal.buscar', ['fecha' => '2026-02-31', 'categoria' => 999999]))
            ->assertOk()
            ->assertSee('Escribe el nombre de un equipo');

        $this->get(route('portal.buscar', ['equipo' => '%']))
            ->assertOk()
            ->assertSee('No encontramos nada con esos filtros.')
            ->assertDontSee('Halcones Búsqueda');
    }

    public function test_el_formulario_es_accesible_y_sin_javascript(): void
    {
        $html = $this->get(route('portal.buscar', ['equipo' => 'Tigres']))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('role="search"', $html);
        $this->assertStringContainsString('method="GET"', $html);
        $this->assertStringContainsString('<label for="equipo">', $html);
        $this->assertStringContainsString('value="Tigres"', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('portal.buscar'), '/').'"\s+aria-current="page"/', $html);
    }

    // Revisión del #109: un parámetro en forma de arreglo no da 500
    public function test_equipo_o_fecha_como_arreglo_se_ignoran_sin_error(): void
    {
        foreach (['?equipo[]=x', '?fecha[]=2026-11-14', '?equipo[a][b]=x'] as $consulta) {
            $this->get(route('portal.buscar').$consulta)
                ->assertOk()
                ->assertSee('Escribe el nombre de un equipo, elige una categoría o una fecha para empezar.');
        }
    }

    // Revisión del #109: la fecha de un comunicado es la de México; publicado a las 21:00 del 26/11 (03:00 UTC del 27)
    public function test_el_filtro_de_fecha_usa_la_hora_de_mexico_en_los_comunicados(): void
    {
        $asamblea = Asamblea::query()->create([
            'tipo' => 'Ordinaria', 'fecha' => '2026-11-20', 'hora' => '18:00', 'lugar' => 'Sala',
            'orden_del_dia' => 'Asuntos', 'estado' => Asamblea::ESTADO_CELEBRADA,
        ]);
        $comunicado = ComunicadoAsamblea::query()->create(['asamblea_id' => $asamblea->id, 'titulo' => 'Aviso de noche', 'contenido' => 'Cambio de horario.', 'registrado_por_usuario_id' => $this->programador->id]);
        $comunicado->forceFill(['publicado_en_portal' => true, 'publicado_por_usuario_id' => $this->programador->id, 'publicado_en' => Carbon::parse('2026-11-27 03:00:00', 'UTC')])->save();

        $this->get(route('portal.buscar', ['fecha' => '2026-11-26']))
            ->assertOk()
            ->assertSee('Aviso de noche')
            ->assertSee('<time datetime="2026-11-26">26/11/2026</time>', false);

        $this->get(route('portal.buscar', ['fecha' => '2026-11-27']))
            ->assertOk()
            ->assertDontSee('Aviso de noche');
    }

    // Revisión del #109: el boletín dentro del buscador respeta la categoría del equipo del sancionado
    public function test_los_castigados_se_filtran_por_equipo_y_por_categoria(): void
    {
        $halcon = $this->jugadorDe($this->primero->equipo_local_id);
        $tigre = $this->jugadorDe($this->segundo->equipo_local_id);
        $this->sancionPublicada($halcon);
        $this->sancionPublicada($tigre);
        $nombreHalcon = trim($halcon->persona->nombre.' '.$halcon->persona->apellidos);
        $nombreTigre = trim($tigre->persona->nombre.' '.$tigre->persona->apellidos);

        $this->get(route('portal.buscar', ['equipo' => 'Búsqueda', 'categoria' => $this->segundo->jornada->grupo->categoria_id]))
            ->assertOk()
            ->assertSeeInOrder(['Castigados', $nombreTigre, 'Tigres Búsqueda'])
            ->assertDontSee($nombreHalcon);

        // Solo con la categoría también aparece el boletín de esa categoría
        $this->get(route('portal.buscar', ['categoria' => $this->primero->jornada->grupo->categoria_id]))
            ->assertOk()
            ->assertSeeInOrder(['Castigados', $nombreHalcon, 'Halcones Búsqueda'])
            ->assertDontSee($nombreTigre);

        $this->get(route('portal.buscar', ['equipo' => 'Tigres']))
            ->assertOk()
            ->assertSee($nombreTigre)
            ->assertDontSee($nombreHalcon);
    }

    // Revisión del #109: una inscripción en «Baja» no cambia el equipo del sancionado
    public function test_el_sancionado_no_aparece_en_un_equipo_donde_esta_de_baja(): void
    {
        $jugador = $this->jugadorDe($this->primero->equipo_local_id);
        Jugador::create(['persona_id' => $jugador->persona_id, 'equipo_id' => $this->segundo->equipo_local_id, 'dorsal' => 98, 'estado_elegibilidad' => 'Baja']);
        $this->sancionPublicada($jugador);
        $nombre = trim($jugador->persona->nombre.' '.$jugador->persona->apellidos);

        $this->get(route('portal.buscar', ['equipo' => 'Tigres']))->assertOk()->assertDontSee($nombre);
        $this->get(route('portal.buscar', ['equipo' => 'Halcones']))->assertOk()->assertSeeInOrder(['Castigados', $nombre, 'Halcones Búsqueda']);
    }

    private function jugadorDe(int $equipoId): Jugador
    {
        return Jugador::query()->where('equipo_id', $equipoId)->with('persona')->orderBy('id')->firstOrFail();
    }

    private function sancionPublicada(Jugador $jugador): Sancion
    {
        $tribunal = $this->usuario('Tribunal de Penas');
        $articulo = CatalogoArticulo::query()->firstOrCreate(['codigo' => 'ART-BUSQ'], ['descripcion' => 'Conducta antideportiva', 'castigo_base_partidos' => 2]);
        $expediente = ExpedienteDisciplinario::create([
            'folio' => 'EXP-BUSQ-'.$jugador->id,
            'partido_id' => $this->primero->id,
            'articulo_id' => $articulo->id,
            'titulo' => 'Expediente de búsqueda',
            'descripcion_hechos' => 'Hechos de prueba',
            'origen' => 'cedula_arbitral',
            'estado' => ExpedienteDisciplinario::ESTADO_ABIERTO,
            'fecha_apertura' => now(),
            'generado_automaticamente' => true,
        ]);

        return Sancion::create([
            'expediente_id' => $expediente->id,
            'persona_id' => $jugador->persona_id,
            'articulo_id' => $articulo->id,
            'tipo_sancion' => Sancion::TIPO_PARTIDOS,
            'descripcion_sancion' => 'Suspensión',
            'partidos_suspendidos' => 2,
            'partidos_cumplidos' => 0,
            'fecha_inicio' => now(Sancion::ZONA_HORARIA)->subDay()->toDateString(),
            'estado' => Sancion::ESTADO_VIGENTE,
            'publicada_en_boletin' => true,
            'validado_por_usuario_id' => $tribunal->id,
            'fecha_validacion' => now(),
            'autorizado_por_usuario_id' => $tribunal->id,
            'autorizado_por_cargo' => 'Tribunal de Penas',
            'fecha_publicacion' => now(),
        ]);
    }

    private function publicado(string $local, string $visitante, string $fecha, string $estado = Jornada::ESTADO_PUBLICADA): Partido
    {
        $partido = $this->partidoConArbitro();
        Jornada::query()->whereKey($partido->jornada_id)->update(['estado' => $estado]);
        Equipo::query()->whereKey($partido->equipo_local_id)->update(['nombre' => $local]);
        Equipo::query()->whereKey($partido->equipo_visitante_id)->update(['nombre' => $visitante]);
        Partido::query()->whereKey($partido->id)->update(['fecha_partido' => $fecha]);

        return $partido->fresh(['jornada.grupo']);
    }
}
