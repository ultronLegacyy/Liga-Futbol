<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
use App\Models\CedulaArbitral;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\User;
use App\Services\CedulaService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreaPartidoConCedula;
use Tests\TestCase;

// HU-036 (SCRUM-218, SCRUM-219 y SCRUM-221): captura desde el celular y reenvío sin duplicar (criterios 1 a 5, RNF-21)
class CedulaCapturaTest extends TestCase
{
    use CreaPartidoConCedula;
    use DatabaseTransactions;

    private Partido $partido;

    private CedulaArbitral $cedula;

    private User $arbitro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->partido = $this->partidoConArbitro();
        $this->cedula = app(CedulaService::class)->abrir($this->partido);
        $this->arbitro = $this->usuario('Árbitro en Campo', $this->partido->arbitro);
    }

    // Criterios 2 y 3: la pantalla de captura muestra la alineación precargada, sin escribir nombres
    public function test_la_pantalla_de_captura_muestra_la_alineacion_precargada(): void
    {
        $this->actingAs($this->arbitro)
            ->get(route('cedulas.show', $this->partido))
            ->assertOk()
            ->assertSee('name="csrf-token"', false)
            ->assertSee('Todo guardado')
            ->assertSee('Equipo 1, Jugador 1')
            ->assertSee('Equipo 2, Jugador 4')
            ->assertSee('(Suspendido)')
            ->assertSee('Registrar evento')
            ->assertSee('Registrar incidencia')
            ->assertSee(route('cedulas.marcador', $this->partido));
    }

    // Criterio 1: alineación, marcador, gol, tarjeta e incidencia desde el celular
    public function test_guarda_alineacion_marcador_eventos_e_incidencias(): void
    {
        $jugador = $this->jugador('local');
        $this->actingAs($this->arbitro);

        $this->putJson(route('cedulas.alineacion', [$this->partido, $jugador->id]), ['clave_envio' => (string) Str::uuid(), 'asistio' => true, 'numero_camiseta' => 9, 'es_capitan' => true])
            ->assertOk()
            ->assertExactJson(['jugador_id' => $jugador->id, 'asistio' => true, 'numero_camiseta' => 9, 'es_capitan' => true]);

        $this->putJson(route('cedulas.marcador', $this->partido), ['clave_envio' => (string) Str::uuid(), 'goles_local' => 2, 'goles_visitante' => 1])
            ->assertOk()
            ->assertExactJson(['goles_local' => 2, 'goles_visitante' => 1]);

        $this->postJson(route('cedulas.eventos', $this->partido), ['clave_envio' => (string) Str::uuid(), 'jugador_id' => $jugador->id, 'tipo' => 'Roja', 'minuto' => 80])
            ->assertOk()
            ->assertJson(['jugador_id' => $jugador->id, 'tipo' => 'Roja', 'autogol' => false, 'minuto' => 80]);

        // El equipo llega como texto desde el select
        $this->postJson(route('cedulas.incidencias', $this->partido), ['clave_envio' => (string) Str::uuid(), 'descripcion' => 'Aficionado invadió la cancha.', 'equipo_id' => (string) $this->partido->equipo_visitante_id])
            ->assertOk()
            ->assertJson(['equipo_id' => $this->partido->equipo_visitante_id, 'minuto' => null, 'descripcion' => 'Aficionado invadió la cancha.']);

        $cedula = $this->cedula->refresh();
        $this->assertSame([2, 1], [$cedula->goles_local, $cedula->goles_visitante]);
        $this->assertTrue($cedula->alineaciones()->where('jugador_id', $jugador->id)->value('es_capitan'));
        $this->assertSame(1, $cedula->eventos()->where('tipo', 'Roja')->count());
        $this->assertSame(1, $cedula->incidencias()->count());
    }

    // Criterio 4 y RNF-33: el mismo envío dos veces responde igual, no duplica y deja una sola entrada en la auditoría
    public function test_un_reenvio_con_la_misma_clave_responde_igual_y_no_duplica(): void
    {
        $datos = ['clave_envio' => (string) Str::uuid(), 'jugador_id' => $this->jugador('local')->id, 'tipo' => 'Gol', 'minuto' => 12];
        $ultimoId = (int) AuditoriaLog::query()->max('id');
        $this->actingAs($this->arbitro);

        $primera = $this->postJson(route('cedulas.eventos', $this->partido), $datos)->assertOk()->json();
        $segunda = $this->postJson(route('cedulas.eventos', $this->partido), $datos)->assertOk()->json();

        $this->assertSame($primera, $segunda);
        $this->assertSame(1, $this->cedula->eventos()->count());
        $this->assertSame(1, AuditoriaLog::query()->where('id', '>', $ultimoId)->where('tabla_afectada', 'eventos_partido')->count());

        $marcador = ['clave_envio' => (string) Str::uuid(), 'goles_local' => 1, 'goles_visitante' => 0];
        $this->putJson(route('cedulas.marcador', $this->partido), $marcador)->assertOk();
        $this->putJson(route('cedulas.marcador', $this->partido), $marcador)->assertOk()->assertExactJson(['goles_local' => 1, 'goles_visitante' => 0]);
        $this->assertSame(1, AuditoriaLog::query()->where('id', '>', $ultimoId)->where('tabla_afectada', 'cedulas_arbitrales')->count());
    }

    // Criterio 5: el 403 sigue funcionando en las rutas nuevas
    #[DataProvider('rutasDeCaptura')]
    public function test_otro_arbitro_recibe_403_en_la_captura(string $ruta, string $metodo): void
    {
        $otro = $this->usuario('Árbitro en Campo', $this->arbitro());

        $this->actingAs($otro)
            ->json($metodo, $this->url($ruta), $this->datosValidos($ruta))
            ->assertForbidden()
            ->assertJson(['mensaje' => 'Solo el árbitro asignado a este partido puede acceder a su cédula.']);

        $this->assertSame(0, $this->cedula->eventos()->count() + $this->cedula->incidencias()->count() + $this->cedula->refresh()->goles_local);
    }

    #[DataProvider('rutasDeCaptura')]
    public function test_quien_solo_consulta_no_captura(string $ruta, string $metodo): void
    {
        $this->actingAs($this->usuario('Tribunal de Penas'))
            ->json($metodo, $this->url($ruta), $this->datosValidos($ruta))
            ->assertForbidden()
            ->assertJson(['mensaje' => 'No cuenta con privilegios para capturar cédulas arbitrales.']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function rutasDeCaptura(): array
    {
        return [
            'alineación' => ['alineacion', 'PUT'],
            'marcador' => ['marcador', 'PUT'],
            'eventos' => ['eventos', 'POST'],
            'incidencias' => ['incidencias', 'POST'],
        ];
    }

    // Validación con mensajes en español
    public function test_valida_los_datos_con_mensajes_en_espanol(): void
    {
        $this->actingAs($this->arbitro);

        $this->putJson(route('cedulas.marcador', $this->partido), ['goles_local' => 1, 'goles_visitante' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['clave_envio' => 'Falta la clave de envío del cambio.']);
        $this->putJson(route('cedulas.marcador', $this->partido), ['clave_envio' => (string) Str::uuid(), 'goles_local' => 100, 'goles_visitante' => 0])
            ->assertJsonValidationErrors(['goles_local' => 'Los goles de cada equipo deben estar entre 0 y 99.']);
        $this->postJson(route('cedulas.eventos', $this->partido), ['clave_envio' => 'no-es-uuid', 'jugador_id' => 1, 'tipo' => 'Gol'])
            ->assertJsonValidationErrors(['clave_envio' => 'La clave de envío no es válida.']);
        $this->postJson(route('cedulas.eventos', $this->partido), ['clave_envio' => (string) Str::uuid(), 'jugador_id' => $this->jugador('local')->id, 'tipo' => 'Azul'])
            ->assertJsonValidationErrors(['tipo' => 'El evento debe ser Gol, Amarilla o Roja.']);
        $this->postJson(route('cedulas.incidencias', $this->partido), ['clave_envio' => (string) Str::uuid(), 'descripcion' => ''])
            ->assertJsonValidationErrors(['descripcion' => 'Describe la incidencia.']);

        // Un jugador de otro partido: lo rechaza el servicio (RNF-29)
        $ajeno = Jugador::query()->where('equipo_id', $this->partidoConArbitro()->equipo_local_id)->firstOrFail();
        $this->postJson(route('cedulas.eventos', $this->partido), ['clave_envio' => (string) Str::uuid(), 'jugador_id' => $ajeno->id, 'tipo' => 'Gol'])
            ->assertJsonValidationErrors(['jugador_id' => 'El jugador no pertenece a los equipos de este partido.']);

        $this->assertSame(0, $this->cedula->eventos()->count());
    }

    // Sin cédula abierta, con la cédula cerrada o con el partido fuera de juego no se captura
    public function test_no_se_captura_sin_cedula_abierta_ni_fuera_de_los_estados_permitidos(): void
    {
        $sinCedula = $this->partidoConArbitro($this->partido->arbitro, '12:00:00');
        $this->actingAs($this->arbitro);

        $this->putJson(route('cedulas.marcador', $sinCedula), ['clave_envio' => (string) Str::uuid(), 'goles_local' => 1, 'goles_visitante' => 0])
            ->assertJsonValidationErrors(['cedula' => 'El árbitro todavía no abre la cédula de este partido.']);
        $this->assertFalse(CedulaArbitral::query()->where('partido_id', $sinCedula->id)->exists());

        $this->partido->update(['estado' => 'borrador']);
        $this->putJson(route('cedulas.marcador', $this->partido), ['clave_envio' => (string) Str::uuid(), 'goles_local' => 1, 'goles_visitante' => 0])
            ->assertJsonValidationErrors(['estado' => 'La cédula solo se abre y se captura cuando el partido está programado, confirmado o en juego.']);
        // Fuera de juego, la pantalla queda solo de consulta
        $this->get(route('cedulas.show', $this->partido))->assertOk()->assertDontSee('Registrar evento');

        $this->partido->update(['estado' => 'programado']);
        $this->cedula->update(['estado' => CedulaArbitral::CERRADA]);
        $this->postJson(route('cedulas.incidencias', $this->partido), ['clave_envio' => (string) Str::uuid(), 'descripcion' => 'Lluvia'])
            ->assertJsonValidationErrors(['cedula' => 'La cédula está cerrada: ya no se puede modificar.']);
        $this->get(route('cedulas.show', $this->partido))->assertOk()->assertSee('La cédula está cerrada: solo se consulta.');
        $this->assertSame(0, $this->cedula->refresh()->goles_local);
    }

    public function test_un_jugador_no_numerico_da_404(): void
    {
        $this->actingAs($this->arbitro)
            ->putJson('/partidos/'.$this->partido->id.'/cedula/alineacion/abc', ['clave_envio' => (string) Str::uuid(), 'asistio' => true])
            ->assertNotFound();
    }

    private function url(string $ruta): string
    {
        return $ruta === 'alineacion'
            ? route('cedulas.alineacion', [$this->partido, $this->jugador('local')->id])
            : route('cedulas.'.$ruta, $this->partido);
    }

    /** @return array<string, mixed> */
    private function datosValidos(string $ruta): array
    {
        return ['clave_envio' => (string) Str::uuid(), ...match ($ruta) {
            'alineacion' => ['asistio' => true],
            'marcador' => ['goles_local' => 3, 'goles_visitante' => 0],
            'eventos' => ['jugador_id' => $this->jugador('local')->id, 'tipo' => 'Gol'],
            default => ['descripcion' => 'Riña'],
        }];
    }

    private function jugador(string $lado): Jugador
    {
        return Jugador::query()
            ->where('equipo_id', $lado === 'local' ? $this->partido->equipo_local_id : $this->partido->equipo_visitante_id)
            ->where('estado_elegibilidad', 'Activo')
            ->orderBy('id')
            ->firstOrFail();
    }
}
