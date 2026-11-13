<?php

namespace Tests\Feature;

use App\Models\Arbitro;
use App\Models\BloqueoCancha;
use App\Models\Cancha;
use App\Models\Categoria;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\Partido;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pruebas completas para HU-030 (SCRUM-45):
 * Publicar la programación semanal tras su revisión y aprobación (SCRUM-199, SCRUM-200, SCRUM-201, SCRUM-202).
 */
class JornadaPublicacionYConflictosTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $programador;

    private User $comiteUser;

    private Temporada $temporada;

    private Grupo $grupo;

    private Jornada $jornada;

    private Cancha $cancha1;

    private Cancha $cancha2;

    private Arbitro $arbitro;

    private Equipo $equipoA;

    private Equipo $equipoB;

    private Equipo $equipoC;

    private Equipo $equipoD;

    private int $clubIndex = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = $this->crearUsuario('admin_pub@liga.com', 'Administrador General');
        $this->programador = $this->crearUsuario('prog_pub@liga.com', 'Programador de Partidos');
        $this->comiteUser = $this->crearUsuario('presidente_pub@liga.com', 'Presidente');

        $this->temporada = Temporada::factory()->activa()->create([
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
        ]);
        $categoria = Categoria::factory()->create(['temporada_id' => $this->temporada->id]);
        $this->grupo = Grupo::factory()->create(['categoria_id' => $categoria->id]);

        $this->jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-20',
            'fecha_fin' => '2026-11-22',
            'estado' => Jornada::ESTADO_BORRADOR,
        ]);

        $this->cancha1 = Cancha::factory()->create([
            'nombre' => 'Cancha 1 - Polideportivo',
            'estado' => 'Disponible',
            'capacidad_partidos_consecutivos' => 4,
        ]);

        $this->cancha2 = Cancha::factory()->create([
            'nombre' => 'Cancha 2 - Polideportivo',
            'estado' => 'Disponible',
            'capacidad_partidos_consecutivos' => 4,
        ]);

        $this->arbitro = $this->crearArbitro('ARBI910101HDFRRN01');

        $this->equipoA = $this->crearEquipo('Cobras FC');
        $this->equipoB = $this->crearEquipo('Águilas Reales');
        $this->equipoC = $this->crearEquipo('Delfines');
        $this->equipoD = $this->crearEquipo('Lobos BUAP');
    }

    public function test_flujo_completo_de_aprobacion_y_publicacion_semanal(): void
    {
        // Programar dos partidos en horarios válidos sin traslape
        Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->equipoA->id,
            'equipo_visitante_id' => $this->equipoB->id,
            'cancha_id' => $this->cancha1->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '09:00:00',
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->equipoC->id,
            'equipo_visitante_id' => $this->equipoD->id,
            'cancha_id' => $this->cancha1->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '11:00:00', // +120 min después, sin empalme
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        // 1. Enviar a revisión
        $this->actingAs($this->programador)
            ->post(route('jornadas.enviar-revision', $this->jornada))
            ->assertRedirect(route('jornadas.show', $this->jornada))
            ->assertSessionHas('success');

        $this->assertSame(Jornada::ESTADO_EN_REVISION, $this->jornada->fresh()->estado);

        // 2. Aprobación por Comité Ejecutivo
        $this->actingAs($this->comiteUser)
            ->post(route('jornadas.aprobar', $this->jornada))
            ->assertRedirect(route('jornadas.show', $this->jornada))
            ->assertSessionHas('success');

        $jornadaAprobada = $this->jornada->fresh();
        $this->assertSame(Jornada::ESTADO_APROBADA, $jornadaAprobada->estado);
        $this->assertSame($this->comiteUser->id, $jornadaAprobada->aprobado_por_id);
        $this->assertNotNull($jornadaAprobada->fecha_aprobacion);

        // 3. Publicación oficial
        $this->actingAs($this->programador)
            ->post(route('jornadas.publicar', $this->jornada))
            ->assertRedirect(route('jornadas.show', $this->jornada))
            ->assertSessionHas('success');

        $jornadaPublicada = $this->jornada->fresh();
        $this->assertSame(Jornada::ESTADO_PUBLICADA, $jornadaPublicada->estado);
        $this->assertNotNull($jornadaPublicada->fecha_publicacion);

        // Los partidos pasan automáticamente de borrador a programado
        foreach ($jornadaPublicada->partidos as $partido) {
            $this->assertSame(Partido::ESTADO_PROGRAMADO, $partido->estado);
        }
    }

    public function test_no_se_puede_enviar_a_revision_jornada_vacia(): void
    {
        $this->actingAs($this->programador)
            ->post(route('jornadas.enviar-revision', $this->jornada))
            ->assertSessionHasErrors(['jornada']);

        $this->assertSame(Jornada::ESTADO_BORRADOR, $this->jornada->fresh()->estado);
    }

    public function test_bloqueo_de_publicacion_por_traslape_en_misma_cancha(): void
    {
        // Partido 1 a las 10:00 (duración 90 min hasta 11:30)
        Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->equipoA->id,
            'equipo_visitante_id' => $this->equipoB->id,
            'cancha_id' => $this->cancha1->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        // Partido 2 a las 10:45 en la MISMA cancha (traslape horario)
        Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->equipoC->id,
            'equipo_visitante_id' => $this->equipoD->id,
            'cancha_id' => $this->cancha1->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:45:00',
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        // Intentar publicar debe ser rechazado (incluso estando en estado aprobada)
        $this->jornada->update(['estado' => Jornada::ESTADO_APROBADA]);

        $this->actingAs($this->programador)
            ->post(route('jornadas.publicar', $this->jornada))
            ->assertSessionHasErrors(['publicacion']);

        $this->assertNotSame(Jornada::ESTADO_PUBLICADA, $this->jornada->fresh()->estado);

        // El endpoint JSON de conflictos debe detallar la colisión
        $response = $this->actingAs($this->programador)
            ->getJson(route('jornadas.conflictos', $this->jornada))
            ->assertOk()
            ->json();

        $this->assertTrue($response['tiene_conflictos']);
        $this->assertNotEmpty($response['conflictos']);
    }

    public function test_bloqueo_de_publicacion_si_cancha_tiene_bloqueo_activo(): void
    {
        BloqueoCancha::create([
            'cancha_id' => $this->cancha1->id,
            'fecha_inicio' => '2026-11-20 08:00:00',
            'fecha_fin' => '2026-11-20 18:00:00',
            'motivo' => 'Reparación de luminarias',
        ]);

        Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->equipoA->id,
            'equipo_visitante_id' => $this->equipoB->id,
            'cancha_id' => $this->cancha1->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        $this->jornada->update(['estado' => Jornada::ESTADO_APROBADA]);

        $this->actingAs($this->programador)
            ->post(route('jornadas.publicar', $this->jornada))
            ->assertSessionHasErrors(['publicacion']);

        $this->assertNotSame(Jornada::ESTADO_PUBLICADA, $this->jornada->fresh()->estado);
    }

    public function test_publicacion_rechaza_jornada_que_no_este_aprobada(): void
    {
        Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->equipoA->id,
            'equipo_visitante_id' => $this->equipoB->id,
            'cancha_id' => $this->cancha1->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        // Intentar publicar jornada en borrador es rechazado por política
        $this->actingAs($this->programador)
            ->post(route('jornadas.publicar', $this->jornada))
            ->assertForbidden();

        // Incluso el Administrador General es rechazado si intenta saltar el flujo
        $this->actingAs($this->admin)
            ->post(route('jornadas.publicar', $this->jornada))
            ->assertForbidden();

        $this->assertSame(Jornada::ESTADO_BORRADOR, $this->jornada->fresh()->estado);
    }

    public function test_aprobacion_rechaza_jornada_que_no_este_en_revision(): void
    {
        Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->equipoA->id,
            'equipo_visitante_id' => $this->equipoB->id,
            'cancha_id' => $this->cancha1->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        // La jornada sigue en borrador, no se puede aprobar directamente
        $this->actingAs($this->comiteUser)
            ->post(route('jornadas.aprobar', $this->jornada))
            ->assertForbidden();

        $this->assertSame(Jornada::ESTADO_BORRADOR, $this->jornada->fresh()->estado);
    }

    public function test_enviar_a_revision_rechaza_jornada_que_no_este_en_borrador(): void
    {
        $this->jornada->update(['estado' => Jornada::ESTADO_EN_REVISION]);

        $this->actingAs($this->programador)
            ->post(route('jornadas.enviar-revision', $this->jornada))
            ->assertForbidden();
    }

    public function test_bloqueo_de_publicacion_por_equipo_duplicado_o_en_descanso(): void
    {
        // Asignar descanso a equipoA
        $this->jornada->update(['equipo_descanso_id' => $this->equipoA->id]);

        // Programar a equipoA a pesar de tener descanso
        Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->equipoA->id,
            'equipo_visitante_id' => $this->equipoB->id,
            'cancha_id' => $this->cancha1->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        // Programar a equipoB en un segundo partido de la misma jornada
        Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->equipoB->id,
            'equipo_visitante_id' => $this->equipoC->id,
            'cancha_id' => $this->cancha2->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '14:00:00',
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        $response = $this->actingAs($this->programador)
            ->getJson(route('jornadas.conflictos', $this->jornada))
            ->assertOk()
            ->json();

        $this->assertTrue($response['tiene_conflictos']);
        $textosConflictos = implode(' ', $response['conflictos']);
        $this->assertStringContainsString('descanso', $textosConflictos);
        $this->assertStringContainsString('más de una vez', $textosConflictos);
    }

    public function test_programador_no_puede_aprobar_su_propia_jornada(): void
    {
        $this->jornada->update(['estado' => Jornada::ESTADO_EN_REVISION]);

        // El Programador de Partidos tiene prohibido aprobar (Obligatorio 9)
        $this->actingAs($this->programador)
            ->post(route('jornadas.aprobar', $this->jornada))
            ->assertForbidden();

        $this->assertSame(Jornada::ESTADO_EN_REVISION, $this->jornada->fresh()->estado);
    }

    public function test_partido_cancelado_no_bloquea_publicacion_ni_genera_conflictos(): void
    {
        // Partido activo completo
        Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->equipoA->id,
            'equipo_visitante_id' => $this->equipoB->id,
            'cancha_id' => $this->cancha1->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_PROGRAMADO,
        ]);

        // Partido cancelado sin cancha (no debe generar conflicto según Obligatorio 11)
        Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->equipoC->id,
            'equipo_visitante_id' => $this->equipoD->id,
            'cancha_id' => null,
            'fecha_partido' => null,
            'hora_partido' => null,
            'estado' => Partido::ESTADO_CANCELADO,
        ]);

        $conflictos = app(\App\Services\PublicacionProgramacionService::class)->detectarConflictos($this->jornada);
        $this->assertEmpty($conflictos, 'Los partidos cancelados no deben generar conflictos de horario o asignación incompleta.');
    }

    public function test_modificar_o_agregar_partido_en_jornada_aprobada_la_regresa_a_borrador(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->equipoA->id,
            'equipo_visitante_id' => $this->equipoB->id,
            'cancha_id' => $this->cancha1->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        $this->jornada->update([
            'estado' => Jornada::ESTADO_APROBADA,
            'aprobado_por_id' => $this->comiteUser->id,
            'fecha_aprobacion' => now(),
        ]);

        // Al agregar un nuevo partido a la jornada aprobada
        app(\App\Services\ProgramacionJornadaService::class)->programarPartido($this->jornada, [
            'equipo_local_id' => $this->equipoC->id,
            'equipo_visitante_id' => $this->equipoD->id,
            'cancha_id' => $this->cancha2->id,
            'fecha_partido' => '2026-11-21',
            'hora_partido' => '12:00:00',
        ]);

        // Obligatorio 10: La jornada regresa a borrador para requerir nueva revisión
        $this->assertSame(Jornada::ESTADO_BORRADOR, $this->jornada->fresh()->estado);
        $this->assertNull($this->jornada->fresh()->aprobado_por_id);
    }

    private function crearEquipo(string $nombre, ?Grupo $grupo = null): Equipo
    {
        $this->clubIndex++;
        $club = Club::create(['nombre' => 'Club '.$this->clubIndex.' '.$nombre]);

        return Equipo::create([
            'club_id' => $club->id,
            'grupo_id' => $grupo ? $grupo->id : $this->grupo->id,
            'nombre' => $nombre,
        ]);
    }

    private function crearArbitro(string $curp, ?Temporada $temporada = null): Arbitro
    {
        $rol = app(\App\Services\IdentidadPersonaService::class)->asignarRol([
            'nombre' => 'Árbitro',
            'apellidos' => 'Prueba '.$curp,
            'fecha_nacimiento' => '1988-03-12',
            'curp' => $curp,
        ], 'arbitro', $temporada ?? $this->temporada);

        return Arbitro::create(['persona_id' => $rol->persona_id, 'estado' => 'Activo']);
    }

    private function crearUsuario(string $email, string $rol): User
    {
        $usuario = User::create([
            'name' => 'Usuario '.$rol,
            'email' => $email,
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'requires_password_change' => false,
        ]);

        $usuario->assignRole($rol);

        return $usuario;
    }
}
