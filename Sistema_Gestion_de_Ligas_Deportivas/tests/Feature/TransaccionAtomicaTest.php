<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
use App\Models\Convocatoria;
use App\Models\Temporada;
use App\Models\User;
use App\Services\AuditService;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class TransaccionAtomicaTest extends TestCase
{
    // Manejo transaccional: conMotivo abre una transacción anidada (savepoint) dentro de la de la prueba
    use DatabaseTransactions;

    private AuditService $auditoria;

    private int $ligaId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auditoria = app(AuditService::class);

        $this->actingAs(User::create([
            'name' => 'Usuario Transacciones',
            'email' => 'transacciones@liga.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Administrador General',
        ]));

        $this->ligaId = DB::table('ligas')->insertGetId([
            'nombre' => 'Liga de Prueba de Transacciones',
            'estado' => 'activa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_una_falla_a_la_mitad_no_deja_la_temporada_ni_su_auditoria(): void
    {
        $antes = $this->conteos();

        $error = $this->capturarError(fn () => $this->auditoria->conMotivo('Alta que falla a la mitad', function () {
            Temporada::create($this->datosTemporada('Temporada Interrumpida'));

            throw new RuntimeException('Falla simulada a la mitad de la operación');
        }));

        $this->assertInstanceOf(RuntimeException::class, $error);
        $this->assertDatabaseMissing('temporadas', ['nombre' => 'Temporada Interrumpida']);
        $this->assertSame($antes, $this->conteos());
    }

    public function test_si_falla_la_segunda_escritura_no_queda_ninguna(): void
    {
        $antes = $this->conteos();

        $error = $this->capturarError(fn () => $this->auditoria->conMotivo('Alta de temporada con convocatoria', function () {
            $temporada = Temporada::create($this->datosTemporada('Temporada Sin Convocatoria'));

            // archivo_pdf admite 255 caracteres: MariaDB en modo estricto rechaza la inserción
            Convocatoria::create([
                'temporada_id' => $temporada->id,
                'archivo_pdf' => str_repeat('x', 300),
            ]);
        }));

        $this->assertInstanceOf(QueryException::class, $error);
        $this->assertDatabaseMissing('temporadas', ['nombre' => 'Temporada Sin Convocatoria']);
        $this->assertSame($antes, $this->conteos());
    }

    public function test_si_falla_el_registro_de_auditoria_se_revierte_el_cambio(): void
    {
        $temporada = Temporada::create($this->datosTemporada('Temporada Estable'));
        $antes = $this->conteos();
        $evento = 'eloquent.creating: '.AuditoriaLog::class;

        // Simula una falla al insertar la auditoría
        Event::listen($evento, function (): never {
            throw new RuntimeException('Falla simulada al registrar la auditoría');
        });

        try {
            $error = $this->capturarError(fn () => $this->auditoria->conMotivo('Cambio sin auditoría posible', fn () => $temporada->update([
                'nombre' => 'Temporada Alterada',
            ])));
        } finally {
            Event::forget($evento);
        }

        $this->assertInstanceOf(RuntimeException::class, $error);
        $this->assertSame('Falla simulada al registrar la auditoría', $error->getMessage());
        $this->assertSame('Temporada Estable', DB::table('temporadas')->where('id', $temporada->id)->value('nombre'));
        $this->assertSame($antes, $this->conteos());
    }

    public function test_una_operacion_exitosa_guarda_el_cambio_y_su_auditoria_con_el_motivo(): void
    {
        $antes = $this->conteos();

        $convocatoria = $this->auditoria->conMotivo('Alta oficial de la temporada', function () {
            $temporada = Temporada::create($this->datosTemporada('Temporada Confirmada'));

            return Convocatoria::create([
                'temporada_id' => $temporada->id,
                'archivo_pdf' => 'convocatorias/temporada_confirmada.pdf',
            ]);
        });

        $this->assertDatabaseHas('temporadas', ['id' => $convocatoria->temporada_id, 'nombre' => 'Temporada Confirmada']);
        $this->assertDatabaseHas('convocatorias', ['id' => $convocatoria->id]);
        $this->assertSame($antes['temporadas'] + 1, $this->conteos()['temporadas']);
        $this->assertSame($antes['convocatorias'] + 1, $this->conteos()['convocatorias']);
        $this->assertSame($antes['auditoria_logs'] + 2, $this->conteos()['auditoria_logs']);

        foreach (['temporadas' => $convocatoria->temporada_id, 'convocatorias' => $convocatoria->id] as $tabla => $id) {
            $log = AuditoriaLog::query()
                ->where('tabla_afectada', $tabla)
                ->where('registro_id', $id)
                ->where('accion', 'crear')
                ->sole();

            $this->assertSame('Alta oficial de la temporada', $log->motivo);
            $this->assertSame(auth()->id(), $log->usuario_id);
        }
    }

    /**
     * @return array{temporadas: int, convocatorias: int, auditoria_logs: int}
     */
    private function conteos(): array
    {
        return [
            'temporadas' => DB::table('temporadas')->count(),
            'convocatorias' => DB::table('convocatorias')->count(),
            'auditoria_logs' => DB::table('auditoria_logs')->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function datosTemporada(string $nombre): array
    {
        return [
            'liga_id' => $this->ligaId,
            'nombre' => $nombre,
            'fecha_inicio' => '2027-01-10',
            'fecha_fin' => '2027-06-30',
            'estado' => 'borrador',
        ];
    }

    // Ejecuta la operación y devuelve la excepción que lanzó; falla si no lanzó ninguna
    private function capturarError(Closure $operacion): Throwable
    {
        try {
            $operacion();
        } catch (Throwable $e) {
            return $e;
        }

        $this->fail('La operación debió fallar.');
    }
}
