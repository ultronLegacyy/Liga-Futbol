<?php

namespace Tests\Feature;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UsuarioBdAplicacionTest extends TestCase
{
    // Conexiones temporales de la prueba
    private const CONEXION_ADMIN = 'prueba_bd_administracion';
    private const CONEXION_APP = 'prueba_bd_aplicacion';

    public function test_el_usuario_de_la_aplicacion_no_puede_modificar_ni_borrar_la_auditoria(): void
    {
        // Sin credenciales de administrador no se pueden crear usuarios de MariaDB
        if (blank(config('database.administracion.usuario'))) {
            $this->markTestSkipped('Define DB_ADMIN_USERNAME y DB_ADMIN_PASSWORD para probar el usuario de BD de la aplicación.');
        }

        $usuario = 'sgld_prueba_'.bin2hex(random_bytes(4));
        $password = 'Prueba-'.bin2hex(random_bytes(8));

        try {
            // Desde Docker las conexiones no llegan como localhost, por eso el host es %
            $salida = Artisan::call('sgld:usuario-bd', [
                'usuario' => $usuario,
                '--host' => '%',
                '--password' => $password,
            ]);
            $this->assertSame(0, $salida, Artisan::output());

            $app = $this->conexion(self::CONEXION_APP, $usuario, $password);

            // UPDATE y DELETE sobre la auditoría se rechazan por falta de privilegios
            $this->assertSinPrivilegio(fn () => $app->update("UPDATE auditoria_logs SET accion = 'alterada' WHERE 1 = 0"));
            $this->assertSinPrivilegio(fn () => $app->delete('DELETE FROM auditoria_logs WHERE 1 = 0'));

            // Puede leer e insertar en la auditoría; la inserción se revierte para no dejar filas
            $this->assertIsNumeric($app->selectOne('SELECT COUNT(*) AS total FROM auditoria_logs')->total);

            $app->beginTransaction();
            $this->assertTrue($app->table('auditoria_logs')->insert([
                'accion' => 'prueba_privilegios',
                'tabla_afectada' => 'temporadas',
            ]));
            $app->rollBack();

            // En las demás tablas conserva UPDATE
            $this->assertSame(0, $app->update("UPDATE temporadas SET nombre = 'sin cambios' WHERE 1 = 0"));
        } finally {
            DB::purge(self::CONEXION_APP);
            $this->conexion(self::CONEXION_ADMIN)->statement("DROP USER IF EXISTS '{$usuario}'@'%'");
            DB::purge(self::CONEXION_ADMIN);
        }
    }

    public function test_rechaza_un_usuario_con_caracteres_no_permitidos(): void
    {
        $salida = Artisan::call('sgld:usuario-bd', [
            'usuario' => "x'@'%",
            '--password' => 'no-importa',
        ]);

        $this->assertSame(1, $salida);
        $this->assertStringContainsString('El usuario solo puede tener', Artisan::output());
    }

    // Conexión dinámica basada en la de la aplicación; sin usuario, usa las credenciales de administrador
    private function conexion(string $nombre, ?string $usuario = null, ?string $password = null): Connection
    {
        $base = config('database.connections.'.config('database.default'));

        config(["database.connections.{$nombre}" => array_merge($base, [
            'url' => null,
            'username' => $usuario ?? config('database.administracion.usuario'),
            'password' => $password ?? (string) config('database.administracion.password'),
        ])]);

        return DB::connection($nombre);
    }

    private function assertSinPrivilegio(Closure $consulta): void
    {
        try {
            $consulta();
            $this->fail('La consulta debió rechazarse por falta de privilegios.');
        } catch (QueryException $e) {
            // 1142: comando denegado al usuario para la tabla
            $this->assertSame(1142, $e->errorInfo[1] ?? null, $e->getMessage());
        }
    }
}
