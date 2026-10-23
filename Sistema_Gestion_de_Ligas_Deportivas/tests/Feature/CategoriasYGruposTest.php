<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
use App\Models\Categoria;
use App\Models\Grupo;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\TemporadaCerradaDePruebaSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// HU-007: categorías y grupos de cada temporada (RF-02.1, RF-02.2) y bloqueo en temporada cerrada (RF-01.4)
class CategoriasYGruposTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private Temporada $activa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = $this->crearUsuario('admin_categorias@liga.com', 'Administrador General');
        $this->activa = Temporada::factory()->activa()->create();
    }

    // CA1: crear una categoría no requiere modificar el código
    public function test_el_administrador_agrega_una_categoria_a_la_temporada(): void
    {
        $respuesta = $this->actingAs($this->admin)->post(route('temporadas.categorias.store', $this->activa), [
            'nombre' => 'Juvenil',
            'rama' => 'Libre',
            'anio_nacimiento_desde' => 2009,
            'anio_nacimiento_hasta' => 2010,
        ]);

        $respuesta->assertRedirect();
        $respuesta->assertSessionHasNoErrors();
        $respuesta->assertSessionHas('success', 'Categoría registrada correctamente.');

        $this->assertDatabaseHas('categorias', [
            'temporada_id' => $this->activa->id,
            'nombre' => 'Juvenil',
            'rama' => 'Libre',
            'anio_nacimiento_desde' => 2009,
            'anio_nacimiento_hasta' => 2010,
            'edad_min' => null,
        ]);
    }

    // CA1: se pueden agregar las categorías que la Liga necesite, también con solo una edad mínima
    public function test_se_agregan_mas_categorias_a_una_temporada_que_ya_tiene(): void
    {
        Categoria::factory()->create(['temporada_id' => $this->activa->id, 'nombre' => 'Primera Fuerza', 'rama' => 'Libre']);

        $this->actingAs($this->admin)->post(route('temporadas.categorias.store', $this->activa), [
            'nombre' => 'Reserva',
            'rama' => 'Libre',
            'edad_min' => 18,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $this->activa->categorias()->count());
        $this->assertDatabaseHas('categorias', ['nombre' => 'Reserva', 'edad_min' => 18, 'anio_nacimiento_desde' => null]);
    }

    // CA1: renombrar una categoría y cambiar sus datos
    public function test_el_administrador_renombra_una_categoria(): void
    {
        $categoria = Categoria::factory()->create([
            'temporada_id' => $this->activa->id,
            'nombre' => 'Primera Fuerza',
            'rama' => 'Libre',
        ]);

        $respuesta = $this->actingAs($this->admin)->put(route('categorias.update', $categoria), [
            'nombre' => 'Primera Fuerza A',
            'rama' => 'Libre',
            'edad_min' => 18,
        ]);

        $respuesta->assertSessionHasNoErrors();
        $respuesta->assertSessionHas('success', 'Categoría actualizada correctamente.');

        $this->assertDatabaseHas('categorias', [
            'id' => $categoria->id,
            'nombre' => 'Primera Fuerza A',
            'edad_min' => 18,
        ]);
    }

    // Guardar sin cambiar el nombre no choca con la propia categoría
    public function test_guardar_una_categoria_sin_cambiar_su_nombre_no_marca_duplicado(): void
    {
        $categoria = Categoria::factory()->create([
            'temporada_id' => $this->activa->id,
            'nombre' => 'Veteranos Libre',
            'rama' => 'Veteranos',
        ]);

        $this->actingAs($this->admin)->put(route('categorias.update', $categoria), [
            'nombre' => 'Veteranos Libre',
            'rama' => 'Veteranos',
            'anio_nacimiento_hasta' => 1990,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categorias', ['id' => $categoria->id, 'anio_nacimiento_hasta' => 1990]);
    }

    // El nombre se repite solo en otra rama o en otra temporada
    public function test_el_nombre_es_unico_dentro_de_la_misma_temporada_y_rama(): void
    {
        Categoria::factory()->create(['temporada_id' => $this->activa->id, 'nombre' => 'Juvenil', 'rama' => 'Libre']);

        $this->actingAs($this->admin)->post(route('temporadas.categorias.store', $this->activa), [
            'nombre' => 'Juvenil',
            'rama' => 'Libre',
        ])->assertSessionHasErrors(['nombre' => 'Ya existe una categoría con ese nombre en la misma rama de esta temporada.']);

        $this->actingAs($this->admin)->post(route('temporadas.categorias.store', $this->activa), [
            'nombre' => 'Juvenil',
            'rama' => 'Infantil',
        ])->assertSessionHasNoErrors();

        $otra = Temporada::factory()->activa()->create();
        $this->actingAs($this->admin)->post(route('temporadas.categorias.store', $otra), [
            'nombre' => 'Juvenil',
            'rama' => 'Libre',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $this->activa->categorias()->where('nombre', 'Juvenil')->count());
        $this->assertSame(1, $otra->categorias()->count());
    }

    // Renombrar a un nombre que ya usa otra categoría de la misma rama se rechaza
    public function test_renombrar_a_un_nombre_ocupado_se_rechaza(): void
    {
        Categoria::factory()->create(['temporada_id' => $this->activa->id, 'nombre' => 'Segunda', 'rama' => 'Libre']);
        $tercera = Categoria::factory()->create(['temporada_id' => $this->activa->id, 'nombre' => 'Tercera', 'rama' => 'Libre']);

        $this->actingAs($this->admin)->put(route('categorias.update', $tercera), [
            'nombre' => 'Segunda',
            'rama' => 'Libre',
        ])->assertSessionHasErrors('nombre');

        $this->assertDatabaseHas('categorias', ['id' => $tercera->id, 'nombre' => 'Tercera']);
    }

    // CA2: a una categoría de la temporada se le agregan uno o varios grupos
    public function test_a_una_categoria_se_le_agregan_varios_grupos(): void
    {
        $categoria = Categoria::factory()->create(['temporada_id' => $this->activa->id, 'rama' => 'Libre']);

        foreach (['Grupo A', 'Grupo B'] as $nombre) {
            $this->actingAs($this->admin)->post(route('categorias.grupos.store', $categoria), [
                'nombre' => $nombre,
            ])->assertSessionHasNoErrors()->assertSessionHas('success', 'Grupo registrado correctamente.');
        }

        $this->assertSame(['Grupo A', 'Grupo B'], $categoria->grupos()->orderBy('nombre')->pluck('nombre')->all());
        $this->assertSame(2, Grupo::where('categoria_id', $categoria->id)->where('temporada_id', $this->activa->id)->count());
    }

    // CA2: el grupo toma la temporada de su categoría, aunque la petición envíe otra
    public function test_el_grupo_pertenece_a_la_temporada_de_su_categoria(): void
    {
        $categoria = Categoria::factory()->create(['temporada_id' => $this->activa->id]);
        $otra = Temporada::factory()->activa()->create();

        $this->actingAs($this->admin)->post(route('categorias.grupos.store', $categoria), [
            'nombre' => 'Grupo Único',
            'temporada_id' => $otra->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('grupos', [
            'categoria_id' => $categoria->id,
            'temporada_id' => $this->activa->id,
            'nombre' => 'Grupo Único',
        ]);
    }

    // CA2: se renombra un grupo y su nombre no se repite dentro de la categoría
    public function test_se_renombra_un_grupo_sin_repetir_nombre_en_la_categoria(): void
    {
        $categoria = Categoria::factory()->create(['temporada_id' => $this->activa->id]);
        $grupoA = Grupo::factory()->create(['categoria_id' => $categoria->id, 'nombre' => 'Grupo A']);
        Grupo::factory()->create(['categoria_id' => $categoria->id, 'nombre' => 'Grupo B']);

        $this->actingAs($this->admin)->put(route('grupos.update', $grupoA), ['nombre' => 'Grupo Norte'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Grupo actualizado correctamente.');

        $this->actingAs($this->admin)->put(route('grupos.update', $grupoA), ['nombre' => 'Grupo B'])
            ->assertSessionHasErrors(['nombre' => 'Ya existe un grupo con ese nombre en esta categoría.']);

        $this->assertDatabaseHas('grupos', ['id' => $grupoA->id, 'nombre' => 'Grupo Norte']);
    }

    // CA2: la consulta muestra las categorías de la temporada con sus grupos
    public function test_la_consulta_muestra_las_categorias_con_sus_grupos(): void
    {
        $categoria = Categoria::factory()->create(['temporada_id' => $this->activa->id, 'nombre' => 'Primera Fuerza', 'rama' => 'Libre']);
        Grupo::factory()->create(['categoria_id' => $categoria->id, 'nombre' => 'Grupo A']);
        Categoria::factory()->create(['nombre' => 'De otra temporada']);

        $this->actingAs($this->admin)->getJson(route('temporadas.categorias.index', $this->activa))
            ->assertOk()
            ->assertJsonPath('temporada_cerrada', false)
            ->assertJsonCount(1, 'categorias')
            ->assertJsonPath('categorias.0.nombre', 'Primera Fuerza')
            ->assertJsonPath('categorias.0.grupos.0.nombre', 'Grupo A');

        $this->actingAs($this->admin)->getJson(route('categorias.show', $categoria))
            ->assertOk()
            ->assertJsonPath('rama', 'Libre')
            ->assertJsonPath('grupos.0.nombre', 'Grupo A');
    }

    // CA3: en una temporada cerrada no se agregan ni cambian categorías, ni siquiera el Administrador
    public function test_en_una_temporada_cerrada_no_se_agregan_ni_cambian_categorias(): void
    {
        $cerrada = Temporada::factory()->cerrada()->conCategoriasYGrupos()->create();
        $categoria = $cerrada->categorias()->firstOrFail();

        $this->actingAs($this->admin)->post(route('temporadas.categorias.store', $cerrada), [
            'nombre' => 'Categoría nueva',
            'rama' => 'Libre',
        ])->assertForbidden()->assertSee('Una temporada cerrada no admite cambios en sus categorías.');

        $this->actingAs($this->admin)->put(route('categorias.update', $categoria), [
            'nombre' => 'Nombre cambiado',
            'rama' => $categoria->rama,
        ])->assertForbidden()->assertSee('Una temporada cerrada no admite cambios en sus categorías.');

        $this->assertSame(2, $cerrada->categorias()->count());
        $this->assertDatabaseHas('categorias', ['id' => $categoria->id, 'nombre' => $categoria->nombre]);
    }

    // CA3: en una temporada cerrada no se agregan ni cambian grupos
    public function test_en_una_temporada_cerrada_no_se_agregan_ni_cambian_grupos(): void
    {
        $cerrada = Temporada::factory()->cerrada()->conCategoriasYGrupos()->create();
        $categoria = $cerrada->categorias()->firstOrFail();
        $grupo = $categoria->grupos()->firstOrFail();
        $gruposAntes = $cerrada->grupos()->count();

        $this->actingAs($this->admin)->post(route('categorias.grupos.store', $categoria), ['nombre' => 'Grupo nuevo'])
            ->assertForbidden()
            ->assertSee('Una temporada cerrada no admite cambios en sus grupos.');

        $this->actingAs($this->admin)->put(route('grupos.update', $grupo), ['nombre' => 'Grupo cambiado'])
            ->assertForbidden()
            ->assertSee('Una temporada cerrada no admite cambios en sus grupos.');

        $this->assertSame($gruposAntes, $cerrada->grupos()->count());
        $this->assertDatabaseHas('grupos', ['id' => $grupo->id, 'nombre' => $grupo->nombre]);
    }

    // CA3: las categorías y los grupos de una temporada cerrada sí se consultan
    public function test_las_categorias_de_una_temporada_cerrada_se_consultan(): void
    {
        $cerrada = Temporada::factory()->cerrada()->conCategoriasYGrupos()->create();

        $this->actingAs($this->admin)->getJson(route('temporadas.categorias.index', $cerrada))
            ->assertOk()
            ->assertJsonPath('temporada_cerrada', true)
            ->assertJsonCount(2, 'categorias');

        $this->actingAs($this->admin)->getJson(route('categorias.show', $cerrada->categorias()->firstOrFail()))
            ->assertOk();
    }

    // CA4: sin nombre ni rama no se guarda y los mensajes están en español
    public function test_sin_datos_obligatorios_no_se_guarda_y_los_mensajes_estan_en_espanol(): void
    {
        $this->actingAs($this->admin)->post(route('temporadas.categorias.store', $this->activa), [
            'nombre' => '',
            'rama' => '',
        ])->assertSessionHasErrors([
            'nombre' => 'El nombre de la categoría es obligatorio.',
            'rama' => 'La rama es obligatoria.',
        ]);

        $categoria = Categoria::factory()->create(['temporada_id' => $this->activa->id]);
        $this->actingAs($this->admin)->post(route('categorias.grupos.store', $categoria), ['nombre' => ''])
            ->assertSessionHasErrors(['nombre' => 'El nombre del grupo es obligatorio.']);

        $this->assertSame(1, $this->activa->categorias()->count());
        $this->assertSame(0, $categoria->grupos()->count());
    }

    // CA4: rama, años y edad inválidos se rechazan con mensajes claros, sin error 500
    public function test_datos_invalidos_de_la_categoria_se_rechazan_con_mensajes_claros(): void
    {
        $ruta = route('temporadas.categorias.store', $this->activa);
        $casos = [
            [['rama' => 'Mixta'], 'rama', 'La rama debe ser Libre, Veteranos, Femenil o Infantil.'],
            [['rama' => ['Libre']], 'rama', 'La rama debe ser Libre, Veteranos, Femenil o Infantil.'],
            [['nombre' => ['Juvenil']], 'nombre', 'El nombre de la categoría debe ser texto.'],
            [['anio_nacimiento_desde' => 'dos mil'], 'anio_nacimiento_desde', 'El año de nacimiento inicial debe ser un año válido.'],
            [['anio_nacimiento_desde' => 1850], 'anio_nacimiento_desde', 'El año de nacimiento inicial no puede ser anterior a 1900.'],
            [['anio_nacimiento_hasta' => now()->year + 1], 'anio_nacimiento_hasta', 'El año de nacimiento final no puede ser posterior a '.now()->year.'.'],
            [['anio_nacimiento_desde' => 2010, 'anio_nacimiento_hasta' => 2009], 'anio_nacimiento_hasta', 'El año de nacimiento final no puede ser anterior al inicial.'],
            [['edad_min' => -1], 'edad_min', 'La edad mínima no puede ser negativa.'],
            [['edad_min' => '18 años'], 'edad_min', 'La edad mínima debe ser un número entero.'],
        ];

        foreach ($casos as [$cambio, $campo, $mensaje]) {
            $this->actingAs($this->admin)
                ->post($ruta, $cambio + ['nombre' => 'Categoría inválida', 'rama' => 'Libre'])
                ->assertSessionHasErrors([$campo => $mensaje]);
        }

        $this->assertSame(0, $this->activa->categorias()->count());
    }

    // Otro rol recibe 403 al agregar o cambiar; el Presidente del Club solo consulta
    public function test_otro_rol_recibe_403_al_agregar_o_cambiar_categorias_y_grupos(): void
    {
        $directivo = $this->crearUsuario('directivo_categorias@liga.com', 'Presidente del Club');
        $categoria = Categoria::factory()->create(['temporada_id' => $this->activa->id, 'nombre' => 'Original']);
        $grupo = Grupo::factory()->create(['categoria_id' => $categoria->id, 'nombre' => 'Grupo Original']);

        $this->actingAs($directivo)->getJson(route('temporadas.categorias.index', $this->activa))->assertOk();

        $this->actingAs($directivo)->post(route('temporadas.categorias.store', $this->activa), [
            'nombre' => 'No autorizada',
            'rama' => 'Libre',
        ])->assertForbidden()->assertSee('No cuenta con privilegios para registrar categorías.');

        $this->actingAs($directivo)->put(route('categorias.update', $categoria), [
            'nombre' => 'Alterada',
            'rama' => 'Libre',
        ])->assertForbidden();

        $this->actingAs($directivo)->post(route('categorias.grupos.store', $categoria), ['nombre' => 'Grupo intruso'])
            ->assertForbidden()
            ->assertSee('No cuenta con privilegios para administrar grupos.');

        $this->actingAs($directivo)->put(route('grupos.update', $grupo), ['nombre' => 'Grupo alterado'])->assertForbidden();

        $this->assertDatabaseHas('categorias', ['id' => $categoria->id, 'nombre' => 'Original']);
        $this->assertDatabaseHas('grupos', ['id' => $grupo->id, 'nombre' => 'Grupo Original']);
        $this->assertSame(1, $this->activa->categorias()->count());
    }

    // Consultar requiere permiso: el Tesorero no tiene categorias.ver
    public function test_consultar_categorias_requiere_permiso(): void
    {
        $tesorero = $this->crearUsuario('tesorero_categorias@liga.com', 'Tesorero');

        $this->actingAs($tesorero)->getJson(route('temporadas.categorias.index', $this->activa))
            ->assertForbidden()
            ->assertJsonPath('mensaje', 'No cuenta con privilegios para consultar las categorías.');
    }

    // Sin sesión se redirige al inicio de sesión
    public function test_sin_sesion_se_redirige_al_inicio_de_sesion(): void
    {
        $this->get(route('temporadas.categorias.index', $this->activa))->assertRedirect(route('login'));
        $this->post(route('temporadas.categorias.store', $this->activa), ['nombre' => 'Anónima', 'rama' => 'Libre'])
            ->assertRedirect(route('login'));

        $this->assertSame(0, $this->activa->categorias()->count());
    }

    // El alta, el renombrado y los grupos quedan en la auditoría con el usuario
    public function test_altas_y_renombrados_quedan_en_la_auditoria(): void
    {
        $this->actingAs($this->admin)->post(route('temporadas.categorias.store', $this->activa), [
            'nombre' => 'Cuarta',
            'rama' => 'Libre',
        ])->assertSessionHasNoErrors();

        $categoria = $this->activa->categorias()->where('nombre', 'Cuarta')->sole();

        $this->actingAs($this->admin)->put(route('categorias.update', $categoria), [
            'nombre' => 'Cuarta Fuerza',
            'rama' => 'Libre',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post(route('categorias.grupos.store', $categoria), ['nombre' => 'Grupo A'])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->admin->id, $this->logsDe($categoria, 'crear')->sole()->usuario_id);

        $renombrado = $this->logsDe($categoria, 'actualizar')->sole();
        $this->assertSame(['nombre' => 'Cuarta'], $renombrado->valores_anteriores);
        $this->assertSame(['nombre' => 'Cuarta Fuerza'], $renombrado->valores_nuevos);
        $this->assertSame($this->admin->id, $renombrado->usuario_id);

        $grupo = $categoria->grupos()->sole();
        $this->assertSame($this->admin->id, $this->logsDe($grupo, 'crear')->sole()->usuario_id);
    }

    // S2-T19: dato de prueba de temporada cerrada con categorías y grupos (lo usan HU-006 y HU-008)
    public function test_la_temporada_cerrada_de_prueba_trae_categorias_y_grupos(): void
    {
        $cerrada = Temporada::factory()->cerrada()->conCategoriasYGrupos()->create();

        $this->assertSame('cerrada', $cerrada->estado);
        $this->assertSame(['Juvenil', 'Primera Fuerza'], $cerrada->categorias()->orderBy('nombre')->pluck('nombre')->all());
        $this->assertSame(3, $cerrada->grupos()->count());
        $this->assertSame(0, Grupo::whereIn('categoria_id', $cerrada->categorias()->pluck('id'))
            ->where('temporada_id', '!=', $cerrada->id)
            ->count());
    }

    // S2-T19: el seeder de la demostración crea la temporada cerrada una sola vez
    public function test_el_seeder_de_la_temporada_cerrada_no_la_duplica(): void
    {
        $this->seed(TemporadaCerradaDePruebaSeeder::class);
        $this->seed(TemporadaCerradaDePruebaSeeder::class);

        $temporada = Temporada::where('nombre', TemporadaCerradaDePruebaSeeder::NOMBRE)->sole();

        $this->assertSame('cerrada', $temporada->estado);
        $this->assertSame(2, $temporada->categorias()->count());
    }

    // Borrar una temporada con categorías lo impide la base, en lugar de borrarlas en cascada
    public function test_la_base_impide_borrar_una_temporada_con_categorias(): void
    {
        $temporada = Temporada::factory()->activa()->conCategoriasYGrupos()->create();

        try {
            $temporada->delete();
            $this->fail('La base debió impedir borrar una temporada con categorías.');
        } catch (QueryException) {
            // Esperado: la llave foránea de categorias.temporada_id restringe el borrado
        }

        $this->assertDatabaseHas('temporadas', ['id' => $temporada->id]);
        $this->assertSame(2, Categoria::where('temporada_id', $temporada->id)->count());
        $this->assertSame(3, Grupo::where('temporada_id', $temporada->id)->count());
    }

    private function crearUsuario(string $email, string $rol): User
    {
        $usuario = User::create([
            'name' => 'Usuario '.$rol,
            'email' => $email,
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            // La columna rol usa «Directivo Club»; el rol de Spatie se llama «Presidente del Club»
            'rol' => $rol === 'Presidente del Club' ? 'Directivo Club' : $rol,
        ]);

        $usuario->assignRole($rol);

        return $usuario;
    }

    /**
     * @return Builder<AuditoriaLog>
     */
    private function logsDe(Model $modelo, string $accion): Builder
    {
        return AuditoriaLog::query()
            ->where('tabla_afectada', $modelo->getTable())
            ->where('registro_id', $modelo->getKey())
            ->where('accion', $accion);
    }
}
