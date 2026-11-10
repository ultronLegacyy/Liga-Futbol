<?php

namespace Tests\Feature;

use App\Models\AcuerdoAsamblea;
use App\Models\Asamblea;
use App\Models\Club;
use App\Models\ComunicadoAsamblea;
use App\Models\Temporada;
use App\Models\User;
use App\Services\AsambleaService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** HU-054 (RF-14.2, RF-14.3): acuerdos y comunicados de asamblea. */
class AsambleaAcuerdosComunicadosTest extends TestCase
{
    use DatabaseTransactions;

    private User $secretario;

    private Temporada $temporada;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->secretario = $this->usuario('Secretario');
        $this->temporada = Temporada::factory()->activa()->create();
    }

    public function test_registra_un_acuerdo_que_no_modifica_el_reglamento(): void
    {
        $asamblea = $this->asambleaCelebrada();

        $this->actingAs($this->secretario)->post(route('asambleas.acuerdos.store', $asamblea), [
            'descripcion' => 'Se aprueba el calendario de la segunda vuelta.',
            'modifica_reglamento' => '0',
        ])->assertRedirect(route('asambleas.show', $asamblea))->assertSessionHasNoErrors();

        $acuerdo = AcuerdoAsamblea::query()->sole();
        $this->assertSame(1, $acuerdo->numero);
        $this->assertFalse($acuerdo->modifica_reglamento);
        $this->assertNull($acuerdo->temporada_id);
        $this->assertFalse($acuerdo->es_regla_permanente);
        $this->assertSame($this->secretario->id, $acuerdo->registrado_por_usuario_id);
    }

    public function test_un_acuerdo_que_modifica_el_reglamento_queda_ligado_a_su_temporada(): void
    {
        $asamblea = $this->asambleaCelebrada();

        $this->actingAs($this->secretario)->post(route('asambleas.acuerdos.store', $asamblea), [
            'descripcion' => 'Se reduce a 3 el número de amonestaciones para suspensión.',
            'modifica_reglamento' => '1',
        ])->assertSessionHasNoErrors();

        $acuerdo = AcuerdoAsamblea::query()->sole();
        $this->assertTrue($acuerdo->modifica_reglamento);
        $this->assertSame($this->temporada->id, $acuerdo->temporada_id, 'Sin temporada explícita se usa la de la asamblea.');
        $this->assertFalse($acuerdo->es_regla_permanente, 'Modificar el reglamento no lo vuelve regla permanente.');

        $this->actingAs($this->secretario)->get(route('asambleas.show', $asamblea))
            ->assertOk()
            ->assertSee('Modifica el reglamento de '.$this->temporada->nombre);
    }

    public function test_exige_indicar_si_modifica_el_reglamento_y_la_temporada_si_lo_modifica(): void
    {
        $asamblea = $this->asambleaCelebrada(conTemporada: false);
        $ruta = route('asambleas.acuerdos.store', $asamblea);

        $this->actingAs($this->secretario)->post($ruta, ['descripcion' => 'Acuerdo sin indicar'])
            ->assertSessionHasErrors('modifica_reglamento');

        $this->actingAs($this->secretario)->post($ruta, ['descripcion' => 'Acuerdo sin temporada', 'modifica_reglamento' => '1'])
            ->assertSessionHasErrors('temporada_id');

        $this->assertDatabaseCount('acuerdos_asamblea', 0);
    }

    public function test_numera_los_acuerdos_de_cada_asamblea(): void
    {
        $asamblea = $this->asambleaCelebrada();

        foreach (['Primer acuerdo de la sesión', 'Segundo acuerdo de la sesión'] as $descripcion) {
            $this->actingAs($this->secretario)->post(route('asambleas.acuerdos.store', $asamblea), [
                'descripcion' => $descripcion,
                'modifica_reglamento' => '0',
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame([1, 2], AcuerdoAsamblea::query()->orderBy('numero')->pluck('numero')->all());
    }

    public function test_sin_asistencia_registrada_no_se_capturan_acuerdos_ni_comunicados(): void
    {
        $asamblea = app(AsambleaService::class)->convocar($this->datosConvocatoria(), $this->secretario);

        $this->actingAs($this->secretario)->post(route('asambleas.acuerdos.store', $asamblea), [
            'descripcion' => 'Acuerdo anticipado',
            'modifica_reglamento' => '0',
        ])->assertSessionHasErrors('asamblea');

        $this->actingAs($this->secretario)->post(route('asambleas.comunicados.store', $asamblea), [
            'titulo' => 'Comunicado anticipado',
            'contenido' => 'No debe guardarse.',
        ])->assertSessionHasErrors('asamblea');

        $this->assertDatabaseCount('acuerdos_asamblea', 0);
        $this->assertDatabaseCount('comunicados_asamblea', 0);
    }

    public function test_el_secretario_no_puede_convertir_un_acuerdo_en_regla_permanente(): void
    {
        $asamblea = $this->asambleaCelebrada();
        $acuerdo = $this->acuerdoQueModifica($asamblea);

        $this->actingAs($this->secretario)->post(route('asambleas.acuerdos.autorizar', [$asamblea, $acuerdo]), [
            'motivo' => 'Se quiere aplicar en todas las temporadas.',
        ])->assertForbidden();

        $this->assertFalse($acuerdo->refresh()->es_regla_permanente);
        $this->assertNull($acuerdo->autorizado_por_usuario_id);
    }

    public function test_el_servicio_rechaza_la_conversion_sin_autorizacion_aunque_no_pase_por_la_ruta(): void
    {
        $acuerdo = $this->acuerdoQueModifica($this->asambleaCelebrada());

        $this->expectException(AuthorizationException::class);

        try {
            app(AsambleaService::class)->convertirEnReglaPermanente($acuerdo, 'Intento directo sin permiso.', $this->secretario);
        } finally {
            $this->assertFalse($acuerdo->refresh()->es_regla_permanente);
        }
    }

    public function test_la_asignacion_masiva_no_convierte_un_acuerdo_en_regla_permanente(): void
    {
        $acuerdo = $this->acuerdoQueModifica($this->asambleaCelebrada());

        $acuerdo->update(['es_regla_permanente' => true, 'autorizado_por_usuario_id' => $this->secretario->id]);

        $this->assertFalse($acuerdo->refresh()->es_regla_permanente);
        $this->assertNull($acuerdo->autorizado_por_usuario_id);
    }

    public function test_el_presidente_autoriza_la_regla_permanente_con_motivo_y_queda_auditado(): void
    {
        $asamblea = $this->asambleaCelebrada();
        $acuerdo = $this->acuerdoQueModifica($asamblea);
        $presidente = $this->usuario('Presidente');
        $ruta = route('asambleas.acuerdos.autorizar', [$asamblea, $acuerdo]);

        $this->actingAs($presidente)->post($ruta, ['motivo' => 'corto'])->assertSessionHasErrors('motivo');

        $this->actingAs($presidente)->post($ruta, [
            'motivo' => 'La asamblea votó aplicarlo en las siguientes temporadas.',
        ])->assertRedirect(route('asambleas.show', $asamblea))->assertSessionHasNoErrors();

        $acuerdo->refresh();
        $this->assertTrue($acuerdo->es_regla_permanente);
        $this->assertSame($presidente->id, $acuerdo->autorizado_por_usuario_id);
        $this->assertNotNull($acuerdo->autorizado_en);
        $this->assertSame('La asamblea votó aplicarlo en las siguientes temporadas.', $acuerdo->motivo_autorizacion);
        $this->assertTrue(DB::table('auditoria_logs')
            ->where('tabla_afectada', 'acuerdos_asamblea')
            ->where('registro_id', $acuerdo->id)
            ->where('accion', 'actualizar')
            ->exists());

        $this->actingAs($presidente)->post($ruta, ['motivo' => 'Segundo intento de autorización.'])
            ->assertSessionHasErrors('acuerdo');
    }

    public function test_un_acuerdo_que_no_modifica_el_reglamento_no_puede_ser_regla_permanente(): void
    {
        $asamblea = $this->asambleaCelebrada();
        $this->actingAs($this->secretario)->post(route('asambleas.acuerdos.store', $asamblea), [
            'descripcion' => 'Se felicita al club campeón.',
            'modifica_reglamento' => '0',
        ]);
        $acuerdo = AcuerdoAsamblea::query()->sole();

        $this->actingAs($this->usuario('Presidente'))->post(route('asambleas.acuerdos.autorizar', [$asamblea, $acuerdo]), [
            'motivo' => 'Intento sobre un acuerdo que no cambia reglas.',
        ])->assertSessionHasErrors('acuerdo');

        $this->assertFalse($acuerdo->refresh()->es_regla_permanente);
    }

    public function test_no_autoriza_un_acuerdo_por_la_ruta_de_otra_asamblea(): void
    {
        $acuerdo = $this->acuerdoQueModifica($this->asambleaCelebrada());
        $otra = $this->asambleaCelebrada();

        $this->actingAs($this->usuario('Presidente'))->post(route('asambleas.acuerdos.autorizar', [$otra, $acuerdo]), [
            'motivo' => 'Ruta cruzada entre asambleas.',
        ])->assertNotFound();
    }

    public function test_registra_comunicados_de_la_asamblea(): void
    {
        $asamblea = $this->asambleaCelebrada();

        $this->actingAs($this->secretario)->post(route('asambleas.comunicados.store', $asamblea), [
            'titulo' => 'Cambio de sede de la final',
            'contenido' => 'La final se jugará en la Unidad Deportiva Norte.',
        ])->assertRedirect(route('asambleas.show', $asamblea))->assertSessionHasNoErrors();

        $comunicado = ComunicadoAsamblea::query()->sole();
        $this->assertSame($asamblea->id, $comunicado->asamblea_id);
        $this->assertSame($this->secretario->id, $comunicado->registrado_por_usuario_id);

        $this->actingAs($this->secretario)->get(route('asambleas.show', $asamblea))
            ->assertOk()
            ->assertSee('Cambio de sede de la final')
            ->assertSee('Unidad Deportiva Norte');
    }

    public function test_solo_el_secretario_registra_acuerdos_y_comunicados(): void
    {
        $asamblea = $this->asambleaCelebrada();
        $tesorero = $this->usuario('Tesorero');

        $this->actingAs($tesorero)->post(route('asambleas.acuerdos.store', $asamblea), [
            'descripcion' => 'Acuerdo sin permiso',
            'modifica_reglamento' => '0',
        ])->assertForbidden();
        $this->actingAs($tesorero)->post(route('asambleas.comunicados.store', $asamblea), [
            'titulo' => 'Sin permiso',
            'contenido' => 'No debe guardarse.',
        ])->assertForbidden();

        $this->assertDatabaseCount('acuerdos_asamblea', 0);
        $this->assertDatabaseCount('comunicados_asamblea', 0);
    }

    private function asambleaCelebrada(bool $conTemporada = true): Asamblea
    {
        $servicio = app(AsambleaService::class);
        $datos = $this->datosConvocatoria();

        if ($conTemporada) {
            $datos['temporada_id'] = $this->temporada->id;
        }

        $this->actingAs($this->secretario);
        $asamblea = $servicio->convocar($datos, $this->secretario);
        $club = Club::query()->create(['nombre' => 'Club Acuerdos '.str()->random(4), 'estado' => 'activo']);

        return $servicio->registrarAsistencia($asamblea, [$club->id => ['asistio' => '1', 'nombre_asistente' => 'Delegado presente']], $this->secretario);
    }

    private function acuerdoQueModifica(Asamblea $asamblea): AcuerdoAsamblea
    {
        $this->actingAs($this->secretario);

        return app(AsambleaService::class)->registrarAcuerdo($asamblea, [
            'descripcion' => 'Se amplía a 25 el número de jugadores registrables por equipo.',
            'modifica_reglamento' => true,
        ], $this->secretario);
    }

    /** @return array{tipo: string, fecha: string, hora: string, lugar: string, orden_del_dia: string} */
    private function datosConvocatoria(): array
    {
        return [
            'tipo' => 'Ordinaria',
            'fecha' => now()->addDays(3)->toDateString(),
            'hora' => '18:00',
            'lugar' => 'Sala de juntas',
            'orden_del_dia' => 'Acuerdos de la temporada',
        ];
    }

    private function usuario(string $rol): User
    {
        $usuario = User::query()->create([
            'name' => $rol.' '.str()->random(4),
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'rol' => 'Consultor',
            'estatus' => 'activo',
        ]);
        $usuario->assignRole($rol);

        return $usuario;
    }
}
