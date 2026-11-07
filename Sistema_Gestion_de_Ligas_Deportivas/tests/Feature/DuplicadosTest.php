<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Club;
use App\Models\Grupo;
use App\Models\Persona;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// HU-014: alertas de posibles duplicados de personas y de equipos (RF-03.3)
class DuplicadosTest extends TestCase
{
    use DatabaseTransactions;

    private User $secretario;

    private User $administrador;

    private Temporada $temporada;

    private Categoria $categoria;

    private Grupo $grupo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->secretario = $this->crearUsuario('secretario_duplicados@liga.com', 'Secretario');
        $this->administrador = $this->crearUsuario('admin_duplicados@liga.com', 'Administrador General');
        $this->temporada = Temporada::factory()->activa()->create();
        $this->categoria = Categoria::factory()->create(['temporada_id' => $this->temporada->id]);
        $this->grupo = Grupo::factory()->create(['categoria_id' => $this->categoria->id, 'temporada_id' => $this->temporada->id]);

        Persona::factory()->create([
            'nombre' => 'José',
            'apellidos' => 'Pérez Ficticio',
            'fecha_nacimiento' => '1995-05-10',
            'curp' => 'PEFJ950510HMNRCS01',
        ]);
    }

    // CA1: otra CURP con el mismo nombre completo y fecha, aunque cambien acentos y mayúsculas, alerta antes de guardar
    public function test_mismo_nombre_y_fecha_alerta_antes_de_registrar(): void
    {
        $this->actingAs($this->secretario)
            ->post(route('personas.store'), $this->persona(['nombre' => 'JOSE', 'apellidos' => 'perez  ficticio', 'curp' => 'XXXX950510HMNRCS09']))
            ->assertRedirect(route('personas.create'))
            ->assertSessionHas('posibles_duplicados', fn (array $similares) => $similares[0]['motivo'] === 'Tiene el mismo nombre completo y la misma fecha de nacimiento.');

        $this->assertDatabaseMissing('personas', ['curp' => 'XXXX950510HMNRCS09']);
    }

    // CA1: una CURP que solo cambia la homoclave también alerta
    public function test_curp_casi_igual_alerta(): void
    {
        $this->actingAs($this->secretario)
            ->post(route('personas.store'), $this->persona(['nombre' => 'Otro', 'apellidos' => 'Nombre Distinto', 'curp' => 'PEFJ950510HMNRCS77']))
            ->assertRedirect(route('personas.create'))
            ->assertSessionHas('posibles_duplicados', fn (array $similares) => $similares[0]['motivo'] === 'Su CURP solo difiere en los dos últimos caracteres.');
    }

    // CA1: la alerta se muestra en el formulario con la persona parecida, sin exponer su CURP
    public function test_el_formulario_muestra_la_alerta(): void
    {
        $this->actingAs($this->secretario)
            ->followingRedirects()
            ->post(route('personas.store'), $this->persona(['curp' => 'XXXX950510HMNRCS09']))
            ->assertOk()
            ->assertSee('Posible duplicado: la persona no se ha registrado todavía.')
            ->assertSee('Pérez Ficticio, José')
            ->assertSee('Confirmar registro')
            ->assertDontSee('PEFJ950510HMNRCS01');
    }

    // CA1: al confirmar los mismos datos se registra
    public function test_al_confirmar_se_registra(): void
    {
        $datos = $this->persona(['curp' => 'XXXX950510HMNRCS09']);
        $this->actingAs($this->secretario)->post(route('personas.store'), $datos);
        $huella = session('huella_duplicado');

        $this->actingAs($this->secretario)
            ->post(route('personas.store'), $datos + ['confirmar_duplicado' => $huella])
            ->assertSessionHas('success', 'Persona registrada con su rol.');

        $this->assertDatabaseHas('personas', ['curp' => 'XXXX950510HMNRCS09']);
    }

    // La confirmación no sirve para otros datos: si cambian, se vuelve a revisar
    public function test_una_confirmacion_de_otros_datos_no_registra(): void
    {
        $this->actingAs($this->secretario)->post(route('personas.store'), $this->persona(['curp' => 'XXXX950510HMNRCS09']));
        $huella = session('huella_duplicado');

        $this->actingAs($this->secretario)
            ->post(route('personas.store'), $this->persona(['curp' => 'YYYY950510HMNRCS09', 'confirmar_duplicado' => $huella]))
            ->assertRedirect(route('personas.create'))
            ->assertSessionHas('posibles_duplicados');

        $this->assertDatabaseMissing('personas', ['curp' => 'YYYY950510HMNRCS09']);
    }

    // Misma CURP con otro nombre: alerta y, al confirmar, reutiliza la identidad sin cambiar sus datos (HU-013)
    public function test_misma_curp_con_otros_datos_alerta_y_no_cambia_la_identidad(): void
    {
        $datos = $this->persona(['nombre' => 'Juan', 'curp' => 'pefj950510hmnrcs01']);

        $this->actingAs($this->secretario)
            ->post(route('personas.store'), $datos)
            ->assertSessionHas('posibles_duplicados', fn (array $similares) => $similares[0]['motivo'] === 'Ya existe esta CURP con otro nombre o fecha de nacimiento.');

        $this->actingAs($this->secretario)
            ->post(route('personas.store'), $datos + ['confirmar_duplicado' => session('huella_duplicado')])
            ->assertSessionHas('success', 'La persona ya estaba registrada: se reutilizó su identidad sin cambiar sus datos y se le asignó el rol.');

        $this->assertSame('José', Persona::where('curp', 'PEFJ950510HMNRCS01')->sole()->nombre);
    }

    // Sin parecidos no hay alerta, y la misma CURP con los mismos datos se reutiliza sin preguntar (HU-013)
    public function test_sin_parecidos_ni_cambios_no_alerta(): void
    {
        $this->actingAs($this->secretario)
            ->post(route('personas.store'), $this->persona(['nombre' => 'Ana', 'apellidos' => 'Sin Parecido', 'fecha_nacimiento' => '2001-02-03', 'curp' => 'SIPA010203MMNNRN05']))
            ->assertSessionHas('success', 'Persona registrada con su rol.');

        $this->actingAs($this->secretario)
            ->post(route('personas.store'), $this->persona(['rol' => 'delegado']))
            ->assertSessionHas('success', 'La persona ya estaba registrada: se reutilizó su identidad y se le asignó el rol.');
    }

    // CA2: el mismo nombre en la misma categoría, aunque cambien mayúsculas, acentos o espacios, alerta y no registra
    public function test_equipo_con_el_mismo_nombre_alerta(): void
    {
        $this->equipo('Tigres de Morelia');

        $this->actingAs($this->administrador)
            ->postJson(route('equipos.store'), $this->equipoNuevo('TIGRES  de Morelía'))
            ->assertStatus(409)
            ->assertJsonPath('requiere_confirmacion', true)
            ->assertJsonPath('posibles_duplicados.0.nombre', 'Tigres de Morelia')
            ->assertJsonPath('posibles_duplicados.0.motivo', 'Tiene el mismo nombre.');

        $this->assertDatabaseMissing('equipos', ['nombre' => 'TIGRES  de Morelía']);
    }

    // Acuerdo del equipo: solo el mismo nombre alerta; uno parecido se registra sin aviso
    public function test_equipo_con_nombre_solo_parecido_no_alerta(): void
    {
        $this->equipo('Tigres de Morelia');

        $this->actingAs($this->administrador)
            ->postJson(route('equipos.store'), $this->equipoNuevo('Tigre de Morelia'))
            ->assertCreated();
    }

    // CA2: al confirmar se registra
    public function test_equipo_confirmado_se_registra(): void
    {
        $this->equipo('Tigres de Morelia');

        $this->actingAs($this->administrador)
            ->postJson(route('equipos.store'), $this->equipoNuevo('tigres de morelia') + ['confirmar_duplicado' => true])
            ->assertCreated();
    }

    // Sin alertas de más: otra categoría, otro nombre o solo distinto número («Sub 17» y «Sub 18»)
    public function test_equipo_sin_parecido_real_no_alerta(): void
    {
        $this->equipo('Sub 17');
        $otraCategoria = Categoria::factory()->create(['temporada_id' => $this->temporada->id]);
        $otroGrupo = Grupo::factory()->create(['categoria_id' => $otraCategoria->id, 'temporada_id' => $this->temporada->id]);
        Club::create(['nombre' => 'Club ajeno'])->equipos()->create([
            'temporada_id' => $this->temporada->id,
            'grupo_id' => $otroGrupo->id,
            'nombre' => 'Pumas',
        ]);

        foreach (['Sub 18', 'Pumas', 'Leones'] as $nombre) {
            $this->actingAs($this->administrador)
                ->postJson(route('equipos.store'), $this->equipoNuevo($nombre))
                ->assertCreated();
        }
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function persona(array $cambios = []): array
    {
        return $cambios + [
            'nombre' => 'José',
            'apellidos' => 'Pérez Ficticio',
            'fecha_nacimiento' => '1995-05-10',
            'curp' => 'PEFJ950510HMNRCS01',
            'rol' => 'jugador',
            'temporada_id' => $this->temporada->id,
        ];
    }

    private function equipo(string $nombre): void
    {
        Club::create(['nombre' => 'Club existente '.$nombre])->equipos()->create([
            'temporada_id' => $this->temporada->id,
            'grupo_id' => $this->grupo->id,
            'nombre' => $nombre,
        ]);
    }

    /** @return array<string, mixed> */
    private function equipoNuevo(string $nombre): array
    {
        return [
            'club_id' => Club::create(['nombre' => 'Club nuevo '.$nombre])->id,
            'temporada_id' => $this->temporada->id,
            'categoria_id' => $this->categoria->id,
            'grupo_id' => $this->grupo->id,
            'nombre' => $nombre,
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
