# SGLD · Sistema de Gestión para la Liga Municipal de Morelia

Sistema web para administrar la operación de la **Liga Municipal de Fútbol Amateur de Morelia, A.C.**: cuentas y roles, configuración de temporadas, canchas, afiliación de equipos y jugadores, calendario de partidos, resultados, régimen disciplinario y portal público.

Es un proyecto académico de la materia Ingeniería de Software del **TecNM Campus Morelia**. Los requisitos están en una ERS basada en ISO/IEC/IEEE 29148 y el trabajo se organiza con Scrum.

> **Estado:** en desarrollo (octubre–diciembre de 2026).

## Módulos

| Módulo | Qué cubre |
|---|---|
| Núcleo, seguridad y gobernanza | Cuentas con uno o varios roles, contraseña temporal y permisos por rol |
| Afiliación y padrón | Registro de clubes, equipos y jugadores |
| Logística y calendario | Temporadas, categorías, grupos, canchas, disponibilidad y rol de juegos |
| Operación de juego y resultados | Cédulas arbitrales, marcadores y tabla de posiciones |
| Régimen disciplinario | Sanciones y su seguimiento |
| Finanzas y administración | Pagos y control administrativo |
| Fases finales | Liguilla y torneos especiales |
| Portal público | Consulta de calendario, resultados y posiciones |

## Tecnologías

- PHP 8.5 y Laravel 13
- MariaDB 12.3 LTS
- Blade, Tailwind CSS 4 y Vite 8
- PHPUnit, PHPStan y Laravel Pint

## Instalación local

El paso a paso completo (requisitos, Docker, variables de entorno, datos de ejemplo, cron y respaldos) está en el [manual técnico de instalación](Sistema_Gestion_de_Ligas_Deportivas/docs/manual-tecnico-instalacion.md). Este es el camino corto para tenerlo corriendo en cualquier equipo con Linux, macOS o Windows (WSL).

### 1. Requisitos

PHP 8.5 (con `pdo_mysql`, `mbstring`, `openssl`, `sodium`, `intl` y las demás extensiones del manual), Composer 2, Node.js 22 o más reciente, Docker y Git.

```bash
php -v && composer -V && node -v && docker --version
```

### 2. Código

```bash
git clone https://github.com/ultronLegacyy/Liga-Futbol.git
cd Liga-Futbol/Sistema_Gestion_de_Ligas_Deportivas
```

### 3. Base de datos y correo de prueba (Docker)

Elige tus propias contraseñas para `root` y para la cuenta `sgld`.

```bash
docker run -d --name sgld-mariadb --restart unless-stopped \
  -p 127.0.0.1:3306:3306 -v sgld-mariadb:/var/lib/mysql \
  -e MARIADB_ROOT_PASSWORD='<contraseña-de-root>' \
  -e MARIADB_DATABASE=sgld_db \
  -e MARIADB_USER=sgld -e MARIADB_PASSWORD='<contraseña-de-sgld>' \
  mariadb:12.3

# Mailpit atrapa los correos de la aplicación; ninguno sale a internet
docker run -d --name mailpit -p 127.0.0.1:1025:1025 -p 127.0.0.1:8025:8025 \
  -e MP_SMTP_AUTH="sgld:mailpit-local" -e MP_SMTP_AUTH_ALLOW_INSECURE=true \
  axllent/mailpit:v1.31.6
```

Si el equipo se reinicia: `docker start sgld-mariadb mailpit`.

### 4. Configuración e instalación

```bash
cp .env.example .env && chmod 600 .env
# En .env: DB_USERNAME=sgld y DB_PASSWORD con la contraseña de sgld del paso 3
composer install
php artisan key:generate
npm install && npm run build
php artisan migrate --seed          # tablas, 13 roles con sus permisos y datos de ejemplo
```

### 5. Levantarlo

Usa dos terminales, las dos dentro de `Sistema_Gestion_de_Ligas_Deportivas`:

```bash
php artisan serve          # terminal 1: la aplicación en http://127.0.0.1:8000
php artisan schedule:work  # terminal 2: cola de correos cada minuto, recordatorios de pago y respaldos
```

Los correos (avisos de programación, recordatorios, comunicados y recuperación de contraseña) llegan a http://127.0.0.1:8025.

### 6. Pruebas automáticas (opcional)

Usan una base aparte, `sgld_testing`. Créala una vez (te pide la contraseña de `root`):

```bash
docker exec -it sgld-mariadb mariadb -uroot -p \
  -e "CREATE DATABASE IF NOT EXISTS sgld_testing; GRANT ALL ON sgld_testing.* TO 'sgld'@'%';"
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test
```

### 7. Actualizar a la última versión

No uses `git pull`, porque el repositorio trabaja sin rebase:

```bash
git fetch origin
git switch develop
git merge --ff-only origin/develop
composer install && npm install && npm run build
php artisan migrate
php artisan db:seed --class=RoleAndPermissionSeeder   # permisos nuevos de cada versión
php artisan optimize:clear
```

En una base con datos reales, antes de migrar haz un respaldo: `php artisan respaldo:crear`.

## Cuentas de prueba

`php artisan migrate --seed` crea una cuenta por rol. Todas comparten la contraseña de ejemplo de `database/seeders/DatabaseSeeder.php`, que te da el equipo. **Úsalas solo para pruebas y demostraciones, nunca con datos reales ni en producción.**

| Rol | Cuenta | Qué puede probar |
|---|---|---|
| Administrador General | `admin@ligafutbol.com` | Todo el sistema, cuentas, permisos por rol y registro de correos (`/correos`) |
| Presidente | `presidente@ligafutbol.com` | Temporadas, convocatorias, reglas de la temporada, reportes financieros y autorización de acuerdos de asamblea |
| Secretario | `secretario@ligafutbol.com` | Asambleas, acuerdos y comunicados a grupos (`/comunicados/crear`) |
| Tesorero | `tesoreria@ligafutbol.com` | Cargos, pagos, adeudos de los clubes y recordatorios de pago |
| Comisión de la Rama Infantil | `infantil@ligafutbol.com` | Categorías infantiles y control de edad |
| Programador de Partidos | `programador@ligafutbol.com` | Canchas, jornadas, rol de juegos y su publicación |
| Capturista | `capturista@ligafutbol.com` | Captura y cierre de la cédula en papel |
| Tribunal de Penas | `tribunal@ligafutbol.com` | Expedientes, audiencias, sanciones y boletín de castigados |
| Comisión Arbitral | `comision.arbitral@ligafutbol.com` | Padrón de árbitros y asignación de ternas |
| Árbitro en Campo | `arbitro@ligafutbol.com` | Cédula digital de sus partidos y firma de los capitanes |
| Presidente del Club | `presidente.club@ligafutbol.com` y `directivo@moreliafc.com` | Su club, sus equipos y las sanciones de su club |
| Delegado del Club | `delegado@moreliafc.com` y `delegado.club@ligafutbol.com` | Jugadores de su club, avisos de programación y recordatorios de pago |
| Jugador | `jugador@ligafutbol.com` | Sus datos, su historial y sus sanciones |

Sin cuenta se puede consultar la programación pública en `/programacion`.
