<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// Vistas de categorías con sus grupos (S2-T20, HU-007)
class CategoriaVistasTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private Temporada $activa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = $this->crearUsuario('admin_vistas_categorias@liga.com', 'Administrador General');
        $this->activa = Temporada::factory()->activa()->create();
    }

    // El listado agrupa las categorías por rama y muestra sus grupos y el acceso al alta
    public function test_el_listado_muestra_las_categorias_por_rama_con_sus_grupos(): void
    {
        $temporada = Temporada::factory()->activa()->conCategoriasYGrupos()->create();

        $this->actingAs($this->admin)->get(route('temporadas.categorias.index', $temporada))
            ->assertOk()
            ->assertViewIs('categorias.index')
            ->assertSeeInOrder(['Rama Libre', 'Primera Fuerza', 'Grupo A, Grupo B', 'Rama Infantil', 'Juvenil', '2009 – 2010', 'Grupo Único'])
            ->assertSee('18 años')
            ->assertSee(route('temporadas.categorias.create', $temporada), false);
    }

    public function test_el_listado_vacio_lo_indica(): void
    {
        $this->actingAs($this->admin)->get(route('temporadas.categorias.index', $this->activa))
            ->assertOk()
            ->assertSee('Esta temporada no tiene categorías registradas.');
    }

    // CA1: el alta desde el formulario deja la categoría en el listado de su temporada
    public function test_el_alta_desde_el_formulario_vuelve_al_listado_con_la_categoria(): void
    {
        $this->actingAs($this->admin)->get(route('temporadas.categorias.create', $this->activa))
            ->assertOk()
            ->assertSee('Registrar categoría')
            ->assertSee('<option value="Veteranos"', false);

        $this->actingAs($this->admin)
            ->from(route('temporadas.categorias.create', $this->activa))
            ->post(route('temporadas.categorias.store', $this->activa), [
                'nombre' => 'Master 40',
                'rama' => 'Veteranos',
                'anio_nacimiento_desde' => '',
                'anio_nacimiento_hasta' => 1986,
                'edad_min' => 40,
            ])
            ->assertRedirect(route('temporadas.categorias.index', $this->activa));

        $this->actingAs($this->admin)->get(route('temporadas.categorias.index', $this->activa))
            ->assertSee('Categoría registrada correctamente.')
            ->assertSeeInOrder(['Rama Veteranos', 'Master 40', 'Hasta 1986', '40 años', 'Sin grupos']);
    }

    // La edición parte de los datos actuales y la ficha muestra los cambios
    public function test_la_edicion_muestra_los_datos_actuales_y_la_ficha_los_cambios(): void
    {
        $categoria = Categoria::factory()->create([
            'temporada_id' => $this->activa->id,
            'nombre' => 'Reserva',
            'rama' => 'Libre',
            'edad_min' => 17,
        ]);

        $this->actingAs($this->admin)->get(route('categorias.edit', $categoria))
            ->assertOk()
            ->assertSee('value="Reserva"', false)
            ->assertSee('<option value="Libre" selected', false)
            ->assertSee('value="17"', false);

        $this->actingAs($this->admin)
            ->from(route('categorias.edit', $categoria))
            ->put(route('categorias.update', $categoria), [
                'nombre' => 'Reserva Femenil',
                'rama' => 'Femenil',
                'edad_min' => 16,
            ])
            ->assertRedirect(route('categorias.show', $categoria));

        $this->actingAs($this->admin)->get(route('categorias.show', $categoria))
            ->assertOk()
            ->assertViewIs('categorias.show')
            ->assertSee('Categoría actualizada correctamente.')
            ->assertSee('Reserva Femenil')
            ->assertSee('Rama Femenil')
            ->assertSee('16 años')
            ->assertSee('Sin rango registrado');
    }

    // CA2: desde la ficha se agregan varios grupos y cada alta regresa a la ficha
    public function test_desde_la_ficha_se_agregan_varios_grupos(): void
    {
        $categoria = Categoria::factory()->create(['temporada_id' => $this->activa->id, 'rama' => 'Libre']);

        $this->actingAs($this->admin)->get(route('categorias.show', $categoria))
            ->assertOk()
            ->assertSee('Esta categoría no tiene grupos.')
            ->assertSee('action="'.route('categorias.grupos.store', $categoria).'"', false);

        foreach (['Grupo B', 'Grupo A'] as $nombre) {
            $this->actingAs($this->admin)
                ->from(route('categorias.show', $categoria))
                ->post(route('categorias.grupos.store', $categoria), ['nombre' => $nombre])
                ->assertRedirect(route('categorias.show', $categoria));
        }

        $this->actingAs($this->admin)->get(route('categorias.show', $categoria))
            ->assertSee('Grupo registrado correctamente.')
            ->assertSeeInOrder(['Grupo A', 'Grupo B']);
    }

    // Renombrar un grupo en su formulario regresa a la ficha de su categoría
    public function test_renombrar_un_grupo_regresa_a_la_ficha_de_la_categoria(): void
    {
        $temporada = Temporada::factory()->activa()->conCategoriasYGrupos()->create();
        $categoria = $temporada->categorias()->where('nombre', 'Juvenil')->sole();
        $grupo = $categoria->grupos()->sole();

        $this->actingAs($this->admin)->get(route('categorias.show', $categoria))
            ->assertSee(route('grupos.edit', $grupo), false);

        $this->actingAs($this->admin)->get(route('grupos.edit', $grupo))
            ->assertOk()
            ->assertSee('Renombrar grupo')
            ->assertSee('value="Grupo Único"', false);

        $this->actingAs($this->admin)
            ->from(route('grupos.edit', $grupo))
            ->put(route('grupos.update', $grupo), ['nombre' => 'Grupo Juvenil'])
            ->assertRedirect(route('categorias.show', $categoria));

        $this->actingAs($this->admin)->get(route('categorias.show', $categoria))
            ->assertSee('Grupo actualizado correctamente.')
            ->assertSee('Grupo Juvenil')
            ->assertDontSee('Grupo Único');
    }

    // CA4: un dato faltante o inválido vuelve al formulario con el mensaje en español y conserva lo capturado
    public function test_los_errores_se_muestran_en_espanol_en_cada_formulario(): void
    {
        $this->actingAs($this->admin)
            ->from(route('temporadas.categorias.create', $this->activa))
            ->followingRedirects()
            ->post(route('temporadas.categorias.store', $this->activa), [
                'nombre' => '',
                'rama' => 'Infantil',
                'anio_nacimiento_desde' => 2012,
                'anio_nacimiento_hasta' => 2010,
            ])
            ->assertOk()
            ->assertViewIs('categorias.create')
            ->assertSee('No se guardó la categoría. Revisa los campos marcados.')
            ->assertSee('El nombre de la categoría es obligatorio.')
            ->assertSee('El año de nacimiento final no puede ser anterior al inicial.')
            ->assertSee('<option value="Infantil" selected', false)
            ->assertSee('value="2012"', false)
            ->assertSee('aria-invalid="true"', false);

        $categoria = Categoria::factory()->create(['temporada_id' => $this->activa->id, 'rama' => 'Libre']);

        $this->actingAs($this->admin)
            ->from(route('categorias.show', $categoria))
            ->followingRedirects()
            ->post(route('categorias.grupos.store', $categoria), ['nombre' => ''])
            ->assertOk()
            ->assertViewIs('categorias.show')
            ->assertSee('El nombre del grupo es obligatorio.');

        $this->assertSame(1, $this->activa->categorias()->count());
        $this->assertSame(0, $categoria->grupos()->count());
    }

    // CA3: en una temporada cerrada se consultan sin botones de cambio, y los formularios dan 403
    public function test_la_temporada_cerrada_solo_se_consulta(): void
    {
        $cerrada = Temporada::factory()->cerrada()->conCategoriasYGrupos()->create();
        $categoria = $cerrada->categorias()->where('nombre', 'Primera Fuerza')->sole();
        $grupo = $categoria->grupos()->where('nombre', 'Grupo A')->sole();

        $this->actingAs($this->admin)->get(route('temporadas.categorias.index', $cerrada))
            ->assertOk()
            ->assertSee('Temporada cerrada: sus categorías y grupos solo se consultan.')
            ->assertSee('Primera Fuerza')
            ->assertDontSee(route('temporadas.categorias.create', $cerrada), false)
            ->assertDontSee(route('categorias.edit', $categoria), false);

        $this->actingAs($this->admin)->get(route('categorias.show', $categoria))
            ->assertOk()
            ->assertSee('Grupo A')
            ->assertDontSee(route('categorias.edit', $categoria), false)
            ->assertDontSee(route('grupos.edit', $grupo), false)
            ->assertDontSee(route('categorias.grupos.store', $categoria), false);

        $this->actingAs($this->admin)->get(route('temporadas.categorias.create', $cerrada))->assertForbidden();
        $this->actingAs($this->admin)->get(route('categorias.edit', $categoria))->assertForbidden();
        $this->actingAs($this->admin)->get(route('grupos.edit', $grupo))->assertForbidden();
    }

    // Quien solo consulta ve categorías y grupos sin alta ni edición, y esos formularios le dan 403
    public function test_un_rol_de_consulta_no_ve_alta_ni_edicion(): void
    {
        $presidente = $this->crearUsuario('presidente_vistas_categorias@liga.com', 'Presidente');
        $temporada = Temporada::factory()->activa()->conCategoriasYGrupos()->create();
        $categoria = $temporada->categorias()->where('nombre', 'Juvenil')->sole();
        $grupo = $categoria->grupos()->sole();

        $this->actingAs($presidente)->get(route('temporadas.categorias.index', $temporada))
            ->assertOk()
            ->assertSee('Juvenil')
            ->assertDontSee(route('temporadas.categorias.create', $temporada), false)
            ->assertDontSee(route('categorias.edit', $categoria), false);

        $this->actingAs($presidente)->get(route('categorias.show', $categoria))
            ->assertOk()
            ->assertSee('Grupo Único')
            ->assertDontSee(route('grupos.edit', $grupo), false)
            ->assertDontSee(route('categorias.grupos.store', $categoria), false);

        $this->actingAs($presidente)->get(route('temporadas.categorias.create', $temporada))->assertForbidden();
        $this->actingAs($presidente)->get(route('categorias.edit', $categoria))->assertForbidden();
        $this->actingAs($presidente)->get(route('grupos.edit', $grupo))->assertForbidden();
    }

    // Sin permiso para consultar categorías no hay listado ni ficha
    public function test_sin_permiso_no_hay_listado_ni_ficha(): void
    {
        $tesorero = $this->crearUsuario('tesorero_vistas_categorias@liga.com', 'Tesorero');
        $categoria = Categoria::factory()->create(['temporada_id' => $this->activa->id, 'rama' => 'Libre']);

        $this->actingAs($tesorero)->get(route('temporadas.categorias.index', $this->activa))->assertForbidden();
        $this->actingAs($tesorero)->get(route('categorias.show', $categoria))->assertForbidden();
    }

    // El detalle de la temporada enlaza sus categorías solo a quien puede consultarlas
    public function test_la_temporada_enlaza_sus_categorias_segun_el_permiso(): void
    {
        $enlace = 'href="'.route('temporadas.categorias.index', $this->activa).'"';

        $this->actingAs($this->admin)->get(route('temporadas.show', $this->activa))
            ->assertOk()
            ->assertSee($enlace, false);

        // El Árbitro consulta temporadas, pero no categorías
        $arbitro = $this->crearUsuario('arbitro_vistas_categorias@liga.com', 'Arbitro');

        $this->actingAs($arbitro)->get(route('temporadas.show', $this->activa))
            ->assertOk()
            ->assertDontSee($enlace, false);
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
