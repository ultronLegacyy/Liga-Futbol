<?php

namespace App\Services\Respaldos;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

// Volcado e importación con los programas configurados. La contraseña va en MYSQL_PWD, nunca en los argumentos
class ClienteMariaDb
{
    // Tiempo máximo de cada programa: el RTO es de 4 horas
    private const LIMITE_SEGUNDOS = 4 * 3600;

    // Nombre de la base de operación, la de la conexión por defecto
    public function baseDeOperacion(): string
    {
        return $this->identificador((string) $this->conexion()['database']);
    }

    /**
     * Vuelca la base de operación comprimida con gzip en $destinoGz, por bloques.
     */
    public function volcar(string $destinoGz): void
    {
        $gz = gzopen($destinoGz, 'wb6');

        if ($gz === false) {
            throw new RespaldoException('No se pudo crear el archivo comprimido de la base.');
        }

        $fallaCompresion = false;

        try {
            $proceso = $this->proceso(false)->start(
                [...$this->programa('volcado'), ...$this->argumentos(false), '--single-transaction', '--routines', '--triggers', $this->baseDeOperacion()],
                function (string $tipo, string $salida) use ($gz, &$fallaCompresion): void {
                    if ($tipo === 'out' && gzwrite($gz, $salida) === false) {
                        $fallaCompresion = true;
                    }
                }
            );
            $resultado = $proceso->wait();
        } finally {
            gzclose($gz);
        }

        if (! $resultado->successful()) {
            throw new RespaldoException('Falló el volcado de la base (código '.$resultado->exitCode().'): '.$this->detalle($resultado->errorOutput()));
        }

        if ($fallaCompresion) {
            throw new RespaldoException('Falló la compresión del volcado de la base; revisa el espacio en disco.');
        }
    }

    // Crea vacía la base de restauración
    public function prepararBase(string $base): void
    {
        $base = $this->noOperacion($base);

        $this->ejecutar("DROP DATABASE IF EXISTS `{$base}`; CREATE DATABASE `{$base}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    public function eliminarBase(string $base): void
    {
        $base = $this->noOperacion($base);

        $this->ejecutar("DROP DATABASE IF EXISTS `{$base}`");
    }

    // Borrar o recrear nunca alcanza a la base de operación, aunque la configuración esté mal
    private function noOperacion(string $base): string
    {
        $base = $this->identificador($base);

        if (strcasecmp($base, $this->baseDeOperacion()) === 0) {
            throw new RespaldoException("No se borra ni se recrea «{$base}»: es la base de operación.");
        }

        return $base;
    }

    /**
     * Importa el SQL descomprimido en la base indicada, leyéndolo como entrada estándar.
     */
    public function importar(string $base, string $archivoSql): void
    {
        $entrada = fopen($archivoSql, 'rb');

        if ($entrada === false) {
            throw new RespaldoException('No se pudo leer el volcado descomprimido de la base.');
        }

        try {
            $resultado = $this->proceso(true)->input($entrada)
                ->run([...$this->programa('importacion'), ...$this->argumentos(true), $this->identificador($base)]);
        } finally {
            fclose($entrada);
        }

        if (! $resultado->successful()) {
            throw new RespaldoException('Falló la importación de la base (código '.$resultado->exitCode().'): '.$this->detalle($resultado->errorOutput()));
        }
    }

    /**
     * Filas de cada tabla en la base restaurada, para la prueba mensual.
     *
     * @param  list<string>  $tablas
     * @return array<string, int>
     */
    public function contarFilas(string $base, array $tablas): array
    {
        $conteos = array_map(fn (string $tabla): string => '(SELECT COUNT(*) FROM `'.$this->identificador($tabla).'`)', $tablas);
        $salida = $this->ejecutar('SELECT '.implode(', ', $conteos), $base);
        $valores = preg_split('/\s+/', trim($salida)) ?: [];

        if (count($valores) !== count($tablas)) {
            throw new RespaldoException('No se pudieron contar las filas de las tablas principales de la base restaurada.');
        }

        return array_combine($tablas, array_map('intval', $valores));
    }

    private function ejecutar(string $sql, ?string $base = null): string
    {
        $comando = [...$this->programa('importacion'), ...$this->argumentos(true), '--batch', '--skip-column-names', '--execute='.$sql];

        if ($base !== null) {
            $comando[] = $this->identificador($base);
        }

        $resultado = $this->proceso(true)->run($comando);

        if (! $resultado->successful()) {
            throw new RespaldoException('MariaDB rechazó la operación de restauración (código '.$resultado->exitCode().'): '.$this->detalle($resultado->errorOutput()));
        }

        return $resultado->output();
    }

    // Para crear y borrar la base de restauración se usa el administrador, si está configurado
    private function proceso(bool $administrador): PendingProcess
    {
        return Process::timeout(self::LIMITE_SEGUNDOS)
            ->env(['MYSQL_PWD' => $this->credenciales($administrador)['password']]);
    }

    /**
     * @return list<string>
     */
    private function argumentos(bool $administrador): array
    {
        $conexion = $this->conexion();

        return [
            '--host='.$conexion['host'],
            '--port='.$conexion['port'],
            '--user='.$this->credenciales($administrador)['usuario'],
            '--default-character-set=utf8mb4',
        ];
    }

    /**
     * @return list<string>
     */
    private function programa(string $cual): array
    {
        $partes = preg_split('/\s+/', trim((string) config('respaldos.'.$cual))) ?: [];

        if ($partes === [] || $partes[0] === '') {
            throw new RespaldoException('Falta el programa de '.($cual === 'volcado' ? 'volcado (RESPALDO_VOLCADO)' : 'importación (RESPALDO_IMPORTACION)').'.');
        }

        return $partes;
    }

    /**
     * @return array{usuario: string, password: string}
     */
    private function credenciales(bool $administrador): array
    {
        $admin = config('database.administracion.usuario');

        if ($administrador && is_string($admin) && $admin !== '') {
            return ['usuario' => $admin, 'password' => (string) config('database.administracion.password')];
        }

        $conexion = $this->conexion();

        return ['usuario' => (string) $conexion['username'], 'password' => (string) ($conexion['password'] ?? '')];
    }

    /**
     * @return array<string, mixed>
     */
    private function conexion(): array
    {
        $conexion = config('database.connections.'.config('database.default'));

        if (! is_array($conexion) || ! in_array($conexion['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RespaldoException('Los respaldos de la base necesitan una conexión MySQL o MariaDB.');
        }

        return $conexion;
    }

    // Solo nombres seguros: se interpolan en SQL y en los argumentos
    private function identificador(string $nombre): string
    {
        if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $nombre) !== 1) {
            throw new RespaldoException("El nombre «{$nombre}» no es válido para una base o tabla.");
        }

        return $nombre;
    }

    private function detalle(string $error): string
    {
        $linea = strtok(RespaldoException::sinSecretos($error), "\n");

        return $linea === false ? 'sin detalle' : $linea;
    }
}
