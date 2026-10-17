<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;

class ConfigurarUsuarioBdAplicacion extends Command
{
    // La aplicación solo puede leer e insertar en la auditoría (RNF-33)
    private const TABLA_AUDITORIA = 'auditoria_logs';

    // Conexión temporal con las credenciales de administrador
    private const CONEXION = 'sgld_administracion';

    protected $signature = 'sgld:usuario-bd
        {usuario : Usuario de MariaDB con el que se conecta la aplicación}
        {--host=localhost : Host desde el que se conecta la aplicación (% para cualquiera)}
        {--password= : Contraseña nueva; si se omite, se conserva la actual}';

    protected $description = 'Crea o actualiza el usuario de BD de la aplicación sin UPDATE ni DELETE sobre auditoria_logs (RNF-33)';

    public function handle(): int
    {
        $usuario = (string) $this->argument('usuario');
        $host = (string) $this->option('host');

        // Solo caracteres seguros: sin comillas, espacios ni barras invertidas
        if (preg_match('/^[A-Za-z0-9_.-]{1,80}$/', $usuario) !== 1) {
            $this->error('El usuario solo puede tener letras, números, guion bajo, punto o guion (máximo 80 caracteres).');

            return self::FAILURE;
        }

        if (preg_match('/^[A-Za-z0-9_.%:-]{1,255}$/', $host) !== 1) {
            $this->error('El host solo puede tener letras, números, punto, guion, dos puntos, guion bajo o %.');

            return self::FAILURE;
        }

        $adminUsuario = config('database.administracion.usuario');

        if (! is_string($adminUsuario) || $adminUsuario === '') {
            $this->error('Faltan las credenciales de administrador: define DB_ADMIN_USERNAME y DB_ADMIN_PASSWORD en el .env.');

            return self::FAILURE;
        }

        $base = config('database.connections.'.config('database.default'));

        if (! is_array($base) || ! in_array($base['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $this->error('La conexión por defecto debe ser MySQL o MariaDB.');

            return self::FAILURE;
        }

        $baseDatos = (string) ($base['database'] ?? '');

        if (preg_match('/^[A-Za-z0-9_$]{1,64}$/', $baseDatos) !== 1) {
            $this->error("El nombre de la base «{$baseDatos}» no es válido para este comando.");

            return self::FAILURE;
        }

        // Misma conexión que la aplicación, pero con las credenciales de administrador
        config(['database.connections.'.self::CONEXION => array_merge($base, [
            'url' => null,
            'username' => $adminUsuario,
            'password' => (string) config('database.administracion.password'),
        ])]);

        try {
            return $this->configurar(DB::connection(self::CONEXION), $usuario, $host, $baseDatos);
        } catch (QueryException|PDOException $e) {
            // Solo el mensaje del servidor: la consulta completa podría contener la contraseña
            $detalle = $e instanceof QueryException ? ($e->errorInfo[2] ?? 'error desconocido') : $e->getMessage();
            $this->error("MariaDB rechazó la operación: {$detalle}");

            return self::FAILURE;
        } finally {
            DB::purge(self::CONEXION);
        }
    }

    private function configurar(Connection $conexion, string $usuario, string $host, string $baseDatos): int
    {
        $cuenta = "'{$usuario}'@'{$host}'";
        $bd = $this->identificador($baseDatos);

        $tablas = array_map(fn ($fila) => (string) $fila->TABLE_NAME, $conexion->select(
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME",
            [$baseDatos]
        ));

        if (! in_array(self::TABLA_AUDITORIA, $tablas, true)) {
            $this->error("La tabla auditoria_logs no existe en {$baseDatos}: ejecuta las migraciones antes.");

            return self::FAILURE;
        }

        $existe = (int) $conexion->selectOne(
            'SELECT COUNT(*) AS total FROM mysql.user WHERE User = ? AND Host = ?',
            [$usuario, $host]
        )->total > 0;

        // Con UPDATE o DELETE a nivel global o de base, la restricción por tabla no serviría
        $amplios = $existe ? $this->privilegiosAmplios($conexion, $cuenta, $baseDatos) : [];

        if ($amplios !== []) {
            $this->error("{$cuenta} ya tiene privilegios que permitirían modificar o borrar la auditoría; no se cambió nada:");

            foreach ($amplios as $privilegio) {
                $this->line("  - {$privilegio}");
            }

            $this->line("Retíralos con REVOKE usando exactamente el ámbito mostrado (por ejemplo, REVOKE ALL PRIVILEGES ON <ámbito> FROM {$cuenta}) y vuelve a ejecutar el comando.");

            return self::FAILURE;
        }

        $password = $this->option('password');

        if (! $existe && (! is_string($password) || $password === '')) {
            $password = $this->secret("Contraseña para el usuario nuevo {$cuenta}");
        }

        if (! $existe && (! is_string($password) || $password === '')) {
            $this->error('El usuario no existe y no se indicó una contraseña.');

            return self::FAILURE;
        }

        // PDO::exec directo y contraseña con PDO::quote: no pasa por el registro de consultas de Laravel
        $pdo = $conexion->getPdo();

        if (! $existe) {
            $pdo->exec("CREATE USER IF NOT EXISTS {$cuenta} IDENTIFIED BY ".$pdo->quote((string) $password));
        } elseif (is_string($password) && $password !== '') {
            $pdo->exec("ALTER USER {$cuenta} IDENTIFIED BY ".$pdo->quote($password));
        }

        // Quita cualquier privilegio previo sobre la auditoría que no sea SELECT o INSERT
        $sobrantes = array_values(array_filter(
            array_map(fn ($fila) => (string) $fila->PRIVILEGE_TYPE, $conexion->select(
                "SELECT PRIVILEGE_TYPE FROM information_schema.TABLE_PRIVILEGES WHERE GRANTEE = ? AND TABLE_SCHEMA = ? AND TABLE_NAME = ? AND PRIVILEGE_TYPE NOT IN ('SELECT', 'INSERT')",
                [$cuenta, $baseDatos, self::TABLA_AUDITORIA]
            )),
            fn (string $privilegio) => preg_match('/^[A-Z ]+$/', $privilegio) === 1
        ));

        if ($sobrantes !== []) {
            $conexion->unprepared('REVOKE '.implode(', ', $sobrantes)." ON {$bd}.".$this->identificador(self::TABLA_AUDITORIA)." FROM {$cuenta}");
        }

        foreach ($tablas as $tabla) {
            $privilegios = $tabla === self::TABLA_AUDITORIA ? 'SELECT, INSERT' : 'SELECT, INSERT, UPDATE, DELETE';
            $conexion->unprepared("GRANT {$privilegios} ON {$bd}.".$this->identificador($tabla)." TO {$cuenta}");
        }

        $this->info("Usuario {$cuenta} configurado en la base {$baseDatos}.");
        $this->line('  - Usuario: '.($existe ? 'ya existía' : 'creado'));
        $this->line('  - Contraseña: '.(! $existe || (is_string($password) && $password !== '') ? 'asignada' : 'sin cambios'));
        $this->line('  - SELECT, INSERT, UPDATE, DELETE en '.(count($tablas) - 1).' tablas');
        $this->line('  - Solo SELECT, INSERT en auditoria_logs');

        if ($sobrantes !== []) {
            $this->line('  - Retirado de auditoria_logs: '.implode(', ', $sobrantes));
        }

        $this->warn('Vuelve a ejecutar este comando después de cada migración nueva: las tablas nuevas no reciben privilegios solas.');

        return self::SUCCESS;
    }

    /**
     * Privilegios globales o de base que incluyen UPDATE o DELETE (ALL los incluye).
     *
     * @return list<string>
     */
    private function privilegiosAmplios(Connection $conexion, string $cuenta, string $baseDatos): array
    {
        $hallazgos = [];

        $globales = $conexion->select(
            "SELECT PRIVILEGE_TYPE FROM information_schema.USER_PRIVILEGES WHERE GRANTEE = ? AND PRIVILEGE_TYPE IN ('UPDATE', 'DELETE')",
            [$cuenta]
        );

        foreach ($globales as $fila) {
            $hallazgos[] = "{$fila->PRIVILEGE_TYPE} ON *.*";
        }

        // TABLE_SCHEMA es un patrón de LIKE (por ejemplo sgld\_testing), igual que en el GRANT original
        $deBase = $conexion->select(
            "SELECT TABLE_SCHEMA, PRIVILEGE_TYPE FROM information_schema.SCHEMA_PRIVILEGES WHERE GRANTEE = ? AND PRIVILEGE_TYPE IN ('UPDATE', 'DELETE') AND ? LIKE TABLE_SCHEMA",
            [$cuenta, $baseDatos]
        );

        foreach ($deBase as $fila) {
            $hallazgos[] = "{$fila->PRIVILEGE_TYPE} ON `{$fila->TABLE_SCHEMA}`.*";
        }

        return $hallazgos;
    }

    private function identificador(string $nombre): string
    {
        return '`'.str_replace('`', '``', $nombre).'`';
    }
}
