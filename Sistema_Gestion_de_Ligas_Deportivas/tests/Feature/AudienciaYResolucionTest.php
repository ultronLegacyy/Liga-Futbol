<?php

namespace Tests\Feature;

use App\Models\CatalogoArticulo;
use App\Models\ExpedienteDisciplinario;
use App\Models\ExpedienteInvolucrado;
use App\Models\Jugador;
use App\Models\Partido;
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

        // Crear expediente disciplinario abierto
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

        $jugador = Jugador::where('equipo_id', $this->partido->equipo_local_id)->first();
        if ($jugador !== null) {
            ExpedienteInvolucrado::create([
                'expediente_id' => $this->expediente->id,
                'persona_id' => $jugador->persona_id,
                'equipo_id' => $this->partido->equipo_local_id,
                'tipo_involucrado' => 'jugador_expulsado',
                'descripcion_participacion' => 'Expulsión directa con tarjeta roja',
            ]);
        }
    }

    /**
     * RF-10.2 Criterio 1: Registro formal de audiencia disciplinaria con comparecientes y alegatos.
     */
    public function test_tribunal_puede_registrar_audiencia_disciplinaria(): void
    {
        $response = $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.audiencias.store', $this->expediente), [
                'fecha_audiencia' => now()->format('Y-m-d H:i:s'),
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
     * RF-10.2 Criterio 1: Validación de campos requeridos al registrar audiencia.
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
     * RF-10.2 Criterio 2: Dictamen de resolución motivada que aplica sanción a persona y resuelve expediente.
     */
    public function test_tribunal_puede_dictaminar_sancion_y_resolver_expediente(): void
    {
        $jugador = Jugador::where('equipo_id', $this->partido->equipo_local_id)->firstOrFail();

        $response = $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.sanciones.store', $this->expediente), [
                'persona_id' => $jugador->persona_id,
                'equipo_id' => $this->partido->equipo_local_id,
                'articulo_id' => $this->articulo->id,
                'tipo_sancion' => Sancion::TIPO_SUSPENSION_PARTIDOS,
                'partidos_suspension' => 3,
                'fecha_inicio' => now()->toDateString(),
                'motivo_resolucion' => 'Se acreditó plenamente la agresión física reportada en cédula conforme al artículo 101 del Reglamento.',
            ]);

        $response->assertRedirect(route('disciplina.expedientes.show', $this->expediente));
        $response->assertSessionHas('success');

        // Verificar que la sanción quedó registrada y vigente
        $this->assertDatabaseHas('sanciones', [
            'expediente_id' => $this->expediente->id,
            'persona_id' => $jugador->persona_id,
            'articulo_id' => $this->articulo->id,
            'partidos_suspendidos' => 3,
            'partidos_cumplidos' => 0,
            'estado' => Sancion::ESTADO_VIGENTE,
        ]);

        // Verificar que el expediente cambió a Resuelto
        $this->expediente->refresh();
        $this->assertTrue($this->expediente->estaResuelto());
        $this->assertEquals(ExpedienteDisciplinario::ESTADO_RESUELTO, $this->expediente->estado);
    }

    /**
     * RF-10.2 Criterio 3: Dictamen de sanción colectiva a un equipo con multa económica.
     */
    public function test_tribunal_puede_dictaminar_sancion_economica_a_equipo(): void
    {
        $response = $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.sanciones.store', $this->expediente), [
                'equipo_id' => $this->partido->equipo_local_id,
                'articulo_id' => $this->articulo->id,
                'tipo_sancion' => Sancion::TIPO_MULTA_ECONOMICA,
                'monto_multa' => 2500.50,
                'motivo_resolucion' => 'Conducta impropia de la porra del club local conforme a los estatutos de la liga.',
            ]);

        $response->assertRedirect(route('disciplina.expedientes.show', $this->expediente));

        $this->assertDatabaseHas('sanciones', [
            'expediente_id' => $this->expediente->id,
            'equipo_id' => $this->partido->equipo_local_id,
            'persona_id' => null,
            'tipo_sancion' => Sancion::TIPO_MULTA_ECONOMICA,
            'partidos_suspendidos' => 0,
            'monto_multa' => 2500.50,
        ]);
    }

    /**
     * RF-10.2 Criterio 4: No se permite registrar audiencia ni sancionar si el expediente ya está resuelto.
     */
    public function test_no_se_pueden_registrar_audiencias_en_expediente_ya_resuelto(): void
    {
        $this->expediente->update(['estado' => ExpedienteDisciplinario::ESTADO_RESUELTO]);

        $response = $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.audiencias.store', $this->expediente), [
                'fecha_audiencia' => now()->format('Y-m-d H:i:s'),
                'lugar_o_medio' => 'Sala de Audiencias',
                'comparecientes' => 'Comparecientes de prueba',
                'alegatos_defensa' => 'Alegatos de defensa suficientemente largos para validar',
            ]);

        $response->assertSessionHasErrors(['expediente']);
    }

    /**
     * RF-10.2 Criterio 5: RBAC - Delegado no puede registrar audiencias ni dictaminar sanciones (403).
     */
    public function test_usuario_no_autorizado_recibe_403(): void
    {
        $responseAud = $this->actingAs($this->delegadoUser)
            ->post(route('disciplina.expedientes.audiencias.store', $this->expediente), [
                'fecha_audiencia' => now()->format('Y-m-d H:i:s'),
                'lugar_o_medio' => 'Sala de Audiencias',
                'comparecientes' => 'Compareciente',
                'alegatos_defensa' => 'Alegatos',
            ]);

        $responseAud->assertForbidden();

        $responseSancion = $this->actingAs($this->delegadoUser)
            ->post(route('disciplina.expedientes.sanciones.store', $this->expediente), [
                'equipo_id' => $this->partido->equipo_local_id,
                'articulo_id' => $this->articulo->id,
                'tipo_sancion' => Sancion::TIPO_MULTA_ECONOMICA,
                'monto_multa' => 500,
                'motivo_resolucion' => 'Intento no autorizado',
            ]);

        $responseSancion->assertForbidden();
    }

    /**
     * RF-10.2 Criterio 6: Vista de detalle muestra el historial inmutable de audiencias y sanciones.
     */
    public function test_vista_muestra_historial_inmutable_de_audiencias_y_sanciones(): void
    {
        // Registrar audiencia
        $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.audiencias.store', $this->expediente), [
                'fecha_audiencia' => now()->format('Y-m-d H:i:s'),
                'lugar_o_medio' => 'Tribunal Sala Principal',
                'comparecientes' => 'Directiva y Árbitro Test',
                'alegatos_defensa' => 'Alegato registrado para prueba visual',
            ]);

        // Registrar sanción
        $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.sanciones.store', $this->expediente), [
                'equipo_id' => $this->partido->equipo_local_id,
                'articulo_id' => $this->articulo->id,
                'tipo_sancion' => Sancion::TIPO_SUSPENSION_PARTIDOS,
                'partidos_suspension' => 2,
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
     * Flujo HTTP completo en expediente resuelto: validar sanción, publicar en boletín y retirar del boletín.
     */
    public function test_flujo_http_validar_publicar_y_despublicar_sancion_desde_expediente(): void
    {
        $jugador = Jugador::where('equipo_id', $this->partido->equipo_local_id)->firstOrFail();

        $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.sanciones.store', $this->expediente), [
                'persona_id' => $jugador->persona_id,
                'equipo_id' => $this->partido->equipo_local_id,
                'articulo_id' => $this->articulo->id,
                'tipo_sancion' => Sancion::TIPO_PARTIDOS,
                'partidos_suspendidos' => 2,
                'motivo_resolucion' => 'Sanción dictaminada pendiente de validación colegiada RNF-44',
            ])
            ->assertRedirect(route('disciplina.expedientes.show', $this->expediente));

        $sancion = Sancion::where('expediente_id', $this->expediente->id)->firstOrFail();
        $this->assertFalse($sancion->estaValidada());

        // 1. Validar sanción vía POST
        $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.sanciones.validar', $sancion), [
                'motivo' => 'Validación colegiada de la Comisión Disciplinaria',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $sancion->refresh();
        $this->assertTrue($sancion->estaValidada());

        // 2. Publicar en boletín vía POST guardando cargo
        $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.sanciones.publicar-boletin', $sancion), [
                'autorizado_por_cargo' => 'Presidente del Tribunal de Penas',
                'motivo' => 'Publicación oficial semanal',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $sancion->refresh();
        $this->assertTrue($sancion->publicada_en_boletin);
        $this->assertSame('Presidente del Tribunal de Penas', $sancion->autorizado_por_cargo);

        // 3. Despublicar vía POST
        $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.sanciones.despublicar-boletin', $sancion), [
                'motivo' => 'Suspensión provisional por recurso',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $sancion->refresh();
        $this->assertFalse($sancion->publicada_en_boletin);
    }
}
