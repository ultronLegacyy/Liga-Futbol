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
use App\Models\Temporada;
use App\Models\User;
use App\Services\ProgramacionJornadaService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * HU-027 (SCRUM-42 / SCRUM-154, SCRUM-155, SCRUM-156, SCRUM-157)
 * Pruebas completas de programación manual de partidos, colisiones de canchas y árbitros,
 * control de descanso y control de accesos (RF-06.1, RF-06.2, IU-16, RNF-29, RNF-33).
 */
class PartidosProgramacionTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $programador;

    private User $tesoreroUser;

    private Temporada $temporada;

    private Grupo $grupo;

    private Jornada $jornada;

    private Cancha $cancha;

    private Arbitro $arbitro;

    private Equipo $local;

    private Equipo $visitante;

    private int $clubIndex = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = $this->crearUsuario('admin_partidos@liga.com', 'Administrador General');
        $this->programador = $this->crearUsuario('programador_partidos@liga.com', 'Programador de Partidos');
        $this->tesoreroUser = $this->crearUsuario('tesorero_partidos@liga.com', 'Tesorero');

        $this->temporada = Temporada::factory()->activa()->create();
        $categoria = Categoria::factory()->create(['temporada_id' => $this->temporada->id]);
        $this->grupo = Grupo::factory()->create(['categoria_id' => $categoria->id]);

        $this->jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-20',
            'fecha_fin' => '2026-11-22',
        ]);

        $this->cancha = Cancha::factory()->create([
            'nombre' => 'Cancha Central',
            'estado' => 'Disponible',
            'capacidad_partidos_consecutivos' => 4,
        ]);

        $this->arbitro = $this->crearArbitro('ARBI850101HDFRRN01');

        $this->local = $this->crearEquipo('Halcones FC');
        $this->visitante = $this->crearEquipo('Pumas FC');
    }

    public function test_programador_y_admin_pueden_programar_partido_manualmente(): void
    {
        $payload = [
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00',
        ];

        $this->actingAs($this->programador)
            ->post(route('jornadas.partidos.store', $this->jornada), $payload)
            ->assertRedirect(route('jornadas.show', $this->jornada))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('partidos', [
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => 'programado',
        ]);
    }

    public function test_usuario_sin_permisos_recibe_403_al_intentar_programar_partido(): void
    {
        $payload = [
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00',
        ];

        $this->actingAs($this->tesoreroUser)
            ->post(route('jornadas.partidos.store', $this->jornada), $payload)
            ->assertForbidden();

        $this->assertSame(0, $this->jornada->partidos()->count());
    }

    public function test_programacion_falla_si_equipo_local_es_igual_a_visitante(): void
    {
        $payload = [
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->local->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00',
        ];

        $this->actingAs($this->programador)
            ->post(route('jornadas.partidos.store', $this->jornada), $payload)
            ->assertSessionHasErrors('equipo_visitante_id');

        $this->assertSame(0, $this->jornada->partidos()->count());
    }

    public function test_programacion_falla_si_equipo_no_pertenece_al_grupo(): void
    {
        $otroGrupo = Grupo::factory()->create();
        $equipoExterno = $this->crearEquipo('Equipo Ajeno', $otroGrupo);

        $payload = [
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $equipoExterno->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00',
        ];

        $this->actingAs($this->programador)
            ->post(route('jornadas.partidos.store', $this->jornada), $payload)
            ->assertSessionHasErrors('equipo_visitante_id');
    }

    public function test_programacion_falla_si_equipo_descansa_en_la_jornada(): void
    {
        $this->jornada->update(['equipo_descanso_id' => $this->local->id]);

        $payload = [
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00',
        ];

        $this->actingAs($this->programador)
            ->post(route('jornadas.partidos.store', $this->jornada), $payload)
            ->assertSessionHasErrors('equipo_local_id');

        $this->assertSame(0, $this->jornada->partidos()->count());
    }

    public function test_programacion_falla_si_equipo_ya_tiene_partido_en_la_misma_jornada(): void
    {
        Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => 'programado',
        ]);

        $tercerEquipo = $this->crearEquipo('Tercer Equipo');
        $otroArbitro = $this->crearArbitro('ARBI850101HDFRRN02');
        $otraCancha = Cancha::factory()->create(['estado' => 'Disponible']);

        // Intentar programar a $this->local nuevamente en la misma jornada
        $payload = [
            'equipo_local_id' => $tercerEquipo->id,
            'equipo_visitante_id' => $this->local->id,
            'cancha_id' => $otraCancha->id,
            'arbitro_id' => $otroArbitro->id,
            'fecha_partido' => '2026-11-21',
            'hora_partido' => '12:00',
        ];

        $this->actingAs($this->programador)
            ->post(route('jornadas.partidos.store', $this->jornada), $payload)
            ->assertSessionHasErrors('equipo_visitante_id');

        $this->assertSame(1, $this->jornada->partidos()->count());
    }

    public function test_programacion_falla_si_arbitro_no_esta_disponible_para_la_temporada(): void
    {
        $otraTemporada = Temporada::factory()->activa()->create();
        $arbitroOtraTemporada = $this->crearArbitro('ARBI850101HDFRRN03', $otraTemporada);

        $payload = [
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $arbitroOtraTemporada->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00',
        ];

        $this->actingAs($this->programador)
            ->post(route('jornadas.partidos.store', $this->jornada), $payload)
            ->assertSessionHasErrors('arbitro_id');

        $this->assertSame(0, $this->jornada->partidos()->count());
    }

    public function test_programacion_falla_si_arbitro_ya_tiene_partido_en_misma_fecha_y_hora(): void
    {
        // Primer partido con el árbitro a las 10:00
        Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => 'programado',
        ]);

        $equipo3 = $this->crearEquipo('Equipo Tres');
        $equipo4 = $this->crearEquipo('Equipo Cuatro');
        $otraCancha = Cancha::factory()->create(['estado' => 'Disponible']);

        // Segundo partido intentando asignar el mismo árbitro a las 10:00
        $payload = [
            'equipo_local_id' => $equipo3->id,
            'equipo_visitante_id' => $equipo4->id,
            'cancha_id' => $otraCancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00',
        ];

        $this->actingAs($this->programador)
            ->post(route('jornadas.partidos.store', $this->jornada), $payload)
            ->assertSessionHasErrors('arbitro_id');

        $this->assertSame(1, $this->jornada->partidos()->count());
    }

    public function test_programacion_falla_si_cancha_tiene_colision_de_horario(): void
    {
        // Primer partido en Cancha Central a las 10:00
        Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => 'programado',
        ]);

        $equipo3 = $this->crearEquipo('Equipo C');
        $equipo4 = $this->crearEquipo('Equipo D');
        $otroArbitro = $this->crearArbitro('ARBI850101HDFRRN04');

        // Segundo partido en la misma cancha a las 10:30 (dentro de los 90 min)
        $payload = [
            'equipo_local_id' => $equipo3->id,
            'equipo_visitante_id' => $equipo4->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $otroArbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:30',
        ];

        $this->actingAs($this->programador)
            ->post(route('jornadas.partidos.store', $this->jornada), $payload)
            ->assertSessionHasErrors('cancha_id');

        $this->assertSame(1, $this->jornada->partidos()->count());
    }

    public function test_reprogramar_partido_actualiza_cancha_arbitro_y_horario(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => 'programado',
        ]);

        $nuevaCancha = Cancha::factory()->create(['estado' => 'Disponible']);
        $nuevoArbitro = $this->crearArbitro('ARBI850101HDFRRN05');

        $payload = [
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $nuevaCancha->id,
            'arbitro_id' => $nuevoArbitro->id,
            'fecha_partido' => '2026-11-21',
            'hora_partido' => '16:00',
            'motivo' => 'Ajuste de horario y sede por disponibilidad de campo.',
        ];

        $this->actingAs($this->programador)
            ->put(route('partidos.update', $partido), $payload)
            ->assertRedirect(route('jornadas.show', $this->jornada))
            ->assertSessionHas('success');

        $partido->refresh();
        $this->assertSame($nuevaCancha->id, $partido->cancha_id);
        $this->assertSame($nuevoArbitro->id, $partido->arbitro_id);
        $this->assertSame('2026-11-21', $partido->fecha_partido?->format('Y-m-d'));
        $this->assertSame('16:00:00', $partido->hora_partido);
    }

    public function test_temporada_cerrada_impide_programar_partidos(): void
    {
        $temporadaCerrada = Temporada::factory()->cerrada()->create();
        $categoria = Categoria::factory()->create(['temporada_id' => $temporadaCerrada->id]);
        $grupoCerrado = Grupo::factory()->create(['categoria_id' => $categoria->id]);
        $jornadaCerrada = Jornada::create([
            'grupo_id' => $grupoCerrado->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-20',
            'fecha_fin' => '2026-11-22',
        ]);

        $equipo1 = $this->crearEquipo('Eq 1', $grupoCerrado);
        $equipo2 = $this->crearEquipo('Eq 2', $grupoCerrado);

        $payload = [
            'equipo_local_id' => $equipo1->id,
            'equipo_visitante_id' => $equipo2->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00',
        ];

        $this->actingAs($this->programador)
            ->post(route('jornadas.partidos.store', $jornadaCerrada), $payload)
            ->assertForbidden();
    }

    public function test_eliminar_partido_elimina_registro_y_crea_auditoria(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => 'borrador',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('partidos.destroy', $partido))
            ->assertRedirect(route('jornadas.show', $this->jornada))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('partidos', ['id' => $partido->id]);

        $this->assertDatabaseHas('auditoria_logs', [
            'tabla_afectada' => 'partidos',
            'accion' => 'eliminar',
            'usuario_id' => $this->admin->id,
        ]);
    }

    public function test_ver_vistas_de_programacion_y_detalle_de_partido(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => 'programado',
        ]);

        // Formulario de creación
        $this->actingAs($this->programador)
            ->get(route('jornadas.partidos.create', $this->jornada))
            ->assertOk()
            ->assertSee('Programar Partido (IU-16)');

        // Formulario de edición
        $this->actingAs($this->programador)
            ->get(route('partidos.edit', $partido))
            ->assertOk()
            ->assertSee('Editar / Reprogramar Partido');

        // Ficha de detalle
        $this->actingAs($this->programador)
            ->get(route('partidos.show', $partido))
            ->assertOk()
            ->assertSee('Halcones FC')
            ->assertSee('Pumas FC')
            ->assertSee('Cancha Central');
    }

    public function test_programacion_falla_si_fecha_partido_es_anterior_o_posterior_a_la_jornada(): void
    {
        // Fecha anterior al inicio de la jornada (jornada es del 2026-11-20 al 2026-11-22)
        $payloadAnterior = [
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-19',
            'hora_partido' => '10:00',
        ];

        $this->actingAs($this->programador)
            ->post(route('jornadas.partidos.store', $this->jornada), $payloadAnterior)
            ->assertSessionHasErrors(['fecha_partido']);

        $this->assertDatabaseMissing('partidos', [
            'jornada_id' => $this->jornada->id,
            'fecha_partido' => '2026-11-19',
        ]);

        // Fecha posterior al fin de la jornada (ej. 2027-05-05)
        $payloadPosterior = [
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2027-05-05',
            'hora_partido' => '10:00',
        ];

        $this->actingAs($this->programador)
            ->post(route('jornadas.partidos.store', $this->jornada), $payloadPosterior)
            ->assertSessionHasErrors(['fecha_partido']);

        $this->assertDatabaseMissing('partidos', [
            'jornada_id' => $this->jornada->id,
            'fecha_partido' => '2027-05-05',
        ]);
    }

    public function test_reprogramacion_falla_si_fecha_partido_queda_fuera_de_la_jornada(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => 'programado',
        ]);

        // Reprogramar con fecha posterior fuera del margen (2027-05-05)
        $this->actingAs($this->programador)
            ->put(route('partidos.update', $partido), [
                'fecha_partido' => '2027-05-05',
                'hora_partido' => '10:00',
            ])
            ->assertSessionHasErrors(['fecha_partido']);

        $this->assertDatabaseHas('partidos', [
            'id' => $partido->id,
            'fecha_partido' => '2026-11-20',
        ]);

        // Reprogramar con fecha anterior al margen (2026-11-15)
        $this->actingAs($this->programador)
            ->put(route('partidos.update', $partido), [
                'fecha_partido' => '2026-11-15',
                'hora_partido' => '10:00',
            ])
            ->assertSessionHasErrors(['fecha_partido']);

        $this->assertDatabaseHas('partidos', [
            'id' => $partido->id,
            'fecha_partido' => '2026-11-20',
        ]);
    }

    public function test_servicio_valida_rango_de_fechas_al_programar_y_reprogramar(): void
    {
        $servicio = app(ProgramacionJornadaService::class);

        // Servicio lanza ValidationException si fecha_partido es posterior al fin de la jornada
        $this->expectException(ValidationException::class);
        $servicio->programarPartido($this->jornada, [
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2027-05-05',
            'hora_partido' => '10:00',
        ]);
    }

    public function test_servicio_valida_rango_de_fechas_al_actualizar_partido(): void
    {
        $servicio = app(ProgramacionJornadaService::class);

        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => 'programado',
        ]);

        $this->expectException(ValidationException::class);
        $servicio->actualizarPartido($partido, [
            'fecha_partido' => '2027-05-05',
        ]);
    }

    public function test_programacion_bloquea_jornada_con_lock_for_update_en_transaccion(): void
    {
        DB::enableQueryLog();

        $payload = [
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-21',
            'hora_partido' => '12:00',
        ];

        $this->actingAs($this->programador)
            ->post(route('jornadas.partidos.store', $this->jornada), $payload)
            ->assertRedirect(route('jornadas.show', $this->jornada));

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $encontroLockJornada = collect($queries)->contains(function (array $query): bool {
            $sql = strtolower($query['query']);

            return str_contains($sql, 'jornadas') && str_contains($sql, 'for update');
        });

        $this->assertTrue($encontroLockJornada, 'La transacción de programarPartido debe ejecutar lockForUpdate() sobre la jornada.');
    }

    public function test_actualizar_partido_bloquea_jornada_con_lock_for_update_en_transaccion(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => 'programado',
        ]);

        DB::enableQueryLog();

        $this->actingAs($this->programador)
            ->put(route('partidos.update', $partido), [
                'hora_partido' => '16:00',
                'motivo' => 'Ajuste de hora para prueba de bloqueo concurrente.',
            ])
            ->assertRedirect(route('jornadas.show', $this->jornada));

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $encontroLockJornada = collect($queries)->contains(function (array $query): bool {
            $sql = strtolower($query['query']);

            return str_contains($sql, 'jornadas') && str_contains($sql, 'for update');
        });

        $this->assertTrue($encontroLockJornada, 'La transacción de actualizarPartido debe ejecutar lockForUpdate() sobre la jornada.');
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
