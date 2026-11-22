<?php

namespace Tests\Feature;

use App\Mail\AvisoProgramacion;
use App\Models\Arbitro;
use App\Models\Cancha;
use App\Models\Categoria;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\Partido;
use App\Models\RegistroCorreo;
use App\Models\Temporada;
use App\Models\User;
use App\Services\IdentidadPersonaService;
use App\Services\ProgramacionJornadaService;
use App\Services\PublicacionProgramacionService;
use App\Services\ReprogramacionPartidoService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

// HU-064 (SCRUM-74, RF-15.1): los delegados de los dos clubes reciben un correo al publicarse o cambiar su partido
class AvisoProgramacionTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $programador;

    private Temporada $temporada;

    private Jornada $jornada;

    private Cancha $cancha1;

    private Cancha $cancha2;

    private Arbitro $arbitro;

    /** @var array<string, Equipo> */
    private array $equipos = [];

    /** @var array<string, User> */
    private array $delegados = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->admin = $this->usuario('admin.avisos@liga.test', 'Administrador General');
        $this->programador = $this->usuario('programador.avisos@liga.test', 'Programador de Partidos');

        $this->temporada = Temporada::factory()->activa()->create(['fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31']);
        $grupo = Grupo::factory()->create(['nombre' => 'Grupo A', 'categoria_id' => Categoria::factory()->create(['temporada_id' => $this->temporada->id, 'nombre' => 'Libre'])->id]);
        $this->jornada = Jornada::create(['grupo_id' => $grupo->id, 'numero_jornada' => 3, 'fecha_inicio' => '2026-11-20', 'fecha_fin' => '2026-11-22', 'estado' => Jornada::ESTADO_BORRADOR]);

        $this->cancha1 = Cancha::factory()->create(['nombre' => 'Cancha Norte', 'direccion_ubicacion' => 'Av. Juárez 10', 'estado' => 'Disponible', 'capacidad_partidos_consecutivos' => 4]);
        $this->cancha2 = Cancha::factory()->create(['nombre' => 'Cancha Sur', 'estado' => 'Disponible', 'capacidad_partidos_consecutivos' => 4]);
        $rol = app(IdentidadPersonaService::class)->asignarRol(['nombre' => 'Árbitro', 'apellidos' => 'Avisos', 'fecha_nacimiento' => '1988-03-12', 'curp' => 'AVIS880312HDFRRN01'], 'arbitro', $this->temporada);
        $this->arbitro = Arbitro::create(['persona_id' => $rol->persona_id, 'estado' => 'Activo']);

        foreach (['Cobras', 'Águilas', 'Delfines', 'Lobos', 'Ajenos'] as $nombre) {
            $this->equipos[$nombre] = Equipo::create(['club_id' => Club::create(['nombre' => 'Club '.$nombre])->id, 'grupo_id' => $grupo->id, 'nombre' => $nombre]);
        }

        // Cobras tiene dos delegados; Águilas, uno activo y uno desactivado; Lobos, ninguno; Ajenos no juega esta jornada
        $this->delegados = [
            'cobras' => $this->delegado('cobras@liga.test', 'Cobras'),
            'cobras2' => $this->delegado('cobras2@liga.test', 'Cobras'),
            'aguilas' => $this->delegado('aguilas@liga.test', 'Águilas'),
            'aguilasInactivo' => $this->delegado('aguilas.baja@liga.test', 'Águilas', 'inactivo'),
            'delfines' => $this->delegado('delfines@liga.test', 'Delfines'),
            'ajenos' => $this->delegado('ajenos@liga.test', 'Ajenos'),
        ];
    }

    // Criterio 1: al publicar la jornada, cada delegado recibe un correo con los partidos de su club
    public function test_al_publicar_cada_delegado_recibe_los_partidos_de_su_club(): void
    {
        Mail::fake();
        $this->partido('Cobras', 'Águilas', '09:00:00', Partido::ESTADO_BORRADOR);
        $this->partido('Delfines', 'Lobos', '11:00:00', Partido::ESTADO_BORRADOR);
        $this->jornada->update(['estado' => Jornada::ESTADO_APROBADA]);

        app(PublicacionProgramacionService::class)->publicar($this->jornada, $this->programador);

        Mail::assertQueuedCount(4);
        foreach (['cobras', 'cobras2', 'aguilas', 'delfines'] as $clave) {
            Mail::assertQueued(AvisoProgramacion::class, fn (AvisoProgramacion $aviso): bool => $aviso->hasTo($this->delegados[$clave]->email));
        }
        foreach (['aguilasInactivo', 'ajenos'] as $clave) {
            Mail::assertNotQueued(AvisoProgramacion::class, fn (AvisoProgramacion $aviso): bool => $aviso->hasTo($this->delegados[$clave]->email));
        }

        Mail::assertQueued(AvisoProgramacion::class, function (AvisoProgramacion $aviso): bool {
            return $aviso->hasTo('cobras@liga.test')
                && $aviso->asunto === 'Programación publicada: Jornada 3 · Grupo A (Libre)'
                && str_contains($aviso->mensaje, "Cobras vs Águilas\nFecha y hora: 20/11/2026 a las 09:00 hrs\nCancha: Cancha Norte (Av. Juárez 10)")
                && ! str_contains($aviso->mensaje, 'Delfines');
        });
    }

    // Criterio 1: una reprogramación avisa a los delegados de los dos clubes con lo nuevo, lo anterior y el motivo
    public function test_la_reprogramacion_avisa_con_los_datos_nuevos(): void
    {
        Mail::fake();
        $partido = $this->partido('Cobras', 'Águilas', '09:00:00', Partido::ESTADO_PROGRAMADO);

        app(ReprogramacionPartidoService::class)->reprogramar($partido, $this->cambio(), $this->programador);

        Mail::assertQueuedCount(3);
        Mail::assertQueued(AvisoProgramacion::class, function (AvisoProgramacion $aviso): bool {
            return $aviso->hasTo('aguilas@liga.test')
                && $aviso->asunto === 'Cambio de programación: Cobras vs Águilas'
                && str_contains($aviso->mensaje, "Fecha y hora: 21/11/2026 a las 16:00 hrs\nCancha: Cancha Sur")
                && str_contains($aviso->mensaje, 'Antes: 20/11/2026 a las 09:00 hrs · Cancha Norte')
                && str_contains($aviso->mensaje, 'Motivo: Lluvia intensa en la cancha norte desde la mañana.');
        });
    }

    // La edición de sede u horario de un partido publicado también avisa; la de un partido en borrador, no
    public function test_la_edicion_avisa_solo_si_el_partido_ya_se_publico(): void
    {
        Mail::fake();
        $borrador = $this->partido('Delfines', 'Lobos', '11:00:00', Partido::ESTADO_BORRADOR);
        app(ProgramacionJornadaService::class)->actualizarPartido($borrador, $this->edicion($borrador, '12:00'), $this->programador);
        Mail::assertNothingQueued();

        $publicado = $this->partido('Cobras', 'Águilas', '09:00:00', Partido::ESTADO_PROGRAMADO);
        app(ProgramacionJornadaService::class)->actualizarPartido($publicado, $this->edicion($publicado, '14:00') + ['motivo' => 'Cambio de horario solicitado por la liga.'], $this->programador);
        Mail::assertQueuedCount(3);
        Mail::assertQueued(AvisoProgramacion::class, fn (AvisoProgramacion $aviso): bool => $aviso->hasTo('cobras@liga.test') && str_contains($aviso->mensaje, 'a las 14:00 hrs'));
    }

    // Si la operación se revierte, no sale ningún correo
    public function test_si_la_operacion_se_revierte_no_sale_ningun_aviso(): void
    {
        Mail::fake();
        $partido = $this->partido('Cobras', 'Águilas', '09:00:00', Partido::ESTADO_PROGRAMADO);

        try {
            DB::transaction(function () use ($partido): void {
                app(ReprogramacionPartidoService::class)->reprogramar($partido, $this->cambio(), $this->programador);

                throw new RuntimeException('Falla simulada después de reprogramar.');
            });
            $this->fail('Se esperaba la falla simulada.');
        } catch (RuntimeException $error) {
            $this->assertSame('Falla simulada después de reprogramar.', $error->getMessage());
        }

        $this->assertSame('09:00:00', $partido->refresh()->hora_partido);

        // Una publicación rechazada por conflictos (la misma cancha a las 10:00) tampoco avisa
        $this->partido('Delfines', 'Lobos', '10:00:00', Partido::ESTADO_BORRADOR);
        $this->jornada->update(['estado' => Jornada::ESTADO_APROBADA]);
        $this->assertThrows(fn () => app(PublicacionProgramacionService::class)->publicar($this->jornada, $this->programador), ValidationException::class);

        Mail::assertNothingQueued();
    }

    // De punta a punta, por la cola: cada aviso queda en el registro de correos como enviado
    public function test_los_avisos_quedan_en_el_registro_de_correos(): void
    {
        $partido = $this->partido('Cobras', 'Águilas', '09:00:00', Partido::ESTADO_PROGRAMADO);

        app(ReprogramacionPartidoService::class)->reprogramar($partido, $this->cambio(), $this->programador);

        $registros = RegistroCorreo::query()->where('tipo', 'aviso_programacion')->orderBy('id')->get();
        $this->assertSame([['cobras@liga.test'], ['cobras2@liga.test'], ['aguilas@liga.test']], $registros->pluck('destinatarios')->all());
        $this->assertSame([RegistroCorreo::ENVIADO], $registros->pluck('estado')->unique()->values()->all());
        $this->assertCount(3, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    /**
     * @return array{fecha_partido: string, hora_partido: string, cancha_id: int, arbitro_id: int, estado: string, motivo: string}
     */
    private function cambio(): array
    {
        return [
            'fecha_partido' => '2026-11-21',
            'hora_partido' => '16:00',
            'cancha_id' => $this->cancha2->id,
            'arbitro_id' => $this->arbitro->id,
            'estado' => Partido::ESTADO_REPROGRAMADO,
            'motivo' => 'Lluvia intensa en la cancha norte desde la mañana.',
        ];
    }

    /**
     * @return array{equipo_local_id: int, equipo_visitante_id: int, cancha_id: int, arbitro_id: int, fecha_partido: string, hora_partido: string}
     */
    private function edicion(Partido $partido, string $hora): array
    {
        return [
            'equipo_local_id' => $partido->equipo_local_id,
            'equipo_visitante_id' => $partido->equipo_visitante_id,
            'cancha_id' => $this->cancha1->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => $hora,
        ];
    }

    private function partido(string $local, string $visitante, string $hora, string $estado): Partido
    {
        return Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->equipos[$local]->id,
            'equipo_visitante_id' => $this->equipos[$visitante]->id,
            'cancha_id' => $this->cancha1->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => $hora,
            'estado' => $estado,
        ]);
    }

    private function delegado(string $correo, string $equipo, string $estatus = 'activo'): User
    {
        $usuario = $this->usuario($correo, 'Delegado del Club', $estatus);
        $usuario->update(['club_id' => $this->equipos[$equipo]->club_id]);

        return $usuario;
    }

    private function usuario(string $correo, string $rol, string $estatus = 'activo'): User
    {
        $usuario = User::query()->create([
            'name' => 'Usuario '.$correo,
            'email' => $correo,
            'password' => Hash::make('Clave-De-Prueba-2026'),
            'estatus' => $estatus,
            'requires_password_change' => false,
        ]);
        $usuario->assignRole($rol);

        return $usuario;
    }
}
