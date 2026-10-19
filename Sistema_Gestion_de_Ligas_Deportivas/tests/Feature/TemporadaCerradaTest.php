<?php

namespace Tests\Feature;

use App\Models\Convocatoria;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TemporadaCerradaTest extends TestCase
{
    // Manejo transaccional para revertir cambios tras cada prueba
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Sembrado de roles y permisos del sistema
        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = User::create([
            'name' => 'Admin Temporada Cerrada',
            'email' => 'admin_temporada_cerrada@liga.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Administrador General',
        ]);
        $this->admin->assignRole('Administrador General');
    }

    public function test_administrador_recibe_403_al_editar_una_temporada_cerrada(): void
    {
        $cerrada = Temporada::factory()->cerrada()->create();

        $respuesta = $this->actingAs($this->admin)->get(route('temporadas.edit', $cerrada));

        $respuesta->assertForbidden();
        $respuesta->assertSee('Una temporada cerrada no puede modificarse');
    }

    public function test_administrador_recibe_403_al_actualizar_una_temporada_cerrada_y_no_cambia(): void
    {
        $cerrada = Temporada::factory()->cerrada()->create([
            'nombre' => 'Temporada Clausurada 2025',
            'fecha_inicio' => '2025-01-01',
            'fecha_fin' => '2025-06-30',
        ]);

        $respuesta = $this->actingAs($this->admin)->put(route('temporadas.update', $cerrada), [
            'nombre' => 'Nombre Alterado',
            'fecha_inicio' => '2030-01-01',
            'fecha_fin' => '2030-06-30',
        ]);

        $respuesta->assertForbidden();
        $respuesta->assertSee('Una temporada cerrada no puede modificarse');

        // La temporada conserva sus datos originales
        $this->assertDatabaseHas('temporadas', [
            'id' => $cerrada->id,
            'nombre' => 'Temporada Clausurada 2025',
            'fecha_inicio' => '2025-01-01',
            'fecha_fin' => '2025-06-30',
            'estado' => 'cerrada',
        ]);
    }

    public function test_administrador_recibe_403_al_eliminar_una_temporada_cerrada_y_se_conserva_con_su_convocatoria(): void
    {
        $cerrada = Temporada::factory()->cerrada()->create();
        $convocatoria = Convocatoria::create([
            'temporada_id' => $cerrada->id,
            'archivo_pdf' => 'convocatorias/temporada_cerrada.pdf',
            'reglas_versionadas' => json_encode([]),
        ]);

        $respuesta = $this->actingAs($this->admin)->delete(route('temporadas.destroy', $cerrada));

        $respuesta->assertForbidden();
        $respuesta->assertSee('Una temporada cerrada no puede eliminarse');

        // La temporada y su convocatoria siguen existiendo
        $this->assertDatabaseHas('temporadas', ['id' => $cerrada->id, 'estado' => 'cerrada']);
        $this->assertDatabaseHas('convocatorias', ['id' => $convocatoria->id, 'temporada_id' => $cerrada->id]);
    }

    public function test_administrador_puede_consultar_una_temporada_cerrada(): void
    {
        $cerrada = Temporada::factory()->cerrada()->create();

        $respuesta = $this->actingAs($this->admin)->get(route('temporadas.show', $cerrada));

        $respuesta->assertOk();
        $respuesta->assertJson([
            'temporada_id' => $cerrada->id,
            'nombre' => $cerrada->nombre,
            'estado' => 'cerrada',
        ]);
    }

    public function test_politica_niega_actualizar_y_eliminar_una_temporada_cerrada_al_administrador(): void
    {
        $cerrada = Temporada::factory()->cerrada()->create();

        $this->assertTrue(Gate::forUser($this->admin)->denies('update', $cerrada));
        $this->assertTrue(Gate::forUser($this->admin)->denies('delete', $cerrada));

        // La consulta sigue permitida
        $this->assertTrue(Gate::forUser($this->admin)->allows('view', $cerrada));
    }

    public function test_administrador_puede_actualizar_una_temporada_activa(): void
    {
        $activa = Temporada::factory()->activa()->create();

        $respuesta = $this->actingAs($this->admin)->put(route('temporadas.update', $activa), [
            'nombre' => 'Temporada Activa Renombrada',
            'fecha_inicio' => '2027-01-10',
            'fecha_fin' => '2027-06-30',
        ]);

        $respuesta->assertRedirect();
        $respuesta->assertSessionHas('success', 'Temporada actualizada correctamente.');
        $this->assertDatabaseHas('temporadas', [
            'id' => $activa->id,
            'nombre' => 'Temporada Activa Renombrada',
            'fecha_inicio' => '2027-01-10',
            'fecha_fin' => '2027-06-30',
            'estado' => 'activa',
        ]);
    }

    public function test_administrador_puede_eliminar_una_temporada_activa(): void
    {
        $activa = Temporada::factory()->activa()->create();

        $respuesta = $this->actingAs($this->admin)->delete(route('temporadas.destroy', $activa));

        $respuesta->assertRedirect();
        $respuesta->assertSessionHas('success', 'Temporada eliminada.');
        $this->assertDatabaseMissing('temporadas', ['id' => $activa->id]);
    }

    public function test_usuario_sin_permisos_recibe_403_al_actualizar_o_eliminar_una_temporada_activa(): void
    {
        // El rol Tesorero no tiene permisos sobre temporadas
        $tesorero = User::create([
            'name' => 'Tesorero Sin Permisos',
            'email' => 'tesorero_temporadas@liga.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Tesorero',
        ]);
        $tesorero->assignRole('Tesorero');

        $activa = Temporada::factory()->activa()->create(['nombre' => 'Temporada Activa Protegida']);

        $this->actingAs($tesorero)->put(route('temporadas.update', $activa), [
            'nombre' => 'Nombre Alterado',
            'fecha_inicio' => '2030-01-01',
            'fecha_fin' => '2030-06-30',
        ])->assertForbidden();

        $this->actingAs($tesorero)->delete(route('temporadas.destroy', $activa))->assertForbidden();

        // La temporada sigue intacta
        $this->assertDatabaseHas('temporadas', [
            'id' => $activa->id,
            'nombre' => 'Temporada Activa Protegida',
            'estado' => 'activa',
        ]);
    }
}
