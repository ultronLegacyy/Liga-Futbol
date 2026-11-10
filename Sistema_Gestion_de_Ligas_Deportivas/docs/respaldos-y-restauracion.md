# Respaldos y restauración (HU-009, RNF-23)

El sistema respalda la base de datos y los documentos privados (convocatorias, comprobantes y los que vengan) en `storage/app/private`. Cada respaldo se **cifra antes de salir del servidor** y se guarda en el disco de `RESPALDO_DISCO`:

- `respaldos` (`storage/app/respaldos`), en desarrollo;
- `r2` (Cloudflare R2), en producción.

## Qué se respalda y cuándo

| Tarea | Horario (hora de México) | Qué hace |
| --- | --- | --- |
| `respaldo:crear --completo` | Domingo, 03:00 | La base completa y **todos** los documentos. |
| `respaldo:crear` | Lunes a sábado, 03:00 | La base completa y solo los documentos **creados o modificados** desde el último completo. |
| `respaldo:depurar` | Diario, 04:00 | Borra los respaldos de más de 30 días. Conserva el último completo y los completos de los que depende algún diario vigente. |
| `respaldo:probar` | Día 1 de cada mes, 05:00 | Restaura el último respaldo aparte y comprueba RPO ≤ 24 h y RTO ≤ 4 h. |

Las tareas necesitan el cron del programador en el servidor (HU-010):

```cron
* * * * * cd /ruta/del/sistema && php artisan schedule:run >> /dev/null 2>&1
```

La salida de cada tarea queda en `storage/logs/respaldos.log`. Los errores también quedan en el log de la aplicación.

Cada respaldo es una carpeta con su tipo y su fecha, por ejemplo `diario-20261109-030000`. Tiene estas partes:

- `base.sql.gz.cifrado`: el volcado de la base;
- `documentos.gz.cifrado`: los documentos;
- `manifiesto.json.cifrado`: el tipo, la fecha, el completo del que depende, la lista de documentos y el SHA-256 de cada parte.

El manifiesto se sube al final. Una carpeta sin manifiesto quedó a medias: no se usa para restaurar y `respaldo:depurar` la borra.

## La clave de cifrado

> **Guarda `RESPALDO_CLAVE` fuera del servidor**, por ejemplo en el gestor de contraseñas de la liga, con acceso para al menos dos personas. Si el servidor se pierde, **sin la clave los respaldos no sirven**: nadie puede descifrarlos, ni siquiera nosotros.

La clave son 32 bytes en base64. Se genera una vez:

```bash
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
```

Pégala en el `.env` como `RESPALDO_CLAVE=...`. No la cambies después: los respaldos anteriores solo se descifran con la clave con la que se crearon.

## Configuración

Variables del `.env`. Las que están vacías toman el valor por defecto:

| Variable | Para qué sirve |
| --- | --- |
| `RESPALDO_DISCO` | `respaldos` (por defecto) o `r2`. |
| `RESPALDO_CLAVE` | La clave de cifrado. |
| `RESPALDO_VOLCADO` | El programa de volcado (por defecto `mariadb-dump`). |
| `RESPALDO_IMPORTACION` | El programa de importación (por defecto `mariadb`). |
| `RESPALDO_BASE_RESTAURACION` | La base aparte para restaurar y para la prueba mensual (por defecto `sgld_restauracion`). |
| `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, `R2_ENDPOINT` | Las credenciales del bucket de R2. |

- **Contraseña de la base:** los programas la reciben en la variable de entorno `MYSQL_PWD`, nunca como argumento, así que no aparece en la lista de procesos.
- **Administrador:** el volcado, la importación y la base de restauración usan `DB_ADMIN_USERNAME` y `DB_ADMIN_PASSWORD`, si existen; si no, la cuenta de la aplicación. **En producción configura `DB_ADMIN_*`:** la cuenta de la aplicación que crea `sgld:usuario-bd` solo tiene SELECT, INSERT, UPDATE y DELETE por tabla, y el volcado falla al leer los triggers de la auditoría («you need (at least one of) the TRIGGER privilege(s)», error 1227).
- **Permisos de la cuenta de respaldos:**
  - para el volcado, sobre la base de operación: `SELECT`, `SHOW VIEW`, `TRIGGER` y `LOCK TABLES`;
  - para restaurar y para la prueba mensual: todos los permisos sobre la base de `RESPALDO_BASE_RESTAURACION`, porque la crea, la borra e importa en ella.

  Por ejemplo:

  ```sql
  CREATE USER 'sgld_respaldos'@'localhost' IDENTIFIED BY '<contraseña>';
  GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES ON sgld_db.* TO 'sgld_respaldos'@'localhost';
  GRANT ALL PRIVILEGES ON sgld_restauracion.* TO 'sgld_respaldos'@'localhost';
  ```

  Para restaurar con `--operacion` la cuenta necesita además permisos de escritura y de estructura sobre la base de operación. En un desastre conviene usar la cuenta `root` de MariaDB.
- **Base en Docker:** si MariaDB corre en Docker y el servidor no tiene los clientes, los programas pueden llevar argumentos:

  ```dotenv
  RESPALDO_VOLCADO="docker exec -i -e MYSQL_PWD sgld-mariadb mariadb-dump"
  RESPALDO_IMPORTACION="docker exec -i -e MYSQL_PWD sgld-mariadb mariadb"
  ```

## Restaurar en un servidor nuevo

1. Instala el sistema desde el repositorio, con `composer install`, y crea la base de operación vacía.
2. Copia el `.env` de producción con la **misma** `RESPALDO_CLAVE` y las credenciales de R2.
3. Revisa los respaldos disponibles en el bucket. Cada carpeta tiene la fecha en su nombre.
4. Restaura primero **aparte**, para comprobar que el respaldo sirve:

   ```bash
   php artisan respaldo:restaurar                          # el más reciente
   php artisan respaldo:restaurar diario-20261109-030000   # uno en particular
   ```

   El comando trae el respaldo y el completo del que depende, verifica el SHA-256 de cada parte y lo descifra. Después importa la base en `RESPALDO_BASE_RESTAURACION` y deja los documentos en `storage/app/restauracion`: primero los del completo y después los del diario.
5. Cuando el resultado sea correcto, restaura sobre la operación:

   ```bash
   php artisan respaldo:restaurar --operacion
   ```

   Pide confirmación y queda en el log. Reemplaza las tablas de la base de operación y escribe los documentos en `storage/app/private`. Hay que correrlo en una terminal interactiva; sin ella, la respuesta es «no».
6. Corre `php artisan migrate --force` por si el código es más nuevo que el respaldo. Después entra al sistema y revisa los datos.

Si el SHA-256 no coincide, el respaldo se alteró o se dañó: el comando no toca nada. Prueba con el respaldo anterior.

## Cómo leer la prueba mensual

`php artisan respaldo:probar` muestra el respaldo probado, una tabla de medidas y las filas de las tablas principales:

```
Respaldo probado: diario-20261130-030000 (37 documentos).
| RPO (antigüedad del respaldo) | 2 h     | ≤ 24 h | Cumple |
| RTO (tiempo de restauración)  | 1.4 min | ≤ 4 h  | Cumple |
| usuarios | 14 |
...
CUMPLE: RPO ≤ 24 h y RTO ≤ 4 h, con datos en las tablas principales.
```

- **RPO:** la antigüedad del respaldo más reciente, es decir, cuántos datos se perderían si el servidor fallara ahora. Si pasa de 24 h, el respaldo diario dejó de correr: revisa el cron y `storage/logs/respaldos.log`.
- **RTO:** lo que tardó la restauración completa (descarga, verificación, descifrado e importación). Si pasa de 4 h, hay que revisar el tamaño de los respaldos o la conexión con R2.
- **Tablas principales:** `migrations`, `usuarios`, `roles`, `temporadas`, `clubes` y `auditoria_logs` deben tener filas. Una tabla vacía indica un volcado incompleto.

La última línea dice `CUMPLE` o `NO CUMPLE`, con el motivo. El comando termina con código 0 si cumple y con 1 si no. Al final borra la base y la carpeta de prueba.
