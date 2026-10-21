<?php

namespace Tests\Feature;

use App\Models\ReglaTemporada;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
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

        $reglasV1 = [
            'puntos_victoria' => 3,
            'puntos_empate' => 1,
            'puntos_derrota' => 0,
        ];

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

        $reglasV2 = [
            'puntos_victoria' => 4,
            'puntos_empate' => 1,
            'puntos_derrota' => 0,
        ];

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
                'reglas_json' => json_encode([
                    'puntos_victoria' => 3,
                ]),
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

    public function test_motivo_es_obligatorio(): void
    {
        $admin = $this->crearAdministrador();
        $temporada = Temporada::factory()->activa()->create();

        $this->actingAs($admin)->post(
            route('temporadas.reglas.store', $temporada),
            [
                'reglas_json' => json_encode([
                    'puntos_victoria' => 3,
                ]),
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
            'reglas' => ['puntos_victoria' => 3],
            'usuario_id' => $admin->id,
            'motivo' => 'Versión inicial.',
        ]);

        $this->actingAs($admin)
            ->get(route('temporadas.reglas.index', $temporada))
            ->assertOk()
            ->assertSee('Versión inicial.')
            ->assertSee('v1');
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
}
