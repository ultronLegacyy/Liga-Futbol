<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
use App\Models\Categoria;
use App\Models\Grupo;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// HU-008: formato de competencia y criterios de clasificación de cada grupo (RF-02.3); temporada cerrada solo consulta (RF-01.4)
class FormatoGrupoTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private Grupo $grupo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = $this->crearUsuario('admin_formato@liga.com', 'Administrador General');
        $this->grupo = $this->grupoEn(Temporada::factory()->activa()->create());
    }

    // CA1: el formato de competencia queda guardado
    public function test_el_administrador_guarda_el_formato_de_un_grupo(): void
    {
        $this->actingAs($this->admin)
            ->put(route('grupos.configuracion.update', $this->grupo), $this->formulario())
            ->assertRedirect(route('grupos.configuracion', $this->grupo))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Formato y criterios de clasificación guardados.');

        $this->assertSame('todos_contra_todos', $this->grupo->fresh()->formato_competencia);
    }

    // CA2: los criterios quedan guardados y se pueden consultar (los leerán HU-041 y HU-042)
    public function test_los_criterios_se_guardan_y_se_consultan(): void
    {
        $this->actingAs($this->admin)->put(route('grupos.configuracion.update', $this->grupo), $this->formulario());

        $this->assertSame(['clasifican_liguilla' => 8, 'clasifican_consolacion' => 8], $this->grupo->fresh()->criterios_clasificacion);

        $this->actingAs($this->admin)
            ->getJson(route('grupos.configuracion', $this->grupo))
            ->assertOk()
            ->assertJson([
                'formato_competencia' => 'todos_contra_todos',
                'criterios_clasificacion' => ['clasifican_liguilla' => 8, 'clasifican_consolacion' => 8],
            ]);
    }

    // CA3: al modificarlos, la consulta muestra los valores nuevos
    public function test_al_modificar_la_consulta_muestra_los_valores_nuevos(): void
    {
        $this->actingAs($this->admin)->put(route('grupos.configuracion.update', $this->grupo), $this->formulario());

        $this->actingAs($this->admin)->put(route('grupos.configuracion.update', $this->grupo), $this->formulario([
            'clasifican_liguilla' => 4,
            'clasifican_consolacion' => 0,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(['clasifican_liguilla' => 4, 'clasifican_consolacion' => 0], $this->grupo->fresh()->criterios_clasificacion);

        $this->actingAs($this->admin)
            ->get(route('grupos.configuracion', $this->grupo))
            ->assertOk()
            ->assertSeeInOrder(['Todos contra todos', 'Equipos que clasifican a la liguilla', '4']);
    }

    // CA4: en una temporada cerrada no se pueden cambiar, pero sí consultar
    public function test_una_temporada_cerrada_impide_cambios_y_permite_consultar(): void
    {
        $cerrado = $this->grupoEn(Temporada::factory()->cerrada()->create(), [
            'formato_competencia' => 'todos_contra_todos',
            'criterios_clasificacion' => ['clasifican_liguilla' => 8, 'clasifican_consolacion' => 8],
        ]);

        $this->actingAs($this->admin)
            ->put(route('grupos.configuracion.update', $cerrado), $this->formulario(['clasifican_liguilla' => 2]))
            ->assertForbidden();

        $this->assertSame(8, $cerrado->fresh()->criterios_clasificacion['clasifican_liguilla']);

        $this->actingAs($this->admin)
            ->get(route('grupos.configuracion', $cerrado))
            ->assertOk()
            ->assertSee('solo se pueden consultar')
            ->assertDontSee('Guardar configuración');
    }

    public function test_el_formato_debe_ser_del_catalogo_y_los_criterios_validos(): void
    {
        $this->actingAs($this->admin)
            ->put(route('grupos.configuracion.update', $this->grupo), [
                'formato_competencia' => 'eliminacion_directa',
                'criterios_clasificacion' => ['clasifican_liguilla' => -1, 'clasifican_consolacion' => 'ocho'],
            ])
            ->assertSessionHasErrors([
                'formato_competencia',
                'criterios_clasificacion.clasifican_liguilla',
                'criterios_clasificacion.clasifican_consolacion',
            ]);

        $this->assertNull($this->grupo->fresh()->formato_competencia);
    }

    // Puntos y desempates pertenecen a las reglas de la temporada (HU-006): el grupo no los acepta
    public function test_el_grupo_no_acepta_criterios_de_la_temporada(): void
    {
        $this->actingAs($this->admin)
            ->put(route('grupos.configuracion.update', $this->grupo), [
                'formato_competencia' => 'todos_contra_todos',
                'criterios_clasificacion' => ['clasifican_liguilla' => 8, 'clasifican_consolacion' => 8, 'puntos_victoria' => 3],
            ])
            ->assertSessionHasErrors('criterios_clasificacion');

        $this->assertNull($this->grupo->fresh()->criterios_clasificacion);
    }

    public function test_sin_permiso_de_edicion_solo_se_consulta(): void
    {
        $presidente = $this->crearUsuario('presidente_formato@liga.com', 'Presidente');

        $this->actingAs($presidente)->get(route('grupos.configuracion', $this->grupo))->assertOk();
        $this->actingAs($presidente)
            ->put(route('grupos.configuracion.update', $this->grupo), $this->formulario())
            ->assertForbidden();

        $tesorero = $this->crearUsuario('tesorero_formato@liga.com', 'Tesorero');
        $this->actingAs($tesorero)->get(route('grupos.configuracion', $this->grupo))->assertForbidden();
    }

    // El cambio queda en la bitácora con el valor anterior y el nuevo
    public function test_el_cambio_queda_auditado(): void
    {
        $this->actingAs($this->admin)->put(route('grupos.configuracion.update', $this->grupo), $this->formulario());

        $registro = AuditoriaLog::where('tabla_afectada', 'grupos')
            ->where('registro_id', $this->grupo->id)
            ->where('accion', 'actualizar')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($this->admin->id, $registro->usuario_id);
        $this->assertSame('todos_contra_todos', $registro->valores_nuevos['formato_competencia']);
    }

    /**
     * @param array<string, mixed> $atributos
     */
    private function grupoEn(Temporada $temporada, array $atributos = []): Grupo
    {
        $categoria = Categoria::factory()->create(['temporada_id' => $temporada->id]);

        return Grupo::factory()->create(['categoria_id' => $categoria->id] + $atributos);
    }

    /**
     * @param array<string, int> $criterios
     * @return array<string, mixed>
     */
    private function formulario(array $criterios = []): array
    {
        return [
            'formato_competencia' => 'todos_contra_todos',
            'criterios_clasificacion' => $criterios + ['clasifican_liguilla' => 8, 'clasifican_consolacion' => 8],
        ];
    }

    private function crearUsuario(string $email, string $rol): User
    {
        $usuario = User::create([
            'name' => 'Usuario '.$rol,
            'email' => $email,
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => $rol,
        ]);

        $usuario->assignRole($rol);

        return $usuario;
    }
}
