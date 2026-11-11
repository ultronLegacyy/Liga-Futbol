<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
use App\Services\Respaldos\AlmacenRespaldos;
use App\Services\Respaldos\CifradoRespaldo;
use App\Services\Respaldos\DirectorioTrabajo;
use App\Services\Respaldos\PaqueteDocumentos;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use RuntimeException;
use Tests\TestCase;

// HU-009 (SCRUM-174 a SCRUM-176): crear, programar, depurar, restaurar y probar los respaldos cifrados (RNF-23)
#[RequiresPhpExtension('sodium')]
class RespaldosTest extends TestCase
{
    // La restauración deja su registro en auditoria_logs
    use DatabaseTransactions;

    private const VOLCADO = "-- volcado de prueba\nINSERT INTO usuarios VALUES (1,'admin');";

    private const CONTRASENA_BD = 'contrasena-secreta-bd';

    private string $temporal;

    private string $baseOperacion;

    // Lo que recibió el programa de importación por la entrada estándar
    private ?string $importado = null;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('respaldos');

        $this->temporal = sys_get_temp_dir().'/sgld-respaldos-prueba-'.uniqid();
        $conexion = (string) config('database.default');
        $this->baseOperacion = (string) config("database.connections.{$conexion}.database");

        config([
            'respaldos.clave' => base64_encode(random_bytes(32)),
            'respaldos.disco' => 'respaldos',
            'respaldos.trabajo' => $this->temporal.'/trabajo',
            'respaldos.restauracion.base' => 'sgld_restauracion_prueba',
            'respaldos.restauracion.carpeta' => $this->temporal.'/restauracion',
            "database.connections.{$conexion}.password" => self::CONTRASENA_BD,
            // Sin administrador: todos los programas usan la cuenta de la aplicación
            'database.administracion.usuario' => null,
        ]);

        $this->fingirProcesos();
        // Domingo 08/11/2026 a las 02:00 en Morelia
        $this->travelTo(CarbonImmutable::parse('2026-11-08 02:00:00', 'America/Mexico_City'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporal);

        parent::tearDown();
    }

    // Criterio 1: el diario lleva la base completa y solo los documentos creados o modificados desde el completo
    public function test_el_diario_trae_la_base_y_solo_los_documentos_que_cambiaron(): void
    {
        Storage::disk('local')->put('comprobantes/a.pdf', 'A1');
        Storage::disk('local')->put('convocatorias/b.pdf', 'B1');
        $this->crear(completo: true);

        $this->travelTo(CarbonImmutable::parse('2026-11-09 02:00:00', 'America/Mexico_City'));
        Storage::disk('local')->put('convocatorias/b.pdf', 'B2');
        Storage::disk('local')->put('comprobantes/c.pdf', 'C1');
        $salida = $this->crear();

        $this->assertStringContainsString('Respaldo diario-20261109-020000 creado en el disco «respaldos»: la base completa y 2 documentos que cambiaron desde completo-20261108-020000.', $salida);

        $manifiesto = $this->manifiesto('diario-20261109-020000');
        $this->assertSame('diario', $manifiesto['tipo']);
        $this->assertSame('completo-20261108-020000', $manifiesto['depende_de']);
        $this->assertSame(['comprobantes/a.pdf' => false, 'comprobantes/c.pdf' => true, 'convocatorias/b.pdf' => true], array_map(fn (array $huella): bool => $huella['incluido'], $manifiesto['documentos']));
        $this->assertSame(['comprobantes/c.pdf' => 'C1', 'convocatorias/b.pdf' => 'B2'], $this->documentosDelPaquete('diario-20261109-020000'));
        $this->assertSame(self::VOLCADO."\n", $this->baseDelRespaldo('diario-20261109-020000'));

        Process::assertRan(fn (PendingProcess $proceso): bool => $this->comando($proceso)[0] === 'mariadb-dump'
            && array_slice($this->comando($proceso), -4) === ['--single-transaction', '--routines', '--triggers', $this->baseOperacion]);
    }

    // Criterio 2: el completo lleva todos los documentos y no depende de otro respaldo
    public function test_el_completo_trae_todos_los_documentos(): void
    {
        Storage::disk('local')->put('comprobantes/a.pdf', 'A1');
        Storage::disk('local')->put('convocatorias/b.pdf', 'B1');
        Storage::disk('local')->put('.gitignore', '*');

        $this->assertStringContainsString('la base completa y 2 documentos.', $this->crear(completo: true));

        $manifiesto = $this->manifiesto('completo-20261108-020000');
        $this->assertNull($manifiesto['depende_de']);
        $this->assertSame(['comprobantes/a.pdf' => 'A1', 'convocatorias/b.pdf' => 'B1'], $this->documentosDelPaquete('completo-20261108-020000'));
        $this->assertSame(self::VOLCADO."\n", $this->baseDelRespaldo('completo-20261108-020000'));
    }

    // Criterios 1 a 4: el completo los domingos, el diario los demás días, la depuración y la prueba mensual
    public function test_la_programacion_queda_con_la_hora_de_mexico_y_sin_traslapes(): void
    {
        $eventos = collect(app(Schedule::class)->events())
            ->filter(fn (Event $evento): bool => str_contains((string) $evento->command, 'respaldo:'))
            ->mapWithKeys(fn (Event $evento): array => [
                trim((string) preg_replace('/^.*artisan.? /', '', (string) $evento->command)) => [$evento->expression, $evento->timezone, $evento->withoutOverlapping],
            ]);

        $this->assertSame([
            'respaldo:crear --completo' => ['0 3 * * 0', 'America/Mexico_City', true],
            'respaldo:crear' => ['0 3 * * 1,2,3,4,5,6', 'America/Mexico_City', true],
            'respaldo:depurar' => ['0 4 * * *', 'America/Mexico_City', true],
            'respaldo:probar' => ['0 5 1 * *', 'America/Mexico_City', true],
        ], $eventos->all());
    }

    // Criterio 3: todo se sube cifrado al disco configurado, con su SHA-256 en el manifiesto
    public function test_se_sube_cifrado_al_disco_configurado(): void
    {
        Storage::fake('otro_destino');
        config(['respaldos.disco' => 'otro_destino']);
        Storage::disk('local')->put('comprobantes/recibo.pdf', 'CURP GOMA800101HMNRRR09');

        $this->crear(completo: true);

        $destino = Storage::disk('otro_destino');
        $this->assertSame([], Storage::disk('respaldos')->allFiles());
        $this->assertSame([
            'completo-20261108-020000/'.AlmacenRespaldos::BASE,
            'completo-20261108-020000/'.AlmacenRespaldos::DOCUMENTOS,
            'completo-20261108-020000/'.AlmacenRespaldos::MANIFIESTO,
        ], $destino->allFiles());

        foreach ($destino->allFiles() as $parte) {
            $contenido = (string) $destino->get($parte);
            $this->assertStringStartsWith("SGLDRESP1\n", $contenido);
            $this->assertStringNotContainsString('GOMA800101HMNRRR09', $contenido);
            $this->assertStringNotContainsString('INSERT INTO usuarios', $contenido);
        }

        $manifiesto = $this->manifiesto('completo-20261108-020000');
        $this->assertSame(hash('sha256', (string) $destino->get('completo-20261108-020000/'.AlmacenRespaldos::BASE)), $manifiesto['partes'][AlmacenRespaldos::BASE]['sha256']);
    }

    // Criterio 3: más de 30 días se borra, salvo el completo de un diario vigente y el último completo
    public function test_la_depuracion_respeta_los_30_dias_y_la_cadena_completo_diario(): void
    {
        $dia = CarbonImmutable::parse('2026-11-08 02:00:00', 'America/Mexico_City');

        for ($i = 0; $i <= 44; $i++) {
            $this->travelTo($dia->addDays($i));
            $this->crear(completo: $dia->addDays($i)->isSunday());
        }

        // Carpetas sin manifiesto: una vieja y una de hace una hora
        Storage::disk('respaldos')->put('diario-20261201-050000/'.AlmacenRespaldos::BASE, 'a medias');
        Storage::disk('respaldos')->put('diario-20261222-030000/'.AlmacenRespaldos::BASE, 'a medias');

        // 22/12/2026 03:30: el límite es el 22/11 a las 03:30
        $this->travelTo(CarbonImmutable::parse('2026-12-22 03:30:00', 'America/Mexico_City'));
        $this->assertSame(0, Artisan::call('respaldo:depurar'));

        $quedan = Storage::disk('respaldos')->directories();
        // El completo del 22/11 tiene más de 30 días, pero el diario del 23/11 depende de él
        $this->assertContains('completo-20261122-020000', $quedan);
        $this->assertContains('diario-20261123-020000', $quedan);
        $this->assertNotContains('completo-20261115-020000', $quedan);
        $this->assertNotContains('diario-20261121-020000', $quedan);
        $this->assertNotContains('completo-20261108-020000', $quedan);
        $this->assertNotContains('diario-20261201-050000', $quedan);
        $this->assertContains('diario-20261222-030000', $quedan);
        // Los 30 de los últimos 30 días, el completo del 22/11 y la carpeta a medias de hace media hora
        $this->assertCount(32, $quedan);

        // Sin respaldos nuevos en meses, el último completo sigue ahí para poder restaurar
        $this->travelTo(CarbonImmutable::parse('2027-04-01 03:30:00', 'America/Mexico_City'));
        $this->assertSame(0, Artisan::call('respaldo:depurar'));
        $this->assertSame(['completo-20261220-020000'], Storage::disk('respaldos')->directories());
    }

    // Si falla el volcado no se sube nada, no quedan temporales y el mensaje no muestra la contraseña
    public function test_si_falla_el_volcado_no_se_sube_nada_ni_se_muestra_la_contrasena(): void
    {
        Process::fake(['*mariadb-dump*' => Process::result(
            errorOutput: "mariadb-dump: Got error: 1045: Access denied (password ".self::CONTRASENA_BD.')',
            exitCode: 2,
        )]);
        Log::spy();

        $this->assertSame(1, Artisan::call('respaldo:crear', ['--completo' => true]));
        $salida = Artisan::output();

        $this->assertStringContainsString('No se creó el respaldo. Falló el volcado de la base (código 2): mariadb-dump: Got error: 1045: Access denied (password [oculto])', $salida);
        $this->assertStringNotContainsString(self::CONTRASENA_BD, $salida);
        $this->assertStringNotContainsString((string) config('respaldos.clave'), $salida);
        $this->assertSame([], Storage::disk('respaldos')->allFiles());
        $this->assertSame([], File::allFiles($this->temporal.'/trabajo'));
        Log::shouldHaveReceived('error')->withArgs(fn (string $mensaje, array $contexto): bool => ! str_contains((string) json_encode($contexto), self::CONTRASENA_BD));
    }

    // La cuenta de la aplicación (sgld:usuario-bd) no puede volcar los triggers: se usa DB_ADMIN_* si existe
    public function test_el_volcado_usa_el_administrador_si_existe_y_si_no_la_cuenta_de_la_aplicacion(): void
    {
        $usuarioAplicacion = (string) config('database.connections.'.config('database.default').'.username');
        $this->crear(completo: true);
        Process::assertRan(fn (PendingProcess $proceso): bool => $this->comando($proceso)[0] === 'mariadb-dump'
            && in_array('--user='.$usuarioAplicacion, $this->comando($proceso), true)
            && ($proceso->environment['MYSQL_PWD'] ?? null) === self::CONTRASENA_BD);

        config(['database.administracion.usuario' => 'sgld_respaldos', 'database.administracion.password' => 'contrasena-admin-respaldos']);
        $this->travelTo(CarbonImmutable::parse('2026-11-08 02:01:00', 'America/Mexico_City'));
        $this->crear(completo: true);

        Process::assertRan(fn (PendingProcess $proceso): bool => $this->comando($proceso)[0] === 'mariadb-dump'
            && in_array('--user=sgld_respaldos', $this->comando($proceso), true)
            && ($proceso->environment['MYSQL_PWD'] ?? null) === 'contrasena-admin-respaldos');
        Process::assertNotRan(fn (PendingProcess $proceso): bool => str_contains(implode(' ', $this->comando($proceso)), 'contrasena-admin-respaldos'));
    }

    // Las advertencias (WARNING:) no tapan el error real del volcado
    public function test_el_error_del_volcado_muestra_el_error_real_y_no_las_advertencias(): void
    {
        $error = "mariadb-dump: Couldn't execute 'SHOW CREATE TRIGGER auditoria_logs_impedir_update': Access denied; you need (at least one of) the TRIGGER privilege(s) for this operation (1227)";
        Process::fake(['*mariadb-dump*' => Process::result(
            errorOutput: "WARNING: option --ssl-verify-server-cert is disabled, because of an insecure passwordless login.\n{$error}\n",
            exitCode: 2,
        )]);

        $this->assertSame(1, Artisan::call('respaldo:crear', ['--completo' => true]));
        $salida = Artisan::output();
        $this->assertStringContainsString('Falló el volcado de la base (código 2): '.$error, $salida);
        $this->assertStringNotContainsString('WARNING', $salida);

        // Si solo hay advertencias, se muestra la última
        Process::fake(['*mariadb-dump*' => Process::result(errorOutput: "WARNING: primera advertencia\nWARNING: última advertencia\n", exitCode: 2)]);

        $this->assertSame(1, Artisan::call('respaldo:crear', ['--completo' => true]));
        $this->assertStringContainsString('Falló el volcado de la base (código 2): WARNING: última advertencia', Artisan::output());
    }

    // Si falla la subida del manifiesto, se borran las partes que ya habían subido
    public function test_si_falla_la_subida_no_queda_un_respaldo_a_medias(): void
    {
        Storage::disk('local')->put('comprobantes/a.pdf', 'A1');
        $falso = Storage::disk('respaldos');
        $this->assertInstanceOf(FilesystemAdapter::class, $falso);
        $disco = Mockery::mock(FilesystemAdapter::class, [$falso->getDriver(), $falso->getAdapter(), $falso->getConfig()])->makePartial();
        $disco->shouldReceive('writeStream')->andReturnUsing(function (string $ruta, $recurso, array $opciones = []) use ($falso): bool {
            if (str_ends_with($ruta, AlmacenRespaldos::MANIFIESTO)) {
                throw new RuntimeException('R2 no responde');
            }

            return $falso->writeStream($ruta, $recurso, $opciones);
        });
        Storage::set('respaldos', $disco);

        $this->assertSame(1, Artisan::call('respaldo:crear', ['--completo' => true]));

        $this->assertStringContainsString('No se pudo subir manifiesto.json.cifrado al disco «respaldos»: R2 no responde', Artisan::output());
        $this->assertSame([], $falso->allFiles());
        $this->assertSame([], File::allFiles($this->temporal.'/trabajo'));
    }

    public function test_un_diario_sin_completo_previo_crea_un_completo_y_lo_avisa(): void
    {
        $salida = $this->crear();

        $this->assertStringContainsString('No había ningún respaldo completo: se creó uno completo en lugar del diario.', $salida);
        $this->assertSame(['completo-20261108-020000'], Storage::disk('respaldos')->directories());
    }

    // La contraseña de la base viaja en MYSQL_PWD, nunca en los argumentos de los programas
    public function test_la_contrasena_de_la_base_no_va_en_los_argumentos(): void
    {
        $this->crear(completo: true);
        $this->assertSame(0, Artisan::call('respaldo:restaurar'));

        Process::assertRanTimes(fn (PendingProcess $proceso): bool => ($proceso->environment['MYSQL_PWD'] ?? null) === self::CONTRASENA_BD, 3);
        Process::assertNotRan(fn (PendingProcess $proceso): bool => str_contains(implode(' ', $this->comando($proceso)), self::CONTRASENA_BD));
    }

    // Criterio 4, de principio a fin: completo y diario, y la restauración aplica los dos en una base y una carpeta aparte
    public function test_restaurar_un_diario_aplica_su_completo_y_despues_el_diario(): void
    {
        Storage::disk('local')->put('comprobantes/a.pdf', 'A1');
        Storage::disk('local')->put('convocatorias/b.pdf', 'B1');
        // e.pdf no cambia: el diario no lo lleva y se toma del completo
        Storage::disk('local')->put('comprobantes/e.pdf', 'E1');
        $this->crear(completo: true);
        $this->travelTo(CarbonImmutable::parse('2026-11-09 02:00:00', 'America/Mexico_City'));
        Storage::disk('local')->put('convocatorias/b.pdf', 'B2');
        Storage::disk('local')->put('comprobantes/c.pdf', 'C1');
        Storage::disk('local')->delete('comprobantes/a.pdf');
        Storage::disk('local')->put('comprobantes/d.pdf', 'D1');
        $this->crear();
        Storage::disk('local')->delete('comprobantes/d.pdf');

        $this->assertSame(0, Artisan::call('respaldo:restaurar'));

        $this->assertStringContainsString('Respaldo diario-20261109-020000 restaurado en la base «sgld_restauracion_prueba» y 4 documentos', Artisan::output());
        // a.pdf se borró antes del diario: no regresa; d.pdf se borró después: sí regresa
        $this->assertSame(
            ['comprobantes/c.pdf' => 'C1', 'comprobantes/d.pdf' => 'D1', 'comprobantes/e.pdf' => 'E1', 'convocatorias/b.pdf' => 'B2'],
            $this->archivosEn($this->temporal.'/restauracion')
        );
        $this->assertSame(self::VOLCADO."\n", $this->importado);
        Process::assertRan(fn (PendingProcess $proceso): bool => str_contains(implode(' ', $this->comando($proceso)), '--execute=DROP DATABASE IF EXISTS `sgld_restauracion_prueba`; CREATE DATABASE `sgld_restauracion_prueba`'));
        Process::assertRan(fn (PendingProcess $proceso): bool => $this->comando($proceso)[0] === 'mariadb' && last($this->comando($proceso)) === 'sgld_restauracion_prueba');

        // Un completo indicado por nombre trae sus propios documentos
        $this->assertSame(0, Artisan::call('respaldo:restaurar', ['respaldo' => 'completo-20261108-020000']));
        $this->assertSame(['comprobantes/a.pdf' => 'A1', 'comprobantes/e.pdf' => 'E1', 'convocatorias/b.pdf' => 'B1'], $this->archivosEn($this->temporal.'/restauracion'));
    }

    public function test_un_respaldo_alterado_se_rechaza_por_el_sha256(): void
    {
        $this->crear(completo: true);
        $parte = 'completo-20261108-020000/'.AlmacenRespaldos::BASE;
        $datos = (string) Storage::disk('respaldos')->get($parte);
        $datos[40] = chr(ord($datos[40]) ^ 0x01);
        Storage::disk('respaldos')->put($parte, $datos);

        $this->assertSame(1, Artisan::call('respaldo:restaurar'));

        $this->assertStringContainsString('El respaldo completo-20261108-020000 está alterado: el SHA-256 de base.sql.gz.cifrado no coincide con el manifiesto.', Artisan::output());
        $this->assertNull($this->importado);
        $this->assertDirectoryDoesNotExist($this->temporal.'/restauracion');
    }

    // Sin --operacion nunca se toca la base de operación; con la opción, pide confirmación y queda en el log
    public function test_la_base_de_operacion_no_se_toca_sin_la_opcion_explicita(): void
    {
        Storage::disk('local')->put('comprobantes/a.pdf', 'A1');
        $this->crear(completo: true);
        Storage::disk('local')->put('comprobantes/a.pdf', 'cambiado después');

        $this->assertSame(0, Artisan::call('respaldo:restaurar'));
        Process::assertNotRan(fn (PendingProcess $proceso): bool => $this->comando($proceso)[0] === 'mariadb' && in_array($this->baseOperacion, $this->comando($proceso), true));

        $this->importado = null;
        $this->artisan('respaldo:restaurar', ['--operacion' => true])
            ->expectsConfirmation('¿Seguro que quieres sobrescribir los datos de operación?', 'no')
            ->expectsOutput('No se restauró nada.')
            ->assertFailed();
        $this->assertNull($this->importado);
        $this->assertSame('cambiado después', Storage::disk('local')->get('comprobantes/a.pdf'));

        Log::spy();
        $this->artisan('respaldo:restaurar', ['--operacion' => true])
            ->expectsConfirmation('¿Seguro que quieres sobrescribir los datos de operación?', 'yes')
            ->assertSuccessful();
        $this->assertSame(self::VOLCADO."\n", $this->importado);
        $this->assertSame('A1', Storage::disk('local')->get('comprobantes/a.pdf'));
        Process::assertRan(fn (PendingProcess $proceso): bool => $this->comando($proceso)[0] === 'mariadb' && last($this->comando($proceso)) === $this->baseOperacion);
        Process::assertNotRan(fn (PendingProcess $proceso): bool => str_contains(implode(' ', $this->comando($proceso)), "DROP DATABASE IF EXISTS `{$this->baseOperacion}`"));
        Log::shouldHaveReceived('warning')->with('Restauración sobre la base y los documentos de operación', ['respaldo' => 'completo-20261108-020000', 'base' => $this->baseOperacion]);
    }

    // RNF-33: la restauración queda en la auditoría después de importar, con el respaldo, la base y los documentos
    public function test_la_restauracion_queda_en_la_auditoria(): void
    {
        Storage::disk('local')->put('comprobantes/a.pdf', 'A1');
        $this->crear(completo: true);
        $ultimoId = (int) AuditoriaLog::query()->max('id');

        $this->artisan('respaldo:restaurar', ['--operacion' => true])
            ->expectsConfirmation('¿Seguro que quieres sobrescribir los datos de operación?', 'yes')
            ->assertSuccessful();

        $registro = AuditoriaLog::query()->where('id', '>', $ultimoId)->sole();
        $this->assertSame('RESTAURACION_RESPALDO', $registro->accion);
        $this->assertSame('sistema', $registro->tabla_afectada);
        $this->assertNull($registro->usuario_id);
        $this->assertSame('Restauración del respaldo sobre la base de operación', $registro->motivo);
        $this->assertSame(['respaldo' => 'completo-20261108-020000', 'base' => $this->baseOperacion, 'documentos' => 1, 'operacion' => true], $registro->valores_nuevos);

        // En la base aparte también queda registrada, en la base de operación, con la base de destino
        $this->assertSame(0, Artisan::call('respaldo:restaurar'));
        $aparte = AuditoriaLog::query()->where('id', '>', $registro->id)->sole();
        $this->assertSame('RESTAURACION_RESPALDO', $aparte->accion);
        $this->assertSame(['respaldo' => 'completo-20261108-020000', 'base' => 'sgld_restauracion_prueba', 'documentos' => 1, 'operacion' => false], $aparte->valores_nuevos);
        $this->assertSame('Restauración del respaldo en la base aparte sgld_restauracion_prueba', $aparte->motivo);
    }

    // Si la importación falla, no queda un registro de restauración
    public function test_una_restauracion_fallida_no_queda_como_hecha_en_la_auditoria(): void
    {
        $this->crear(completo: true);
        $ultimoId = (int) AuditoriaLog::query()->max('id');
        Process::fake(['*' => Process::result(errorOutput: 'ERROR 1045 (28000): Access denied', exitCode: 1)]);

        $this->assertSame(1, Artisan::call('respaldo:restaurar'));
        $this->assertSame(0, AuditoriaLog::query()->where('id', '>', $ultimoId)->where('accion', 'RESTAURACION_RESPALDO')->count());
    }

    // Aunque la configuración apunte a la base de operación, nunca se recrea ni se borra
    public function test_la_base_de_restauracion_no_puede_ser_la_de_operacion(): void
    {
        $this->crear(completo: true);
        config(['respaldos.restauracion.base' => strtoupper($this->baseOperacion)]);

        $this->assertSame(1, Artisan::call('respaldo:restaurar'));
        $this->assertStringContainsString('La base de restauración no puede ser la de operación', Artisan::output());

        $this->assertSame(1, Artisan::call('respaldo:probar'));
        Process::assertNotRan(fn (PendingProcess $proceso): bool => str_contains(strtolower(implode(' ', $this->comando($proceso))), 'drop database'));
        $this->assertNull($this->importado);
    }

    // Criterio 4: con un respaldo de menos de 24 h se cumple, y lo restaurado se borra al final
    public function test_la_prueba_mensual_cumple_con_un_respaldo_reciente(): void
    {
        Storage::disk('local')->put('comprobantes/a.pdf', 'A1');
        $this->crear(completo: true);
        $this->travelTo(CarbonImmutable::parse('2026-11-08 07:00:00', 'America/Mexico_City'));

        $this->assertSame(0, Artisan::call('respaldo:probar'));
        $salida = Artisan::output();

        $this->assertStringContainsString('Respaldo probado: completo-20261108-020000 (1 documentos).', $salida);
        $this->assertMatchesRegularExpression('/RPO \(antigüedad del respaldo\)\s*\|\s*5 h\s*\|\s*≤ 24 h\s*\|\s*Cumple/u', $salida);
        $this->assertMatchesRegularExpression('/RTO \(tiempo de restauración\)\s*\|\s*[\d.]+ (s|min)\s*\|\s*≤ 4 h\s*\|\s*Cumple/u', $salida);
        $this->assertMatchesRegularExpression('/usuarios\s*\|\s*5/', $salida);
        $this->assertStringContainsString('CUMPLE: RPO ≤ 24 h y RTO ≤ 4 h, con datos en las tablas principales.', $salida);
        Process::assertRan(fn (PendingProcess $proceso): bool => str_contains(implode(' ', $this->comando($proceso)), '--execute=DROP DATABASE IF EXISTS `sgld_restauracion_prueba`')
            && ! str_contains(implode(' ', $this->comando($proceso)), 'CREATE'));
        $this->assertDirectoryDoesNotExist($this->temporal.'/restauracion');
    }

    public function test_la_prueba_mensual_no_cumple_con_un_respaldo_de_mas_de_24_horas(): void
    {
        $this->crear(completo: true);
        $this->travelTo(CarbonImmutable::parse('2026-11-09 08:00:00', 'America/Mexico_City'));

        $this->assertSame(1, Artisan::call('respaldo:probar'));
        $salida = Artisan::output();

        $this->assertMatchesRegularExpression('/RPO \(antigüedad del respaldo\)\s*\|\s*30 h\s*\|\s*≤ 24 h\s*\|\s*No cumple/u', $salida);
        $this->assertStringContainsString('NO CUMPLE: el respaldo tiene 30 h (RPO > 24 h).', $salida);
        $this->assertDirectoryDoesNotExist($this->temporal.'/restauracion');
    }

    public function test_la_prueba_mensual_no_cumple_si_una_tabla_principal_esta_vacia(): void
    {
        $this->fingirProcesos("3\t0\t13\t2\t4\t120");
        $this->crear(completo: true);

        $this->assertSame(1, Artisan::call('respaldo:probar'));
        $this->assertStringContainsString('NO CUMPLE: tablas sin filas: usuarios.', Artisan::output());
    }

    public function test_sin_respaldos_la_restauracion_y_la_prueba_lo_informan(): void
    {
        $this->assertSame(1, Artisan::call('respaldo:restaurar'));
        $this->assertStringContainsString('No hay respaldos en el disco «respaldos».', Artisan::output());

        $this->assertSame(1, Artisan::call('respaldo:probar'));
        $this->assertStringContainsString('NO CUMPLE: la restauración de prueba falló. No hay respaldos en el disco «respaldos».', Artisan::output());
    }

    // Si respaldo:crear muere a la mitad (kill -9), su carpeta temporal tiene el volcado sin cifrar: se borra al día siguiente
    public function test_la_depuracion_borra_las_carpetas_temporales_de_mas_de_un_dia(): void
    {
        $vieja = new DirectorioTrabajo();
        file_put_contents($vieja->ruta('base.sql.gz'), 'volcado sin cifrar');

        $this->travelTo(CarbonImmutable::parse('2026-11-09 03:00:00', 'America/Mexico_City'));
        $reciente = new DirectorioTrabajo();
        file_put_contents($reciente->ruta('base.sql.gz'), 'respaldo en curso');
        // Una carpeta que no es de DirectorioTrabajo no se toca
        File::ensureDirectoryExists($this->temporal.'/trabajo/otra-carpeta');

        $this->assertSame(0, Artisan::call('respaldo:depurar'));

        $this->assertStringContainsString('Carpetas temporales viejas borradas: 1', Artisan::output());
        $this->assertFileDoesNotExist($vieja->ruta('base.sql.gz'));
        $this->assertFileExists($reciente->ruta('base.sql.gz'));
        $this->assertDirectoryExists($this->temporal.'/trabajo/otra-carpeta');
    }

    // Criterio 3: en producción un disco local deja el respaldo en el mismo servidor
    public function test_en_produccion_un_disco_local_avisa_al_crear_y_no_cumple_en_la_prueba(): void
    {
        $this->app['env'] = 'production';
        Log::spy();

        $salida = $this->crear(completo: true);

        $this->assertStringContainsString('El disco de respaldos «respaldos» es local: en producción el respaldo debe quedar fuera del servidor (criterio 3). Configura RESPALDO_DISCO=r2.', $salida);
        $this->assertSame(['completo-20261108-020000'], Storage::disk('respaldos')->directories());
        Log::shouldHaveReceived('warning')->with('Respaldo en un disco local en producción', ['disco' => 'respaldos']);

        $this->travelTo(CarbonImmutable::parse('2026-11-08 03:00:00', 'America/Mexico_City'));
        $this->assertSame(1, Artisan::call('respaldo:probar'));
        $this->assertStringContainsString('NO CUMPLE: el disco «respaldos» es local: el respaldo no queda fuera del servidor (criterio 3).', Artisan::output());
    }

    public function test_fuera_de_produccion_o_con_un_disco_remoto_no_hay_aviso(): void
    {
        $this->assertStringNotContainsString('es local', $this->crear(completo: true));

        // Un disco de otro driver (como r2) sí queda fuera del servidor
        $this->app['env'] = 'production';
        config(['filesystems.disks.respaldos.driver' => 's3']);
        $this->travelTo(CarbonImmutable::parse('2026-11-08 02:01:00', 'America/Mexico_City'));

        $this->assertStringNotContainsString('es local', $this->crear(completo: true));
        $this->assertSame(0, Artisan::call('respaldo:probar'));
    }

    // Los errores del disco (R2 sin conexión) llegan en inglés: se informan en español y sin credenciales
    public function test_los_errores_del_disco_se_informan_en_espanol_y_sin_credenciales(): void
    {
        config(['filesystems.disks.r2.key' => 'AKIALLAVEDEPRUEBA', 'filesystems.disks.r2.secret' => 'secreto-r2-de-prueba']);
        $disco = $this->discoQueFalla();
        $disco->shouldReceive('directories')->andThrow(new RuntimeException("Unable to list contents for '', shallow listing. Credential AKIALLAVEDEPRUEBA / secreto-r2-de-prueba"));

        $this->assertSame(1, Artisan::call('respaldo:restaurar'));
        $salida = Artisan::output();
        $this->assertStringContainsString('No se restauró el respaldo. No se pudo consultar los respaldos en el disco «respaldos»: Unable to list contents', $salida);
        $this->assertStringNotContainsString('AKIALLAVEDEPRUEBA', $salida);
        $this->assertStringNotContainsString('secreto-r2-de-prueba', $salida);

        $this->assertSame(1, Artisan::call('respaldo:depurar'));
        $this->assertStringContainsString('No se depuraron los respaldos. No se pudo consultar los respaldos en el disco «respaldos»', Artisan::output());
    }

    public function test_un_error_al_descargar_o_borrar_se_informa_en_espanol(): void
    {
        $this->crear(completo: true);
        $disco = $this->discoQueFalla();
        $disco->shouldReceive('readStream')->andThrow(new RuntimeException('Unable to read file from location: completo-20261108-020000/manifiesto.json.cifrado.'));
        $disco->shouldReceive('deleteDirectory')->andThrow(new RuntimeException('Unable to delete directory located at: completo-20261108-020000.'));

        $this->assertSame(1, Artisan::call('respaldo:restaurar'));
        $this->assertStringContainsString('No se pudo descargar manifiesto.json.cifrado del respaldo completo-20261108-020000 en el disco «respaldos»: Unable to read file', Artisan::output());

        $this->expectExceptionMessage('No se pudo borrar el respaldo completo-20261108-020000 en el disco «respaldos»: Unable to delete directory');
        app(AlmacenRespaldos::class)->borrar('completo-20261108-020000');
    }

    // Disco de respaldos real (falso) al que cada prueba le hace fallar una operación
    private function discoQueFalla(): Mockery\MockInterface
    {
        $falso = Storage::disk('respaldos');
        $this->assertInstanceOf(FilesystemAdapter::class, $falso);
        $disco = Mockery::mock(FilesystemAdapter::class, [$falso->getDriver(), $falso->getAdapter(), $falso->getConfig()])->makePartial();
        Storage::set('respaldos', $disco);

        return $disco;
    }

    private function fingirProcesos(string $conteos = "3\t5\t13\t2\t4\t120"): void
    {
        $this->importado = null;

        Process::fake([
            '*mariadb-dump*' => Process::result(self::VOLCADO),
            '*COUNT(*)*' => Process::result($conteos),
            '*' => function (PendingProcess $proceso) {
                if (is_resource($proceso->input)) {
                    $this->importado = (string) stream_get_contents($proceso->input);
                }

                return Process::result();
            },
        ]);
    }

    private function crear(bool $completo = false): string
    {
        $codigo = Artisan::call('respaldo:crear', $completo ? ['--completo' => true] : []);
        $salida = Artisan::output();
        $this->assertSame(0, $codigo, $salida);

        return $salida;
    }

    /**
     * @return list<string>
     */
    private function comando(PendingProcess $proceso): array
    {
        return array_values(array_map('strval', (array) $proceso->command));
    }

    /**
     * @return array<string, mixed>
     */
    private function manifiesto(string $nombre): array
    {
        $trabajo = new DirectorioTrabajo();

        try {
            return app(AlmacenRespaldos::class)->manifiesto($nombre, $trabajo);
        } finally {
            $trabajo->limpiar();
        }
    }

    private function baseDelRespaldo(string $nombre): string
    {
        $trabajo = new DirectorioTrabajo();

        try {
            app(AlmacenRespaldos::class)->descargar($nombre, AlmacenRespaldos::BASE, $trabajo->ruta('base.cifrado'));
            app(CifradoRespaldo::class)->descifrar($trabajo->ruta('base.cifrado'), $trabajo->ruta('base.sql.gz'));

            return (string) gzdecode((string) file_get_contents($trabajo->ruta('base.sql.gz')));
        } finally {
            $trabajo->limpiar();
        }
    }

    /**
     * @return array<string, string>
     */
    private function documentosDelPaquete(string $nombre): array
    {
        $trabajo = new DirectorioTrabajo();

        try {
            app(AlmacenRespaldos::class)->descargar($nombre, AlmacenRespaldos::DOCUMENTOS, $trabajo->ruta('documentos.cifrado'));
            app(CifradoRespaldo::class)->descifrar($trabajo->ruta('documentos.cifrado'), $trabajo->ruta('documentos.gz'));
            app(PaqueteDocumentos::class)->extraer($trabajo->ruta('documentos.gz'), $trabajo->ruta('extraidos'));

            return $this->archivosEn($trabajo->ruta('extraidos'));
        } finally {
            $trabajo->limpiar();
        }
    }

    /**
     * @return array<string, string>
     */
    private function archivosEn(string $carpeta): array
    {
        if (! is_dir($carpeta)) {
            return [];
        }

        $archivos = [];

        foreach (File::allFiles($carpeta) as $archivo) {
            $archivos[str_replace('\\', '/', $archivo->getRelativePathname())] = $archivo->getContents();
        }

        ksort($archivos);

        return $archivos;
    }
}
