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
APP_KEY=base64:33pQW8lWkG42oDqZg2g0B76L69cffw2eW0uKk+B7U/g=
APP_DEBUG=true
APP_TIMEZONE=UTC
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sgld_db
DB_USERNAME=root
DB_PASSWORD=7500
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
- Generacion de los 8 roles base del sistema via Spatie: `Administrador General`, `Presidente`, `Secretario`, `Tesorero`, `Comision Disciplinaria`, `Directivo de Club`, `Arbitro` y `Consulta Publica`.
- Asignacion automatica de roles Spatie a los usuarios seed (`Administrador General`, `Directivo de Club` y `Arbitro`).
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

### Comando para ejecutar las pruebas:

```bash
php artisan test
```

### Resultado de ejecucion:

```text
Tests:    8 passed (68 assertions)
Duration: 0.28s
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

En este apartado se documenta el historial de tareas tecnicas implementadas en el proyecto, su responsable, alcance y evidencia de cumplimiento.

---

### Tarea 1: SCRUM-94 (S1-T02) — Diseno e Implementacion del Esquema Relacional de Base de Datos y Seeders

- **Identificador Jira:** `SCRUM-94`
- **Codigo Interno:** `S1-T02`
- **Responsable:** Rafael Piedra (Dev 2 — Backend Lead)
- **Sprint:** Sprint 1
- **Rama de Trabajo:** `Backend-Lead-Rafa`
- **Commit de Respaldo:** `4a57634`
- **Estado:** Completada y validada

#### Descripcion del Alcance Tecnico
1. **Inicializacion y Arquitectura:** Creacion del proyecto Laravel `Sistema_Gestion_de_Ligas_Deportivas` con configuracion de entorno `.env` enlazada a MariaDB (`sgld_db`).
2. **Persistencia Relacional (35 Tablas):** Codificacion de 6 migraciones modulares cubriendo los 6 modulos funcionales estipulados en `BASE_DE_DATOS.md` (Core/Gobernanza, Estructura Deportiva, Participantes/Documentos, Logistica/Programacion, Arbitraje/Disciplina, y Competencia/Finanzas).
3. **Poblado de Datos (Seeders):** Creacion del seeder maestro `DatabaseSeeder.php` con datos consistentes y estructurados para las 35 tablas, permitiendo recrear un ecosistema representativo completo de la liga deportiva.
4. **Verificacion Automatizada:** Creacion y ejecucion de la suite `tests/Feature/DatabaseSchemaTest.php` verificando la existencia fisica de todas las tablas y la integridad referencial de los datos sembrados (4 pruebas, 42 aserciones, 100% exitosas).
5. **Autonomia del Proyecto:** Integracion de `BASE_DE_DATOS.md` y un `.gitignore` integral dentro del proyecto que excluye credenciales, volcados SQL, bitacoras y dependencias pesadas.

#### Matriz de Cumplimiento (DoD)

| Requerimiento | Estado | Detalle de Implementacion |
|---|---|---|
| Nombre del proyecto Laravel | Cumplido | `Sistema_Gestion_de_Ligas_Deportivas` creado con Laravel 13 |
| Implementacion de BD segun `BASE_DE_DATOS.md` | Cumplido | 35 tablas relacionales creadas con tipos de datos e indices estrictos |
| Modularidad de migraciones | Cumplido | 6 migraciones tematicas respetando orden de dependencias de llaves foraneas |
| Configuracion `.env` | Cumplido | Conexion a MariaDB configurada y validada (`sgld_db`) |
| Seeder integral | Cumplido | Seeder en `DatabaseSeeder.php` con cobertura total de las 35 entidades |
| Pruebas automatizadas | Cumplido | 4 tests y 42 aserciones pasando sin errores |
| Flujo de ramas y Git | Cumplido | Desarrollado en rama `Backend-Lead-Rafa`, listo para PR hacia `develop` |

---

### Tarea 2: SCRUM-95 (S1-T03) — Instalacion y Configuracion de Laravel Fortify 1.41 y Spatie Laravel-Permission 8.3

- **Identificador Jira:** `SCRUM-95`
- **Codigo Interno:** `S1-T03`
- **Responsable:** Rafael Piedra (Dev 2 — Backend Lead)
- **Sprint:** Sprint 1
- **Rama de Trabajo:** `Backend-Lead-Rafa`
- **Estado:** Completada y validada

#### Descripcion del Alcance Tecnico
1. **Instalacion de Dependencias:** Incorporacion de paquetes oficiales via Composer:
   - `laravel/fortify` (v1.41.0): Backend de autenticacion headless (registro, login, recuperacion de credenciales, verificacion de correo, autenticacion en dos factores 2FA y passkeys).
   - `spatie/laravel-permission` (v8.3.0): Control de acceso granular basado en roles y permisos (RBAC) con persistencia relacional y cache optimizado.
2. **Configuracion de Laravel Fortify:**
   - Publicacion del archivo de configuracion `config/fortify.php`.
   - Registro del proveedor `FortifyServiceProvider` en `bootstrap/providers.php`.
   - Publicacion de acciones de autenticacion en `app/Actions/Fortify/*`.
   - Habilitacion de caracteristicas de seguridad, incluyendo restablecimiento de contrasenas, actualizacion de perfiles y autenticacion de dos factores (2FA).
3. **Adaptacion al Esquema de Base de Datos (`usuarios`):**
   - Ajuste de la migracion `2026_10_14_182649_add_two_factor_columns_to_users_table.php` para anadir las columnas `two_factor_secret`, `two_factor_recovery_codes` y `two_factor_confirmed_at` a la tabla `usuarios`.
   - Ajuste de la migracion `2026_10_14_182650_create_passkeys_table.php` para establecer la llave foranea `user_id` vinculada a la tabla `usuarios` con eliminacion en cascada.
4. **Configuracion de Spatie Laravel-Permission:**
   - Publicacion de configuracion `config/permission.php`.
   - Publicacion y ejecucion de la migracion relacional `2026_10_14_182723_create_permission_tables.php` que crea las tablas `roles`, `permissions`, `model_has_roles`, `model_has_permissions` y `role_has_permissions`.
   - Integracion de los traits `HasRoles` y `TwoFactorAuthenticatable` en el modelo `app/Models/User.php`.
5. **Registro de Middleware en Laravel 13:**
   - Registro de alias de middleware en `bootstrap/app.php`: `role`, `permission` y `role_or_permission` asociados a los manejadores de Spatie para proteccion granular de rutas y controladores.
6. **Poblado de Roles Oficiales del Sistema:**
   - Actualizacion de `database/seeders/DatabaseSeeder.php` para instanciar los 8 roles oficiales del sistema deportivo:
     - `Administrador General`
     - `Presidente`
     - `Secretario`
     - `Tesorero`
     - `Comision Disciplinaria`
     - `Directivo de Club`
     - `Arbitro`
     - `Consulta Publica`
   - Asignacion automatica de roles correspondientes a los usuarios sembrados (`adminUser` recibe `Administrador General`, `directivoUser` recibe `Directivo de Club`, y `arbitroUser` recibe `Arbitro`).
7. **Pruebas Automatizadas de Verificacion:**
   - Creacion de `tests/Feature/AuthenticationAndRbacTest.php` con 4 casos de prueba y 26 aserciones que certifican:
     - Presencia de tablas de seguridad y columnas 2FA en MariaDB.
     - Registro y asociacion efectiva de los 8 roles con los usuarios de prueba.
     - Integracion funcional de rutas y servicios de Laravel Fortify.
     - Dinamica de concesion, evaluacion y revocacion de permisos en el modelo `User`.

#### Matriz de Cumplimiento (DoD)

| Requerimiento | Estado | Detalle de Implementacion |
|---|---|---|
| Instalacion de Laravel Fortify 1.41 | Cumplido | Instalado via Composer; proveedor registrado en `bootstrap/providers.php` |
| Instalacion de Spatie Laravel-Permission 8.3 | Cumplido | Instalado via Composer; configuracion y migraciones publicadas |
| Adaptacion a tabla personalizada `usuarios` | Cumplido | Migraciones 2FA y passkeys enlazadas a `usuarios` en vez de `users` |
| Traits integrados en modelo User | Cumplido | `HasRoles` y `TwoFactorAuthenticatable` configurados en `app/Models/User.php` |
| Alias de middleware en Laravel 13 | Cumplido | `role`, `permission` y `role_or_permission` registrados en `bootstrap/app.php` |
| Definicion y poblado de 8 roles base | Cumplido | Generados en `DatabaseSeeder.php` y asignados a usuarios de muestra |
| Suite de pruebas de autenticacion y RBAC | Cumplido | 4 tests nuevos y 26 aserciones en `AuthenticationAndRbacTest.php` |
| Integridad de pruebas del proyecto | Cumplido | 8 tests y 68 aserciones ejecutadas exitosamente (100% aprobadas) |
| Flujo de ramas y Git | Cumplido | Desarrollado en rama `Backend-Lead-Rafa`, listo para integracion a `develop` |
