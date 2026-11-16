<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
use App\Models\Categoria;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\HistorialTransferenciaJugador;
use App\Models\Jugador;
use App\Models\Persona;
use App\Models\Temporada;
use App\Models\User;
use App\Services\GestionJugadorService;
use App\Services\RegistroJugadorService;
use App\Services\VentaClubService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\Concerns\DatosPrivacidadJugador;
use Tests\TestCase;

/**
 * HU-017 (SCRUM-32): reglas pedidas en la revisión del PR #69.
 */
class JugadorReglasHu017Test extends TestCase
{
    use DatabaseTransactions;
    use DatosPrivacidadJugador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    // --- Punto 1: la venta de un club (HU-011) conserva el historial de transferencias ---

    public function test_vender_clubes_con_transferencias_funciona_y_conserva_el_historial(): void
    {
        $temporada = $this->temporada('activa');
        [$clubA, $delegadoA] = $this->crearClubYDelegado('Origen venta');
        [$clubB] = $this->crearClubYDelegado('Destino venta');
        $origen = $this->inscribir($this->crearEquipo($clubA, $temporada, 'A venta'));
        $equipoB = $this->crearEquipo($clubB, $temporada, 'B venta');

        $this->actingAs($delegadoA)->postJson(route('jugadores.transferir', $origen), ['equipo_id' => $equipoB->id])->assertCreated();
        $historial = HistorialTransferenciaJugador::query()->sole();

        $nuevoPropietario = $this->crearUsuario('Nuevo propietario', 'Presidente del Club');
        app(VentaClubService::class)->vender($clubB, $nuevoPropietario->id, '2026-11-01');
        app(VentaClubService::class)->vender($clubA, $nuevoPropietario->id, '2026-11-01');

        $historial->refresh();
        $this->assertNull($historial->jugador_origen_id);
        $this->assertNull($historial->jugador_destino_id);
        $this->assertSame($clubA->id, (int) $historial->club_origen_id);
        $this->assertSame($clubB->id, (int) $historial->club_destino_id);
        $this->assertSame($origen->persona_id, (int) $historial->persona_id);
    }

    // --- Punto 2: no se renueva una inscripción transferida ni una que no es la más reciente ---

    public function test_el_club_de_origen_no_puede_renovar_a_un_jugador_que_transfirio(): void
    {
        $temporada = $this->temporada('activa', '-4 months');
        [$clubA, $delegadoA] = $this->crearClubYDelegado('Origen renovación');
        [$clubB, $delegadoB] = $this->crearClubYDelegado('Destino renovación');
        $origen = $this->inscribir($this->crearEquipo($clubA, $temporada, 'A renovación'));
        $equipoB = $this->crearEquipo($clubB, $temporada, 'B renovación');
        $respuesta = $this->actingAs($delegadoA)->postJson(route('jugadores.transferir', $origen), ['equipo_id' => $equipoB->id])->assertCreated();
        $destino = Jugador::query()->findOrFail($respuesta->json('transferencia.jugador_destino_id'));

        $temporadaNueva = $this->temporada('activa', '-1 month');
        $equipoNuevoA = $this->crearEquipo($clubA, $temporadaNueva, 'A nueva temporada');
        $equipoNuevoB = $this->crearEquipo($clubB, $temporadaNueva, 'B nueva temporada');

        $this->actingAs($delegadoA)->postJson(route('jugadores.renovar', $origen), ['equipo_id' => $equipoNuevoA->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['jugador' => 'Esta inscripción se transfirió a otro club; la renovación le corresponde al club actual del jugador.']);

        $this->actingAs($delegadoB)->postJson(route('jugadores.renovar', $destino), ['equipo_id' => $equipoNuevoB->id])->assertCreated();
    }

    public function test_solo_se_renueva_desde_la_inscripcion_mas_reciente(): void
    {
        [$club, $delegado] = $this->crearClubYDelegado('Renovación reciente');
        $persona = $this->crearPersona();
        $vieja = $this->inscribir($this->crearEquipo($club, $this->temporada('cerrada', '-2 years'), 'Hace dos años'), $persona);
        $this->inscribir($this->crearEquipo($club, $this->temporada('cerrada', '-1 year'), 'Hace un año'), $persona);
        $equipoActual = $this->crearEquipo($club, $this->temporada('activa'), 'Actual');

        $this->actingAs($delegado)->postJson(route('jugadores.renovar', $vieja), ['equipo_id' => $equipoActual->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['jugador' => 'Solo se puede renovar desde la inscripción más reciente del jugador.']);
    }

    // --- Punto 3: las temporadas cerradas solo se consultan ---

    public function test_no_se_da_de_baja_una_inscripcion_de_temporada_cerrada(): void
    {
        [$club, $delegado] = $this->crearClubYDelegado('Baja cerrada');
        $inscripcion = $this->inscribir($this->crearEquipo($club, $this->temporada('cerrada', '-1 year'), 'Cerrada'));

        $this->actingAs($delegado)->postJson(route('jugadores.baja', $inscripcion), ['motivo' => 'Retiro', 'fecha_baja' => GestionJugadorService::hoy()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('jugador');

        $this->assertSame('Activo', $inscripcion->fresh()->estado_elegibilidad);
    }

    public function test_transferir_desde_temporada_cerrada_no_modifica_la_inscripcion_de_origen(): void
    {
        [$clubA, $delegadoA] = $this->crearClubYDelegado('Origen cerrada');
        [$clubB] = $this->crearClubYDelegado('Destino activa');
        $origen = $this->inscribir($this->crearEquipo($clubA, $this->temporada('cerrada', '-1 year'), 'A cerrada'));
        $equipoB = $this->crearEquipo($clubB, $this->temporada('activa'), 'B activa');

        $this->actingAs($delegadoA)->postJson(route('jugadores.transferir', $origen), ['equipo_id' => $equipoB->id])->assertCreated();

        $origen->refresh();
        $this->assertSame('Activo', $origen->estado_elegibilidad);
        $this->assertNull($origen->motivo_baja);
        $this->assertNull($origen->fecha_baja);
        $this->assertDatabaseHas('historial_transferencias_jugador', ['jugador_origen_id' => $origen->id, 'club_destino_id' => $clubB->id]);
        $this->assertTrue(Jugador::query()->where('persona_id', $origen->persona_id)->where('equipo_id', $equipoB->id)->where('estado_elegibilidad', 'Activo')->exists());

        $this->actingAs($delegadoA)->postJson(route('jugadores.transferir', $origen), ['equipo_id' => $equipoB->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['jugador' => 'Esta inscripción ya fue transferida a otro club.']);
    }

    // --- Punto 4: fecha de baja con formato, mínimo y «hoy» en hora de México ---

    public function test_la_fecha_de_baja_exige_formato_y_no_es_anterior_al_inicio_de_la_temporada(): void
    {
        [$club, $delegado] = $this->crearClubYDelegado('Fecha baja');
        $temporada = $this->temporada('activa', '-3 months');
        $inscripcion = $this->inscribir($this->crearEquipo($club, $temporada, 'Fecha'));
        $this->actingAs($delegado);

        $this->postJson(route('jugadores.baja', $inscripcion), ['motivo' => 'Retiro', 'fecha_baja' => '01/11/2026'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_baja' => 'La fecha de baja debe tener el formato AAAA-MM-DD.']);
        $this->postJson(route('jugadores.baja', $inscripcion), ['motivo' => 'Retiro', 'fecha_baja' => '1990-01-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_baja' => 'La fecha de baja no puede ser anterior al inicio de la temporada ('.Carbon::parse($temporada->fecha_inicio)->format('d/m/Y').').']);

        $this->assertSame('Activo', $inscripcion->fresh()->estado_elegibilidad);
    }

    public function test_hoy_se_calcula_en_hora_de_mexico_para_la_baja_y_la_transferencia(): void
    {
        // 01:30 del 10/11 en UTC son las 19:30 del 09/11 en Ciudad de México.
        $this->travelTo(Carbon::parse('2026-11-10 01:30:00', 'UTC'));
        $temporada = $this->temporada('activa', '2026-08-01');
        [$clubA, $delegadoA] = $this->crearClubYDelegado('Hora origen');
        [$clubB] = $this->crearClubYDelegado('Hora destino');
        $paraBaja = $this->inscribir($this->crearEquipo($clubA, $temporada, 'Hora baja'));
        $paraTransferir = $this->inscribir($this->crearEquipo($clubA, $temporada, 'Hora transferencia'));
        $equipoB = $this->crearEquipo($clubB, $temporada, 'Hora B');
        $this->actingAs($delegadoA);

        $this->postJson(route('jugadores.baja', $paraBaja), ['motivo' => 'Retiro', 'fecha_baja' => '2026-11-10'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_baja' => 'La fecha de baja no puede ser posterior a hoy.']);
        $this->postJson(route('jugadores.baja', $paraBaja), ['motivo' => 'Retiro', 'fecha_baja' => '2026-11-09'])->assertOk();

        $this->postJson(route('jugadores.transferir', $paraTransferir), ['equipo_id' => $equipoB->id])->assertCreated();
        $this->assertSame('2026-11-09', $paraTransferir->fresh()->fecha_baja?->toDateString());
    }

    // --- Punto 5: mensajes en español en el 403 y en la validación ---

    public function test_el_403_de_baja_renovacion_y_transferencia_esta_en_espanol(): void
    {
        $temporada = $this->temporada('activa');
        [$clubA] = $this->crearClubYDelegado('Propio 403');
        [, $delegadoAjeno] = $this->crearClubYDelegado('Ajeno 403');
        $inscripcion = $this->inscribir($this->crearEquipo($clubA, $temporada, 'Propio'));
        $jugador = $this->crearUsuario('Jugador 403', 'Jugador', null, $inscripcion->persona_id);

        foreach ([$delegadoAjeno, $jugador] as $usuario) {
            $this->actingAs($usuario)->postJson(route('jugadores.baja', $inscripcion), ['motivo' => 'Retiro', 'fecha_baja' => GestionJugadorService::hoy()])
                ->assertForbidden()->assertJsonPath('mensaje', 'Solo el Delegado del club del jugador puede darlo de baja.');
            $this->actingAs($usuario)->postJson(route('jugadores.renovar', $inscripcion), ['equipo_id' => $inscripcion->equipo_id])
                ->assertForbidden()->assertJsonPath('mensaje', 'Solo el Delegado del club del jugador puede renovarlo.');
            $this->actingAs($usuario)->postJson(route('jugadores.transferir', $inscripcion), ['equipo_id' => $inscripcion->equipo_id])
                ->assertForbidden()->assertJsonPath('mensaje', 'Solo el Delegado del club de origen puede transferir al jugador.');
        }

        $this->assertSame('Activo', $inscripcion->fresh()->estado_elegibilidad);
    }

    public function test_la_validacion_de_baja_renovacion_y_transferencia_esta_en_espanol(): void
    {
        [$club, $delegado] = $this->crearClubYDelegado('Validación');
        $inscripcion = $this->inscribir($this->crearEquipo($club, $this->temporada('activa'), 'Validación'));
        $this->actingAs($delegado);

        $this->postJson(route('jugadores.baja', $inscripcion), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo' => 'El motivo de la baja es obligatorio.', 'fecha_baja' => 'La fecha de baja es obligatoria.']);
        $this->postJson(route('jugadores.renovar', $inscripcion), ['equipo_id' => 999999, 'dorsal' => 500])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['equipo_id' => 'El equipo seleccionado no existe.', 'dorsal' => 'El dorsal no puede ser mayor que 99.']);
        $this->postJson(route('jugadores.transferir', $inscripcion), ['equipo_id' => $inscripcion->equipo_id, 'club_origen_id' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['club_origen_id' => 'El club de origen no se puede enviar; lo determina el sistema.']);
    }

    // --- Punto 6: listado en el menú y errores visibles en la ficha ---

    public function test_el_delegado_llega_a_sus_jugadores_desde_el_menu_y_solo_ve_los_de_su_club(): void
    {
        $temporada = $this->temporada('activa');
        [$clubA, $delegadoA] = $this->crearClubYDelegado('Listado propio');
        [$clubB] = $this->crearClubYDelegado('Listado ajeno');
        $propio = $this->inscribir($this->crearEquipo($clubA, $temporada, 'Listado A'), $this->crearPersona('Propio', 'Del Club'));
        $this->inscribir($this->crearEquipo($clubB, $temporada, 'Listado B'), $this->crearPersona('Ajeno', 'Otro Club'));

        $this->actingAs($delegadoA)->get(route('jugadores.create'))->assertSee(route('jugadores.index'));
        $this->get(route('jugadores.index'))
            ->assertOk()
            ->assertSee('Propio Del Club')
            ->assertDontSee('Ajeno Otro Club')
            ->assertSee(route('jugadores.gestion', $propio));
        $this->get(route('jugadores.index', ['q' => 'Ajeno']))->assertOk()->assertSee('No hay jugadores que coincidan.');
    }

    public function test_un_rol_sin_club_no_ve_el_listado(): void
    {
        $tesorero = $this->crearUsuario('Tesorero listado', 'Tesorero');

        $this->actingAs($tesorero)->getJson(route('jugadores.index'))
            ->assertForbidden()
            ->assertJsonPath('mensaje', 'No cuenta con autorización para consultar el listado de jugadores.');
    }

    public function test_la_ficha_muestra_los_errores_de_validacion_y_del_servicio(): void
    {
        [$club, $delegado] = $this->crearClubYDelegado('Ficha errores');
        $activa = $this->inscribir($this->crearEquipo($club, $this->temporada('activa'), 'Ficha activa'));
        $cerrada = $this->inscribir($this->crearEquipo($club, $this->temporada('cerrada', '-1 year'), 'Ficha cerrada'));
        $this->actingAs($delegado);

        $this->followingRedirects()->from(route('jugadores.gestion', $activa))
            ->post(route('jugadores.baja', $activa), ['motivo' => ''])
            ->assertOk()
            ->assertSee('No se pudo completar la operación')
            ->assertSee('El motivo de la baja es obligatorio.');

        $this->followingRedirects()->from(route('jugadores.gestion', $cerrada))
            ->post(route('jugadores.baja', $cerrada), ['motivo' => 'Retiro', 'fecha_baja' => GestionJugadorService::hoy()])
            ->assertOk()
            ->assertSee('Solo se puede dar de baja una inscripción de la temporada activa');
    }

    // --- Punto 7: una sola entrada de auditoría por baja ---

    public function test_la_baja_deja_una_sola_entrada_en_la_auditoria(): void
    {
        [$club, $delegado] = $this->crearClubYDelegado('Auditoría');
        $inscripcion = $this->inscribir($this->crearEquipo($club, $this->temporada('activa'), 'Auditoría'));
        $antes = AuditoriaLog::query()->where('tabla_afectada', 'jugadores_equipos')->where('registro_id', $inscripcion->id)->count();

        $this->actingAs($delegado)->postJson(route('jugadores.baja', $inscripcion), ['motivo' => 'Cambio de ciudad', 'fecha_baja' => GestionJugadorService::hoy()])->assertOk();

        $this->assertSame($antes + 1, AuditoriaLog::query()->where('tabla_afectada', 'jugadores_equipos')->where('registro_id', $inscripcion->id)->count());
    }

    // --- Punto 8: la regla de edad (con edad_max) aplica al registro, la renovación y la transferencia ---

    public function test_la_edad_maxima_se_valida_en_la_transferencia_la_renovacion_y_el_registro(): void
    {
        $temporadaAnterior = $this->temporada('cerrada', '-1 year');
        $temporada = $this->temporada('activa');
        [$clubA, $delegadoA] = $this->crearClubYDelegado('Edad origen');
        [$clubB, $delegadoB] = $this->crearClubYDelegado('Edad destino');
        $adulto = $this->crearPersona('Jugador', 'Adulto', '2000-01-01');
        $actual = $this->inscribir($this->crearEquipo($clubA, $temporada, 'Edad A'), $adulto);
        $anterior = $this->inscribir($this->crearEquipo($clubB, $temporadaAnterior, 'Edad B anterior'), $this->crearPersona('Otro', 'Adulto', '2000-01-01'));
        $infantilB = $this->crearEquipo($clubB, $temporada, 'Infantil B', 1, ['edad_max' => 12]);
        $mensaje = 'El jugador no cumple los requisitos de edad de la categoría '.$infantilB->grupo->categoria->nombre.'.';

        $this->actingAs($delegadoA)->postJson(route('jugadores.transferir', $actual), ['equipo_id' => $infantilB->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['equipo_id' => $mensaje]);
        $this->actingAs($delegadoB)->postJson(route('jugadores.renovar', $anterior), ['equipo_id' => $infantilB->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['equipo_id' => $mensaje]);

        $this->expectException(ValidationException::class);
        app(RegistroJugadorService::class)->registrar($infantilB, ['persona_id' => $this->crearPersona('Nuevo', 'Adulto', '2000-01-01')->id] + $this->privacidad(), $delegadoB);
    }

    // --- Recomendados: reglas que ninguna prueba cubría ---

    public function test_solo_se_da_de_baja_a_un_jugador_activo(): void
    {
        [$club, $delegado] = $this->crearClubYDelegado('Baja suspendido');
        $suspendido = $this->inscribir($this->crearEquipo($club, $this->temporada('activa'), 'Suspendido'), null, 'Suspendido');

        $this->actingAs($delegado)->postJson(route('jugadores.baja', $suspendido), ['motivo' => 'Retiro', 'fecha_baja' => GestionJugadorService::hoy()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['jugador' => 'Solo se puede dar de baja a un jugador activo.']);
    }

    public function test_la_renovacion_exige_mismo_club_temporada_activa_distinta_y_sin_otra_inscripcion(): void
    {
        [$club, $delegado] = $this->crearClubYDelegado('Renovación reglas');
        [$otroClub] = $this->crearClubYDelegado('Renovación otro');
        $anterior = $this->inscribir($this->crearEquipo($club, $this->temporada('cerrada', '-1 year'), 'Anterior'));
        $temporada = $this->temporada('activa');
        $this->actingAs($delegado);

        $this->postJson(route('jugadores.renovar', $anterior), ['equipo_id' => $this->crearEquipo($otroClub, $temporada, 'Otro club')->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['equipo_id' => 'La renovación debe conservar el club de origen del jugador.']);
        $this->postJson(route('jugadores.renovar', $anterior), ['equipo_id' => $this->crearEquipo($club, $this->temporada('cerrada', '-6 months'), 'Cerrada')->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['equipo_id' => 'La renovación debe dirigirse a un equipo de la temporada activa.']);
        $this->postJson(route('jugadores.renovar', $anterior), ['equipo_id' => $this->crearEquipo($club, $anterior->equipo->temporada, 'Misma temporada')->id])
            ->assertUnprocessable()->assertJsonValidationErrors('equipo_id');

        // Si ya tiene inscripción en la temporada activa, la anterior deja de ser la más reciente.
        $this->inscribir($this->crearEquipo($club, $temporada, 'Ya inscrito'), $anterior->persona);
        $this->postJson(route('jugadores.renovar', $anterior), ['equipo_id' => $this->crearEquipo($club, $temporada, 'Segundo')->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['jugador' => 'Solo se puede renovar desde la inscripción más reciente del jugador.']);
        $this->assertSame(2, Jugador::query()->where('persona_id', $anterior->persona_id)->count());
    }

    public function test_la_transferencia_exige_destino_en_temporada_activa(): void
    {
        [$clubA, $delegadoA] = $this->crearClubYDelegado('Destino cerrado origen');
        [$clubB] = $this->crearClubYDelegado('Destino cerrado');
        $origen = $this->inscribir($this->crearEquipo($clubA, $this->temporada('activa'), 'Origen'));
        $destino = $this->crearEquipo($clubB, $this->temporada('cerrada', '-1 year'), 'Destino cerrado');

        $this->actingAs($delegadoA)->postJson(route('jugadores.transferir', $origen), ['equipo_id' => $destino->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['equipo_id' => 'El equipo de destino debe pertenecer a la temporada activa.']);
    }

    public function test_el_dorsal_ocupado_se_rechaza_pero_el_de_una_baja_queda_libre(): void
    {
        $temporada = $this->temporada('activa');
        [$clubA, $delegadoA] = $this->crearClubYDelegado('Dorsal origen');
        [$clubB] = $this->crearClubYDelegado('Dorsal destino');
        $equipoB = $this->crearEquipo($clubB, $temporada, 'Dorsal B');
        $this->inscribir($equipoB, null, 'Activo', 10);
        $this->inscribir($equipoB, null, 'Baja', 7);
        $primero = $this->inscribir($this->crearEquipo($clubA, $temporada, 'Dorsal A1'), null, 'Activo', 10);
        $segundo = $this->inscribir($this->crearEquipo($clubA, $temporada, 'Dorsal A2'), null, 'Activo', 7);
        $this->actingAs($delegadoA);

        $this->postJson(route('jugadores.transferir', $primero), ['equipo_id' => $equipoB->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dorsal' => 'El dorsal 10 ya está asignado a otro jugador de este equipo.']);
        $this->postJson(route('jugadores.transferir', $segundo), ['equipo_id' => $equipoB->id])->assertCreated();
    }

    private function temporada(string $estado, string $inicio = '-3 months'): Temporada
    {
        $fecha = str_starts_with($inicio, '-') || str_starts_with($inicio, '+')
            ? now(GestionJugadorService::ZONA_HORARIA)->modify($inicio)->toDateString()
            : $inicio;

        return Temporada::factory()->{$estado}()->create(['fecha_inicio' => $fecha]);
    }

    /** @return array{Club, User} */
    private function crearClubYDelegado(string $nombre): array
    {
        $club = Club::query()->create(['nombre' => 'Club '.$nombre.' '.str()->random(5)]);

        return [$club, $this->crearUsuario('Delegado '.$nombre, 'Delegado del Club', $club)];
    }

    private function crearUsuario(string $nombre, string $rol, ?Club $club = null, ?int $personaId = null): User
    {
        $usuario = User::query()->create([
            'name' => $nombre,
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            // La columna usuarios.rol es un enum: los roles de club se guardan como «Directivo Club».
            'rol' => in_array($rol, ['Delegado del Club', 'Presidente del Club'], true) ? 'Directivo Club' : $rol,
            'estatus' => 'activo',
            'club_id' => $club?->id,
        ]);
        if ($personaId !== null) {
            $usuario->forceFill(['persona_id' => $personaId])->save();
        }
        $usuario->assignRole(Role::query()->firstOrCreate(['name' => $rol, 'guard_name' => 'web']));

        return $usuario;
    }

    /** @param array<string, int> $categoria */
    private function crearEquipo(Club $club, Temporada $temporada, string $nombre, int $jerarquia = 1, array $categoria = []): Equipo
    {
        $cat = Categoria::factory()->create(['temporada_id' => $temporada->id, 'jerarquia_salto' => $jerarquia] + $categoria);
        $grupo = Grupo::factory()->create(['categoria_id' => $cat->id, 'temporada_id' => $temporada->id]);

        return $club->equipos()->create(['temporada_id' => $temporada->id, 'grupo_id' => $grupo->id, 'nombre' => $nombre.' '.str()->random(4)]);
    }

    private function crearPersona(string $nombre = 'Jugador', string $apellidos = 'HU-017', string $nacimiento = '2010-01-01'): Persona
    {
        return Persona::query()->create([
            'nombre' => $nombre,
            'apellidos' => $apellidos,
            'fecha_nacimiento' => $nacimiento,
            'curp' => fake()->unique()->regexify('[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[0-9A-Z][0-9]'),
        ]);
    }

    private function inscribir(Equipo $equipo, ?Persona $persona = null, string $estado = 'Activo', ?int $dorsal = null): Jugador
    {
        return $equipo->jugadores()->create([
            'persona_id' => ($persona ?? $this->crearPersona())->id,
            'estado_elegibilidad' => $estado,
            'dorsal' => $dorsal,
            'motivo_baja' => $estado === 'Baja' ? 'Retiro' : null,
            'fecha_baja' => $estado === 'Baja' ? GestionJugadorService::hoy() : null,
        ]);
    }
}
