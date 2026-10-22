<?php

namespace Tests\Feature;

use App\Models\ReglaTemporada;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Tests\TestCase;

class ReglasTemporadaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_administrador_puede_guardar_versiones_consecutivas_y_auditadas(): void
    {
        $admin = $this->crearAdministrador();
        $temporada = Temporada::factory()->activa()->create();

        $reglasV1 = $this->reglasValidas(3);

        $this->actingAs($admin)->post(
            route('temporadas.reglas.store', $temporada),
            [
                'reglas_json' => json_encode($reglasV1),
                'motivo' => 'Configuración inicial.',
            ]
        )->assertRedirect(route('temporadas.reglas.index', $temporada));

        $this->assertDatabaseHas('regla_temporadas', [
            'temporada_id' => $temporada->id,
            'version' => 1,
            'motivo' => 'Configuración inicial.',
        ]);

        $reglasV2 = $this->reglasValidas(4);

        $this->actingAs($admin)->post(
            route('temporadas.reglas.store', $temporada),
            [
                'reglas_json' => json_encode($reglasV2),
                'motivo' => 'Ajuste de puntuación.',
            ]
        )->assertRedirect(route('temporadas.reglas.index', $temporada));

        $this->assertDatabaseHas('regla_temporadas', [
            'temporada_id' => $temporada->id,
            'version' => 2,
            'motivo' => 'Ajuste de puntuación.',
        ]);

        $this->assertSame(
            2,
            ReglaTemporada::where('temporada_id', $temporada->id)->count()
        );

        $this->assertDatabaseHas('auditoria_logs', [
            'tabla_afectada' => 'regla_temporadas',
            'accion' => 'crear',
            'motivo' => 'Ajuste de puntuación.',
        ]);
    }

    public function test_no_se_pueden_modificar_reglas_de_temporada_cerrada(): void
    {
        $admin = $this->crearAdministrador();
        $temporada = Temporada::factory()->cerrada()->create();

        $this->actingAs($admin)->post(
            route('temporadas.reglas.store', $temporada),
            [
                'reglas_json' => json_encode($this->reglasValidas(3)),
                'motivo' => 'Intento de cambio.',
            ]
        )->assertForbidden();

        $this->assertDatabaseMissing('regla_temporadas', [
            'temporada_id' => $temporada->id,
        ]);
    }

    public function test_json_invalido_se_rechaza(): void
    {
        $admin = $this->crearAdministrador();
        $temporada = Temporada::factory()->activa()->create();

        $this->actingAs($admin)->post(
            route('temporadas.reglas.store', $temporada),
            [
                'reglas_json' => '{json-invalido',
                'motivo' => 'Prueba de validación.',
            ]
        )->assertSessionHasErrors('reglas_json');

        $this->assertDatabaseMissing('regla_temporadas', [
            'temporada_id' => $temporada->id,
        ]);
    }

    public function test_rechaza_categorias_desconocidas_en_el_json(): void
    {
        $admin = $this->crearAdministrador();
        $temporada = Temporada::factory()->activa()->create();

        $reglas = $this->reglasValidas(3);
        $reglas['categoria_inventada'] = ['valor' => 1];

        $this->actingAs($admin)->post(
            route('temporadas.reglas.store', $temporada),
            [
                'reglas_json' => json_encode($reglas),
                'motivo' => 'Prueba de categoría desconocida.',
            ]
        )->assertSessionHasErrors('reglas_json');

        $this->assertDatabaseMissing('regla_temporadas', [
            'temporada_id' => $temporada->id,
        ]);
    }

    public function test_rechaza_una_lista_json_en_lugar_de_un_objeto(): void
    {
        $admin = $this->crearAdministrador();
        $temporada = Temporada::factory()->activa()->create();

        $this->actingAs($admin)->post(
            route('temporadas.reglas.store', $temporada),
            [
                'reglas_json' => json_encode([
                    $this->reglasValidas(3),
                ]),
                'motivo' => 'Prueba de estructura incorrecta.',
            ]
        )->assertSessionHasErrors('reglas_json');

        $this->assertDatabaseMissing('regla_temporadas', [
            'temporada_id' => $temporada->id,
        ]);
    }

    public function test_rechaza_criterios_de_desempate_fuera_del_catalogo(): void
    {
        $admin = $this->crearAdministrador();
        $temporada = Temporada::factory()->activa()->create();

        $reglas = $this->reglasValidas(3);
        $reglas['desempates'] = ['criterio_inventado'];

        $this->actingAs($admin)->post(
            route('temporadas.reglas.store', $temporada),
            [
                'reglas_json' => json_encode($reglas),
                'motivo' => 'Prueba de desempate desconocido.',
            ]
        )->assertSessionHasErrors('reglas_json');

        $this->assertDatabaseMissing('regla_temporadas', [
            'temporada_id' => $temporada->id,
        ]);
    }

    public function test_rechaza_montos_de_cuotas_negativos(): void
    {
        $admin = $this->crearAdministrador();
        $temporada = Temporada::factory()->activa()->create();

        $reglas = $this->reglasValidas(3);
        $reglas['cuotas']['inscripcion'] = -100;

        $this->actingAs($admin)->post(
            route('temporadas.reglas.store', $temporada),
            [
                'reglas_json' => json_encode($reglas),
                'motivo' => 'Prueba de cuota negativa.',
            ]
        )->assertSessionHasErrors('reglas_json');

        $this->assertDatabaseMissing('regla_temporadas', [
            'temporada_id' => $temporada->id,
        ]);
    }

    public function test_rechaza_fechas_limite_invalidas(): void
    {
        $admin = $this->crearAdministrador();
        $temporada = Temporada::factory()->activa()->create();

        $reglas = $this->reglasValidas(3);
        $reglas['fechas_limite']['registro_jugadores'] = '2026-02-30';

        $this->actingAs($admin)->post(
            route('temporadas.reglas.store', $temporada),
            [
                'reglas_json' => json_encode($reglas),
                'motivo' => 'Prueba de fecha inválida.',
            ]
        )->assertSessionHasErrors('reglas_json');

        $this->assertDatabaseMissing('regla_temporadas', [
            'temporada_id' => $temporada->id,
        ]);
    }

    public function test_rechaza_puntos_de_clasificacion_no_enteros(): void
    {
        $admin = $this->crearAdministrador();
        $temporada = Temporada::factory()->activa()->create();

        $reglas = $this->reglasValidas(3);
        $reglas['clasificacion']['puntos_victoria'] = 3.5;

        $this->actingAs($admin)->post(
            route('temporadas.reglas.store', $temporada),
            [
                'reglas_json' => json_encode($reglas),
                'motivo' => 'Prueba de puntos no enteros.',
            ]
        )->assertSessionHasErrors('reglas_json');

        $this->assertDatabaseMissing('regla_temporadas', [
            'temporada_id' => $temporada->id,
        ]);
    }

    public function test_usuario_sin_permiso_no_puede_consultar_el_historial(): void
    {
        $usuario = $this->crearUsuarioConsultaPublica();
        $temporada = Temporada::factory()->activa()->create();

        $this->actingAs($usuario)
            ->get(route('temporadas.reglas.index', $temporada))
            ->assertForbidden();
    }

    public function test_usuario_sin_permiso_no_puede_guardar_reglas(): void
    {
        $usuario = $this->crearUsuarioConsultaPublica();
        $temporada = Temporada::factory()->activa()->create();

        $this->actingAs($usuario)->post(
            route('temporadas.reglas.store', $temporada),
            [
                'reglas_json' => json_encode($this->reglasValidas(3)),
                'motivo' => 'Intento no autorizado.',
            ]
        )->assertForbidden();

        $this->assertDatabaseMissing('regla_temporadas', [
            'temporada_id' => $temporada->id,
        ]);
    }

    public function test_motivo_es_obligatorio(): void
    {
        $admin = $this->crearAdministrador();
        $temporada = Temporada::factory()->activa()->create();

        $this->actingAs($admin)->post(
            route('temporadas.reglas.store', $temporada),
            [
                'reglas_json' => json_encode($this->reglasValidas(3)),
                'motivo' => '',
            ]
        )->assertSessionHasErrors('motivo');

        $this->assertDatabaseMissing('regla_temporadas', [
            'temporada_id' => $temporada->id,
        ]);
    }

    public function test_historial_muestra_las_versiones_registradas(): void
    {
        $admin = $this->crearAdministrador();
        $temporada = Temporada::factory()->activa()->create();

        ReglaTemporada::create([
            'temporada_id' => $temporada->id,
            'version' => 1,
            'reglas' => $this->reglasValidas(3),
            'usuario_id' => $admin->id,
            'motivo' => 'Versión inicial.',
        ]);

        $this->actingAs($admin)
            ->get(route('temporadas.reglas.index', $temporada))
            ->assertOk()
            ->assertSee('Versión inicial.')
            ->assertSee('v1');
    }

    public function test_no_se_puede_actualizar_una_version_guardada(): void
    {
        $admin = $this->crearAdministrador();
        $temporada = Temporada::factory()->activa()->create();

        $version = ReglaTemporada::create([
            'temporada_id' => $temporada->id,
            'version' => 1,
            'reglas' => $this->reglasValidas(3),
            'usuario_id' => $admin->id,
            'motivo' => 'Versión inicial.',
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'Las versiones de reglas son inmutables y no pueden modificarse.'
        );

        $version->update([
            'motivo' => 'Intento de modificación.',
        ]);
    }

    public function test_no_se_puede_eliminar_una_version_guardada(): void
    {
        $admin = $this->crearAdministrador();
        $temporada = Temporada::factory()->activa()->create();

        $version = ReglaTemporada::create([
            'temporada_id' => $temporada->id,
            'version' => 1,
            'reglas' => $this->reglasValidas(3),
            'usuario_id' => $admin->id,
            'motivo' => 'Versión inicial.',
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'Las versiones de reglas son inmutables y no pueden eliminarse.'
        );

        $version->delete();
    }

    /**
     * Estructura de prueba de las cinco categorías obligatorias.
     *
     * @return array<string, mixed>
     */
    private function reglasValidas(int $puntosVictoria): array
    {
        return [
            'cuotas' => [
                'inscripcion' => 1000,
                'arbitraje' => 500,
            ],
            'fechas_limite' => [
                'registro_jugadores' => '2026-11-01',
                'registro_equipos' => '2026-11-05',
            ],
            'clasificacion' => [
                'puntos_victoria' => $puntosVictoria,
                'puntos_empate' => 1,
                'puntos_derrota' => 0,
            ],
            'desempates' => [
                'diferencia_goles',
                'goles_favor',
            ],
            'ascenso_descenso' => [
                'equipos_ascienden' => 2,
                'equipos_descienden' => 2,
            ],
        ];
    }

    private function crearAdministrador(): User
    {
        $usuario = User::create([
            'name' => 'Administrador de pruebas',
            'email' => 'admin_reglas_'.uniqid().'@liga.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Administrador General',
        ]);

        $usuario->assignRole('Administrador General');

        return $usuario;
    }

    private function crearUsuarioConsultaPublica(): User
    {
        $usuario = User::create([
            'name' => 'Usuario de consulta pública',
            'email' => 'consulta_reglas_'.uniqid().'@liga.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Arbitro',
        ]);

        $usuario->assignRole('Consulta Publica');

        return $usuario;
    }
}
