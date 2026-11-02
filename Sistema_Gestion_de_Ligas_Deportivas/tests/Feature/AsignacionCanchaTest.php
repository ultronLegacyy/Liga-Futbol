<?php

namespace Tests\Feature;

use App\Models\BloqueoCancha;
use App\Models\Cancha;
use App\Models\Grupo;
use App\Models\Partido;
use App\Models\User;
use App\Services\AsignacionCanchaService;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// HU-025 (SCRUM-40): impedir asignar una cancha bloqueada u ocupada en el mismo horario (RF-05.3)
class AsignacionCanchaTest extends TestCase
{
    use DatabaseTransactions;

    private const FECHA = '2026-11-07';

    private AsignacionCanchaService $servicio;

    private Cancha $cancha;

    private int $jornadaId;

    private int $equipoLocalId;

    private int $equipoVisitanteId;

    protected function setUp(): void
    {
        parent::setUp();

        config(['programacion.duracion_partido_minutos' => 120]);

        $this->servicio = app(AsignacionCanchaService::class);
        $this->cancha = Cancha::factory()->create(['estado' => 'Disponible']);

        $grupo = Grupo::factory()->create();
        $clubId = DB::table('clubes')->insertGetId([
            'nombre' => 'Club de Prueba HU-025',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->equipoLocalId = $this->crearEquipo($clubId, $grupo->id, 'Local HU-025');
        $this->equipoVisitanteId = $this->crearEquipo($clubId, $grupo->id, 'Visitante HU-025');
        $this->jornadaId = DB::table('jornadas')->insertGetId([
            'grupo_id' => $grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => self::FECHA,
            'fecha_fin' => '2026-11-08',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // CA1: una cancha libre se asigna
    public function test_asigna_una_cancha_libre(): void
    {
        $partido = $this->crearPartido();

        $this->servicio->asignar($partido, $this->cancha, $this->momento('10:00'));

        $partido->refresh();
        $this->assertSame($this->cancha->id, $partido->cancha_id);
        $this->assertSame(self::FECHA, $partido->fecha_partido?->toDateString());
        $this->assertSame('10:00:00', $partido->hora_partido);
    }

    // CA1: una cancha bloqueada se rechaza y se muestra el motivo
    public function test_rechaza_una_cancha_bloqueada_con_el_motivo(): void
    {
        BloqueoCancha::factory()->create([
            'cancha_id' => $this->cancha->id,
            'fecha_inicio' => $this->momento('09:00'),
            'fecha_fin' => $this->momento('13:00'),
            'motivo' => 'Lluvia',
        ]);
        $partido = $this->crearPartido();

        $mensaje = $this->mensajeDeRechazo($partido, $this->cancha, '10:00');

        $this->assertStringContainsString('Motivo: Lluvia', $mensaje);
        $this->assertSame('La cancha está bloqueada del 07/11/2026 09:00 al 07/11/2026 13:00. Motivo: Lluvia.', $mensaje);
        $this->assertNull($partido->fresh()?->cancha_id);
    }

    // CA1: un partido que ya ocupa la cancha impide un solape parcial por cualquiera de los dos lados
    #[DataProvider('solapesParciales')]
    public function test_rechaza_un_solape_parcial_con_otro_partido(string $hora): void
    {
        $this->crearPartido($this->cancha, self::FECHA, '10:00:00');
        $partido = $this->crearPartido();

        $mensaje = $this->mensajeDeRechazo($partido, $this->cancha, $hora);

        $this->assertStringContainsString('de 10:00 a 12:00', $mensaje);
        $this->assertNull($partido->fresh()?->cancha_id);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function solapesParciales(): array
    {
        return [
            'empieza durante el otro (11:30)' => ['11:30'],
            'termina durante el otro (08:30)' => ['08:30'],
        ];
    }

    // Las comparaciones son estrictas: un partido puede empezar justo cuando termina el otro
    #[DataProvider('horariosContiguos')]
    public function test_permite_empezar_justo_antes_o_despues_de_otro_partido(string $hora): void
    {
        $this->crearPartido($this->cancha, self::FECHA, '10:00:00');
        $partido = $this->crearPartido();

        $this->servicio->asignar($partido, $this->cancha, $this->momento($hora));

        $this->assertSame($this->cancha->id, $partido->fresh()?->cancha_id);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function horariosContiguos(): array
    {
        return [
            'a las 12:00, cuando termina' => ['12:00'],
            'a las 08:00, termina cuando empieza' => ['08:00'],
        ];
    }

    // Un partido nocturno del día anterior sigue ocupando la cancha después de medianoche
    public function test_detecta_un_partido_del_dia_anterior_que_termina_despues_de_medianoche(): void
    {
        $this->crearPartido($this->cancha, '2026-11-06', '23:00:00');
        $partido = $this->crearPartido();

        $mensaje = $this->mensajeDeRechazo($partido, $this->cancha, '00:30');

        $this->assertStringContainsString('de 23:00 a 01:00', $mensaje);
    }

    public function test_un_partido_suspendido_no_ocupa_la_cancha(): void
    {
        $this->crearPartido($this->cancha, self::FECHA, '10:00:00', 'suspendido');
        $partido = $this->crearPartido();

        $this->servicio->asignar($partido, $this->cancha, $this->momento('10:00'));

        $this->assertSame($this->cancha->id, $partido->fresh()?->cancha_id);
    }

    public function test_un_partido_en_otra_cancha_no_interfiere(): void
    {
        $this->crearPartido(Cancha::factory()->create(['estado' => 'Disponible']), self::FECHA, '10:00:00');
        $partido = $this->crearPartido();

        $this->servicio->asignar($partido, $this->cancha, $this->momento('10:00'));

        $this->assertSame($this->cancha->id, $partido->fresh()?->cancha_id);
    }

    // Reprogramar un partido no choca con su propio horario anterior
    public function test_reprogramar_un_partido_no_choca_consigo_mismo(): void
    {
        $partido = $this->crearPartido($this->cancha, self::FECHA, '10:00:00');

        $this->servicio->asignar($partido, $this->cancha, $this->momento('11:00'));

        $partido->refresh();
        $this->assertSame($this->cancha->id, $partido->cancha_id);
        $this->assertSame('11:00:00', $partido->hora_partido);
    }

    #[DataProvider('estadosNoDisponibles')]
    public function test_rechaza_canchas_inactivas_o_en_mantenimiento(string $estado): void
    {
        $cancha = Cancha::factory()->create(['estado' => $estado]);
        $partido = $this->crearPartido();

        $mensaje = $this->mensajeDeRechazo($partido, $cancha, '10:00');

        $this->assertSame("La cancha no está disponible: su estado es {$estado}.", $mensaje);
        $this->assertNull($partido->fresh()?->cancha_id);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function estadosNoDisponibles(): array
    {
        return [
            'Inactiva' => ['Inactiva'],
            'Mantenimiento' => ['Mantenimiento'],
        ];
    }

    // CA1 en el endpoint de disponibilidad: responde que no está disponible y da el motivo
    public function test_el_endpoint_de_disponibilidad_devuelve_el_motivo(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $admin = User::create([
            'name' => 'Administrador HU-025',
            'email' => 'admin_hu025@liga.test',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Administrador General',
        ]);
        $admin->assignRole('Administrador General');
        $this->crearPartido($this->cancha, self::FECHA, '10:00:00');

        $this->actingAs($admin)
            ->getJson(route('canchas.disponibilidad', [
                'cancha' => $this->cancha,
                'fecha' => self::FECHA,
                'hora_inicio' => '11:00',
                'hora_fin' => '13:00',
            ]))
            ->assertOk()
            ->assertJson([
                'disponible' => false,
                'motivo' => 'La cancha ya está ocupada por otro partido el 07/11/2026 de 10:00 a 12:00.',
            ]);
    }

    private function crearEquipo(int $clubId, int $grupoId, string $nombre): int
    {
        return DB::table('equipos')->insertGetId([
            'club_id' => $clubId,
            'grupo_id' => $grupoId,
            'nombre' => $nombre,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function crearPartido(?Cancha $cancha = null, ?string $fecha = null, ?string $hora = null, string $estado = 'programado'): Partido
    {
        return Partido::create([
            'jornada_id' => $this->jornadaId,
            'equipo_local_id' => $this->equipoLocalId,
            'equipo_visitante_id' => $this->equipoVisitanteId,
            'cancha_id' => $cancha?->id,
            'fecha_partido' => $fecha,
            'hora_partido' => $hora,
            'estado' => $estado,
        ]);
    }

    private function momento(string $hora): Carbon
    {
        return Carbon::parse(self::FECHA.' '.$hora);
    }

    private function mensajeDeRechazo(Partido $partido, Cancha $cancha, string $hora): string
    {
        try {
            $this->servicio->asignar($partido, $cancha, $this->momento($hora));
        } catch (ValidationException $e) {
            return (string) ($e->errors()['cancha_id'][0] ?? '');
        }

        $this->fail('Se esperaba que la asignación se rechazara.');
    }
}
