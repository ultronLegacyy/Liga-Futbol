<?php

namespace Tests\Feature;

use App\Models\Asamblea;
use App\Models\AsistenciaAsamblea;
use App\Models\Club;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** HU-053 (RF-14.1): convocatoria a asamblea y asistencia de los delegados. */
class AsambleaConvocatoriaAsistenciaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_el_secretario_convoca_una_asamblea(): void
    {
        $secretario = $this->usuario('Secretario');

        $this->actingAs($secretario)->get(route('asambleas.create'))->assertOk()->assertSee('Orden del día');

        $respuesta = $this->actingAs($secretario)->post(route('asambleas.store'), $this->convocatoria());

        $asamblea = Asamblea::query()->where('lugar', 'Salón de la Liga')->sole();
        $respuesta->assertRedirect(route('asambleas.show', $asamblea))->assertSessionHas('success');
        $this->assertSame(Asamblea::ESTADO_CONVOCADA, $asamblea->estado);
        $this->assertSame('Extraordinaria', $asamblea->tipo);
        $this->assertSame($secretario->id, $asamblea->convocada_por_usuario_id);
        $this->assertFalse($asamblea->tieneAsistenciaRegistrada());
        $this->assertDatabaseHas('auditoria_logs', ['tabla_afectada' => 'asambleas', 'registro_id' => $asamblea->id, 'accion' => 'crear']);
    }

    public function test_la_convocatoria_valida_sus_datos_y_no_acepta_fechas_pasadas(): void
    {
        $this->actingAs($this->usuario('Secretario'))
            ->post(route('asambleas.store'), ['tipo' => 'Informal', 'fecha' => now()->subDay()->toDateString()])
            ->assertSessionHasErrors(['tipo', 'fecha', 'hora', 'lugar', 'orden_del_dia']);

        $this->assertDatabaseMissing('asambleas', ['lugar' => 'Salón de la Liga']);
    }

    public function test_registra_la_lista_de_asistentes_y_queda_guardada_con_la_convocatoria(): void
    {
        $secretario = $this->usuario('Secretario');
        $asamblea = $this->asambleaConvocada($secretario);
        $aguilas = Club::query()->create(['nombre' => 'Águilas Asamblea', 'estado' => 'activo']);
        $halcones = Club::query()->create(['nombre' => 'Halcones Asamblea', 'estado' => 'activo']);
        $delegado = $this->usuario('Delegado del Club', $aguilas);

        $this->actingAs($secretario)->get(route('asambleas.asistencia.edit', $asamblea))
            ->assertOk()
            ->assertSee('Águilas Asamblea')
            ->assertSee('Halcones Asamblea')
            ->assertSee($delegado->name);

        $this->actingAs($secretario)->put(route('asambleas.asistencia.update', $asamblea), [
            'asistencias' => [
                $aguilas->id => ['asistio' => '1', 'delegado_usuario_id' => $delegado->id, 'nombre_asistente' => ''],
                $halcones->id => ['asistio' => '0'],
            ],
        ])->assertRedirect(route('asambleas.show', $asamblea))->assertSessionHas('success');

        $asamblea->refresh();
        $this->assertSame(Asamblea::ESTADO_CELEBRADA, $asamblea->estado);
        $this->assertTrue($asamblea->tieneAsistenciaRegistrada());

        $asistio = AsistenciaAsamblea::query()->where('asamblea_id', $asamblea->id)->where('club_id', $aguilas->id)->sole();
        $this->assertTrue($asistio->asistio);
        $this->assertSame($delegado->id, $asistio->delegado_usuario_id);
        $this->assertSame($delegado->name, $asistio->nombre_asistente);
        $this->assertSame($secretario->id, $asistio->registrado_por_usuario_id);

        $falto = AsistenciaAsamblea::query()->where('asamblea_id', $asamblea->id)->where('club_id', $halcones->id)->sole();
        $this->assertFalse($falto->asistio);
        $this->assertNull($falto->nombre_asistente);

        $this->actingAs($secretario)->get(route('asambleas.show', $asamblea))
            ->assertOk()
            ->assertSee('Salón de la Liga')
            ->assertSee('Asistieron 1 de')
            ->assertSee($delegado->name);
    }

    public function test_un_club_que_asistio_debe_indicar_quien_lo_represento(): void
    {
        $secretario = $this->usuario('Secretario');
        $asamblea = $this->asambleaConvocada($secretario);
        $club = Club::query()->create(['nombre' => 'Club sin representante', 'estado' => 'activo']);

        $this->actingAs($secretario)->put(route('asambleas.asistencia.update', $asamblea), [
            'asistencias' => [$club->id => ['asistio' => '1']],
        ])->assertSessionHasErrors("asistencias.{$club->id}.nombre_asistente");

        $this->assertDatabaseCount('asistencias_asamblea', 0);
        $this->assertSame(Asamblea::ESTADO_CONVOCADA, $asamblea->refresh()->estado);
    }

    public function test_no_acepta_un_delegado_de_otro_club_ni_un_club_inactivo(): void
    {
        $secretario = $this->usuario('Secretario');
        $asamblea = $this->asambleaConvocada($secretario);
        $club = Club::query()->create(['nombre' => 'Club Uno', 'estado' => 'activo']);
        $otro = Club::query()->create(['nombre' => 'Club Dos', 'estado' => 'activo']);
        $inactivo = Club::query()->create(['nombre' => 'Club Inactivo', 'estado' => 'inactivo']);
        $delegadoOtro = $this->usuario('Delegado del Club', $otro);

        $this->actingAs($secretario)->put(route('asambleas.asistencia.update', $asamblea), [
            'asistencias' => [
                $club->id => ['asistio' => '1', 'delegado_usuario_id' => $delegadoOtro->id],
                $inactivo->id => ['asistio' => '1', 'nombre_asistente' => 'Alguien'],
            ],
        ])->assertSessionHasErrors(["asistencias.{$club->id}.delegado_usuario_id", "asistencias.{$inactivo->id}"]);

        $this->assertDatabaseCount('asistencias_asamblea', 0);
    }

    public function test_la_correccion_de_la_asistencia_queda_en_la_auditoria(): void
    {
        $secretario = $this->usuario('Secretario');
        $asamblea = $this->asambleaConvocada($secretario);
        $club = Club::query()->create(['nombre' => 'Club Corrección', 'estado' => 'activo']);
        $ruta = route('asambleas.asistencia.update', $asamblea);

        $this->actingAs($secretario)->put($ruta, ['asistencias' => [$club->id => ['asistio' => '0']]])->assertSessionHasNoErrors();
        $this->actingAs($secretario)->put($ruta, ['asistencias' => [$club->id => ['asistio' => '1', 'nombre_asistente' => 'Suplente del club']]])->assertSessionHasNoErrors();

        $this->assertSame(1, AsistenciaAsamblea::query()->where('asamblea_id', $asamblea->id)->count());
        $this->assertSame('Suplente del club', AsistenciaAsamblea::query()->where('asamblea_id', $asamblea->id)->sole()->nombre_asistente);
        $this->assertTrue(DB::table('auditoria_logs')->where('tabla_afectada', 'asistencias_asamblea')->where('accion', 'actualizar')->exists());
    }

    public function test_solo_el_secretario_convoca_y_registra_la_asistencia(): void
    {
        $secretario = $this->usuario('Secretario');
        $asamblea = $this->asambleaConvocada($secretario);
        $club = Club::query()->create(['nombre' => 'Club Permisos', 'estado' => 'activo']);
        $delegado = $this->usuario('Delegado del Club', $club);
        $tesorero = $this->usuario('Tesorero');

        foreach ([$delegado, $tesorero] as $usuario) {
            $this->actingAs($usuario)->post(route('asambleas.store'), $this->convocatoria())->assertForbidden();
            $this->actingAs($usuario)->get(route('asambleas.asistencia.edit', $asamblea))->assertForbidden();
            $this->actingAs($usuario)->put(route('asambleas.asistencia.update', $asamblea), [
                'asistencias' => [$club->id => ['asistio' => '1', 'nombre_asistente' => 'Intruso']],
            ])->assertForbidden();
        }

        $this->assertDatabaseCount('asistencias_asamblea', 0);
        $this->actingAs($this->usuario('Presidente'))->get(route('asambleas.show', $asamblea))->assertOk();
        $this->actingAs($tesorero)->get(route('asambleas.index'))->assertForbidden();
    }

    public function test_el_listado_muestra_las_asambleas_con_su_estado(): void
    {
        $secretario = $this->usuario('Secretario');
        $this->asambleaConvocada($secretario);

        $this->actingAs($secretario)->get(route('asambleas.index'))
            ->assertOk()
            ->assertSee('Asambleas de delegados')
            ->assertSee('Convocada')
            ->assertSee('Sin registrar');
    }

    private function asambleaConvocada(User $secretario): Asamblea
    {
        $this->actingAs($secretario)->post(route('asambleas.store'), $this->convocatoria())->assertSessionHasNoErrors();

        return Asamblea::query()->where('lugar', 'Salón de la Liga')->latest('id')->firstOrFail();
    }

    /** @return array<string, string> */
    private function convocatoria(): array
    {
        return [
            'tipo' => 'Extraordinaria',
            'fecha' => now()->addWeek()->toDateString(),
            'hora' => '19:30',
            'lugar' => 'Salón de la Liga',
            'orden_del_dia' => "1. Lista de asistencia\n2. Informe de la temporada",
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
