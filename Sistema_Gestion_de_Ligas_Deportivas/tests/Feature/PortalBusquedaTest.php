<?php

namespace Tests\Feature;

use App\Models\Asamblea;
use App\Models\CedulaArbitral;
use App\Models\ComunicadoAsamblea;
use App\Models\Equipo;
use App\Models\Jornada;
use App\Models\Partido;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
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
