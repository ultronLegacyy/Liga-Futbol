<?php

namespace Tests\Feature;

use App\Models\CedulaArbitral;
use App\Models\EventoPartido;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\TablaPosicion;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreaPartidoConCedula;
use Tests\TestCase;

/**
 * HU-077 (SCRUM-71, RF-12.3, IU-01, RNF-31): resultados, tabla de posiciones y estadísticas en el portal, sin cuenta.
 */
class PortalResultadosTest extends TestCase
{
    use CreaPartidoConCedula;
    use DatabaseTransactions;

    private Partido $oficial;

    private Partido $sinValidar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $validador = $this->usuario('Programador de Partidos');

        $this->oficial = $this->partidoConArbitro();
        $this->cedula($this->oficial, 2, 1, $validador);

        $this->sinValidar = Partido::create([
            'jornada_id' => $this->oficial->jornada_id,
            'equipo_local_id' => $this->oficial->equipo_visitante_id,
            'equipo_visitante_id' => $this->oficial->equipo_local_id,
            'fecha_partido' => '2026-11-15',
            'hora_partido' => '12:00:00',
            'estado' => 'finalizado',
        ]);
        $this->cedula($this->sinValidar, 7, 0, null);

        $grupoId = $this->oficial->jornada->grupo_id;
        TablaPosicion::create(['grupo_id' => $grupoId, 'equipo_id' => $this->oficial->equipo_local_id, 'posicion' => 1, 'pj' => 1, 'pg' => 1, 'pe' => 0, 'pp' => 0, 'gf' => 2, 'gc' => 1, 'dg' => 1, 'puntos' => 3]);
        TablaPosicion::create(['grupo_id' => $grupoId, 'equipo_id' => $this->oficial->equipo_visitante_id, 'posicion' => 2, 'pj' => 1, 'pg' => 0, 'pe' => 0, 'pp' => 1, 'gf' => 1, 'gc' => 2, 'dg' => -1, 'puntos' => 0]);
    }

    public function test_sin_iniciar_sesion_muestra_tabla_resultados_y_estadisticas_oficiales(): void
    {
        $local = $this->oficial->local->nombre;
        $visitante = $this->oficial->visitante->nombre;
        $goleador = $this->jugador($this->oficial->equipo_local_id);

        $this->get(route('portal.resultados'))
            ->assertOk()
            ->assertSee('Resultados y tablas')
            ->assertSeeInOrder(['Tabla de posiciones', '1', $local, '+1', '3', '2', $visitante, '-1', '0'])
            ->assertSeeInOrder(['Resultados', $local, '2 – 1', $visitante, 'Jornada 1', '14/11/2026'])
            ->assertSeeInOrder(['Goleo', trim($goleador->persona->nombre.' '.$goleador->persona->apellidos), $local, '2'])
            ->assertSeeInOrder(['Tarjetas', trim($goleador->persona->nombre.' '.$goleador->persona->apellidos)]);
    }

    // Criterio 1: solo resultados validados (HU-040); un resultado sin validar no se publica ni suma estadísticas
    public function test_no_muestra_resultados_sin_validar_ni_sus_goles(): void
    {
        $html = $this->get(route('portal.resultados'))->assertOk()->getContent();

        $this->assertStringNotContainsString('7 – 0', $html);
        $this->assertSame(1, substr_count($html, 'class="resultado tarjeta"'));
    }

    // RNF-31: de los jugadores solo nombre y equipo; sin CURP, fecha de nacimiento ni formularios
    public function test_no_muestra_datos_privados_ni_ofrece_edicion(): void
    {
        $persona = $this->jugador($this->oficial->equipo_local_id)->persona()->firstOrFail();

        $html = $this->get(route('portal.resultados'))->assertOk()->getContent();

        $this->assertNotEmpty($persona->curp);
        $this->assertStringNotContainsString((string) $persona->curp, $html);
        $this->assertStringNotContainsString($persona->fecha_nacimiento->format('d/m/Y'), $html);
        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_un_grupo_invalido_muestra_el_primero_y_sin_resultados_avisa(): void
    {
        $this->get(route('portal.resultados', ['grupo' => 999999]))->assertOk()->assertSee($this->oficial->local->nombre);

        TablaPosicion::query()->delete();

        $this->get(route('portal.resultados'))->assertOk()
            ->assertSee('Todavía no hay resultados oficiales.')
            ->assertDontSee('Tabla de posiciones');
    }

    public function test_la_pagina_es_accesible_y_esta_en_el_menu(): void
    {
        $html = $this->get(route('portal.resultados'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<caption>', $html);
        $this->assertStringContainsString('scope="col"', $html);
        $this->assertDoesNotMatchRegularExpression('/<link\b|\bsrc=/i', $html);
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('portal.resultados'), '/').'"\s+aria-current="page"/', $html);
        $this->get(route('portal.programacion'))->assertOk()->assertSee(route('portal.resultados'), false);
    }

    public function test_los_resultados_publicos_limitan_las_consultas_por_minuto(): void
    {
        for ($i = 0; $i < 300; $i++) {
            $this->get(route('portal.resultados'))->assertOk();
        }

        $this->get(route('portal.resultados'))->assertStatus(429);
    }

    private function cedula(Partido $partido, int $golesLocal, int $golesVisitante, ?User $validador): CedulaArbitral
    {
        $cedula = CedulaArbitral::create([
            'partido_id' => $partido->id,
            'estado' => CedulaArbitral::CERRADA,
            'goles_local' => $golesLocal,
            'goles_visitante' => $golesVisitante,
            'cerrada_en' => now(),
            'validada_por_id' => $validador?->id,
            'validada_en' => $validador !== null ? now() : null,
        ]);

        $goleador = $this->jugador($partido->equipo_local_id);
        for ($gol = 0; $gol < $golesLocal; $gol++) {
            EventoPartido::create(['cedula_id' => $cedula->id, 'jugador_id' => $goleador->id, 'tipo' => 'Gol', 'minuto' => 10 + $gol]);
        }
        EventoPartido::create(['cedula_id' => $cedula->id, 'jugador_id' => $goleador->id, 'tipo' => 'Amarilla', 'minuto' => 40]);
        // Un autogol no suma al goleo
        EventoPartido::create(['cedula_id' => $cedula->id, 'jugador_id' => $goleador->id, 'tipo' => 'Gol', 'autogol' => true, 'minuto' => 80]);

        return $cedula;
    }

    private function jugador(int $equipoId): Jugador
    {
        return Jugador::query()->where('equipo_id', $equipoId)->with('persona')->orderBy('id')->firstOrFail();
    }
}
