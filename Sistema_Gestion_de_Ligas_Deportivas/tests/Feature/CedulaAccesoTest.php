<?php

namespace Tests\Feature;

use App\Models\AlineacionPartido;
use App\Models\CedulaArbitral;
use App\Models\Partido;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreaPartidoConCedula;
use Tests\TestCase;

// HU-036 (SCRUM-217 y SCRUM-220): acceso a la cédula; el Árbitro en Campo solo ve sus partidos (criterio 5, RNF-29)
class CedulaAccesoTest extends TestCase
{
    use CreaPartidoConCedula;
    use DatabaseTransactions;

    private Partido $partido;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->partido = $this->partidoConArbitro();
    }

    // El árbitro asignado abre su cédula, con la alineación precargada de las dos plantillas
    public function test_el_arbitro_asignado_abre_su_cedula_con_la_alineacion_precargada(): void
    {
        $arbitro = $this->usuario('Árbitro en Campo', $this->partido->arbitro);

        $this->actingAs($arbitro)
            ->get(route('cedulas.show', $this->partido))
            ->assertOk()
            ->assertSee('Equipo Cédula 1 vs. Equipo Cédula 2')
            ->assertSee('Alineaciones');

        $cedula = CedulaArbitral::query()->where('partido_id', $this->partido->id)->sole();
        $this->assertSame(CedulaArbitral::ABIERTA, $cedula->estado);
        // 4 por equipo: 3 activos y 1 suspendido; la baja no entra
        $this->assertSame(
            [$this->partido->equipo_local_id => 4, $this->partido->equipo_visitante_id => 4],
            $cedula->alineaciones()->get()->countBy('equipo_id')->all()
        );
        $this->assertSame([1, 2, 3, 4], $cedula->alineaciones()->where('equipo_id', $this->partido->equipo_local_id)->orderBy('numero_camiseta')->pluck('numero_camiseta')->all());
        $this->assertFalse($cedula->alineaciones()->where('asistio', true)->exists());

        // Abrirla otra vez no crea otra cédula ni duplica la alineación
        $this->get(route('cedulas.show', $this->partido))->assertOk();
        $this->assertSame(1, CedulaArbitral::query()->where('partido_id', $this->partido->id)->count());
        $this->assertSame(8, AlineacionPartido::query()->where('cedula_id', $cedula->id)->count());
    }

    // Criterio 5: otro árbitro que cambia el identificador en la URL recibe 403 en español
    public function test_otro_arbitro_recibe_403(): void
    {
        $otro = $this->usuario('Árbitro en Campo', $this->arbitro());

        $this->actingAs($otro)
            ->get(route('cedulas.show', $this->partido))
            ->assertForbidden()
            ->assertSee('Solo el árbitro asignado a este partido puede acceder a su cédula.');

        $this->assertFalse(CedulaArbitral::query()->where('partido_id', $this->partido->id)->exists());
    }

    // Un usuario con el rol de árbitro pero sin persona enlazada tampoco entra
    public function test_un_arbitro_sin_persona_enlazada_recibe_403(): void
    {
        $this->actingAs($this->usuario('Árbitro en Campo'))
            ->get(route('cedulas.show', $this->partido))
            ->assertForbidden();
    }

    #[DataProvider('rolesSinPermiso')]
    public function test_un_rol_sin_cedulas_ver_recibe_403(string $rol): void
    {
        $this->actingAs($this->usuario($rol))
            ->get(route('cedulas.show', $this->partido))
            ->assertForbidden()
            ->assertSee('No cuenta con privilegios para consultar cédulas arbitrales.');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rolesSinPermiso(): array
    {
        return [
            'Tesorero' => ['Tesorero'],
            'Jugador' => ['Jugador'],
            'Delegado del Club' => ['Delegado del Club'],
        ];
    }

    public function test_un_identificador_no_numerico_da_404(): void
    {
        $this->actingAs($this->usuario('Árbitro en Campo', $this->partido->arbitro))
            ->get('/partidos/abc/cedula')
            ->assertNotFound();
    }

    public function test_sin_sesion_redirige_al_inicio_de_sesion(): void
    {
        $this->get(route('cedulas.show', $this->partido))->assertRedirect(route('login'));
    }

    // El Administrador General entra por el before() de la política
    public function test_el_administrador_general_abre_cualquier_cedula(): void
    {
        $this->actingAs($this->usuario('Administrador General'))
            ->get(route('cedulas.show', $this->partido))
            ->assertOk();

        $this->assertTrue(CedulaArbitral::query()->where('partido_id', $this->partido->id)->exists());
    }

    // Quien solo consulta (Programador de Partidos: cedulas.ver sin capturar) no abre la cédula
    public function test_quien_solo_consulta_no_abre_la_cedula(): void
    {
        $this->actingAs($this->usuario('Programador de Partidos'))
            ->get(route('cedulas.show', $this->partido))
            ->assertOk()
            ->assertSee('El árbitro todavía no abre la cédula de este partido.');

        $this->assertFalse(CedulaArbitral::query()->where('partido_id', $this->partido->id)->exists());
    }
}
