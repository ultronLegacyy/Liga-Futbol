<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Jugador;
use App\Models\Temporada;
use App\Models\User;
use App\Services\ReglaSaltoCategoriaService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EquipoRegistrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_delegado_registra_equipo_en_temporada_activa_categoria_y_grupo(): void
    {
        $club = $this->crearClub('Club del Delegado');
        $delegado = $this->crearDelegado($club);
        [$temporada, $categoria, $grupo] = $this->crearGrupoActivo('Torneo Activo');

        $respuesta = $this->actingAs($delegado)->postJson(route('equipos.store'), [
            'temporada_id' => $temporada->id,
            'categoria_id' => $categoria->id,
            'grupo_id' => $grupo->id,
            'nombre' => 'Sub 18',
            'club_id' => $this->crearClub('Club Manipulado')->id,
        ])->assertCreated()
            ->assertJsonPath('equipo.temporada_id', $temporada->id)
            ->assertJsonPath('equipo.categoria_id', $categoria->id)
            ->assertJsonPath('equipo.grupo_id', $grupo->id);

        $equipoId = (int) $respuesta->json('equipo.id');

        $this->assertDatabaseHas('equipos', [
            'club_id' => $club->id,
            'grupo_id' => $grupo->id,
            'nombre' => 'Sub 18',
        ]);
        $this->assertDatabaseHas('auditoria_logs', [
            'accion' => 'crear',
            'tabla_afectada' => 'equipos',
            'registro_id' => $equipoId,
            'motivo' => 'Registro de equipo deportivo',
        ]);
    }

    public function test_administrador_general_registra_equipo_con_club_explicito_y_se_audita(): void
    {
        $club = $this->crearClub('Club Seleccionado por Admin');
        $administrador = User::create([
            'name' => 'Administrador General SCRUM-26',
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'rol' => 'Administrador General',
            'estatus' => 'activo',
        ]);
        $administrador->assignRole('Administrador General');
        [$temporada, $categoria, $grupo] = $this->crearGrupoActivo('Torneo del Admin');

        $respuesta = $this->actingAs($administrador)->postJson(route('equipos.store'), [
            'club_id' => $club->id,
            'temporada_id' => $temporada->id,
            'categoria_id' => $categoria->id,
            'grupo_id' => $grupo->id,
            'nombre' => 'Equipo creado por admin',
        ]);

        $respuesta->assertCreated()
            ->assertJsonPath('equipo.club_id', $club->id);
        $this->assertDatabaseHas('equipos', [
            'club_id' => $club->id,
            'temporada_id' => $temporada->id,
            'grupo_id' => $grupo->id,
            'nombre' => 'Equipo creado por admin',
        ]);
        $this->assertDatabaseHas('auditoria_logs', [
            'accion' => 'crear',
            'tabla_afectada' => 'equipos',
            'registro_id' => $respuesta->json('equipo.id'),
            'motivo' => 'Registro de equipo deportivo',
        ]);
    }

    public function test_programador_no_recibe_permiso_para_crear_jugadores(): void
    {
        $rolProgramador = \Spatie\Permission\Models\Role::findByName('Programador de Partidos');

        $this->assertFalse($rolProgramador->hasPermissionTo('jugadores.crear'));
    }

    public function test_no_permite_registrar_en_temporada_cerrada(): void
    {
        $club = $this->crearClub('Club de Prueba');
        $delegado = $this->crearDelegado($club);
        $temporadaCerrada = Temporada::factory()->cerrada()->create();
        $categoriaCerrada = Categoria::factory()->create(['temporada_id' => $temporadaCerrada->id]);
        $grupoCerrado = Grupo::factory()->create([
            'categoria_id' => $categoriaCerrada->id,
            'temporada_id' => $temporadaCerrada->id,
        ]);
        $this->actingAs($delegado)->postJson(route('equipos.store'), [
            'temporada_id' => $temporadaCerrada->id,
            'categoria_id' => $categoriaCerrada->id,
            'grupo_id' => $grupoCerrado->id,
            'nombre' => 'Equipo Cerrado',
        ])->assertUnprocessable()->assertJsonValidationErrors(['temporada_id']);

        $this->assertSame(0, Equipo::query()->count());
    }

    public function test_no_permite_registrar_grupo_de_otra_categoria(): void
    {
        $club = $this->crearClub('Club de Prueba');
        $delegado = $this->crearDelegado($club);
        [$temporada, , $grupo] = $this->crearGrupoActivo('Torneo Vigente');
        $otraCategoria = Categoria::factory()->create(['temporada_id' => $temporada->id]);

        $this->actingAs($delegado)->postJson(route('equipos.store'), [
            'temporada_id' => $temporada->id,
            'categoria_id' => $otraCategoria->id,
            'grupo_id' => $grupo->id,
            'nombre' => 'Equipo Mal Asignado',
        ])->assertUnprocessable()->assertJsonValidationErrors(['grupo_id']);

        $this->assertSame(0, Equipo::query()->count());
    }

    public function test_no_permite_duplicar_la_inscripcion_de_un_club_en_el_mismo_grupo(): void
    {
        $club = $this->crearClub('Club Único');
        $delegado = $this->crearDelegado($club);
        [$temporada, $categoria, $grupo] = $this->crearGrupoActivo('Torneo Único');
        $payload = [
            'temporada_id' => $temporada->id,
            'categoria_id' => $categoria->id,
            'grupo_id' => $grupo->id,
            'nombre' => 'Equipo Uno',
        ];

        $this->actingAs($delegado)->postJson(route('equipos.store'), $payload)->assertCreated();
        $this->actingAs($delegado)->postJson(route('equipos.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre']);

        $this->assertSame(1, $club->equipos()->count());
    }

    public function test_la_base_impide_duplicar_club_y_grupo_aun_fuera_del_form_request(): void
    {
        $club = $this->crearClub('Club Protegido');
        [$temporada, $categoria, $grupo] = $this->crearGrupoActivo('Torneo con Índice');
        $otroGrupo = Grupo::factory()->create([
            'categoria_id' => $categoria->id,
            'temporada_id' => $temporada->id,
        ]);
        $club->equipos()->create([
            'temporada_id' => $temporada->id,
            'grupo_id' => $grupo->id,
            'nombre' => 'Primero',
        ]);

        try {
            $club->equipos()->create([
                'temporada_id' => $temporada->id,
                'grupo_id' => $otroGrupo->id,
                'nombre' => 'Primero',
            ]);
            $this->fail('El mismo equipo no debe asignarse a dos grupos en una temporada.');
        } catch (QueryException) {
            $this->assertSame(1, $club->equipos()->count());
        }
    }

    public function test_no_permite_renombrar_equipo_a_nombre_ya_usado_en_la_temporada(): void
    {
        $club = $this->crearClub('Club de Nombres');
        $delegado = $this->crearDelegado($club);
        [$temporada, $categoria, $grupo] = $this->crearGrupoActivo('Torneo de Nombres');
        $otroGrupo = Grupo::factory()->create([
            'categoria_id' => $categoria->id,
            'temporada_id' => $temporada->id,
        ]);
        $equipoExistente = $club->equipos()->create([
            'temporada_id' => $temporada->id,
            'grupo_id' => $grupo->id,
            'nombre' => 'Primero',
        ]);
        $equipoEditar = $club->equipos()->create([
            'temporada_id' => $temporada->id,
            'grupo_id' => $otroGrupo->id,
            'nombre' => 'Segundo',
        ]);

        $this->actingAs($delegado)->putJson(route('equipos.update', $equipoEditar), [
            'nombre' => $equipoExistente->nombre,
        ])->assertUnprocessable()->assertJsonValidationErrors(['nombre']);

        $this->assertDatabaseHas('equipos', ['id' => $equipoEditar->id, 'nombre' => 'Segundo']);
    }

    public function test_inscripcion_de_jugador_rechaza_salto_de_mas_de_un_nivel(): void
    {
        $temporada = Temporada::factory()->activa()->create();
        $origen = Categoria::factory()->create(['temporada_id' => $temporada->id, 'jerarquia_salto' => 1]);
        $destino = Categoria::factory()->create(['temporada_id' => $temporada->id, 'jerarquia_salto' => 3]);

        try {
            app(ReglaSaltoCategoriaService::class)->validar($origen, $destino);
            $this->fail('La inscripción no debe permitir avanzar dos niveles.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('categoria_id', $exception->errors());
        }
    }

    public function test_inscripcion_de_jugador_rechaza_destino_dos_niveles_arriba(): void
    {
        $temporadaOrigen = Temporada::factory()->activa()->create(['nombre' => 'Temporada de Origen']);
        $temporada = Temporada::factory()->activa()->create(['nombre' => 'Temporada de Transferencia']);
        $origen = Categoria::factory()->create([
            'temporada_id' => $temporadaOrigen->id,
            'jerarquia_salto' => 1,
        ]);
        $destino = Categoria::factory()->create([
            'temporada_id' => $temporada->id,
            'jerarquia_salto' => 3,
        ]);
        $grupoOrigen = Grupo::factory()->create([
            'categoria_id' => $origen->id,
            'temporada_id' => $temporadaOrigen->id,
        ]);
        $grupoDestino = Grupo::factory()->create([
            'categoria_id' => $destino->id,
            'temporada_id' => $temporada->id,
        ]);
        $clubOrigen = $this->crearClub('Club de Origen');
        $clubDestino = $this->crearClub('Club de Destino');
        $equipoOrigen = $this->crearEquipoParaGrupo($clubOrigen, $temporadaOrigen, $grupoOrigen, 'Origen');
        $equipoDestino = $this->crearEquipoParaGrupo($clubDestino, $temporada, $grupoDestino, 'Destino');
        $personaId = $this->crearPersona();
        Jugador::create(['persona_id' => $personaId, 'equipo_id' => $equipoOrigen->id]);

        $this->actingAs($this->crearDelegado($clubDestino))
            ->postJson(route('equipos.jugadores.store', $equipoDestino), ['persona_id' => $personaId])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['categoria_id']);

        $this->assertSame(1, Jugador::query()->where('persona_id', $personaId)->count());
    }

    public function test_inscripcion_de_jugador_permite_destino_un_nivel_arriba(): void
    {
        $temporadaOrigen = Temporada::factory()->activa()->create(['nombre' => 'Temporada de Origen Válido']);
        $temporada = Temporada::factory()->activa()->create(['nombre' => 'Temporada de Salto Válido']);
        $origen = Categoria::factory()->create([
            'temporada_id' => $temporadaOrigen->id,
            'jerarquia_salto' => 1,
        ]);
        $destino = Categoria::factory()->create([
            'temporada_id' => $temporada->id,
            'jerarquia_salto' => 2,
        ]);
        $grupoOrigen = Grupo::factory()->create([
            'categoria_id' => $origen->id,
            'temporada_id' => $temporadaOrigen->id,
        ]);
        $grupoDestino = Grupo::factory()->create([
            'categoria_id' => $destino->id,
            'temporada_id' => $temporada->id,
        ]);
        $clubOrigen = $this->crearClub('Origen con Salto Válido');
        $clubDestino = $this->crearClub('Destino con Salto Válido');
        $equipoOrigen = $this->crearEquipoParaGrupo($clubOrigen, $temporadaOrigen, $grupoOrigen, 'Origen');
        $equipoDestino = $this->crearEquipoParaGrupo($clubDestino, $temporada, $grupoDestino, 'Destino');
        $personaId = $this->crearPersona();
        Jugador::create(['persona_id' => $personaId, 'equipo_id' => $equipoOrigen->id]);

        $respuesta = $this->actingAs($this->crearDelegado($clubDestino))
            ->postJson(route('equipos.jugadores.store', $equipoDestino), ['persona_id' => $personaId])
            ->assertCreated()
            ->assertJsonPath('inscripcion.persona_id', $personaId)
            ->assertJsonPath('inscripcion.equipo_id', $equipoDestino->id);

        $this->assertSame(2, Jugador::query()->where('persona_id', $personaId)->count());
        $this->assertDatabaseHas('auditoria_logs', [
            'accion' => 'crear',
            'tabla_afectada' => 'jugadores_equipos',
            'registro_id' => $respuesta->json('inscripcion.id'),
            'motivo' => 'Inscripción de jugador en equipo',
        ]);
    }

    public function test_no_duplica_persona_en_el_mismo_equipo(): void
    {
        $temporada = Temporada::factory()->activa()->create();
        $categoria = Categoria::factory()->create(['temporada_id' => $temporada->id]);
        $grupo = Grupo::factory()->create(['categoria_id' => $categoria->id, 'temporada_id' => $temporada->id]);
        $club = $this->crearClub('Club Duplicado Jugador');
        $equipo = $this->crearEquipoParaGrupo($club, $temporada, $grupo, 'Equipo Duplicado Jugador');
        $delegado = $this->crearDelegado($club);
        $personaId = $this->crearPersona();

        $this->actingAs($delegado)
            ->postJson(route('equipos.jugadores.store', $equipo), ['persona_id' => $personaId])
            ->assertCreated();
        $this->postJson(route('equipos.jugadores.store', $equipo), ['persona_id' => $personaId])
            ->assertUnprocessable()
            ->assertJsonPath('errors.persona_id.0', 'El jugador ya está registrado en este equipo.');

        $this->assertSame(1, Jugador::query()->where('equipo_id', $equipo->id)->where('persona_id', $personaId)->count());
    }

    public function test_no_duplica_dorsal_dentro_del_equipo(): void
    {
        $temporada = Temporada::factory()->activa()->create();
        $categoria = Categoria::factory()->create(['temporada_id' => $temporada->id]);
        $grupo = Grupo::factory()->create(['categoria_id' => $categoria->id, 'temporada_id' => $temporada->id]);
        $club = $this->crearClub('Club Dorsales Únicos');
        $equipo = $this->crearEquipoParaGrupo($club, $temporada, $grupo, 'Equipo Dorsales Únicos');
        $delegado = $this->crearDelegado($club);
        $personaInicial = $this->crearPersona();
        $personaDuplicada = $this->crearPersona();

        $this->actingAs($delegado)
            ->postJson(route('equipos.jugadores.store', $equipo), ['persona_id' => $personaInicial, 'dorsal' => 9])
            ->assertCreated();
        $this->postJson(route('equipos.jugadores.store', $equipo), ['persona_id' => $personaDuplicada, 'dorsal' => 9])
            ->assertUnprocessable()
            ->assertJsonPath('errors.dorsal.0', 'El dorsal ya está asignado a otro jugador de este equipo.');

        $this->assertSame(1, Jugador::query()->where('equipo_id', $equipo->id)->count());
    }

    public function test_no_inscribe_jugador_activo_en_otro_equipo_de_la_misma_temporada(): void
    {
        $temporada = Temporada::factory()->activa()->create();
        $categoria = Categoria::factory()->create(['temporada_id' => $temporada->id]);
        $grupo = Grupo::factory()->create(['categoria_id' => $categoria->id, 'temporada_id' => $temporada->id]);
        $club = $this->crearClub('Club con Plantilla Activa');
        $equipoOrigen = $this->crearEquipoParaGrupo($club, $temporada, $grupo, 'Equipo con Jugador');
        $equipoDestino = $this->crearEquipoParaGrupo($club, $temporada, $grupo, 'Equipo Destino Jugador');
        $personaId = $this->crearPersona();
        Jugador::create([
            'persona_id' => $personaId,
            'equipo_id' => $equipoOrigen->id,
            'estado_elegibilidad' => 'Activo',
        ]);

        $this->actingAs($this->crearDelegado($club))
            ->postJson(route('equipos.jugadores.store', $equipoDestino), ['persona_id' => $personaId])
            ->assertUnprocessable()
            ->assertJsonPath('errors.persona_id.0', 'El jugador ya está activo en otro equipo de esta temporada.');

        $this->assertSame(1, Jugador::query()->where('persona_id', $personaId)->count());
    }

    public function test_inscripcion_de_jugador_permite_avanzar_un_solo_nivel(): void
    {
        $temporada = Temporada::factory()->activa()->create();
        $origen = Categoria::factory()->create(['temporada_id' => $temporada->id, 'jerarquia_salto' => 1]);
        $destino = Categoria::factory()->create(['temporada_id' => $temporada->id, 'jerarquia_salto' => 2]);

        app(ReglaSaltoCategoriaService::class)->validar($origen, $destino);

        $this->assertSame(2, $destino->jerarquia_salto);
    }

    public function test_sin_autenticacion_no_registra_equipo(): void
    {
        $this->postJson(route('equipos.store'), [])->assertUnauthorized();
        $this->assertSame(0, Equipo::query()->count());
    }

    public function test_presidente_del_club_recibe_403_al_intentar_registrar_equipo(): void
    {
        $club = $this->crearClub('Club del Presidente');
        $presidente = User::create([
            'name' => 'Presidente del Club',
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'rol' => 'Directivo Club',
            'estatus' => 'activo',
            'club_id' => $club->id,
        ]);
        $presidente->assignRole('Presidente del Club');
        [$temporada, $categoria, $grupo] = $this->crearGrupoActivo('Torneo con Presidente');

        $this->actingAs($presidente)->postJson(route('equipos.store'), [
            'temporada_id' => $temporada->id,
            'categoria_id' => $categoria->id,
            'grupo_id' => $grupo->id,
            'nombre' => 'Equipo No Autorizado',
        ])->assertForbidden();

        $this->assertSame(0, Equipo::query()->count());
    }

    private function crearClub(string $nombre): Club
    {
        return Club::create(['nombre' => $nombre]);
    }

    private function crearDelegado(Club $club): User
    {
        $delegado = User::create([
            'name' => 'Delegado de '.$club->nombre,
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'rol' => 'Directivo Club',
            'estatus' => 'activo',
            'club_id' => $club->id,
        ]);
        $delegado->assignRole('Delegado del Club');

        return $delegado;
    }

    private function crearPersona(): int
    {
        return (int) DB::table('personas')->insertGetId([
            'nombre' => 'Jugador de Prueba',
            'apellidos' => 'Historia SCRUM-26',
            'fecha_nacimiento' => '2010-01-01',
            'curp' => str()->upper(str()->random(18)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function crearEquipoParaGrupo(Club $club, Temporada $temporada, Grupo $grupo, string $nombre): Equipo
    {
        return $club->equipos()->create([
            'temporada_id' => $temporada->id,
            'grupo_id' => $grupo->id,
            'nombre' => $nombre,
        ]);
    }

    /** @return array{Temporada, Categoria, Grupo} */
    private function crearGrupoActivo(string $nombre): array
    {
        $temporada = Temporada::factory()->activa()->create(['nombre' => $nombre]);
        $categoria = Categoria::factory()->create(['temporada_id' => $temporada->id]);
        $grupo = Grupo::factory()->create([
            'categoria_id' => $categoria->id,
            'temporada_id' => $temporada->id,
        ]);

        return [$temporada, $categoria, $grupo];
    }
}
