<?php

namespace Tests\Feature;

use App\Models\Cancha;
use App\Models\Categoria;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\Partido;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ProgramacionConcurrenciaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->limpiarRestosPreviosConcurrencia();
    }

    public function test_concurrencia_real_dos_procesos_compiten_por_misma_cancha_y_horario_sin_doble_reserva(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('La prueba de bloqueos concurrentes FOR UPDATE requiere MariaDB/MySQL.');
        }

        $ids = $this->crearFixtureConcurrenciaDobleCancha();

        try {
            $barrera = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sgld_barrier_'.uniqid('', true);

            $scriptA = $this->construirScriptAsignacion($ids['jornada_a'], $ids['cancha'], $ids['usuario'], $barrera);
            $scriptB = $this->construirScriptAsignacion($ids['jornada_b'], $ids['cancha'], $ids['usuario'], $barrera);

            $procesoA = $this->iniciarProcesoPhp($scriptA);
            $procesoB = $this->iniciarProcesoPhp($scriptB);

            // Liberar ambos procesos de forma simultánea tras arrancar Laravel
            usleep(350000);
            file_put_contents($barrera, 'go');

            $procesoA->wait();
            $procesoB->wait();
            @unlink($barrera);

            $this->assertTrue(
                $procesoA->isSuccessful(),
                'Proceso A falló: '.$procesoA->getErrorOutput().' | '.$procesoA->getOutput()
            );
            $this->assertTrue(
                $procesoB->isSuccessful(),
                'Proceso B falló: '.$procesoB->getErrorOutput().' | '.$procesoB->getOutput()
            );

            $resA = json_decode(trim($procesoA->getOutput()), true, 512, JSON_THROW_ON_ERROR);
            $resB = json_decode(trim($procesoB->getOutput()), true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame(1, ($resA['asignados'] ?? 0) + ($resB['asignados'] ?? 0), 'Solo un proceso debe asignar la cancha en ese horario.');
            $this->assertSame(1, ($resA['sin_asignar'] ?? 0) + ($resB['sin_asignar'] ?? 0), 'El proceso competidor debe detectar el empalme.');

            $perdedor = ($resA['asignados'] ?? 0) === 0 ? $resA : $resB;
            $this->assertSame('CANCHA_OCUPADA', $perdedor['conflictos'][0]['codigo_regla'] ?? null);

            // Confirmar en base de datos que jamás ocurrió doble reserva
            $ocupados = Partido::query()
                ->whereIn('id', [$ids['partido_a'], $ids['partido_b']])
                ->whereNotNull('cancha_id')
                ->whereNotNull('hora_partido')
                ->count();

            $this->assertSame(1, $ocupados);
        } finally {
            $this->limpiarFixtureConcurrencia($ids);
        }
    }

    public function test_concurrencia_real_asignacion_automatica_vs_enviar_a_revision_mantiene_consistencia(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('La prueba de bloqueos concurrentes FOR UPDATE requiere MariaDB/MySQL.');
        }

        $ids = $this->crearFixtureConcurrenciaRevision();

        try {
            $barrera = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sgld_barrier_rev_'.uniqid('', true);

            $scriptReasignar = $this->construirScriptReasignacion(
                $ids['jornada_a'],
                $ids['cancha'],
                $ids['usuario'],
                $barrera
            );
            $scriptEnviarRevision = $this->construirScriptEnviarARevision(
                $ids['jornada_a'],
                $ids['usuario'],
                $barrera
            );

            $procesoReasignar = $this->iniciarProcesoPhp($scriptReasignar);
            $procesoRevision = $this->iniciarProcesoPhp($scriptEnviarRevision);

            usleep(350000);
            file_put_contents($barrera, 'go');

            $procesoReasignar->wait();
            $procesoRevision->wait();
            @unlink($barrera);

            $this->assertTrue(
                $procesoReasignar->isSuccessful(),
                'Proceso reasignar falló: '.$procesoReasignar->getErrorOutput().' | '.$procesoReasignar->getOutput()
            );
            $this->assertTrue(
                $procesoRevision->isSuccessful(),
                'Proceso enviarARevision falló: '.$procesoRevision->getErrorOutput().' | '.$procesoRevision->getOutput()
            );

            $resReasignar = json_decode(trim($procesoReasignar->getOutput()), true, 512, JSON_THROW_ON_ERROR);
            $resRevision = json_decode(trim($procesoRevision->getOutput()), true, 512, JSON_THROW_ON_ERROR);

            $this->assertTrue($resRevision['ok'], 'enviarARevision debe completar limpiamente bajo el candado FOR UPDATE.');

            $jornadaActual = Jornada::query()->findOrFail($ids['jornada_a']);
            $partidoActual = Partido::query()->findOrFail($ids['partido_a']);

            $this->assertSame(Jornada::ESTADO_EN_REVISION, $jornadaActual->estado);

            // O bien enviarARevision ganó primero (motor aborta sin tocar 09:00:00), o el motor terminó antes (15:00:00 enviado a revisión)
            if (! $resReasignar['ok']) {
                $this->assertSame('validation', $resReasignar['error']);
                $this->assertSame('09:00:00', $partidoActual->hora_partido);
            } else {
                $this->assertSame('15:00:00', $partidoActual->hora_partido);
            }
        } finally {
            $this->limpiarFixtureConcurrencia($ids);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function crearFixtureConcurrenciaDobleCancha(): array
    {
        app(RoleAndPermissionSeeder::class)->run();

        $sufijo = str_replace('.', '', uniqid('conc', true));
        $usuario = $this->resolverUsuarioConcurrencia();

        $temporada = Temporada::factory()->activa()->create(['nombre' => 'Temp Conc '.$sufijo]);
        $categoria = Categoria::factory()->create(['temporada_id' => $temporada->id]);
        $grupoA = Grupo::factory()->create(['categoria_id' => $categoria->id, 'temporada_id' => $temporada->id]);
        $grupoB = Grupo::factory()->create(['categoria_id' => $categoria->id, 'temporada_id' => $temporada->id]);

        $cancha = Cancha::factory()->create([
            'nombre' => 'Cancha Concurrencia '.$sufijo,
            'estado' => 'Disponible',
            'capacidad_partidos_consecutivos' => 5,
        ]);

        $club = Club::create(['nombre' => 'Club Conc '.$sufijo]);
        $equipos = [];
        for ($i = 1; $i <= 4; $i++) {
            $equipos[] = Equipo::create([
                'club_id' => $club->id,
                'grupo_id' => $i <= 2 ? $grupoA->id : $grupoB->id,
                'temporada_id' => $temporada->id,
                'nombre' => "Equipo {$sufijo}-{$i}",
            ])->id;
        }

        $jornadaA = Jornada::create([
            'grupo_id' => $grupoA->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-12-05',
            'fecha_fin' => '2026-12-05',
            'estado' => Jornada::ESTADO_BORRADOR,
        ]);

        $jornadaB = Jornada::create([
            'grupo_id' => $grupoB->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-12-05',
            'fecha_fin' => '2026-12-05',
            'estado' => Jornada::ESTADO_BORRADOR,
        ]);

        $partidoA = Partido::create([
            'jornada_id' => $jornadaA->id,
            'equipo_local_id' => $equipos[0],
            'equipo_visitante_id' => $equipos[1],
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        $partidoB = Partido::create([
            'jornada_id' => $jornadaB->id,
            'equipo_local_id' => $equipos[2],
            'equipo_visitante_id' => $equipos[3],
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        return [
            'usuario' => $usuario->id,
            'temporada' => $temporada->id,
            'categoria' => $categoria->id,
            'grupos' => [$grupoA->id, $grupoB->id],
            'cancha' => $cancha->id,
            'club' => $club->id,
            'equipos' => $equipos,
            'jornadas' => [$jornadaA->id, $jornadaB->id],
            'jornada_a' => $jornadaA->id,
            'jornada_b' => $jornadaB->id,
            'partido_a' => $partidoA->id,
            'partido_b' => $partidoB->id,
            'partidos' => [$partidoA->id, $partidoB->id],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function crearFixtureConcurrenciaRevision(): array
    {
        app(RoleAndPermissionSeeder::class)->run();

        $sufijo = str_replace('.', '', uniqid('concrev', true));
        $usuario = $this->resolverUsuarioConcurrencia();

        $temporada = Temporada::factory()->activa()->create(['nombre' => 'Temp ConcRev '.$sufijo]);
        $categoria = Categoria::factory()->create(['temporada_id' => $temporada->id]);
        $grupo = Grupo::factory()->create(['categoria_id' => $categoria->id, 'temporada_id' => $temporada->id]);

        $cancha = Cancha::factory()->create([
            'nombre' => 'Cancha Rev '.$sufijo,
            'estado' => 'Disponible',
            'capacidad_partidos_consecutivos' => 5,
        ]);

        $club = Club::create(['nombre' => 'Club Rev '.$sufijo]);
        $e1 = Equipo::create(['club_id' => $club->id, 'grupo_id' => $grupo->id, 'temporada_id' => $temporada->id, 'nombre' => "EqR1 {$sufijo}"]);
        $e2 = Equipo::create(['club_id' => $club->id, 'grupo_id' => $grupo->id, 'temporada_id' => $temporada->id, 'nombre' => "EqR2 {$sufijo}"]);

        $jornada = Jornada::create([
            'grupo_id' => $grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-12-12',
            'fecha_fin' => '2026-12-12',
            'estado' => Jornada::ESTADO_BORRADOR,
        ]);

        $partido = Partido::create([
            'jornada_id' => $jornada->id,
            'equipo_local_id' => $e1->id,
            'equipo_visitante_id' => $e2->id,
            'cancha_id' => $cancha->id,
            'fecha_partido' => '2026-12-12',
            'hora_partido' => '09:00:00',
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        return [
            'usuario' => $usuario->id,
            'temporada' => $temporada->id,
            'categoria' => $categoria->id,
            'grupos' => [$grupo->id],
            'cancha' => $cancha->id,
            'club' => $club->id,
            'equipos' => [$e1->id, $e2->id],
            'jornadas' => [$jornada->id],
            'jornada_a' => $jornada->id,
            'partido_a' => $partido->id,
            'partidos' => [$partido->id],
        ];
    }

    /**
     * @param  array<string, mixed>  $ids
     */
    private function resolverUsuarioConcurrencia(): User
    {
        $usuario = User::query()->firstOrCreate(
            ['email' => 'concurrencia_programador@ligatest.com'],
            [
                'name' => 'Programador Concurrencia',
                'password' => Hash::make('password123'),
                'activo' => true,
            ]
        );
        $usuario->syncRoles(['Programador de Partidos']);

        return $usuario;
    }

    /**
     * @param  array<string, mixed>  $ids
     */
    private function limpiarFixtureConcurrencia(array $ids): void
    {
        // auditoria_logs es inmutable por trigger SQL; se limpian únicamente las entidades operativas de la prueba
        DB::table('partidos')->whereIn('id', $ids['partidos'])->delete();
        DB::table('jornadas')->whereIn('id', $ids['jornadas'])->delete();
        DB::table('canchas')->where('id', $ids['cancha'])->delete();
        DB::table('equipos')->whereIn('id', $ids['equipos'])->delete();
        DB::table('clubes')->where('id', $ids['club'])->delete();
        DB::table('grupos')->whereIn('id', $ids['grupos'])->delete();
        DB::table('categorias')->where('id', $ids['categoria'])->delete();
        DB::table('temporadas')->where('id', $ids['temporada'])->delete();
    }

    private function limpiarRestosPreviosConcurrencia(): void
    {
        $tempIds = DB::table('temporadas')->where('nombre', 'like', 'Temp Conc%')->pluck('id');
        if ($tempIds->isNotEmpty()) {
            $grupoIds = DB::table('grupos')->whereIn('temporada_id', $tempIds)->pluck('id');
            $jornadaIds = DB::table('jornadas')->whereIn('grupo_id', $grupoIds)->pluck('id');
            if ($jornadaIds->isNotEmpty()) {
                DB::table('partidos')->whereIn('jornada_id', $jornadaIds)->delete();
                DB::table('jornadas')->whereIn('id', $jornadaIds)->delete();
            }
            DB::table('equipos')->whereIn('temporada_id', $tempIds)->delete();
            DB::table('grupos')->whereIn('temporada_id', $tempIds)->delete();
            DB::table('categorias')->whereIn('temporada_id', $tempIds)->delete();
            DB::table('temporadas')->whereIn('id', $tempIds)->delete();
        }

        DB::table('clubes')->where('nombre', 'like', 'Club Conc%')->orWhere('nombre', 'like', 'Club Rev%')->delete();
        DB::table('canchas')->where('nombre', 'like', 'Cancha Concurrencia%')->orWhere('nombre', 'like', 'Cancha Rev%')->delete();
    }

    private function iniciarProcesoPhp(string $scriptPhp): Process
    {
        $conexion = (string) config('database.default');
        $cfg = config("database.connections.{$conexion}");

        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => $conexion,
            'DB_HOST' => (string) ($cfg['host'] ?? '127.0.0.1'),
            'DB_PORT' => (string) ($cfg['port'] ?? '3306'),
            'DB_DATABASE' => (string) ($cfg['database'] ?? 'sgld_testing'),
            'DB_USERNAME' => (string) ($cfg['username'] ?? 'root'),
            'DB_PASSWORD' => (string) ($cfg['password'] ?? ''),
            'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ];

        $proceso = new Process([PHP_BINARY, '-r', $scriptPhp], base_path(), $env);
        $proceso->setTimeout(30);
        $proceso->start();

        return $proceso;
    }

    private function preambuloScriptProceso(int $usuarioId, string $barrera): string
    {
        $basePath = var_export(base_path(), true);
        $barreraEsc = var_export($barrera, true);

        return <<<PHP
require {$basePath} . '/vendor/autoload.php';
\$app = require {$basePath} . '/bootstrap/app.php';
\$kernel = \$app->make(Illuminate\Contracts\Console\Kernel::class);
\$kernel->bootstrap();
Illuminate\Support\Facades\Auth::loginUsingId({$usuarioId});
\$deadline = microtime(true) + 10.0;
while (! file_exists({$barreraEsc}) && microtime(true) < \$deadline) {
    usleep(5000);
}
PHP;
    }

    private function construirScriptAsignacion(int $jornadaId, int $canchaId, int $usuarioId, string $barrera): string
    {
        return $this->preambuloScriptProceso($usuarioId, $barrera).<<<PHP

\$jornada = App\Models\Jornada::query()->findOrFail({$jornadaId});
\$servicio = \$app->make(App\Services\ProgramacionAutomaticaService::class);
\$res = \$servicio->asignarHorariosAJornada(\$jornada, [
    'horarios' => ['09:00'],
    'cancha_ids' => [{$canchaId}],
]);
echo json_encode(\$res, JSON_THROW_ON_ERROR);
PHP;
    }

    private function construirScriptReasignacion(int $jornadaId, int $canchaId, int $usuarioId, string $barrera): string
    {
        return $this->preambuloScriptProceso($usuarioId, $barrera).<<<PHP

\$jornada = App\Models\Jornada::query()->findOrFail({$jornadaId});
\$servicio = \$app->make(App\Services\ProgramacionAutomaticaService::class);
try {
    \$res = \$servicio->asignarHorariosAJornada(\$jornada, [
        'horarios' => ['15:00'],
        'cancha_ids' => [{$canchaId}],
        'reemplazar_existentes' => true,
    ]);
    echo json_encode(['ok' => true, 'resumen' => \$res], JSON_THROW_ON_ERROR);
} catch (Illuminate\Validation\ValidationException \$e) {
    echo json_encode(['ok' => false, 'error' => 'validation', 'messages' => \$e->errors()], JSON_THROW_ON_ERROR);
}
PHP;
    }

    private function construirScriptEnviarARevision(int $jornadaId, int $usuarioId, string $barrera): string
    {
        return $this->preambuloScriptProceso($usuarioId, $barrera).<<<PHP

\$jornada = App\Models\Jornada::query()->findOrFail({$jornadaId});
\$servicio = \$app->make(App\Services\PublicacionProgramacionService::class);
try {
    \$servicio->enviarARevision(\$jornada);
    echo json_encode(['ok' => true], JSON_THROW_ON_ERROR);
} catch (Illuminate\Validation\ValidationException \$e) {
    echo json_encode(['ok' => false, 'error' => 'validation', 'messages' => \$e->errors()], JSON_THROW_ON_ERROR);
}
PHP;
    }
}
