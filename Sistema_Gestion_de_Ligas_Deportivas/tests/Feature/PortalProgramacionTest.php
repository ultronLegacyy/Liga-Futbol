<?php

namespace Tests\Feature;

use App\Models\Arbitro;
use App\Models\Cancha;
use App\Models\Categoria;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\Partido;
use App\Models\Persona;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** HU-062 (SCRUM-70, RF-12.3, IU-01): programación pública del portal, sin cuenta y sin privilegios de edición. */
class PortalProgramacionTest extends TestCase
{
    use DatabaseTransactions;

    private int $consecutivo = 0;

    private Grupo $grupo;

    protected function setUp(): void
    {
        parent::setUp();
        // Martes 17/11/2026 a mediodía en México: la semana va del lunes 16 al domingo 22
        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00', 'America/Mexico_City'));

        $temporada = Temporada::factory()->activa()->create();
        $categoria = Categoria::factory()->create(['temporada_id' => $temporada->id, 'nombre' => 'Primera Fuerza', 'rama' => 'Libre']);
        $this->grupo = Grupo::factory()->create(['categoria_id' => $categoria->id, 'temporada_id' => $temporada->id, 'nombre' => 'Grupo A']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // Criterio 1: sin iniciar sesión se ve fecha, hora, cancha y equipos
    public function test_sin_iniciar_sesion_muestra_la_programacion_publicada_de_la_semana(): void
    {
        $this->partido(Jornada::ESTADO_PUBLICADA, '2026-11-21', '10:00:00', 'Águilas', 'Halcones', 'Unidad Deportiva Norte');
        $this->partido(Jornada::ESTADO_PUBLICADA, '2026-11-22', '09:30:00', 'Pumas', 'Lobos', 'Campo Municipal');

        $this->assertGuest();
        $this->get(route('portal.programacion'))
            ->assertOk()
            ->assertSee('Programación de partidos')
            ->assertSee('Semana del 16 de noviembre al 22 de noviembre de 2026')
            ->assertSeeInOrder(['Sábado 21 de noviembre', '10:00 h', 'Águilas', 'Halcones', 'Unidad Deportiva Norte', 'Primera Fuerza (Libre) · Grupo A'])
            ->assertSeeInOrder(['Domingo 22 de noviembre', '09:30 h', 'Pumas', 'Lobos', 'Campo Municipal']);
    }

    public function test_no_muestra_partidos_de_jornadas_sin_publicar_ni_en_borrador(): void
    {
        $this->partido(Jornada::ESTADO_BORRADOR, '2026-11-21', '10:00:00', 'Jornada Borrador', 'Rival Uno');
        $this->partido(Jornada::ESTADO_EN_REVISION, '2026-11-21', '11:00:00', 'Jornada En Revision', 'Rival Dos');
        $this->partido(Jornada::ESTADO_APROBADA, '2026-11-21', '12:00:00', 'Jornada Aprobada', 'Rival Tres');
        $this->partido(Jornada::ESTADO_PUBLICADA, '2026-11-21', '13:00:00', 'Partido Borrador', 'Rival Cuatro', estado: Partido::ESTADO_BORRADOR);

        $this->get(route('portal.programacion'))
            ->assertOk()
            ->assertDontSee('Jornada Borrador')
            ->assertDontSee('Jornada En Revision')
            ->assertDontSee('Jornada Aprobada')
            ->assertDontSee('Partido Borrador')
            ->assertSee('No hay partidos publicados para esta semana.');
    }

    // Criterio 1: sin privilegios de edición ni datos privados
    public function test_no_ofrece_edicion_ni_muestra_datos_privados(): void
    {
        $partido = $this->partido(Jornada::ESTADO_PUBLICADA, '2026-11-21', '10:00:00', 'Águilas', 'Halcones');
        $arbitro = Arbitro::create(['persona_id' => Persona::factory()->create(['nombre' => 'Arbitro', 'apellidos' => 'Privado'])->id, 'estado' => 'Activo']);
        $partido->update(['arbitro_id' => $arbitro->id]);

        $respuesta = $this->get(route('portal.programacion'))->assertOk();

        $respuesta->assertDontSee('<form', false)
            ->assertDontSee('Arbitro Privado')
            ->assertDontSee($arbitro->persona->curp)
            ->assertDontSee(route('partidos.edit', $partido), false)
            ->assertDontSee(route('partidos.reprogramar', $partido), false)
            ->assertDontSee(route('cedulas.show', $partido), false);
    }

    public function test_muestra_el_estado_de_los_partidos_que_no_se_juegan_como_estaban(): void
    {
        $this->partido(Jornada::ESTADO_PUBLICADA, '2026-11-21', '10:00:00', 'Equipo Cancelado', 'Rival', estado: Partido::ESTADO_CANCELADO);
        $this->partido(Jornada::ESTADO_PUBLICADA, '2026-11-21', '12:00:00', 'Equipo Suspendido', 'Rival', estado: Partido::ESTADO_SUSPENDIDO);
        $this->partido(Jornada::ESTADO_PUBLICADA, '2026-11-22', null, 'Sin Hora', 'Rival');

        $this->get(route('portal.programacion'))
            ->assertOk()
            ->assertSeeInOrder(['Cancelado', 'Equipo Cancelado'])
            ->assertSeeInOrder(['Suspendido', 'Equipo Suspendido'])
            ->assertSeeInOrder(['Hora por confirmar', 'Sin Hora']);
    }

    public function test_se_navega_entre_semanas_y_una_fecha_invalida_muestra_la_actual(): void
    {
        $this->partido(Jornada::ESTADO_PUBLICADA, '2026-11-28', '10:00:00', 'Semana Siguiente', 'Rival');
        $this->partido(Jornada::ESTADO_PUBLICADA, '2026-11-18', '10:00:00', 'Semana Actual', 'Rival');

        $this->get(route('portal.programacion', ['semana' => '2026-11-26']))
            ->assertOk()
            ->assertSee('Semana del 23 de noviembre al 29 de noviembre de 2026')
            ->assertSee('Semana Siguiente')
            ->assertDontSee('Semana Actual')
            ->assertSee(route('portal.programacion', ['semana' => '2026-11-16']), false)
            ->assertSee(route('portal.programacion', ['semana' => '2026-11-30']), false);

        foreach (['2026-02-30', 'hola', '17/11/2026'] as $invalida) {
            $this->get(route('portal.programacion', ['semana' => $invalida]))->assertOk()->assertSee('Semana Actual');
        }

        $this->get(route('portal.programacion').'?semana[]=x')->assertOk()->assertSee('Semana Actual');
    }

    public function test_sin_partidos_en_la_semana_orienta_a_la_siguiente_que_tiene(): void
    {
        $this->partido(Jornada::ESTADO_PUBLICADA, '2026-12-05', '10:00:00', 'Diciembre', 'Rival');

        $this->get(route('portal.programacion'))
            ->assertOk()
            ->assertSee('No hay partidos publicados para esta semana.')
            ->assertSee('Ver la semana del 30 de noviembre, que ya tiene partidos')
            ->assertSee(route('portal.programacion', ['semana' => '2026-11-30']), false);
    }

    // La semana se cuenta con la hora de México: el domingo a las 20:00 ya es lunes en UTC
    public function test_la_semana_actual_se_cuenta_con_la_hora_de_mexico(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-22 20:00', 'America/Mexico_City'));
        $this->partido(Jornada::ESTADO_PUBLICADA, '2026-11-22', '18:00:00', 'Domingo Tarde', 'Rival');

        $this->get(route('portal.programacion'))->assertOk()->assertSee('Domingo Tarde');
    }

    // RNF-13 y RNF-10: estructura accesible, sin CAPTCHA ni JavaScript, adaptable a 360 px
    public function test_la_pagina_es_accesible_y_ligera(): void
    {
        $this->partido(Jornada::ESTADO_PUBLICADA, '2026-11-21', '10:00:00', 'Águilas', 'Halcones');

        $html = $this->get(route('portal.programacion'))->assertOk()->getContent();

        $this->assertStringContainsString('<html lang="es">', $html);
        $this->assertStringContainsString('name="viewport" content="width=device-width, initial-scale=1.0"', $html);
        $this->assertStringContainsString('<a class="saltar" href="#contenido">Saltar al contenido</a>', $html);
        $this->assertStringContainsString('<main id="contenido"', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('min-height: 44px', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsStringIgnoringCase('captcha', $html);
        $this->assertStringNotContainsString('noindex', $html);
    }

    public function test_una_persona_con_sesion_tambien_la_consulta_y_el_login_la_enlaza(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $usuario = User::query()->create([
            'name' => 'Delegado portal',
            'email' => 'portal@liga.test',
            'password' => Hash::make('secreto123'),
            'rol' => 'Consultor',
            'estatus' => 'activo',
        ]);
        $usuario->assignRole('Delegado del Club');
        $this->partido(Jornada::ESTADO_PUBLICADA, '2026-11-21', '10:00:00', 'Águilas', 'Halcones');

        $this->actingAs($usuario)->get(route('portal.programacion'))->assertOk()->assertSee('Águilas')->assertDontSee('Iniciar sesión');

        auth()->logout();
        $this->get(route('inicio'))->assertOk()->assertSee(route('portal.programacion'), false);
    }

    private function partido(string $estadoJornada, string $fecha, ?string $hora, string $local, string $visitante, string $cancha = 'Cancha Central', string $estado = Partido::ESTADO_PROGRAMADO): Partido
    {
        $this->consecutivo++;
        $jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => $this->consecutivo,
            'fecha_inicio' => $fecha,
            'fecha_fin' => $fecha,
            'estado' => $estadoJornada,
        ]);

        return Partido::create([
            'jornada_id' => $jornada->id,
            'equipo_local_id' => $this->equipo($local)->id,
            'equipo_visitante_id' => $this->equipo($visitante)->id,
            'cancha_id' => Cancha::query()->firstOrCreate(['nombre' => $cancha])->id,
            'fecha_partido' => $fecha,
            'hora_partido' => $hora,
            'estado' => $estado,
        ]);
    }

    private function equipo(string $nombre): Equipo
    {
        $this->consecutivo++;

        return Equipo::create([
            'club_id' => Club::create(['nombre' => 'Club '.$nombre.' '.$this->consecutivo])->id,
            'grupo_id' => $this->grupo->id,
            'nombre' => $nombre,
        ]);
    }
}
