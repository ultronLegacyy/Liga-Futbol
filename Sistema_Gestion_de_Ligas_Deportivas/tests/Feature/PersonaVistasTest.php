<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\PersonaRol;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// HU-013: pantallas del padrón de personas con sus roles por temporada (RF-03.2)
class PersonaVistasTest extends TestCase
{
    use DatabaseTransactions;

    private User $secretario;

    private Temporada $activa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->secretario = $this->crearUsuario('secretario_personas@liga.com', 'Secretario');
        $this->activa = Temporada::factory()->activa()->create(['nombre' => 'Temporada 2026-2027 de prueba']);
    }

    public function test_el_secretario_consulta_el_padron(): void
    {
        PersonaRol::factory()->create([
            'persona_id' => Persona::factory()->create(['nombre' => 'Luisa', 'apellidos' => 'Ficticia Uno']),
            'rol' => 'delegado',
            'temporada_id' => $this->activa->id,
        ]);

        $this->actingAs($this->secretario)->get(route('personas.index'))
            ->assertOk()
            ->assertSee('Ficticia Uno, Luisa')
            ->assertSee('Delegado · Temporada 2026-2027 de prueba');
    }

    public function test_un_rol_sin_permiso_no_consulta_el_padron(): void
    {
        $tesorero = $this->crearUsuario('tesorero_personas@liga.com', 'Tesorero');

        $this->actingAs($tesorero)->get(route('personas.index'))->assertForbidden();
    }

    // CA1: el alta crea la persona con su rol
    public function test_el_secretario_registra_una_persona_con_su_rol(): void
    {
        $respuesta = $this->actingAs($this->secretario)->post(route('personas.store'), $this->formulario());

        $persona = Persona::where('curp', 'FIPA900101HMNCRL01')->firstOrFail();
        $respuesta->assertRedirect(route('personas.show', $persona));
        $respuesta->assertSessionHas('success', 'Persona registrada con su rol.');
        $this->assertDatabaseHas('persona_roles', ['persona_id' => $persona->id, 'rol' => 'jugador', 'temporada_id' => $this->activa->id]);
    }

    // CA1: con una CURP ya registrada se reutiliza la identidad
    public function test_registrar_una_curp_existente_reutiliza_la_identidad(): void
    {
        $this->actingAs($this->secretario)->post(route('personas.store'), $this->formulario());

        $respuesta = $this->actingAs($this->secretario)->post(route('personas.store'), $this->formulario([
            'curp' => 'fipa900101hmncrl01',
            'rol' => 'delegado',
        ]));

        $respuesta->assertSessionHas('success', 'La persona ya estaba registrada: se reutilizó su identidad y se le asignó el rol.');
        $persona = Persona::where('curp', 'FIPA900101HMNCRL01')->sole();
        $this->assertSame(['jugador', 'delegado'], $persona->roles()->orderBy('rol')->pluck('rol')->all());
    }

    // CA1 y CA2: se asigna otro rol desde la ficha y la ficha distingue cada rol y su temporada
    public function test_la_ficha_asigna_otro_rol_y_muestra_los_roles_por_temporada(): void
    {
        $anterior = Temporada::factory()->cerrada()->create(['nombre' => 'Temporada 2025-2026 de prueba']);
        $persona = Persona::factory()->create();
        PersonaRol::factory()->create(['persona_id' => $persona->id, 'rol' => 'jugador', 'temporada_id' => $anterior->id]);

        $this->actingAs($this->secretario)
            ->post(route('personas.roles.store', $persona), ['rol' => 'arbitro', 'temporada_id' => $this->activa->id])
            ->assertRedirect(route('personas.show', $persona))
            ->assertSessionHas('success', 'Rol asignado correctamente.');

        $this->assertSame(1, Persona::whereKey($persona->id)->count());

        $this->actingAs($this->secretario)->get(route('personas.show', $persona))
            ->assertOk()
            ->assertSeeInOrder(['Temporada 2025-2026 de prueba', 'Cerrada · solo consulta', 'Jugador'])
            ->assertSeeInOrder(['Temporada 2026-2027 de prueba', 'Árbitro']);
    }

    // RF-01.4: una temporada cerrada no admite roles nuevos
    public function test_no_se_asignan_roles_en_una_temporada_cerrada(): void
    {
        $cerrada = Temporada::factory()->cerrada()->create();

        $this->actingAs($this->secretario)
            ->from(route('personas.create'))
            ->post(route('personas.store'), $this->formulario(['temporada_id' => $cerrada->id]))
            ->assertRedirect(route('personas.create'))
            ->assertSessionHasErrors('temporada_id');

        $this->assertDatabaseMissing('personas', ['curp' => 'FIPA900101HMNCRL01']);
    }

    public function test_el_formulario_valida_la_curp_y_el_rol(): void
    {
        $this->actingAs($this->secretario)
            ->post(route('personas.store'), $this->formulario(['curp' => 'CORTA', 'rol' => 'entrenador']))
            ->assertSessionHasErrors(['curp', 'rol']);

        $this->assertSame(0, Persona::where('curp', 'CORTA')->count());
    }

    public function test_un_rol_que_solo_consulta_no_registra_personas(): void
    {
        $presidente = $this->crearUsuario('presidente_personas@liga.com', 'Presidente');

        $this->actingAs($presidente)->post(route('personas.store'), $this->formulario())->assertForbidden();
        $this->assertDatabaseMissing('personas', ['curp' => 'FIPA900101HMNCRL01']);
    }

    /**
     * @param array<string, mixed> $cambios
     * @return array<string, mixed>
     */
    private function formulario(array $cambios = []): array
    {
        return $cambios + [
            'nombre' => 'Fidel',
            'apellidos' => 'Prueba Ficticia',
            'fecha_nacimiento' => '1990-01-01',
            'curp' => 'FIPA900101HMNCRL01',
            'rol' => 'jugador',
            'temporada_id' => $this->activa->id,
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
