<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
use App\Models\Persona;
use App\Models\PersonaRol;
use App\Models\Temporada;
use App\Services\IdentidadPersonaService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

// HU-013: la persona es una identidad única y reutilizable con roles por temporada (RF-03.2)
class IdentidadPersonaTest extends TestCase
{
    use DatabaseTransactions;

    private IdentidadPersonaService $servicio;

    private Temporada $temporada;

    protected function setUp(): void
    {
        parent::setUp();

        $this->servicio = app(IdentidadPersonaService::class);
        $this->temporada = Temporada::factory()->activa()->create();
    }

    // CA1: al recibir un rol nuevo, la persona que ya existe se reutiliza en vez de duplicarse
    public function test_un_rol_nuevo_reutiliza_la_identidad_existente(): void
    {
        $datos = $this->datosPersona('PEGA900101HMNRRL01');

        $comoJugador = $this->servicio->asignarRol($datos, 'jugador', $this->temporada);
        $comoDelegado = $this->servicio->asignarRol($datos, 'delegado', $this->temporada);

        $this->assertSame($comoJugador->persona_id, $comoDelegado->persona_id);
        $this->assertSame(1, Persona::where('curp', 'PEGA900101HMNRRL01')->count());
        $this->assertSame(2, PersonaRol::where('persona_id', $comoJugador->persona_id)->count());
    }

    // CA1: la misma CURP escrita con minúsculas o espacios es la misma persona
    public function test_la_curp_se_reconoce_sin_importar_mayusculas_ni_espacios(): void
    {
        $this->servicio->asignarRol($this->datosPersona('PEGA900101HMNRRL01'), 'jugador', $this->temporada);
        $this->servicio->asignarRol($this->datosPersona('  pega900101hmnrrl01 '), 'arbitro', $this->temporada);

        $this->assertSame(1, Persona::where('curp', 'PEGA900101HMNRRL01')->count());
        $this->assertNotNull($this->servicio->buscarPorCurp('pega900101hmnrrl01'));
    }

    // CA1: reutilizar la identidad no sobrescribe los datos que ya tenía la persona
    public function test_reutilizar_la_identidad_no_modifica_sus_datos(): void
    {
        $this->servicio->asignarRol($this->datosPersona('PEGA900101HMNRRL01'), 'jugador', $this->temporada);

        $otrosDatos = ['nombre' => 'Otro', 'apellidos' => 'Nombre', 'fecha_nacimiento' => '2000-05-05', 'curp' => 'PEGA900101HMNRRL01'];
        $this->servicio->asignarRol($otrosDatos, 'directivo', $this->temporada);

        $persona = Persona::where('curp', 'PEGA900101HMNRRL01')->firstOrFail();
        $this->assertSame('Gael', $persona->nombre);
        $this->assertSame('1990-01-01', $persona->fecha_nacimiento->format('Y-m-d'));
    }

    // Asignar dos veces el mismo rol en la misma temporada no lo duplica
    public function test_el_mismo_rol_en_la_misma_temporada_no_se_duplica(): void
    {
        $datos = $this->datosPersona('PEGA900101HMNRRL01');

        $primero = $this->servicio->asignarRol($datos, 'jugador', $this->temporada);
        $segundo = $this->servicio->asignarRol($datos, 'jugador', $this->temporada);

        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(1, PersonaRol::where('persona_id', $primero->persona_id)->count());
    }

    // La base de datos también impide repetir el rol, aunque se salte el servicio
    public function test_la_base_impide_repetir_rol_y_temporada(): void
    {
        $rol = PersonaRol::factory()->create(['rol' => 'arbitro', 'temporada_id' => $this->temporada->id]);

        $this->expectException(QueryException::class);

        PersonaRol::create(['persona_id' => $rol->persona_id, 'rol' => 'arbitro', 'temporada_id' => $this->temporada->id]);
    }

    // CA2: la consulta distingue cada rol y su temporada
    public function test_la_consulta_distingue_cada_rol_y_su_temporada(): void
    {
        $anterior = Temporada::factory()->activa()->create(['nombre' => 'Temporada anterior']);
        $datos = $this->datosPersona('PEGA900101HMNRRL01');

        $this->servicio->asignarRol($datos, 'jugador', $anterior);
        $this->servicio->asignarRol($datos, 'delegado', $this->temporada);
        $this->servicio->asignarRol($datos, 'arbitro', $this->temporada);

        $porTemporada = $this->servicio->buscarPorCurp('PEGA900101HMNRRL01')->rolesPorTemporada();

        $this->assertSame(['jugador'], $porTemporada[$anterior->id]->pluck('rol')->all());
        // El enum se ordena como el catálogo de RF-03.2 (jugador, delegado, arbitro, directivo)
        $this->assertSame(['delegado', 'arbitro'], $porTemporada[$this->temporada->id]->pluck('rol')->all());
        $this->assertSame('Temporada anterior', $porTemporada[$anterior->id]->first()->temporada->nombre);
    }

    // RF-01.4: una temporada cerrada no admite roles nuevos y no se crea la persona
    public function test_una_temporada_cerrada_no_admite_roles_nuevos(): void
    {
        $cerrada = Temporada::factory()->cerrada()->create();

        try {
            $this->servicio->asignarRol($this->datosPersona('PEGA900101HMNRRL01'), 'jugador', $cerrada);
            $this->fail('Se esperaba el rechazo por temporada cerrada.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('temporada_id', $e->errors());
        }

        $this->assertDatabaseMissing('personas', ['curp' => 'PEGA900101HMNRRL01']);
    }

    // Solo se aceptan los roles de RF-03.2
    public function test_un_rol_fuera_del_catalogo_se_rechaza(): void
    {
        $this->expectException(ValidationException::class);

        $this->servicio->asignarRol($this->datosPersona('PEGA900101HMNRRL01'), 'entrenador', $this->temporada);
    }

    // Sin CURP no hay forma segura de identificar a la persona
    public function test_sin_curp_no_se_registra_la_persona(): void
    {
        $this->expectException(ValidationException::class);

        $this->servicio->asignarRol($this->datosPersona('   '), 'jugador', $this->temporada);
    }

    // La CURP no se copia a la bitácora de auditoría
    public function test_la_auditoria_no_guarda_la_curp(): void
    {
        $rol = $this->servicio->asignarRol($this->datosPersona('PEGA900101HMNRRL01'), 'jugador', $this->temporada);

        $registro = AuditoriaLog::where('tabla_afectada', 'personas')
            ->where('registro_id', $rol->persona_id)
            ->where('accion', 'crear')
            ->firstOrFail();

        $this->assertArrayNotHasKey('curp', $registro->valores_nuevos);
        $this->assertSame('Gael', $registro->valores_nuevos['nombre']);
    }

    // El Secretario administra personas (actor de RF-03); el Tesorero no
    public function test_el_secretario_tiene_los_permisos_de_personas(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $secretario = Role::findByName('Secretario', 'web');
        $this->assertTrue($secretario->hasPermissionTo('personas.ver'));
        $this->assertTrue($secretario->hasPermissionTo('personas.crear'));
        $this->assertTrue($secretario->hasPermissionTo('personas.editar'));

        $this->assertTrue(Role::findByName('Administrador General', 'web')->hasPermissionTo('personas.crear'));
        $this->assertFalse(Role::findByName('Tesorero', 'web')->hasPermissionTo('personas.ver'));
    }

    /**
     * @return array{nombre: string, apellidos: string, fecha_nacimiento: string, curp: string}
     */
    private function datosPersona(string $curp): array
    {
        return [
            'nombre' => 'Gael',
            'apellidos' => 'Prueba Ficticia',
            'fecha_nacimiento' => '1990-01-01',
            'curp' => $curp,
        ];
    }
}
