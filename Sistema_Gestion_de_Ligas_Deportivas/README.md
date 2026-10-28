# Sistema de Gestion de Ligas Deportivas

Proyecto base desarrollado con Laravel 13 y MariaDB/MySQL para la administracion integral de ligas de futbol amateur y torneos deportivos.

---

## 1. Informacion Tecnica del Proyecto

- **Nombre del Proyecto:** Sistema_Gestion_de_Ligas_Deportivas
- **Proposito:** Plataforma web para la administracion integral de ligas de futbol amateur, programacion deportiva, arbitraje, justicia disciplinaria y gestion financiera.
- **Stack Tecnologico Base:**
  - Framework: Laravel 13
  - Lenguaje: PHP 8.4+
  - Motor de Base de Datos: MariaDB 12.2+ (puerto 3306) / MySQL 8.0+
  - Driver de Persistencia: PDO MySQL (`pdo_mysql`)
  - Autenticacion: Laravel Fortify 1.41 (Autenticacion headless, 2FA, passkeys)
  - Autorizacion y RBAC: Spatie Laravel-Permission 8.3 (Control de acceso basado en roles y permisos)
  - Testing: PHPUnit 11
- **Estrategia de Ramas:**
  - Rama principal de integracion: `develop`
  - Rama de produccion: `main`

---

## 2. Requisitos Previos

- PHP >= 8.2 (Recomendado PHP 8.4) con extensiones `pdo_mysql`, `mbstring`, `openssl`
- Composer 2.x
- MariaDB >= 10.5 o MySQL >= 8.0
- Base de datos creada: `sgld_db`

---

## 3. Configuracion del Entorno (.env)

El archivo `.env` del proyecto se encuentra configurado para interactuar con la base de datos MariaDB:

```env
APP_NAME="Sistema de Gestion de Ligas Deportivas"
APP_ENV=local
APP_KEY=base64:generar_con_php_artisan_key_generate
APP_DEBUG=true
APP_TIMEZONE=UTC
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sgld_db
DB_USERNAME=root
DB_PASSWORD=
```

---

## 4. Arquitectura de la Base de Datos

El diseno e implementacion del esquema relacional comprende un total de 41 tablas (35 tablas de logica de negocio y 6 tablas de seguridad y RBAC), organizadas en apego a la especificacion funcional `BASE_DE_DATOS.md` y las especificaciones de seguridad:

### Modulo 1: Core, Gobernanza, Autenticacion y RBAC
- `ligas`: Parametrizacion general de la liga deportiva, configuracion y estado.
- `clubes`: Entidades juridicas/deportivas afiliadas a la liga.
- `usuarios`: Cuentas de acceso y credenciales (incluye soporte 2FA: `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`).
- `password_reset_tokens`: Tokens de recuperacion de credenciales.
- `sessions`: Control de sesiones de usuario activas.
- `auditoria_logs`: Trazabilidad completa de acciones y cambios de datos.
- `asambleas`: Registro de convocatorias, quorum y actas de asambleas.
- `comunicados_noticias`: Boletines informativos oficiales de la liga.
- `registro_notificaciones`: Notificaciones enviadas por correo, SMS o internas.
- `roles`: Catalogo de roles de usuario (Spatie Laravel-Permission).
- `permissions`: Permisos especificos y granulares del sistema (Spatie Laravel-Permission).
- `model_has_roles`: Relacion polimorfica entre modelos (usuarios) y roles.
- `model_has_permissions`: Asignacion directa de permisos individuales a modelos.
- `role_has_permissions`: Matriz relacional de permisos asociados a cada rol.
- `passkeys`: Llaves de acceso y autenticacion WebAuthn vinculadas a la tabla `usuarios` (Laravel Fortify).

### Modulo 2: Estructura Deportiva y Temporadas
- `temporadas`: Ciclos deportivos anuales o semestrales.
- `convocatorias`: Requisitos y periodos oficiales de apertura deportiva.
- `categorias`: Divisiones por edad, rama y nivel competitivo.
- `grupos`: Subgrupos de competencia dentro de una categoria.
- `fases_torneo`: Fases del certamen (regular, liguilla, semifinal, final).

### Modulo 3: Entidades Participantes y Documentacion
- `directivos_club`: Representantes legales y operativos de cada club.
- `historial_ventas_club`: Registro de traspasos y cambios de propiedad de clubes.
- `equipos`: Escuadras deportivas inscritas bajo la representacion de un club.
- `personas`: Datos civiles y generales de jugadores, arbitros y cuerpo tecnico.
- `documentos_persona`: Expedientes digitales y validaciones documentales.
- `jugadores_equipos`: Fichajes, estatus de registro, dorsales y vigencias.
- `arbitros`: Colegiados oficiales, categoria arbitral y vigencia.

### Modulo 4: Logistica, Infraestructura y Programacion
- `canchas`: Campos deportivos disponibles para la liga.
- `bloqueos_cancha`: Periodos de mantenimiento o inactividad de campos.
- `prestamos_cancha`: Cesiones temporales de instalaciones entre clubes.
- `preferencias_equipo`: Horarios y dias predilectos para localia.
- `jornadas`: Fechas del calendario competitivo.
- `partidos`: Programacion de encuentros, sedes, horarios y estados.

### Modulo 5: Operacion de Partidos, Arbitraje y Justicia Deportiva
- `cedulas_arbitrales`: Acta electronica oficial del partido con marcador final.
- `eventos_partido`: Cronologia de goles, amonestaciones, expulsiones y sustituciones.
- `catalogo_articulos`: Reglamento de sanciones y articulos disciplinarios.
- `expedientes_disciplinarios`: Casos turnados a la comision disciplinaria.
- `sanciones`: Resoluciones, suspension de partidos y multas economicas.
- `recursos_inconformidad`: Apelaciones y revisiones formales interpuestas por clubes.

### Modulo 6: Competencia y Finanzas
- `tablas_posiciones`: Estadistica acumulada de rendimiento por grupo y fase.
- `llaves_liguilla`: Emparejamientos de fases finales (ida, vuelta, global).
- `cargos_financieros`: Conceptos de cobro (inscripciones, arbitrajes, multas).
- `pagos`: Transacciones registradas, metodos de pago y folios fiscales.

---

## 5. Migraciones

Las migraciones fueron estructuradas modularmente en `database/migrations/`:

1. `2026_10_13_000001_create_core_gobernanza_tables.php`
2. `2026_10_13_000002_create_estructura_deportiva_tables.php`
3. `2026_10_13_000003_create_participantes_documentos_tables.php`
4. `2026_10_13_000004_create_logistica_programacion_tables.php`
5. `2026_10_13_000005_create_operacion_arbitraje_disciplina_tables.php`
6. `2026_10_13_000006_create_competencia_finanzas_tables.php`
7. `2026_10_14_182649_add_two_factor_columns_to_users_table.php` (Columnas 2FA en tabla `usuarios` via Fortify)
8. `2026_10_14_182650_create_passkeys_table.php` (Tabla de passkeys con llave foranea a `usuarios`)
9. `2026_10_14_182723_create_permission_tables.php` (Tablas relacionales RBAC de Spatie Permission)

Para ejecutar las migraciones desde cero:

```bash
php artisan migrate:fresh
```

---

## 6. Seeders y Datos de Prueba

El seeder integral se encuentra en `database/seeders/DatabaseSeeder.php`. Pobla un conjunto cohesionado de datos de prueba cubriendo las entidades del sistema y la infraestructura de seguridad/RBAC:

- Liga activa de muestra: Liga Municipal de Futbol Amateur de Morelia, A.C.
- Generacion de los 13 roles con cuenta de la seccion 2.3 de la ERS via Spatie (HU-080): `Administrador General`, `Presidente`, `Secretario`, `Tesorero`, `Comision de la Rama Infantil`, `Programador de Partidos`, `Capturista`, `Tribunal de Penas`, `Comisión Arbitral`, `Árbitro en Campo`, `Presidente del Club`, `Delegado del Club` y `Jugador`.
- Asignacion automatica de roles Spatie a los usuarios seed (`Administrador General`, `Presidente`, `Tesorero`, `Presidente del Club` y `Árbitro en Campo`).
- 2 Clubes con directivos, historial y 2 equipos por club.
- Temporada 2026-2027 con convocatorias, categoria Primera Especial, grupos y fases.
- 4 Jugadores federados con expediente documental y asignacion de equipo.
- Arbitros colegiados con gafete asignado.
- Infraestructura de canchas (Cancha Principal Felix Cerda Loza), bloqueos y prestamos.
- Jornada 1 con partido programado y finalizado.
- Cedula arbitral oficial con goles, tarjetas amarillas y eventos de juego.
- Catalogo disciplinario, expediente abierto, sancion aplicada y recurso de inconformidad.
- Tabla general de posiciones actualizada.
- Cargos de inscripcion y registro de pago liquidado con folio de comprobante.

Para ejecutar el seeder:

```bash
php artisan db:seed
```

O en conjunto con la migracion limpia:

```bash
php artisan migrate:fresh --seed
```

---

## 7. Pruebas Automatizadas

Se crearon suites de pruebas automatizadas con PHPUnit en `tests/Feature/`:

1. `DatabaseSchemaTest.php`:
   - Existencia e integridad de las 35 tablas maestras y transaccionales de negocio.
   - Correcta ejecucion e insercion de datos del seeder en todas las entidades.
2. `AuthenticationAndRbacTest.php`:
   - Existencia de las tablas de seguridad y RBAC (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`, `passkeys`).
   - Existencia de las columnas 2FA (`two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`) en la tabla `usuarios`.
   - Existencia y definicion de los 8 roles oficiales del sistema y su correcta asignacion a los usuarios de prueba.
   - Registro y resolucion de rutas y componentes de autenticacion de Laravel Fortify.
   - Evaluacion dinamica de asignacion y revocacion de permisos mediante el trait `HasRoles` en el modelo `User`.
3. `SessionAndAuthFlowTest.php`:
   - Flujo de login y logout, validacion de cuenta activa (`activo`, `inactivo`, `suspendido`) y proteccion CSRF.
   - Expiracion diferenciada de sesion por inactividad (30 minutos para roles administrativos y 8 horas para operativos).
4. `RbacGovernanceAnd403Test.php`:
   - Validacion de los 27 permisos de gobernanza asignados al rol Administrador General.
   - Politicas de autorizacion `TemporadaPolicy` (bloqueo de edicion en temporada cerrada) y `UserPolicy` (prevencion de autoeliminacion).
   - Manejo de respuesta HTTP 403 Forbidden estandarizada (vista Blade y JSON estructurado).
5. `IdTamperingAndCrossAccessSecurityTest.php`:
   - Politicas de autorizacion `ClubPolicy` y `EquipoPolicy` para mitigacion de alteracion de identificadores en URLs (RNF-29).
   - Bloqueo de acceso cruzado con respuesta HTTP 403 en el 100% de los intentos no autorizados.

### Comando para ejecutar las pruebas:

```bash
php artisan test
```

### Resultado de ejecucion:

```text
Tests:    38 passed (166 assertions)
Duration: 3.85s
Result:   100% exitoso
```

---

## 8. Ejecucion del Servidor de Desarrollo

Para iniciar el servidor de desarrollo local de Laravel:

```bash
php artisan serve
```

Por defecto, la aplicacion estara disponible en:
```text
http://127.0.0.1:8000
```

### Comandos de Utilidad Frecuente

- **Consola interactiva de Laravel (Tinker):**
  ```bash
  php artisan tinker
  ```
- **Listar rutas registradas:**
  ```bash
  php artisan route:list
  ```
- **Limpiar cache de configuracion y rutas:**
  ```bash
  php artisan optimize:clear
  ```

---

## 9. Reporte Tecnico de Avance y Registro de Tareas

El registro detallado del avance tecnico, el historial de tareas completadas por cada desarrollador del equipo de ingenieria, sus componentes afectados y las matrices de definicion de terminado (DoD) de cada Sprint se encuentran centralizados en el documento exclusivo:

- [Reporte Tecnico de Avance y Registro de Tareas](Reporte%20Tecnico%20de%20Avance%20y%20Registro%20de%20Tareas.md) (`Reporte Tecnico de Avance y Registro de Tareas.md`).

---

## 10. Correo local con Mailpit

Mailpit atrapa todos los correos que envía la aplicación en desarrollo; ninguno sale a internet. Se levanta **con autenticación SMTP** (IC-03), igual que un servidor real: sin usuario y contraseña correctos rechaza el envío.

`.env.example` ya trae su configuración; si tu `.env` es anterior, copia estos valores:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=sgld
MAIL_PASSWORD=mailpit-local
```

`sgld` / `mailpit-local` son credenciales **solo locales** de ejemplo, no secretos reales.

Con Docker:

```bash
docker run -d --name mailpit -p 127.0.0.1:1025:1025 -p 127.0.0.1:8025:8025 \
  -e MP_SMTP_AUTH="sgld:mailpit-local" \
  -e MP_SMTP_AUTH_ALLOW_INSECURE=true \
  axllent/mailpit:v1.31.6
```

- `MP_SMTP_AUTH` define los pares `usuario:contraseña` que acepta Mailpit.
- `MP_SMTP_AUTH_ALLOW_INSECURE=true` permite autenticarse sin TLS; Mailpit lo ofrece solo para pruebas locales.
- Si ya tenías un contenedor `mailpit` sin autenticación, las variables no se le pueden agregar: detenlo (`docker stop mailpit`) y crea otro con un nombre distinto o, si ya no lo necesitas, elimínalo con `docker rm mailpit` antes de volver a crearlo.
- Bandeja de entrada: http://localhost:8025
- Detenerlo o reanudarlo: `docker stop mailpit` / `docker start mailpit`.
- Alternativa sin Docker: instalar el binario nativo de Mailpit (https://github.com/axllent/mailpit) y ejecutar `MP_SMTP_AUTH="sgld:mailpit-local" MP_SMTP_AUTH_ALLOW_INSECURE=true mailpit`, que usa los mismos puertos por defecto.

Prueba de envío (muestra mailer, host, puerto y si hay usuario, nunca la contraseña):

```bash
php artisan config:clear
php artisan sgld:probar-correo prueba@example.com
```

El correo llega a http://localhost:8025 con sus versiones HTML y texto plano. Si la contraseña es incorrecta o Mailpit no está levantado, el comando termina con un mensaje en español y el detalle queda en `storage/logs/laravel.log`.

### Correo en producción

En producción solo cambian variables de entorno; el código es el mismo (RNF-42):

| Variable | Qué se pone |
| --- | --- |
| `MAIL_HOST` | Servidor SMTP del proveedor |
| `MAIL_PORT` | `465` con SMTPS o `587` con STARTTLS |
| `MAIL_SCHEME` | `smtps` para el puerto 465; `smtp` para el 587 (STARTTLS se negocia solo si el servidor lo ofrece) |
| `MAIL_USERNAME` / `MAIL_PASSWORD` | Credenciales que entregue el proveedor |
| `MAIL_FROM_ADDRESS` | Dirección del dominio de la liga (sustituye al marcador `no-responder@example.com`) |

- Se configuran **solo en el `.env` del servidor**, nunca en el repositorio, en `.env.example` ni en este README (IC-04, RNF-42).
- El proveedor se definirá cuando se decida el hospedaje (Review del 30/10, V-09).
- Después de cambiar las variables:

```bash
php artisan config:clear
php artisan sgld:probar-correo <correo-de-prueba>
```

---

## 11. Túnel HTTPS para las Reviews

Para mostrar la aplicación en las Reviews con HTTPS (RNF-32) se usa el túnel rápido de Cloudflare, que no requiere cuenta. Hace falta tener instalado `cloudflared`.

1. Compilar los assets, porque el servidor de desarrollo de Vite no es accesible por el túnel (si `npm run dev` está corriendo, detenlo: mientras exista `public/hot` las vistas piden los assets a Vite):
   ```bash
   npm run build
   ```
2. Levantar la aplicación en el puerto 8000:
   ```bash
   php artisan serve
   ```
3. En otra terminal, abrir el túnel:
   ```bash
   cloudflared tunnel --url http://localhost:8000
   ```
   `cloudflared` muestra una URL `https://<aleatorio>.trycloudflare.com` que cambia en cada ejecución; esa es la que se comparte en la Review.

- La aplicación solo confía en los encabezados `X-Forwarded-*` cuando la petición llega desde `127.0.0.1` o `::1` (ver `bootstrap/app.php`); así genera enlaces `https://` detrás del túnel y registra la IP real del visitante sin que un cliente externo pueda falsificarla.
- Cerrar el túnel (`Ctrl+C`) al terminar la Review: mientras esté abierto, cualquiera con la URL puede entrar.
- Usarlo solo con datos de prueba (`php artisan migrate:fresh --seed`), nunca con datos reales.
