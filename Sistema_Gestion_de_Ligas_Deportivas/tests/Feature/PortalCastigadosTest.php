<?php

namespace Tests\Feature;

use App\Models\Asamblea;
use App\Models\AuditoriaLog;
use App\Models\CatalogoArticulo;
use App\Models\ComunicadoAsamblea;
use App\Models\ExpedienteDisciplinario;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Sancion;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreaPartidoConCedula;
use Tests\TestCase;

/**
 * HU-078 (SCRUM-72, RF-12.3, IU-01, RNF-44, RNF-31): boletín de castigados en el portal público, sin cuenta.
 */
class PortalCastigadosTest extends TestCase
{
    use CreaPartidoConCedula;
    use DatabaseTransactions;

    private Partido $partido;

    private ExpedienteDisciplinario $expediente;

    private CatalogoArticulo $articulo;

    private User $tribunal;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-24 12:00:00', Sancion::ZONA_HORARIA));
        $this->seed(RoleAndPermissionSeeder::class);

        $this->partido = $this->partidoConArbitro();
        $this->tribunal = $this->usuario('Tribunal de Penas');
        $this->articulo = CatalogoArticulo::firstOrCreate(
            ['codigo' => 'ART-PORTAL'],
            ['descripcion' => 'Conducta antideportiva', 'castigo_base_partidos' => 2, 'es_grave' => false]
        );
        $this->expediente = ExpedienteDisciplinario::create([
            'folio' => 'EXP-PORTAL-01',
            'partido_id' => $this->partido->id,
            'articulo_id' => $this->articulo->id,
            'titulo' => 'Expediente del portal',
            'descripcion_hechos' => 'Hechos de prueba',
            'origen' => 'cedula_arbitral',
            'estado' => ExpedienteDisciplinario::ESTADO_ABIERTO,
            'fecha_apertura' => now(),
            'generado_automaticamente' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_sin_iniciar_sesion_muestra_las_sanciones_publicadas_y_vigentes(): void
    {
        $jugador = $this->jugador(0);
        $this->sancion($jugador, ['partidos_suspendidos' => 3, 'partidos_cumplidos' => 1, 'fecha_inicio' => '2026-11-20']);

        $this->get(route('portal.castigados'))
            ->assertOk()
            ->assertSee('Boletín de castigados')
            ->assertSee('Vigente al 24 de noviembre de 2026')
            ->assertSee('1 persona suspendida')
            ->assertSeeInOrder([
                $this->partido->local->nombre,
                '2',
                'partidos pendientes',
                trim($jugador->persona->nombre.' '.$jugador->persona->apellidos),
                'Suspensión de 3 partidos, 1 cumplido',
                'ART-PORTAL · Conducta antideportiva',
                '20/11/2026',
                'Tribunal de Penas',
            ]);
    }

    // RNF-44 y HU-046: lo no validado, lo no publicado y lo ya cumplido no se publica
    public function test_no_muestra_sanciones_sin_validar_sin_publicar_cumplidas_ni_no_inhabilitantes(): void
    {
        $this->sancion($this->jugador(0), ['validado_por_usuario_id' => null, 'fecha_validacion' => null]);
        $this->sancion($this->jugador(1), ['publicada_en_boletin' => false]);
        $this->sancion($this->jugador(2), ['estado' => Sancion::ESTADO_CUMPLIDA]);
        $this->sancion($this->jugador(3), ['tipo_sancion' => Sancion::TIPO_MULTA, 'monto_multa' => 500]);
        $this->sancion($this->jugador(4), [
            'tipo_sancion' => Sancion::TIPO_TEMPORAL,
            'partidos_suspendidos' => 0,
            'fecha_inicio' => '2026-11-01',
            'fecha_fin' => '2026-11-23',
        ]);

        $respuesta = $this->get(route('portal.castigados'))->assertOk()
            ->assertSee('No hay castigos vigentes publicados en el boletín.')
            ->assertSee('0 personas suspendidas');

        foreach (range(0, 4) as $indice) {
            $respuesta->assertDontSee(trim($this->jugador($indice)->persona->nombre.' '.$this->jugador($indice)->persona->apellidos));
        }
    }

    public function test_la_suspension_temporal_muestra_hasta_cuando(): void
    {
        $jugador = $this->jugador(0);
        $this->sancion($jugador, [
            'tipo_sancion' => Sancion::TIPO_TEMPORAL,
            'partidos_suspendidos' => 0,
            'fecha_inicio' => '2026-11-20',
            'fecha_fin' => '2026-12-15',
        ]);

        $this->get(route('portal.castigados'))->assertOk()
            ->assertSeeInOrder(['Suspendido hasta', '15/12', '2026', $jugador->persona->nombre, 'Suspensión temporal hasta el 15/12/2026']);
    }

    // RNF-31 y privacidad: sin CURP, fecha de nacimiento, folio del expediente ni datos de quien autorizó
    public function test_no_muestra_datos_privados_ni_ofrece_edicion(): void
    {
        $jugador = $this->jugador(0);
        $this->sancion($jugador);

        $html = $this->get(route('portal.castigados'))->assertOk()->getContent();

        $this->assertStringNotContainsString((string) $jugador->persona->curp, $html);
        $this->assertStringNotContainsString('EXP-PORTAL-01', $html);
        $this->assertStringNotContainsString($this->tribunal->email, $html);
        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringNotContainsString(route('disciplina.expedientes.show', $this->expediente), $html);
        if ($jugador->persona->fecha_nacimiento) {
            $this->assertStringNotContainsString($jugador->persona->fecha_nacimiento->format('d/m/Y'), $html);
        }
    }

    public function test_la_pagina_es_accesible_ligera_y_esta_en_el_menu(): void
    {
        $this->sancion($this->jugador(0));

        $html = $this->get(route('portal.castigados'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringNotContainsString('<script', $html);
        $this->assertDoesNotMatchRegularExpression('/<link\b|\bsrc=/i', $html);
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('portal.castigados'), '/').'"\s+aria-current="page"/', $html);
        $this->get(route('portal.programacion'))->assertOk()->assertSee(route('portal.castigados'), false);
    }

    public function test_el_boletin_publico_limita_las_consultas_por_minuto(): void
    {
        for ($i = 0; $i < 300; $i++) {
            $this->get(route('portal.castigados'))->assertOk();
        }

        $this->get(route('portal.castigados'))->assertStatus(429);
    }

    // PA-15: los comunicados de asamblea solo se ven en el portal cuando el Secretario o el Presidente los publican
    public function test_solo_los_comunicados_publicados_aparecen_en_el_portal(): void
    {
        $secretario = $this->usuarioConRol('Secretario');
        $publicado = $this->comunicado('Cambio de sede de la final');
        $interno = $this->comunicado('Asunto interno de la mesa');

        $this->get(route('portal.castigados'))->assertOk()
            ->assertSee('No hay comunicados publicados.')
            ->assertDontSee('Cambio de sede de la final');

        $this->actingAs($secretario)
            ->put(route('asambleas.comunicados.portal', [$publicado->asamblea_id, $publicado]), ['publicar' => '1'])
            ->assertRedirect(route('asambleas.show', $publicado->asamblea_id))
            ->assertSessionHas('success', 'Comunicado publicado en el portal.');

        $publicado->refresh();
        $this->assertTrue($publicado->publicado_en_portal);
        $this->assertSame($secretario->id, $publicado->publicado_por_usuario_id);
        $this->assertNotNull($publicado->publicado_en);
        $this->assertTrue(AuditoriaLog::query()
            ->where('tabla_afectada', 'comunicados_asamblea')
            ->where('registro_id', $publicado->id)
            ->exists());

        auth()->logout();
        $html = $this->get(route('portal.castigados'))->assertOk()
            ->assertSeeInOrder(['Comunicados oficiales', '24 de noviembre de 2026', 'Cambio de sede de la final', 'Contenido del comunicado'])
            ->assertDontSee('Asunto interno de la mesa')
            ->getContent();
        $this->assertStringNotContainsString($secretario->email, $html);
        $this->assertStringNotContainsString($secretario->name, $html);
        $this->assertNotNull($interno->fresh());
    }

    public function test_se_retira_un_comunicado_y_no_se_publica_dos_veces(): void
    {
        $presidente = $this->usuarioConRol('Presidente');
        $comunicado = $this->comunicado('Aviso retirado');
        $ruta = route('asambleas.comunicados.portal', [$comunicado->asamblea_id, $comunicado]);

        $this->actingAs($presidente)->put($ruta, ['publicar' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($presidente)->put($ruta, ['publicar' => '1'])
            ->assertSessionHasErrors(['comunicado' => 'Este comunicado ya está publicado en el portal.']);
        $this->actingAs($presidente)->put($ruta, ['publicar' => '0'])
            ->assertSessionHas('success', 'Comunicado retirado del portal.');

        $comunicado->refresh();
        $this->assertFalse($comunicado->publicado_en_portal);
        $this->assertNull($comunicado->publicado_por_usuario_id);

        auth()->logout();
        $this->get(route('portal.castigados'))->assertOk()->assertDontSee('Aviso retirado');
    }

    public function test_solo_el_secretario_o_el_presidente_publican_comunicados(): void
    {
        $comunicado = $this->comunicado('Aviso protegido');
        $ruta = route('asambleas.comunicados.portal', [$comunicado->asamblea_id, $comunicado]);

        foreach (['Tesorero', 'Delegado del Club', 'Capturista'] as $rol) {
            $this->actingAs($this->usuarioConRol($rol))->put($ruta, ['publicar' => '1'])->assertForbidden();
        }

        $this->assertFalse($comunicado->fresh()->publicado_en_portal);

        // La asignación masiva no publica un comunicado
        $otro = ComunicadoAsamblea::query()->create([
            'asamblea_id' => $comunicado->asamblea_id,
            'titulo' => 'Masivo',
            'contenido' => 'x',
            'registrado_por_usuario_id' => $this->tribunal->id,
            'publicado_en_portal' => true,
        ]);
        $this->assertFalse($otro->fresh()->publicado_en_portal);

        // El comunicado debe pertenecer a la asamblea de la ruta
        $otraAsamblea = $this->asamblea();
        $this->actingAs($this->usuarioConRol('Secretario'))
            ->put(route('asambleas.comunicados.portal', [$otraAsamblea, $comunicado]), ['publicar' => '1'])
            ->assertNotFound();
    }

    // Revisión del #106: la fecha del comunicado se muestra en la hora de la liga, no en UTC
    public function test_la_fecha_del_comunicado_sale_en_la_hora_de_mexico(): void
    {
        // 20:00 en México son las 02:00 UTC del día siguiente
        Carbon::setTestNow(Carbon::parse('2026-11-24 20:00:00', Sancion::ZONA_HORARIA));
        $secretario = $this->usuarioConRol('Secretario');
        $comunicado = $this->comunicado('Aviso nocturno');

        $this->actingAs($secretario)
            ->put(route('asambleas.comunicados.portal', [$comunicado->asamblea_id, $comunicado]), ['publicar' => '1'])
            ->assertSessionHasNoErrors();

        $this->actingAs($secretario)->get(route('asambleas.show', $comunicado->asamblea_id))
            ->assertOk()
            ->assertSee('Publicado en el portal · 24/11/2026 20:00');

        auth()->logout();
        $this->get(route('portal.castigados'))->assertOk()
            ->assertSee('<time datetime="2026-11-24">24 de noviembre de 2026</time>', false)
            ->assertDontSee('25 de noviembre de 2026');
    }

    // Revisión del #106: «publicar» se valida; un valor raro no retira un comunicado publicado
    public function test_un_valor_invalido_de_publicar_no_retira_el_comunicado(): void
    {
        $secretario = $this->usuarioConRol('Secretario');
        $comunicado = $this->comunicado('Aviso firme');
        $ruta = route('asambleas.comunicados.portal', [$comunicado->asamblea_id, $comunicado]);
        $this->actingAs($secretario)->put($ruta, ['publicar' => '1'])->assertSessionHasNoErrors();

        foreach ([['publicar' => 'si'], ['publicar' => ['1']], []] as $datos) {
            $this->actingAs($secretario)->put($ruta, $datos)
                ->assertSessionHasErrors(['publicar' => 'Indica si el comunicado se publica o se retira del portal.']);
        }

        $this->assertTrue($comunicado->fresh()->publicado_en_portal);
    }

    // Revisión del #106: el equipo del sancionado es su inscripción vigente, no una baja posterior
    public function test_el_sancionado_sale_en_su_equipo_vigente_aunque_tenga_una_baja_posterior(): void
    {
        $jugador = $this->jugador(0);
        Jugador::create([
            'persona_id' => $jugador->persona_id,
            'equipo_id' => $this->partido->equipo_visitante_id,
            'dorsal' => 99,
            'estado_elegibilidad' => 'Baja',
        ]);
        $this->sancion($jugador);

        $this->get(route('portal.castigados'))->assertOk()
            ->assertSeeInOrder([$this->partido->local->nombre, trim($jugador->persona->nombre.' '.$jugador->persona->apellidos)])
            ->assertDontSee($this->partido->visitante->nombre);
    }

    // Revisión del #106: el resumen cuenta personas distintas, no sanciones
    public function test_una_persona_con_dos_sanciones_cuenta_una_vez(): void
    {
        $jugador = $this->jugador(0);
        $this->sancion($jugador);
        $this->sancion($jugador, ['partidos_suspendidos' => 3]);

        $this->get(route('portal.castigados'))->assertOk()
            ->assertSee('1 persona suspendida')
            ->assertDontSee('2 personas suspendidas');
    }

    private function jugador(int $indice): Jugador
    {
        return Jugador::query()
            ->where('equipo_id', $this->partido->equipo_local_id)
            ->with('persona')
            ->orderBy('id')
            ->skip($indice)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function sancion(Jugador $jugador, array $cambios = []): Sancion
    {
        return Sancion::create(array_merge([
            'expediente_id' => $this->expediente->id,
            'persona_id' => $jugador->persona_id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_PARTIDOS,
            'descripcion_sancion' => 'Suspensión',
            'partidos_suspendidos' => 2,
            'partidos_cumplidos' => 0,
            'fecha_inicio' => '2026-11-20',
            'estado' => Sancion::ESTADO_VIGENTE,
            'publicada_en_boletin' => true,
            'validado_por_usuario_id' => $this->tribunal->id,
            'fecha_validacion' => now(),
            'autorizado_por_usuario_id' => $this->tribunal->id,
            'autorizado_por_cargo' => 'Tribunal de Penas',
            'fecha_publicacion' => now(),
        ], $cambios));
    }

    private function asamblea(): Asamblea
    {
        return Asamblea::query()->create([
            'tipo' => 'Ordinaria',
            'fecha' => '2026-11-20',
            'hora' => '18:00',
            'lugar' => 'Sala de juntas',
            'orden_del_dia' => 'Asuntos generales',
            'estado' => Asamblea::ESTADO_CELEBRADA,
        ]);
    }

    private function comunicado(string $titulo): ComunicadoAsamblea
    {
        return ComunicadoAsamblea::query()->create([
            'asamblea_id' => $this->asamblea()->id,
            'titulo' => $titulo,
            'contenido' => 'Contenido del comunicado',
            'registrado_por_usuario_id' => $this->tribunal->id,
        ]);
    }

    private function usuarioConRol(string $rol): User
    {
        $usuario = User::query()->create([
            'name' => $rol.' '.str()->random(5),
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'rol' => 'Consultor',
            'estatus' => 'activo',
        ]);
        $usuario->assignRole($rol);

        return $usuario;
    }
}
