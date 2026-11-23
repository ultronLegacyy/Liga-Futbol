<?php

namespace Tests\Feature;

use App\Models\CargoFinanciero;
use App\Models\CatalogoArticulo;
use App\Models\ExpedienteDisciplinario;
use App\Models\ExpedienteInvolucrado;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Persona;
use App\Models\Sancion;
use App\Models\User;
use Database\Seeders\CatalogoSancionesArticulosSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreaPartidoConCedula;
use Tests\TestCase;

/**
 * Pruebas de integración para SCRUM-57 / HU-045 / RF-10.2:
 * Registro de audiencia previa, recepción de alegatos de defensa,
 * dictamen de resolución motivada y aplicación de sanciones disciplinarias inmutables.
 */
class AudienciaYResolucionTest extends TestCase
{
    use CreaPartidoConCedula;
    use DatabaseTransactions;

    private Partido $partido;

    private ExpedienteDisciplinario $expediente;

    private User $tribunalUser;

    private User $delegadoUser;

    private User $adminUser;

    private CatalogoArticulo $articulo;

    private Jugador $jugadorInvolucrado;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(CatalogoSancionesArticulosSeeder::class);

        $this->partido = $this->partidoConArbitro();
        $this->tribunalUser = $this->usuario('Tribunal de Penas');
        $this->adminUser = $this->usuario('Administrador General');
        $this->delegadoUser = $this->usuario('Delegado del Club');

        $this->articulo = CatalogoArticulo::firstOrCreate(
            ['codigo' => 'ART-101'],
            [
                'descripcion' => 'Conducta violenta o agresión física a un adversario',
                'castigo_base_partidos' => 3,
                'es_grave' => true,
            ]
        );

        $this->expediente = ExpedienteDisciplinario::create([
            'folio' => 'EXP-2026-TEST-01',
            'partido_id' => $this->partido->id,
            'articulo_id' => $this->articulo->id,
            'titulo' => 'Incidencia disciplinaria en partido de prueba',
            'descripcion_hechos' => 'Agresión física reportada en cédula arbitral',
            'origen' => 'cedula_arbitral',
            'estado' => ExpedienteDisciplinario::ESTADO_ABIERTO,
            'fecha_apertura' => now(),
            'generado_automaticamente' => true,
        ]);

        $this->jugadorInvolucrado = Jugador::where('equipo_id', $this->partido->equipo_local_id)->firstOrFail();

        ExpedienteInvolucrado::create([
            'expediente_id' => $this->expediente->id,
            'persona_id' => $this->jugadorInvolucrado->persona_id,
            'equipo_id' => $this->partido->equipo_local_id,
            'tipo_involucrado' => 'jugador_expulsado',
            'descripcion_participacion' => 'Expulsión directa con tarjeta roja',
        ]);
    }

    /**
     * HU-045 CA1 / RF-10.2: Registro formal de audiencia disciplinaria con comparecientes y alegatos.
     */
    public function test_tribunal_puede_registrar_audiencia_disciplinaria(): void
    {
        $response = $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.audiencias.store', $this->expediente), [
                'fecha_audiencia' => now(Sancion::ZONA_HORARIA)->format('Y-m-d\TH:i'),
                'lugar_o_medio' => 'Sala de Audiencias de la Liga',
                'comparecientes' => 'Árbitro central, Representante del Club Local, Jugador imputado',
                'alegatos_defensa' => 'El jugador manifiesta que no existió intención dolosa de golpear al rival, sino una disputa aérea de balón.',
                'resolucion' => 'Se reciben alegatos y se turna para valoración colegiada de la Comisión.',
            ]);

        $response->assertRedirect(route('disciplina.expedientes.show', $this->expediente));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('audiencias_disciplinarias', [
            'expediente_id' => $this->expediente->id,
            'lugar_o_medio' => 'Sala de Audiencias de la Liga',
            'registrado_por_usuario_id' => $this->tribunalUser->id,
        ]);
    }

    /**
     * HU-045 CA1: Validación de campos requeridos al registrar audiencia.
     */
    public function test_falla_registro_audiencia_sin_campos_obligatorios(): void
    {
        $response = $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.audiencias.store', $this->expediente), [
                'fecha_audiencia' => '',
                'lugar_o_medio' => '',
                'comparecientes' => '',
                'alegatos_defensa' => '',
            ]);

        $response->assertSessionHasErrors(['fecha_audiencia', 'lugar_o_medio', 'comparecientes', 'alegatos_defensa']);
    }

    /**
     * HU-045 CA1 / RF-10.2: Dictamen de resolución motivada que aplica sanción a persona y resuelve expediente.
     */
    public function test_tribunal_puede_dictaminar_sancion_y_resolver_expediente(): void
    {
        $response = $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.sanciones.store', $this->expediente), [
                'persona_id' => $this->jugadorInvolucrado->persona_id,
                'equipo_id' => $this->partido->equipo_local_id,
                'articulo_id' => $this->articulo->id,
                'tipo_sancion' => Sancion::TIPO_PARTIDOS,
                'partidos_suspendidos' => 3,
                'fecha_inicio' => now(Sancion::ZONA_HORARIA)->toDateString(),
                'motivo_resolucion' => 'Se acreditó plenamente la agresión física reportada en cédula conforme al artículo 101 del Reglamento.',
            ]);

        $response->assertRedirect(route('disciplina.expedientes.show', $this->expediente));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('sanciones', [
            'expediente_id' => $this->expediente->id,
            'persona_id' => $this->jugadorInvolucrado->persona_id,
            'articulo_id' => $this->articulo->id,
            'partidos_suspendidos' => 3,
            'partidos_cumplidos' => 0,
            'fecha_fin' => null,
            'estado' => Sancion::ESTADO_VIGENTE,
        ]);

        $this->expediente->refresh();
        $this->assertTrue($this->expediente->estaResuelto());
        $this->assertEquals(ExpedienteDisciplinario::ESTADO_RESUELTO, $this->expediente->estado);
    }

    /**
     * PO #47: Dictamen de multa económica al equipo genera cargo financiero al club y no suspende partidos.
     */
    public function test_tribunal_puede_dictaminar_sancion_economica_a_equipo(): void
    {
        $response = $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.sanciones.store', $this->expediente), [
                'equipo_id' => $this->partido->equipo_local_id,
                'articulo_id' => $this->articulo->id,
                'tipo_sancion' => Sancion::TIPO_MULTA,
                'monto_multa' => 2500.50,
                'motivo_resolucion' => 'Conducta impropia de la porra del club local conforme a los estatutos de la liga.',
            ]);

        $response->assertRedirect(route('disciplina.expedientes.show', $this->expediente));

        $this->assertDatabaseHas('sanciones', [
            'expediente_id' => $this->expediente->id,
            'equipo_id' => $this->partido->equipo_local_id,
            'persona_id' => null,
            'tipo_sancion' => Sancion::TIPO_MULTA,
            'partidos_suspendidos' => 0,
            'monto_multa' => 2500.50,
        ]);

        // PO #47: la multa genera un cargo financiero al club
        $equipo = $this->partido->local;
        $this->assertDatabaseHas('cargos_financieros', [
            'club_id' => $equipo->club_id,
            'equipo_id' => $equipo->id,
            'concepto' => 'Multa',
            'monto' => 2500.50,
            'estado' => 'Pendiente',
        ]);
        $this->assertSame(1, CargoFinanciero::where('club_id', $equipo->club_id)->where('concepto', 'Multa')->count());
    }

    /**
     * PO #47: Suspensión temporal exige fecha_fin explícita (sin 30 días por omisión) y amonestación queda registrada.
     */
    public function test_suspension_temporal_exige_fecha_fin_explicita_y_amonestacion_se_registra(): void
    {
        $sinFechaFin = $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.sanciones.store', $this->expediente), [
                'persona_id' => $this->jugadorInvolucrado->persona_id,
                'articulo_id' => $this->articulo->id,
                'tipo_sancion' => Sancion::TIPO_TEMPORAL,
                'fecha_inicio' => '2026-12-01',
                'motivo_resolucion' => 'Suspensión temporal sin fecha límite no debe permitirse.',
            ]);

        $sinFechaFin->assertSessionHasErrors('fecha_fin');

        $conFechaFin = $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.sanciones.store', $this->expediente), [
                'persona_id' => $this->jugadorInvolucrado->persona_id,
                'articulo_id' => $this->articulo->id,
                'tipo_sancion' => Sancion::TIPO_TEMPORAL,
                'fecha_inicio' => '2026-12-01',
                'fecha_fin' => '2026-12-10',
                'cerrar_expediente' => 0,
                'motivo_resolucion' => 'Suspensión temporal explícita de 10 días calendario por desacato.',
            ]);

        $conFechaFin->assertRedirect(route('disciplina.expedientes.show', $this->expediente));
        $this->assertDatabaseHas('sanciones', [
            'expediente_id' => $this->expediente->id,
            'tipo_sancion' => Sancion::TIPO_TEMPORAL,
            'fecha_fin' => '2026-12-10',
        ]);

        $amonestacion = $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.sanciones.store', $this->expediente), [
                'persona_id' => $this->jugadorInvolucrado->persona_id,
                'articulo_id' => $this->articulo->id,
                'tipo_sancion' => Sancion::TIPO_AMONESTACION,
                'motivo_resolucion' => 'Amonestación escrita preventiva por conducta antideportiva menor.',
            ]);

        $amonestacion->assertRedirect(route('disciplina.expedientes.show', $this->expediente));
        $this->assertDatabaseHas('sanciones', [
            'expediente_id' => $this->expediente->id,
            'tipo_sancion' => Sancion::TIPO_AMONESTACION,
            'partidos_suspendidos' => 0,
        ]);
    }

    /**
     * HU-045 / Concurrencia e inmutabilidad: No se permite registrar audiencia ni sancionar si el expediente ya está resuelto.
     */
    public function test_no_se_pueden_registrar_audiencias_ni_dictaminar_en_expediente_ya_resuelto(): void
    {
        $this->expediente->update(['estado' => ExpedienteDisciplinario::ESTADO_RESUELTO]);

        $responseAud = $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.audiencias.store', $this->expediente), [
                'fecha_audiencia' => now(Sancion::ZONA_HORARIA)->format('Y-m-d H:i:s'),
                'lugar_o_medio' => 'Sala de Audiencias',
                'comparecientes' => 'Comparecientes de prueba',
                'alegatos_defensa' => 'Alegatos de defensa suficientemente largos para validar',
            ]);

        $responseAud->assertSessionHasErrors(['expediente']);

        $responseSancion = $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.sanciones.store', $this->expediente), [
                'persona_id' => $this->jugadorInvolucrado->persona_id,
                'articulo_id' => $this->articulo->id,
                'tipo_sancion' => Sancion::TIPO_PARTIDOS,
                'partidos_suspendidos' => 1,
                'motivo_resolucion' => 'Intento concurrente sobre expediente previamente resuelto.',
            ]);

        $responseSancion->assertSessionHasErrors(['expediente']);
    }

    /**
     * Obligatorio 2: RBAC - Usuarios sin permiso reciben HTTP 403 con mensaje explícito en español en todos los endpoints.
     */
    public function test_usuario_no_autorizado_recibe_403_con_mensaje_en_espanol(): void
    {
        $sancion = Sancion::create([
            'expediente_id' => $this->expediente->id,
            'persona_id' => $this->jugadorInvolucrado->persona_id,
            'equipo_id' => $this->partido->equipo_local_id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_PARTIDOS,
            'partidos_suspendidos' => 2,
            'partidos_cumplidos' => 0,
            'estado' => Sancion::ESTADO_VIGENTE,
        ]);

        $arbitro = $this->usuario('Árbitro en Campo', $this->partido->arbitro);
        $programador = $this->usuario('Programador de Partidos');
        $comisionArbitral = $this->usuario('Comisión Arbitral');

        foreach ([$this->delegadoUser, $arbitro, $programador, $comisionArbitral] as $actor) {
            $resAud = $this->actingAs($actor)->post(route('disciplina.expedientes.audiencias.store', $this->expediente), [
                'fecha_audiencia' => now(Sancion::ZONA_HORARIA)->format('Y-m-d H:i:s'),
                'lugar_o_medio' => 'Sala de Audiencias',
                'comparecientes' => 'Compareciente',
                'alegatos_defensa' => 'Alegatos de defensa largos para pasar validacion',
            ]);
            $resAud->assertForbidden();
            $this->assertStringNotContainsString('This action is unauthorized', $resAud->getContent());
            $this->assertStringContainsString('Tribunal de Penas', $resAud->getContent());

            $resDic = $this->actingAs($actor)->post(route('disciplina.expedientes.sanciones.store', $this->expediente), [
                'persona_id' => $this->jugadorInvolucrado->persona_id,
                'tipo_sancion' => Sancion::TIPO_MULTA,
                'monto_multa' => 500,
                'motivo_resolucion' => 'Intento no autorizado con longitud valida',
            ]);
            $resDic->assertForbidden();
            $this->assertStringNotContainsString('This action is unauthorized', $resDic->getContent());
            $this->assertStringContainsString('Tribunal de Penas', $resDic->getContent());

            $resVal = $this->actingAs($actor)->post(route('disciplina.sanciones.validar', $sancion), [
                'motivo' => 'Intento validar sin privilegio',
            ]);
            $resVal->assertForbidden();
            $this->assertStringNotContainsString('This action is unauthorized', $resVal->getContent());

            $resPub = $this->actingAs($actor)->post(route('disciplina.boletin.publicar'), [
                'sanciones_ids' => [$sancion->id],
                'motivo' => 'Intento publicar sin privilegio',
            ]);
            $resPub->assertForbidden();
            $this->assertStringNotContainsString('This action is unauthorized', $resPub->getContent());

            $resPubOne = $this->actingAs($actor)->post(route('disciplina.sanciones.publicar-boletin', $sancion), [
                'motivo' => 'Intento publicar individual sin privilegio',
            ]);
            $resPubOne->assertForbidden();
            $this->assertStringNotContainsString('This action is unauthorized', $resPubOne->getContent());

            $resDespub = $this->actingAs($actor)->post(route('disciplina.sanciones.despublicar', $sancion), [
                'motivo' => 'Intento despublicar sin privilegio',
            ]);
            $resDespub->assertForbidden();
            $this->assertStringNotContainsString('This action is unauthorized', $resDespub->getContent());
        }
    }

    /**
     * HU-045 CA2: Vista de detalle muestra el historial inmutable de audiencias y sanciones.
     */
    public function test_vista_muestra_historial_inmutable_de_audiencias_y_sanciones(): void
    {
        $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.audiencias.store', $this->expediente), [
                'fecha_audiencia' => now(Sancion::ZONA_HORARIA)->format('Y-m-d H:i:s'),
                'lugar_o_medio' => 'Tribunal Sala Principal',
                'comparecientes' => 'Directiva y Árbitro Test',
                'alegatos_defensa' => 'Alegato registrado para prueba visual',
            ]);

        $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.sanciones.store', $this->expediente), [
                'persona_id' => $this->jugadorInvolucrado->persona_id,
                'equipo_id' => $this->partido->equipo_local_id,
                'articulo_id' => $this->articulo->id,
                'tipo_sancion' => Sancion::TIPO_PARTIDOS,
                'partidos_suspendidos' => 2,
                'motivo_resolucion' => 'Resolución inmutable de prueba',
            ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('disciplina.expedientes.show', $this->expediente));

        $response->assertOk();
        $response->assertSee('Tribunal Sala Principal');
        $response->assertSee('Alegato registrado para prueba visual');
        $response->assertSee('Resolución inmutable de prueba');
        $response->assertSee('Resuelto / Dictaminado');
    }

    /**
     * Obligatorio 4: Acota persona_id a involucrados y equipo_id a equipos del expediente.
     */
    public function test_rechaza_personas_y_equipos_ajenos_al_expediente_y_tipos_fuera_de_catalogo(): void
    {
        $ajeno = Persona::factory()->create();
        $equipoOtro = $this->equipo($this->partido->jornada->grupo);

        $resPersonaAjena = $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.sanciones.store', $this->expediente), [
                'persona_id' => $ajeno->id,
                'tipo_sancion' => Sancion::TIPO_PARTIDOS,
                'partidos_suspendidos' => 2,
                'motivo_resolucion' => 'Intento de sancionar a persona que no figura en el expediente.',
            ]);
        $resPersonaAjena->assertSessionHasErrors('persona_id');

        $resEquipoAjeno = $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.sanciones.store', $this->expediente), [
                'equipo_id' => $equipoOtro->id,
                'tipo_sancion' => Sancion::TIPO_MULTA,
                'monto_multa' => 1000,
                'motivo_resolucion' => 'Intento de multar a un equipo que no participó en el encuentro.',
            ]);
        $resEquipoAjeno->assertSessionHasErrors('equipo_id');

        foreach (['suspension_equipo', 'inhabilitacion'] as $tipoEliminado) {
            $resTipo = $this->actingAs($this->tribunalUser)
                ->post(route('disciplina.expedientes.sanciones.store', $this->expediente), [
                    'persona_id' => $this->jugadorInvolucrado->persona_id,
                    'tipo_sancion' => $tipoEliminado,
                    'partidos_suspendidos' => 1,
                    'motivo_resolucion' => 'Tipo eliminado por decisión del Product Owner.',
                ]);
            $resTipo->assertSessionHasErrors('tipo_sancion');
        }
    }

    /**
     * Obligatorio 4: Fechas con formato estricto YYYY-MM-DD, rechazo de arreglos y monto máximo sin 500.
     */
    public function test_rechaza_fechas_en_formato_incorrecto_arreglos_y_overflow_sin_error_500(): void
    {
        $resFechaMal = $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.sanciones.store', $this->expediente), [
                'persona_id' => $this->jugadorInvolucrado->persona_id,
                'tipo_sancion' => Sancion::TIPO_TEMPORAL,
                'fecha_inicio' => '01/12/2026',
                'fecha_fin' => '15/12/2026',
                'motivo_resolucion' => 'Validación estricta de formato YYYY-MM-DD sin reinterpretación.',
            ]);
        $resFechaMal->assertSessionHasErrors(['fecha_inicio', 'fecha_fin']);

        $resAudienciaMal = $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.audiencias.store', $this->expediente), [
                'fecha_audiencia' => '20/11/2026 10:00',
                'lugar_o_medio' => 'Sala 1',
                'comparecientes' => 'Directiva Club',
                'alegatos_defensa' => 'Alegatos válidos con longitud mínima requerida.',
            ]);
        $resAudienciaMal->assertSessionHasErrors('fecha_audiencia');

        $resArreglos = $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.sanciones.store', $this->expediente), [
                'persona_id' => $this->jugadorInvolucrado->persona_id,
                'tipo_sancion' => ['x'],
                'fecha_inicio' => ['x'],
                'monto_multa' => ['100'],
                'motivo_resolucion' => ['texto en arreglo'],
            ]);
        $resArreglos->assertStatus(302);
        $resArreglos->assertSessionHasErrors(['tipo_sancion', 'motivo_resolucion']);

        $resOverflow = $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.sanciones.store', $this->expediente), [
                'equipo_id' => $this->partido->equipo_local_id,
                'tipo_sancion' => Sancion::TIPO_MULTA,
                'monto_multa' => 99999999999,
                'motivo_resolucion' => 'Monto excesivo fuera de decimal(10,2) debe responder 422/302 en español.',
            ]);
        $resOverflow->assertStatus(302);
        $resOverflow->assertSessionHasErrors('monto_multa');
    }

    /**
     * Recomendados RNF-44 + Obligatorio 4: Validar, publicar (derivando cargo del rol) y despublicar en ruta unificada.
     */
    public function test_flujo_http_validar_publicar_y_despublicar_sancion_desde_expediente(): void
    {
        $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.sanciones.store', $this->expediente), [
                'persona_id' => $this->jugadorInvolucrado->persona_id,
                'equipo_id' => $this->partido->equipo_local_id,
                'articulo_id' => $this->articulo->id,
                'tipo_sancion' => Sancion::TIPO_PARTIDOS,
                'partidos_suspendidos' => 2,
                'motivo_resolucion' => 'Sanción dictaminada pendiente de validación colegiada RNF-44',
            ])
            ->assertRedirect(route('disciplina.expedientes.show', $this->expediente));

        $sancion = Sancion::where('expediente_id', $this->expediente->id)->firstOrFail();
        $this->assertFalse($sancion->estaValidada());

        // Validar exige motivo mínimo de 5 caracteres en español
        $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.sanciones.validar', $sancion), ['motivo' => 'abc'])
            ->assertSessionHasErrors('motivo');

        $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.sanciones.validar', $sancion), [
                'motivo' => 'Validación colegiada de la Comisión Disciplinaria',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $sancion->refresh();
        $this->assertTrue($sancion->estaValidada());

        // No permite revalidar para preservar trazabilidad del validador original
        $this->actingAs($this->adminUser)
            ->post(route('disciplina.sanciones.validar', $sancion), [
                'motivo' => 'Segundo intento de validación por otro usuario',
            ])
            ->assertSessionHasErrors('sancion');
        $this->assertSame($this->tribunalUser->id, $sancion->fresh()->validado_por_usuario_id);

        // Publicar ignora cualquier cargo falseado por el cliente y toma el rol del usuario autenticado
        $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.sanciones.publicar-boletin', $sancion), [
                'autorizado_por_cargo' => 'Rey del Universo',
                'motivo' => 'Publicación oficial semanal',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $sancion->refresh();
        $this->assertTrue($sancion->publicada_en_boletin);
        $this->assertSame('Tribunal de Penas', $sancion->autorizado_por_cargo);

        // Retirar en la ruta única
        $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.sanciones.despublicar', $sancion), [
                'motivo' => 'Suspensión provisional por recurso',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $sancion->refresh();
        $this->assertFalse($sancion->publicada_en_boletin);
    }
}
