<?php

namespace Tests\Feature;

use App\Models\AcuerdoAsamblea;
use App\Models\Asamblea;
use App\Models\Club;
use App\Models\User;
use App\Services\AsambleaService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Revisión del PR #73: mensajes en español, arreglos en campos de texto, hora de México y acceso. */
class AsambleaValidacionTest extends TestCase
{
    use DatabaseTransactions;

    private User $secretario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->secretario = $this->usuario('Secretario');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<string, array{array<string, mixed>, string, string}> */
    public static function convocatoriasInvalidas(): array
    {
        return [
            'día que no existe' => [['fecha' => '2026-11-31'], 'fecha', 'La fecha de la asamblea no es válida; usa el calendario (AAAA-MM-DD).'],
            'fecha con diagonales' => [['fecha' => '05/12/2026'], 'fecha', 'La fecha de la asamblea no es válida; usa el calendario (AAAA-MM-DD).'],
            'lugar corto' => [['lugar' => 'ab'], 'lugar', 'El lugar debe tener al menos 3 caracteres.'],
            'lugar largo' => [['lugar' => str_repeat('a', 201)], 'lugar', 'El lugar no debe tener más de 200 caracteres.'],
            'temporada no numérica' => [['temporada_id' => 'abc'], 'temporada_id', 'Selecciona una temporada de la lista.'],
            'tipo como arreglo' => [['tipo' => ['Ordinaria']], 'tipo', 'Selecciona el tipo de asamblea.'],
        ];
    }

    /** @param array<string, mixed> $cambio */
    #[DataProvider('convocatoriasInvalidas')]
    public function test_la_convocatoria_responde_en_espanol(array $cambio, string $campo, string $mensaje): void
    {
        $this->actingAs($this->secretario)
            ->postJson(route('asambleas.store'), array_merge($this->convocatoria(), $cambio))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$campo => $mensaje]);
    }

    public function test_asistencia_acuerdos_y_comunicados_responden_en_espanol(): void
    {
        $asamblea = $this->asambleaCelebrada();
        $club = Club::query()->create(['nombre' => 'Club Mensajes', 'estado' => 'activo']);
        $acuerdo = $this->acuerdoQueModifica($asamblea);
        $presidente = $this->usuario('Presidente');

        $this->actingAs($this->secretario)->putJson(route('asambleas.asistencia.update', $asamblea), ['asistencias' => 'hola'])
            ->assertJsonValidationErrors(['asistencias' => 'La lista de asistencia no es válida.']);
        $this->actingAs($this->secretario)->putJson(route('asambleas.asistencia.update', $asamblea), [
            'asistencias' => [$club->id => ['asistio' => 'quizas', 'delegado_usuario_id' => 'xyz']],
        ])->assertJsonValidationErrors([
            "asistencias.{$club->id}.asistio" => 'Indica con la casilla si el club asistió.',
            "asistencias.{$club->id}.delegado_usuario_id" => 'Selecciona un delegado de la lista.',
        ]);
        $this->actingAs($this->secretario)->postJson(route('asambleas.acuerdos.store', $asamblea), [
            'descripcion' => str_repeat('a', 5001),
            'modifica_reglamento' => 'tal vez',
        ])->assertJsonValidationErrors([
            'descripcion' => 'El acuerdo no debe tener más de 5000 caracteres.',
            'modifica_reglamento' => 'Indica con Sí o No si el acuerdo modifica el reglamento de la temporada.',
        ]);
        $this->actingAs($this->secretario)->postJson(route('asambleas.comunicados.store', $asamblea), ['titulo' => 'ab', 'contenido' => 'abc'])
            ->assertJsonValidationErrors([
                'titulo' => 'El título del comunicado debe tener al menos 3 caracteres.',
                'contenido' => 'El contenido del comunicado debe tener al menos 5 caracteres.',
            ]);
        $this->actingAs($presidente)->postJson(route('asambleas.acuerdos.autorizar', [$asamblea, $acuerdo]), ['motivo' => str_repeat('a', 2001)])
            ->assertJsonValidationErrors(['motivo' => 'El motivo no debe tener más de 2000 caracteres.']);
    }

    public function test_un_arreglo_en_los_campos_de_texto_no_provoca_un_error_500(): void
    {
        $asamblea = $this->asambleaCelebrada();
        $club = Club::query()->create(['nombre' => 'Club Arreglos', 'estado' => 'activo']);

        $this->actingAs($this->secretario)->from(route('asambleas.create'))
            ->post(route('asambleas.store'), ['fecha' => ['x'], 'hora' => ['x'], 'lugar' => ['x'], 'orden_del_dia' => ['x']])
            ->assertRedirect(route('asambleas.create'))
            ->assertSessionHasErrors(['fecha', 'hora', 'lugar', 'orden_del_dia']);
        $this->actingAs($this->secretario)->get(route('asambleas.create'))->assertOk();

        $this->actingAs($this->secretario)->from(route('asambleas.asistencia.edit', $asamblea))
            ->put(route('asambleas.asistencia.update', $asamblea), [
                'asistencias' => [$club->id => ['asistio' => '1', 'nombre_asistente' => ['x'], 'observaciones' => ['x']]],
            ])->assertSessionHasErrors();
        $this->actingAs($this->secretario)->get(route('asambleas.asistencia.edit', $asamblea))->assertOk();

        $this->actingAs($this->secretario)->from(route('asambleas.show', $asamblea))
            ->post(route('asambleas.acuerdos.store', $asamblea), ['descripcion' => ['x'], 'modifica_reglamento' => '0'])
            ->assertSessionHasErrors('descripcion');
        $this->actingAs($this->secretario)->from(route('asambleas.show', $asamblea))
            ->post(route('asambleas.comunicados.store', $asamblea), ['titulo' => ['x'], 'contenido' => ['x']])
            ->assertSessionHasErrors(['titulo', 'contenido']);
        $this->actingAs($this->secretario)->get(route('asambleas.show', $asamblea))->assertOk();
    }

    public function test_hoy_se_calcula_con_la_hora_de_mexico_al_convocar(): void
    {
        // 19:30 en México ya es el día siguiente en UTC, la zona de la app.
        Carbon::setTestNow(Carbon::parse('2026-11-13 19:30', 'America/Mexico_City'));

        $this->actingAs($this->secretario)->get(route('asambleas.create'))
            ->assertOk()
            ->assertSee('min="2026-11-13"', false);

        $this->actingAs($this->secretario)
            ->post(route('asambleas.store'), array_merge($this->convocatoria(), ['fecha' => '2026-11-13']))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->secretario)
            ->postJson(route('asambleas.store'), array_merge($this->convocatoria(), ['fecha' => '2026-11-12']))
            ->assertJsonValidationErrors(['fecha' => 'No se puede convocar una asamblea en una fecha pasada.']);
    }

    public function test_el_error_de_autorizacion_solo_aparece_debajo_de_su_acuerdo(): void
    {
        $asamblea = $this->asambleaCelebrada();
        $primero = $this->acuerdoQueModifica($asamblea);
        $this->acuerdoQueModifica($asamblea);
        $presidente = $this->usuario('Presidente');

        $ruta = route('asambleas.acuerdos.autorizar', [$asamblea, $primero]);
        $this->actingAs($presidente)->from(route('asambleas.show', $asamblea))
            ->post($ruta, ['motivo' => 'corto'])
            ->assertRedirect(route('asambleas.show', $asamblea))
            ->assertSessionHasErrorsIn('acuerdo_'.$primero->id, 'motivo');

        // followingRedirects: la página que ve el Presidente después de la redirección.
        $pagina = $this->actingAs($presidente)->from(route('asambleas.show', $asamblea))->followingRedirects()
            ->post($ruta, ['motivo' => 'corto'])
            ->assertOk()
            ->getContent();
        $this->assertSame(1, substr_count((string) $pagina, 'Explica por qué el acuerdo se vuelve regla permanente'));
        $this->assertStringContainsString('Acuerdo 1: Explica por qué', (string) $pagina);
    }

    public function test_el_jugador_con_club_o_sin_club_no_entra_a_las_asambleas(): void
    {
        $asamblea = $this->asambleaCelebrada();
        $acuerdo = $this->acuerdoQueModifica($asamblea);
        $club = Club::query()->create(['nombre' => 'Club del Jugador', 'estado' => 'activo']);

        foreach ([$this->usuario('Jugador', $club), $this->usuario('Jugador')] as $jugador) {
            $this->actingAs($jugador)->get(route('asambleas.index'))->assertForbidden();
            $this->actingAs($jugador)->get(route('asambleas.create'))->assertForbidden();
            $this->actingAs($jugador)->get(route('asambleas.show', $asamblea))->assertForbidden();
            $this->actingAs($jugador)->get(route('asambleas.asistencia.edit', $asamblea))->assertForbidden();
            $this->actingAs($jugador)->post(route('asambleas.store'), $this->convocatoria())->assertForbidden();
            $this->actingAs($jugador)->put(route('asambleas.asistencia.update', $asamblea), ['asistencias' => []])->assertForbidden();
            $this->actingAs($jugador)->post(route('asambleas.acuerdos.store', $asamblea), ['descripcion' => 'Intento', 'modifica_reglamento' => '0'])->assertForbidden();
            $this->actingAs($jugador)->post(route('asambleas.acuerdos.autorizar', [$asamblea, $acuerdo]), ['motivo' => 'Intento de un jugador'])->assertForbidden();
            $this->actingAs($jugador)->post(route('asambleas.comunicados.store', $asamblea), ['titulo' => 'Intento', 'contenido' => 'Intento'])->assertForbidden();
        }

        $this->assertSame(1, AcuerdoAsamblea::query()->count());
    }

    public function test_el_403_conserva_el_mensaje_en_espanol_de_la_politica(): void
    {
        $asamblea = $this->asambleaCelebrada();

        $this->actingAs($this->usuario('Tesorero'))
            ->postJson(route('asambleas.acuerdos.store', $asamblea), ['descripcion' => 'Intento', 'modifica_reglamento' => '0'])
            ->assertForbidden()
            ->assertJsonPath('mensaje', 'Solo el Secretario puede registrar los acuerdos y comunicados de una asamblea.');
    }

    public function test_la_consulta_de_permisos_muestra_los_de_asambleas_con_nombres_legibles(): void
    {
        $administrador = $this->usuario('Administrador General');
        $secretario = Role::findByName('Secretario', 'web');
        $presidente = Role::findByName('Presidente', 'web');

        $this->actingAs($administrador)->get(route('admin.roles.show', $secretario->id))
            ->assertOk()
            ->assertSee('Asambleas')
            ->assertSee('Registrar asistencia')
            ->assertSee('Registrar acuerdos y comunicados')
            ->assertDontSee('Acuerdos.registrar');

        $this->actingAs($administrador)->get(route('admin.roles.show', $presidente->id))
            ->assertOk()
            ->assertSee('Autorizar reglas permanentes')
            ->assertDontSee('Acuerdos.autorizar');
    }

    private function asambleaCelebrada(): Asamblea
    {
        $servicio = app(AsambleaService::class);
        $this->actingAs($this->secretario);
        $asamblea = $servicio->convocar($this->convocatoria() + ['temporada_id' => null], $this->secretario);
        $club = Club::query()->create(['nombre' => 'Club Celebrada '.str()->random(4), 'estado' => 'activo']);
        // La asistencia se registra el día de la asamblea o después (hora de México).
        $this->travelTo(Carbon::parse($asamblea->fecha->toDateString().' 19:30', 'America/Mexico_City'));
        $this->flushSession(); // el salto de días no cuenta como inactividad de la sesión

        return $servicio->registrarAsistencia($asamblea, [$club->id => ['asistio' => '1', 'nombre_asistente' => 'Delegado presente']], $this->secretario);
    }

    private function acuerdoQueModifica(Asamblea $asamblea): AcuerdoAsamblea
    {
        $this->actingAs($this->secretario);
        $temporada = \App\Models\Temporada::factory()->activa()->create();

        return app(AsambleaService::class)->registrarAcuerdo($asamblea, [
            'descripcion' => 'Acuerdo que modifica el reglamento de la temporada.',
            'modifica_reglamento' => true,
            'temporada_id' => $temporada->id,
        ], $this->secretario);
    }

    /** @return array{tipo: string, fecha: string, hora: string, lugar: string, orden_del_dia: string} */
    private function convocatoria(): array
    {
        return [
            'tipo' => 'Ordinaria',
            'fecha' => now('America/Mexico_City')->addWeek()->toDateString(),
            'hora' => '18:00',
            'lugar' => 'Salón de la Liga',
            'orden_del_dia' => 'Orden del día de prueba',
        ];
    }

    private function usuario(string $rol, ?Club $club = null): User
    {
        $usuario = User::query()->create([
            'name' => $rol.' '.str()->random(4),
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'rol' => 'Consultor',
            'estatus' => 'activo',
            'club_id' => $club?->id,
        ]);
        $usuario->assignRole($rol);

        return $usuario;
    }
}
