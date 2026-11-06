<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
use App\Models\CargoFinanciero;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Persona;
use App\Models\PersonaRol;
use App\Models\Temporada;
use App\Services\RegistroCargoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// HU-050 (SCRUM-177): registro de cuotas de inscripción y cargos de arbitraje y cancha por temporada (RF-13.1)
class RegistroCargoServiceTest extends TestCase
{
    use DatabaseTransactions;

    private RegistroCargoService $servicio;

    private Temporada $temporada;

    private Grupo $grupo;

    private Club $club;

    private Equipo $equipo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->servicio = app(RegistroCargoService::class);
        $this->grupo = Grupo::factory()->create();
        $this->temporada = Temporada::findOrFail($this->grupo->temporada_id);
        $this->temporada->update(['estado' => 'activa']);
        $this->club = Club::create(['nombre' => 'Club Cargos HU-050']);
        $this->equipo = $this->crearEquipo($this->club, $this->grupo, 'Equipo Cargos HU-050');
    }

    // Criterio 1: la cuota de inscripción de un club queda asociada a su temporada
    public function test_la_inscripcion_de_un_club_queda_en_su_temporada(): void
    {
        $cargo = $this->servicio->registrar($this->datos());

        $this->assertSame($this->temporada->id, $cargo->temporada_id);
        $this->assertSame($this->club->id, $cargo->club_id);
        $this->assertNull($cargo->equipo_id);
        $this->assertNull($cargo->persona_id);
        $this->assertSame('Inscripcion', $cargo->concepto);
        $this->assertSame('Pendiente', $cargo->estado);
        $this->assertSame('1500.50', $cargo->fresh()?->monto);
    }

    public function test_la_inscripcion_de_un_equipo_queda_en_su_temporada(): void
    {
        $cargo = $this->servicio->registrar($this->datos(['equipo_id' => $this->equipo->id]));

        $this->assertSame($this->temporada->id, $cargo->temporada_id);
        $this->assertSame($this->equipo->id, $cargo->equipo_id);
        $this->assertNull($cargo->persona_id);
    }

    // La cuota del jugador se liga a la persona con rol jugador en la temporada (D-037); el equipo es opcional
    public function test_la_inscripcion_de_un_jugador_queda_en_su_temporada(): void
    {
        $persona = $this->crearJugador($this->temporada);

        $sinEquipo = $this->servicio->registrar($this->datos(['persona_id' => $persona->id]));
        $conEquipo = $this->servicio->registrar($this->datos(['persona_id' => $persona->id, 'equipo_id' => $this->equipo->id]));

        $this->assertSame($this->temporada->id, $sinEquipo->temporada_id);
        $this->assertSame($persona->id, $sinEquipo->persona_id);
        $this->assertNull($sinEquipo->equipo_id);
        $this->assertSame($this->temporada->id, $conEquipo->temporada_id);
        $this->assertSame($this->equipo->id, $conEquipo->equipo_id);
    }

    // Criterio 2: el cargo de arbitraje o de cancha queda vinculado al club y al equipo que lo generó
    #[DataProvider('conceptosDeServicio')]
    public function test_el_cargo_de_arbitraje_o_cancha_queda_ligado_a_su_club_y_equipo(string $concepto): void
    {
        $cargo = $this->servicio->registrar($this->datos(['concepto' => $concepto, 'equipo_id' => $this->equipo->id]));

        $this->assertSame($concepto, $cargo->concepto);
        $this->assertSame($this->club->id, $cargo->club_id);
        $this->assertSame($this->equipo->id, $cargo->equipo_id);
        $this->assertSame($this->temporada->id, $cargo->temporada_id);
        $this->assertSame('Pendiente', $cargo->estado);
    }

    public function test_el_cargo_de_arbitraje_puede_ser_solo_del_club(): void
    {
        $cargo = $this->servicio->registrar($this->datos(['concepto' => 'Arbitraje']));

        $this->assertSame($this->club->id, $cargo->club_id);
        $this->assertNull($cargo->equipo_id);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function conceptosDeServicio(): array
    {
        return [
            'arbitraje' => ['Arbitraje'],
            'cancha' => ['Cancha'],
        ];
    }

    // El estado no se recibe de fuera: todo cargo nace pendiente
    public function test_el_cargo_siempre_nace_pendiente(): void
    {
        $cargo = $this->servicio->registrar($this->datos(['estado' => 'Pagado']));

        $this->assertSame('Pendiente', $cargo->fresh()?->estado);
    }

    #[DataProvider('conceptosAjenos')]
    public function test_rechaza_conceptos_que_no_son_de_hu_050(string $concepto): void
    {
        $this->assertRechazo(['concepto' => $concepto], 'concepto', 'El concepto debe ser inscripción, arbitraje o cancha.');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function conceptosAjenos(): array
    {
        return [
            'credenciales' => ['Credenciales'],
            'multa' => ['Multa'],
        ];
    }

    public function test_rechaza_una_temporada_cerrada(): void
    {
        $this->temporada->update(['estado' => 'cerrada']);

        $this->assertRechazo([], 'temporada_id', 'La temporada está cerrada: sus cargos solo se consultan (RF-01.4).');
    }

    public function test_rechaza_una_temporada_inexistente_o_vacia(): void
    {
        $this->assertRechazo(['temporada_id' => 999999999], 'temporada_id', 'La temporada seleccionada no existe.');
        $this->assertRechazo(['temporada_id' => null], 'temporada_id', 'La temporada es obligatoria.');
    }

    public function test_rechaza_un_equipo_de_otro_club(): void
    {
        $otroClub = Club::create(['nombre' => 'Otro Club HU-050']);
        $ajeno = $this->crearEquipo($otroClub, $this->grupo, 'Equipo Ajeno HU-050');

        $this->assertRechazo(['equipo_id' => $ajeno->id], 'equipo_id', 'El equipo no pertenece al club seleccionado.');
        $this->assertRechazo(['concepto' => 'Cancha', 'equipo_id' => $ajeno->id], 'equipo_id', 'El equipo no pertenece al club seleccionado.');
    }

    public function test_rechaza_un_equipo_de_otra_temporada(): void
    {
        $otraTemporada = $this->crearEquipo($this->club, Grupo::factory()->create(), 'Equipo de Otra Temporada HU-050');

        $this->assertRechazo(['equipo_id' => $otraTemporada->id], 'equipo_id', 'El equipo no pertenece a la temporada seleccionada.');
        $this->assertRechazo(['concepto' => 'Arbitraje', 'equipo_id' => $otraTemporada->id], 'equipo_id', 'El equipo no pertenece a la temporada seleccionada.');
    }

    public function test_rechaza_una_persona_sin_rol_de_jugador(): void
    {
        $delegado = Persona::factory()->create();
        PersonaRol::factory()->create(['persona_id' => $delegado->id, 'rol' => 'delegado', 'temporada_id' => $this->temporada->id]);

        $this->assertRechazo(['persona_id' => $delegado->id], 'persona_id', 'La persona no tiene rol de jugador en la temporada seleccionada.');
    }

    public function test_rechaza_una_persona_con_el_rol_de_jugador_en_otra_temporada(): void
    {
        $persona = $this->crearJugador(Temporada::factory()->create(['estado' => 'activa']));

        $this->assertRechazo(['persona_id' => $persona->id], 'persona_id', 'La persona no tiene rol de jugador en la temporada seleccionada.');
    }

    #[DataProvider('montosInvalidos')]
    public function test_rechaza_montos_invalidos(mixed $monto, string $mensaje): void
    {
        $this->assertRechazo(['monto' => $monto], 'monto', $mensaje);
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function montosInvalidos(): array
    {
        return [
            'cero' => ['0', 'El monto debe ser mayor que cero.'],
            'negativo' => ['-150.00', 'El monto debe ser mayor que cero.'],
            'tres decimales' => ['100.005', 'El monto admite como máximo dos decimales.'],
            'no cabe en decimal(10,2)' => ['100000000.00', 'El monto no puede ser mayor que 99,999,999.99.'],
            'texto' => ['mil pesos', 'El monto debe ser un número.'],
        ];
    }

    public function test_rechaza_una_fecha_de_vencimiento_invalida(): void
    {
        $this->assertRechazo(['vencimiento' => '2026-02-30'], 'vencimiento', 'La fecha de vencimiento no es válida.');
    }

    #[DataProvider('conceptosDeServicio')]
    public function test_rechaza_una_persona_en_un_cargo_de_arbitraje_o_cancha(string $concepto): void
    {
        $persona = $this->crearJugador($this->temporada);

        $this->assertRechazo(
            ['concepto' => $concepto, 'persona_id' => $persona->id],
            'persona_id',
            'Un cargo de arbitraje o de cancha se liga al club o al equipo, no a una persona.'
        );
    }

    // RNF-33: cada cargo deja una sola fila en la auditoría
    public function test_cada_cargo_deja_una_sola_fila_de_auditoria(): void
    {
        $ultimoId = (int) AuditoriaLog::query()->max('id');

        $cargo = $this->servicio->registrar($this->datos(['equipo_id' => $this->equipo->id]));

        $log = AuditoriaLog::query()->where('id', '>', $ultimoId)->sole();
        $this->assertSame('cargos_financieros', $log->tabla_afectada);
        $this->assertSame($cargo->id, $log->registro_id);
        $this->assertSame('crear', $log->accion);
        $this->assertSame($this->temporada->id, $log->valores_nuevos['temporada_id']);
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return array_merge([
            'concepto' => 'Inscripcion',
            'temporada_id' => $this->temporada->id,
            'club_id' => $this->club->id,
            'monto' => '1500.50',
            'vencimiento' => '2026-12-15',
        ], $cambios);
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function assertRechazo(array $cambios, string $campo, string $mensaje): void
    {
        $antes = CargoFinanciero::query()->count();

        try {
            $this->servicio->registrar($this->datos($cambios));
            $this->fail('Se esperaba que el cargo se rechazara.');
        } catch (ValidationException $e) {
            $this->assertSame($mensaje, $e->errors()[$campo][0] ?? null, json_encode($e->errors(), JSON_UNESCAPED_UNICODE) ?: '');
        }

        $this->assertSame($antes, CargoFinanciero::query()->count());
    }

    private function crearEquipo(Club $club, Grupo $grupo, string $nombre): Equipo
    {
        return Equipo::create([
            'club_id' => $club->id,
            'temporada_id' => $grupo->temporada_id,
            'grupo_id' => $grupo->id,
            'nombre' => $nombre,
        ]);
    }

    private function crearJugador(Temporada $temporada): Persona
    {
        $persona = Persona::factory()->create();
        PersonaRol::factory()->create(['persona_id' => $persona->id, 'rol' => 'jugador', 'temporada_id' => $temporada->id]);

        return $persona;
    }
}
