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
use App\Models\ReprogramacionPartido;
use App\Models\Temporada;
use App\Models\User;
use App\Services\ProgramacionPublicaService;
use App\Services\PublicacionProgramacionService;
use Database\Seeders\PortalProgramacionDemoSeeder;
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
            ->assertSee('No hay partidos publicados para esta semana.')
            // Nunca se publicaron: no hay nada que avisar (respuesta 52)
            ->assertDontSee('Se está actualizando');
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
        $this->partido(Jornada::ESTADO_PUBLICADA, '2026-11-18', '08:00:00', 'Equipo Finalizado', 'Rival', estado: Partido::ESTADO_FINALIZADO);
        $this->partido(Jornada::ESTADO_PUBLICADA, '2026-11-19', '08:00:00', 'Equipo En Juego', 'Rival', estado: Partido::ESTADO_EN_JUEGO);
        $this->partido(Jornada::ESTADO_PUBLICADA, '2026-11-20', '08:00:00', 'Equipo Reprogramado', 'Rival', estado: Partido::ESTADO_REPROGRAMADO);

        $this->get(route('portal.programacion'))
            ->assertOk()
            ->assertSeeInOrder(['Cancelado', 'Equipo Cancelado'])
            ->assertSeeInOrder(['Suspendido', 'Equipo Suspendido'])
            ->assertSeeInOrder(['Hora por confirmar', 'Sin Hora'])
            ->assertSeeInOrder(['Finalizado', 'Equipo Finalizado'])
            ->assertSeeInOrder(['En juego', 'Equipo En Juego'])
            ->assertSeeInOrder(['Reprogramado', 'Equipo Reprogramado']);
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

        // Fechas válidas pero fuera de 2000 a 2100: la semana actual
        foreach (['9999-12-31', '0001-01-01', '1999-12-31', '2101-01-01'] as $fueraDeRango) {
            $this->get(route('portal.programacion', ['semana' => $fueraDeRango]))
                ->assertOk()
                ->assertSee('Semana del 16 de noviembre al 22 de noviembre de 2026')
                ->assertSee('Semana Actual');
        }
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

    // HAB-S7 (SCRUM-236): el diseño nuevo no carga hojas de estilo, fuentes, imágenes ni scripts externos (RNF-04)
    // y respeta prefers-reduced-motion (RNF-13)
    public function test_el_diseno_del_portal_no_carga_recursos_externos(): void
    {
        $this->partido(Jornada::ESTADO_PUBLICADA, '2026-11-21', '10:00:00', 'Águilas', 'Halcones');

        $html = $this->get(route('portal.programacion'))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/<link\b/i', $html);
        $this->assertDoesNotMatchRegularExpression('/\bsrc=/i', $html);
        $this->assertDoesNotMatchRegularExpression('/@import|url\(\s*[\'"]?https?:/i', $html);
        $this->assertStringContainsString('prefers-reduced-motion: no-preference', $html);
        $this->assertStringContainsString('1 partido publicado', $html);
        $this->assertLessThan(40 * 1024, strlen($html));
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

        $this->actingAs($usuario)->get(route('portal.programacion'))->assertOk()->assertSee('Águilas')->assertDontSee('Iniciar sesión')
            ->assertSee('Ir al panel')
            ->assertSee('href="'.$usuario->rutaInicio().'"', false);

        auth()->logout();
        $this->get(route('inicio'))->assertOk()->assertSee(route('portal.programacion'), false);
    }

    // Recomendado (#90): la jornada sigue el flujo real de HU-030 y solo aparece al publicarse con el servicio
    // Respuesta 52 del PO: si vuelve a revisión, sus partidos se ocultan y la semana avisa que se está actualizando
    public function test_la_jornada_aparece_al_publicarse_y_avisa_al_volver_a_revision(): void
    {
        $partido = $this->partido(Jornada::ESTADO_BORRADOR, '2026-11-21', '10:00:00', 'Flujo Real', 'Rival Real', 'Cancha Flujo', Partido::ESTADO_BORRADOR);
        $jornada = $partido->jornada()->firstOrFail();
        $servicio = app(PublicacionProgramacionService::class);

        $servicio->enviarARevision($jornada);
        $servicio->aprobar($jornada->refresh());
        $this->get(route('portal.programacion'))->assertOk()->assertDontSee('Flujo Real')->assertDontSee('Se está actualizando');

        $servicio->publicar($jornada->refresh());
        $this->assertSame(Partido::ESTADO_PROGRAMADO, $partido->refresh()->estado);
        $this->get(route('portal.programacion'))->assertOk()->assertSee('Flujo Real')->assertDontSee('Se está actualizando');

        // Un cambio a la jornada publicada la regresa a borrador para revisarla otra vez (respuesta 26)
        $jornada->refresh()->revertirABorradorPorModificacion();
        $this->assertSame(Jornada::ESTADO_BORRADOR, $jornada->refresh()->estado);

        $this->get(route('portal.programacion'))
            ->assertOk()
            ->assertDontSee('Flujo Real')
            ->assertSee('Se está actualizando la programación de:')
            ->assertSee('Jornada '.$jornada->numero_jornada.' de Primera Fuerza (Libre)')
            ->assertSee('Sus partidos se mostrarán aquí cuando se publique de nuevo.');

        // En otra semana no se avisa
        $this->get(route('portal.programacion', ['semana' => '2026-11-23']))->assertOk()->assertDontSee('Se está actualizando');
    }

    // Respuesta 53 del PO: en la semana original se avisa a dónde se movió el partido; en la nueva, su fecha anterior
    public function test_un_partido_reprogramado_avisa_en_su_semana_original_y_muestra_la_fecha_anterior(): void
    {
        $partido = $this->partido(Jornada::ESTADO_PUBLICADA, '2026-11-21', '10:00:00', 'Movido', 'Rival Movido');
        $this->partido(Jornada::ESTADO_PUBLICADA, '2026-11-21', '12:00:00', 'Se Queda', 'Rival Fijo');
        $partido->update(['fecha_partido' => '2026-11-26', 'hora_partido' => '18:00:00', 'estado' => Partido::ESTADO_REPROGRAMADO]);
        ReprogramacionPartido::create([
            'partido_id' => $partido->id,
            'usuario_id' => $this->usuarioPortal()->id,
            'estado_anterior' => Partido::ESTADO_PROGRAMADO,
            'estado_nuevo' => Partido::ESTADO_REPROGRAMADO,
            'cancha_anterior_id' => $partido->cancha_id,
            'cancha_nueva_id' => $partido->cancha_id,
            'fecha_anterior' => '2026-11-21',
            'fecha_nueva' => '2026-11-26',
            'hora_anterior' => '10:00:00',
            'hora_nueva' => '18:00:00',
            'motivo' => 'La cancha se inundó por la lluvia.',
        ]);

        $this->get(route('portal.programacion'))
            ->assertOk()
            ->assertSee('Partidos de esta semana que se reprogramaron:')
            ->assertSeeInOrder(['Movido', 'Rival Movido', 'reprogramado al', '26/11/2026, 18:00 h'])
            ->assertSee(route('portal.programacion', ['semana' => '2026-11-26']), false)
            ->assertSee('Se Queda');

        $this->get(route('portal.programacion', ['semana' => '2026-11-23']))
            ->assertOk()
            ->assertSeeInOrder(['18:00 h', 'Reprogramado', 'Antes: 21/11/2026, 10:00 h', 'Movido'])
            ->assertDontSee('Partidos de esta semana que se reprogramaron:');
    }

    // Recomendado (#90): la semana siguiente que se ofrece solo cuenta partidos publicados
    public function test_no_ofrece_una_semana_siguiente_sin_partidos_publicados(): void
    {
        $this->partido(Jornada::ESTADO_EN_REVISION, '2026-11-28', '10:00:00', 'En Revision Futura', 'Rival');
        $this->partido(Jornada::ESTADO_APROBADA, '2026-12-05', '10:00:00', 'Aprobada Futura', 'Rival');
        $this->partido(Jornada::ESTADO_PUBLICADA, '2026-12-12', '10:00:00', 'Borrador Futuro', 'Rival', estado: Partido::ESTADO_BORRADOR);

        $this->get(route('portal.programacion'))
            ->assertOk()
            ->assertSee('No hay partidos publicados para esta semana.')
            ->assertDontSee('que ya tiene partidos');
    }

    // Recomendado (#90): la semana va de lunes a domingo; el lunes siguiente ya es de la otra semana
    public function test_el_lunes_siguiente_no_aparece_en_esta_semana(): void
    {
        $this->partido(Jornada::ESTADO_PUBLICADA, '2026-11-23', '10:00:00', 'Lunes Siguiente', 'Rival');
        $this->partido(Jornada::ESTADO_PUBLICADA, '2026-11-16', '10:00:00', 'Lunes Actual', 'Rival');

        $this->get(route('portal.programacion'))->assertOk()->assertSee('Lunes Actual')->assertDontSee('Lunes Siguiente');
        $this->get(route('portal.programacion', ['semana' => '2026-11-23']))->assertOk()->assertSee('Lunes Siguiente')->assertDontSee('Lunes Actual');
    }

    // Recomendado (#90): 300 consultas por minuto por IP; la siguiente recibe 429 con la página en español
    public function test_el_portal_limita_las_consultas_por_minuto(): void
    {
        $this->assertContains('throttle:300,1', app('router')->getRoutes()->getByName('portal.programacion')?->middleware() ?? []);

        for ($consulta = 1; $consulta <= 300; $consulta++) {
            $this->get(route('portal.programacion'))->assertOk();
        }

        $this->get(route('portal.programacion'))->assertStatus(429);
    }

    public function test_las_fechas_aceptadas_van_de_2000_a_2100(): void
    {
        $servicio = app(ProgramacionPublicaService::class);

        $this->assertSame('2000-01-03', $servicio->inicioDeSemana('2000-01-05')->toDateString());
        $this->assertSame('2100-12-27', $servicio->inicioDeSemana('2100-12-31')->toDateString());
        $this->assertSame('2026-11-16', $servicio->inicioDeSemana('1999-12-31')->toDateString());
        $this->assertSame('2026-11-16', $servicio->inicioDeSemana('2101-01-01')->toDateString());
    }

    // Datos para revisar el portal y medirlo con Lighthouse: una jornada publicada en la semana actual
    public function test_el_seeder_de_demostracion_publica_partidos_en_la_semana_actual(): void
    {
        $this->seed(PortalProgramacionDemoSeeder::class);
        $this->seed(PortalProgramacionDemoSeeder::class);

        $this->assertSame(1, Temporada::query()->where('nombre', PortalProgramacionDemoSeeder::NOMBRE)->count());
        $this->get(route('portal.programacion'))
            ->assertOk()
            ->assertSeeInOrder(['Sábado 21 de noviembre', 'Local 1', 'Visitante 1', 'Cancha de demostración 1'])
            ->assertSeeInOrder(['Domingo 22 de noviembre', 'Local 8', 'Visitante 8'])
            ->assertDontSee('No hay partidos publicados para esta semana.');
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

    private function usuarioPortal(): User
    {
        $this->consecutivo++;

        return User::query()->create([
            'name' => 'Programador portal',
            'email' => 'programador'.$this->consecutivo.'@liga.test',
            'password' => Hash::make('secreto123'),
            'rol' => 'Consultor',
            'estatus' => 'activo',
        ]);
    }
}
