# Manual técnico de instalación

Este manual sirve para instalar el Sistema de Gestión de Ligas Deportivas (SGLD) en un equipo nuevo, sin ayuda del equipo de desarrollo. Siguiéndolo de corrido, la instalación con migraciones y datos de ejemplo debe tomar **una hora o menos** (HU-010, RNF-41).

Todo lo que cambia de un entorno a otro (base de datos, correo, dominio, túnel y almacenamiento de respaldos) se configura en el archivo `.env`, **sin tocar el código** (RNF-42).

> Los comandos se escriben desde la carpeta `Sistema_Gestion_de_Ligas_Deportivas/`, salvo que se indique otra cosa. Donde aparezca `<algo>`, sustitúyelo por tu valor; nunca subas esos valores al repositorio.

## 1. Requisitos

| Programa | Versión | Para qué |
| --- | --- | --- |
| PHP | 8.5 | La aplicación. Extensiones: `pdo_mysql`, `mbstring`, `openssl`, `sodium`, `intl`, `bcmath`, `gd`, `zip`, `fileinfo`, `curl`, `xml`, `tokenizer` y `ctype` |
| Composer | 2.x | Las dependencias de PHP |
| Node.js y npm | 22 LTS o más reciente | Compilar el CSS y el JavaScript (Vite 8) |
| MariaDB | 12.3 LTS | La base de datos. Este manual la corre en Docker |
| Docker | Cualquier versión reciente | MariaDB y, si se quiere, el correo de prueba (Mailpit) |
| Git | Cualquier versión reciente | Descargar el código |

Para comprobarlo:

```bash
php -v && php -m | grep -E 'pdo_mysql|sodium|intl'
composer -V
node -v && npm -v
docker --version
```

## 2. Descargar el código

```bash
git clone https://github.com/ultronLegacyy/Liga-Futbol.git
cd Liga-Futbol/Sistema_Gestion_de_Ligas_Deportivas
```

## 3. MariaDB en Docker

1. Crea el volumen y el contenedor. Elige dos contraseñas largas: una para `root` y otra para la cuenta de la aplicación (`sgld`). El puerto queda abierto solo para el mismo equipo (`127.0.0.1`), y `--restart unless-stopped` lo vuelve a encender si el equipo se reinicia.

   ```bash
   docker volume create sgld-mariadb
   docker run -d --name sgld-mariadb --restart unless-stopped \
     -p 127.0.0.1:3306:3306 \
     -v sgld-mariadb:/var/lib/mysql \
     -e MARIADB_ROOT_PASSWORD='<contraseña-de-root>' \
     -e MARIADB_DATABASE=sgld_db \
     -e MARIADB_USER=sgld \
     -e MARIADB_PASSWORD='<contraseña-de-sgld>' \
     mariadb:12.3
   ```

2. Comprueba que responde:

   ```bash
   docker exec sgld-mariadb mariadb-admin ping
   ```

3. **Solo si vas a correr las pruebas automáticas:** crea la base `sgld_testing` y dale permiso a `sgld`. Te pide la contraseña de `root`.

   ```bash
   docker exec -it sgld-mariadb mariadb -uroot -p \
     -e "CREATE DATABASE IF NOT EXISTS sgld_testing; GRANT ALL ON sgld_testing.* TO 'sgld'@'%';"
   ```

Si el contenedor se detiene, enciéndelo con `docker start sgld-mariadb`.

## 4. Variables de entorno

1. Copia el ejemplo y protégelo, porque guarda contraseñas:

   ```bash
   cp .env.example .env
   chmod 600 .env
   ```

2. Llena las variables. Las marcadas como **secreto** nunca se comparten ni se suben a Git.

| Qué cambia | Variables | Notas |
| --- | --- | --- |
| Aplicación | `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_KEY` (**secreto**) | `APP_KEY` se genera en el paso 5. En producción: `APP_ENV=production` y `APP_DEBUG=false` |
| Dominio | `APP_URL`, `SESSION_SECURE_COOKIE` | La dirección con la que se entra, por ejemplo `https://liga.ejemplo.mx`. Con HTTPS, `SESSION_SECURE_COOKIE=true` |
| Túnel o balanceador | `TRUSTED_PROXIES` | IP de los proxies cuyos encabezados `X-Forwarded-*` se aceptan, separadas por comas. Por defecto `127.0.0.1,::1`, que es el túnel de Cloudflare en el mismo equipo. **No uses `*`:** cualquiera podría falsificar su IP |
| Base de datos | `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (**secreto**) | Con el contenedor del paso 3: `127.0.0.1`, `3306`, `sgld_db`, `sgld` y su contraseña |
| Administrador de la base | `DB_ADMIN_USERNAME`, `DB_ADMIN_PASSWORD` (**secreto**) | Lo usan los respaldos y `php artisan sgld:usuario-bd`. En producción va el usuario `root` o un administrador |
| Correo | `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` (**secreto**), `MAIL_REQUIRE_TLS`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Los valores de `.env.example` son del Mailpit local (README, sección 10). En producción: el SMTP real y `MAIL_REQUIRE_TLS=true` |
| Respaldos (HU-009) | `RESPALDO_DISCO`, `RESPALDO_CLAVE` (**secreto**), `RESPALDO_VOLCADO`, `RESPALDO_IMPORTACION`, `RESPALDO_BASE_RESTAURACION` | Ver la sección 8 |
| Almacenamiento en la nube (R2) | `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY` (**secreto**), `R2_BUCKET`, `R2_ENDPOINT` | Solo si `RESPALDO_DISCO=r2` |
| Programación de partidos | `PARTIDO_DURACION_MINUTOS` | Minutos que un partido ocupa su cancha (por defecto 120) |

El resto (sesión, caché, cola y registros) puede quedarse como viene en `.env.example`.

Si después cambias el `.env` en un servidor con la configuración en caché, ejecuta `php artisan config:clear` (o `php artisan config:cache` en producción) para que tome los valores nuevos.

## 5. Dependencias, clave y compilación

```bash
composer install
php artisan key:generate
npm install
npm run build
```

`npm run build` compila los recursos de Vite en `public/build`.

## 6. Migraciones y datos de ejemplo

```bash
php artisan migrate --seed
```

Esto crea las tablas y carga `DatabaseSeeder`: la liga, una temporada, clubes, equipos, jugadores, árbitros, canchas, partidos, cédulas, finanzas, los 13 roles con sus permisos y usuarios de ejemplo para los roles (`admin@ligafutbol.com`, `arbitro@ligafutbol.com` y otros). Las contraseñas de esos usuarios de ejemplo te las da el equipo. **Úsalos solo para pruebas y demostraciones, nunca con datos reales.**

Datos opcionales:

| Comando | Qué deja |
| --- | --- |
| `php artisan db:seed --class=TemporadaCerradaDePruebaSeeder` | Una temporada cerrada con categorías y grupos, para consultar el historial |
| `php artisan db:seed --class=PruebaUsabilidadCedulaSeeder` | 5 partidos de hoy para la prueba de la cédula arbitral ([guion](arbitros/prueba-usabilidad-cedula.md)) |

Para empezar de cero en una base de prueba: `php artisan migrate:fresh --seed`. **Nunca lo corras en la base de operación:** borra todo.

### Cuenta de la aplicación sin permiso de borrar la auditoría (producción)

La bitácora de auditoría no debe poder modificarse (RNF-33). Con `DB_ADMIN_USERNAME` y `DB_ADMIN_PASSWORD` llenos, este comando crea o ajusta la cuenta de la aplicación sin `UPDATE` ni `DELETE` sobre `auditoria_logs`:

```bash
php artisan sgld:usuario-bd sgld --host=%
```

## 7. Levantar el sistema

- **En un equipo de desarrollo o demostración:**

  ```bash
  php artisan serve
  ```

  Entra a `http://localhost:8000`.
- **Con HTTPS para una demostración:** usa el túnel de Cloudflare del README, sección 11. Pon en `APP_URL` la dirección que te da el túnel y deja `TRUSTED_PROXIES=127.0.0.1,::1`.
- **En un servidor:**
  1. Apunta el servidor web (Nginx o Apache con PHP-FPM) a la carpeta `public/`.
  2. Da permiso de escritura al usuario del servidor web en `storage/` y `bootstrap/cache/`.
  3. Pon `APP_ENV=production` y `APP_DEBUG=false`.
  4. Ejecuta:

     ```bash
     php artisan config:cache
     php artisan route:cache
     php artisan view:cache
     ```

La cola (`QUEUE_CONNECTION=database`) no necesita un proceso aparte: hoy ninguna tarea se manda a la cola.

## 8. Tareas programadas (cron)

Los respaldos se ejecutan solos con el programador de Laravel:
- respaldo diario de lunes a sábado y completo el domingo, a las 3:00;
- depuración de los mayores de 30 días a las 4:00;
- prueba de restauración el día 1 de cada mes a las 5:00 (hora de Morelia).

Para que corran, agrega una sola línea al cron del usuario que ejecuta la aplicación (`crontab -e`):

```cron
* * * * * cd /ruta/a/Liga-Futbol/Sistema_Gestion_de_Ligas_Deportivas && php artisan schedule:run >> /dev/null 2>&1
```

Para comprobarlo: `php artisan schedule:list` muestra las 4 tareas y su próxima ejecución. La bitácora queda en `storage/logs/respaldos.log`.

## 9. Respaldo y restauración (HU-009)

Todo el detalle está en [respaldos-y-restauracion.md](respaldos-y-restauracion.md). Lo mínimo para dejarlo funcionando:

1. Genera la clave de cifrado una sola vez y ponla en `RESPALDO_CLAVE`:

   ```bash
   php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
   ```

   **Guárdala también fuera del servidor**, por ejemplo en el gestor de contraseñas de la liga. Sin ella, ningún respaldo se puede descifrar, y si la cambias, los respaldos anteriores quedan inservibles.
2. Si el equipo no tiene instalados los clientes de MariaDB, usa los del contenedor:

   ```dotenv
   RESPALDO_VOLCADO="docker exec -i -e MYSQL_PWD sgld-mariadb mariadb-dump"
   RESPALDO_IMPORTACION="docker exec -i -e MYSQL_PWD sgld-mariadb mariadb"
   ```

3. Destino: `RESPALDO_DISCO=respaldos` los guarda en `storage/app/respaldos`; `RESPALDO_DISCO=r2`, en Cloudflare R2 con las variables `R2_*`.
4. Cuenta de respaldos: el volcado lee los triggers de la auditoría y la prueba mensual crea su propia base de restauración, así que necesitan `DB_ADMIN_USERNAME` y `DB_ADMIN_PASSWORD`. Los permisos mínimos están en [respaldos-y-restauracion.md](respaldos-y-restauracion.md#configuración). Con el contenedor del paso 3, en un equipo de prueba basta la cuenta `sgld`, que tiene todos los permisos sobre `sgld_db`.
5. Prueba que funciona:

   ```bash
   php artisan respaldo:crear --completo
   ```

   Con la cuenta de administrador configurada, `php artisan respaldo:probar` también restaura el último respaldo aparte y muestra si cumple.

## 10. Comprobación final

- [ ] `http://localhost:8000` (o tu `APP_URL`) muestra la pantalla de inicio de sesión, en español.
- [ ] Entras con un usuario de ejemplo y ves su menú.
- [ ] `php artisan schedule:list` muestra las 4 tareas de respaldo.
- [ ] `php artisan respaldo:crear --completo` termina con «Respaldo completo-… creado».
- [ ] Opcional: con `sgld_testing` lista, `DB_DATABASE=sgld_testing php artisan migrate` y después `php artisan test` terminan sin fallas.

## 11. Problemas frecuentes

| Síntoma | Causa y solución |
| --- | --- |
| `SQLSTATE[HY000] [2002] Connection refused` | El contenedor está detenido: `docker start sgld-mariadb` |
| `SQLSTATE[HY000] [1045] Access denied` | `DB_USERNAME` o `DB_PASSWORD` no coinciden con los del contenedor |
| «No application encryption key has been specified» | Falta `APP_KEY`: `php artisan key:generate` |
| Detrás del túnel los enlaces salen con `http://` y los formularios fallan | `APP_URL` no es la dirección del túnel, o el proxy no está en `TRUSTED_PROXIES` |
| Un cambio en el `.env` no se nota | La configuración está en caché: `php artisan config:clear` |
| `respaldo:crear` dice que falta la clave | `RESPALDO_CLAVE` está vacía o no son 32 bytes en base64 |
