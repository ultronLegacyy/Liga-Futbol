<?php

namespace Tests\Feature;

use App\Exceptions\AuditoriaInmutableException;
use App\Models\AuditoriaLog;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditoriaInmutableTest extends TestCase
{
    // Manejo transaccional: la auditoría no se puede borrar, así que nada debe confirmarse
    use DatabaseTransactions;

    private AuditoriaLog $log;

    /**
     * Fila tal como quedó en la base al crearla.
     *
     * @var array<string, mixed>
     */
    private array $original;

    protected function setUp(): void
    {
        parent::setUp();

        $this->log = AuditoriaLog::create([
            'accion' => 'prueba_inmutable',
            'tabla_afectada' => 'temporadas',
            'registro_id' => 1,
            'valores_anteriores' => ['estado' => 'borrador'],
            'valores_nuevos' => ['estado' => 'activa'],
            'motivo' => 'Registro de prueba de inmutabilidad',
            'ip' => '198.51.100.7',
        ]);

        $this->original = $this->filaEnBase();
    }

    public function test_eloquent_no_permite_actualizar_un_registro_de_auditoria(): void
    {
        $this->assertRechazadoPorElModelo(fn () => $this->log->update(['accion' => 'alterada']));

        $this->assertRegistroIntacto();
    }

    public function test_eloquent_no_permite_eliminar_un_registro_de_auditoria(): void
    {
        $this->assertRechazadoPorElModelo(fn () => $this->log->delete());

        $this->assertRegistroIntacto();
    }

    public function test_el_trigger_bloquea_la_actualizacion_directa_con_query_builder(): void
    {
        $this->assertBloqueadoPorTrigger(fn () => DB::table('auditoria_logs')
            ->where('id', $this->log->id)
            ->update(['motivo' => 'Motivo alterado']));

        $this->assertRegistroIntacto();
    }

    public function test_el_trigger_bloquea_el_borrado_directo_con_query_builder(): void
    {
        $this->assertBloqueadoPorTrigger(fn () => DB::table('auditoria_logs')
            ->where('id', $this->log->id)
            ->delete());

        $this->assertRegistroIntacto();
    }

    public function test_el_trigger_bloquea_la_actualizacion_masiva_que_se_salta_los_eventos_del_modelo(): void
    {
        $this->assertBloqueadoPorTrigger(fn () => AuditoriaLog::query()
            ->whereKey($this->log->id)
            ->update(['accion' => 'alterada']));

        $this->assertRegistroIntacto();
    }

    public function test_el_trigger_bloquea_el_borrado_masivo_que_se_salta_los_eventos_del_modelo(): void
    {
        $this->assertBloqueadoPorTrigger(fn () => AuditoriaLog::query()
            ->whereKey($this->log->id)
            ->delete());

        $this->assertRegistroIntacto();
    }

    /**
     * @return array<string, mixed>
     */
    private function filaEnBase(): array
    {
        return (array) DB::table('auditoria_logs')->where('id', $this->log->id)->first();
    }

    // El registro sigue existiendo y con exactamente los mismos valores
    private function assertRegistroIntacto(): void
    {
        $this->assertNotSame([], $this->original);
        $this->assertSame($this->original, $this->filaEnBase());
    }

    private function assertRechazadoPorElModelo(Closure $operacion): void
    {
        try {
            $operacion();
            $this->fail('El modelo debió rechazar la operación.');
        } catch (AuditoriaInmutableException $e) {
            $this->assertStringContainsString('inmutables', $e->getMessage());
        }
    }

    private function assertBloqueadoPorTrigger(Closure $operacion): void
    {
        try {
            $operacion();
            $this->fail('El trigger debió rechazar la operación.');
        } catch (QueryException $e) {
            // SQLSTATE 45000: error lanzado por SIGNAL en el trigger
            $this->assertSame('45000', $e->getCode());
            $this->assertStringContainsString('La auditoría es inmutable', $e->getMessage());
        }
    }
}
