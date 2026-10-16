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

En este apartado se documenta el historial de tareas tecnicas implementadas en el proyecto, organizadas por Sprint y clasificadas por cada desarrollador del equipo de ingenieria, detallando su alcance, componentes afectados y matrices de definicion de terminado (DoD).

---

### Sprint 1

#### 1. Rafael Piedra Sanchez (Dev 2 — Backend Lead)

##### Tarea 1: SCRUM-94 (S1-T02) — Diseno e Implementacion del Esquema Relacional de Base de Datos y Seeders

- **Identificador Jira:** `SCRUM-94`
- **Codigo Interno:** `S1-T02`
- **Responsable:** Rafael Piedra (Dev 2 — Backend Lead)
- **Sprint:** Sprint 1
- **Rama de Trabajo:** `Backend-Lead-Rafa`
- **Commit de Respaldo:** `4a57634`
- **Estado:** Completada y validada

###### Descripcion del Alcance Tecnico
1. **Inicializacion y Arquitectura:** Creacion del proyecto Laravel `Sistema_Gestion_de_Ligas_Deportivas` con configuracion de entorno `.env` enlazada a MariaDB (`sgld_db`).
2. **Persistencia Relacional (35 Tablas):** Codificacion de 6 migraciones modulares cubriendo los 6 modulos funcionales estipulados en `BASE_DE_DATOS.md` (Core/Gobernanza, Estructura Deportiva, Participantes/Documentos, Logistica/Programacion, Arbitraje/Disciplina, y Competencia/Finanzas).
3. **Poblado de Datos (Seeders):** Creacion del seeder maestro `DatabaseSeeder.php` con datos consistentes y estructurados para las 35 tablas, permitiendo recrear un ecosistema representativo completo de la liga deportiva.
4. **Verificacion Automatizada:** Creacion y ejecucion de la suite `tests/Feature/DatabaseSchemaTest.php` verificando la existencia fisica de todas las tablas y la integridad referencial de los datos sembrados (4 pruebas, 42 aserciones, 100% exitosas).
5. **Autonomia del Proyecto:** Integracion de `BASE_DE_DATOS.md` y un `.gitignore` integral dentro del proyecto que excluye credenciales, volcados SQL, bitacoras y dependencias pesadas.

###### Matriz de Cumplimiento (DoD)

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

##### Tarea 2: SCRUM-95 (S1-T03) — Instalacion y Configuracion de Laravel Fortify 1.41 y Spatie Laravel-Permission 8.3

- **Identificador Jira:** `SCRUM-95`
- **Codigo Interno:** `S1-T03`
- **Responsable:** Rafael Piedra (Dev 2 — Backend Lead)
- **Sprint:** Sprint 1
- **Rama de Trabajo:** `Backend-Lead-Rafa`
- **Estado:** Completada y validada

###### Descripcion del Alcance Tecnico
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

###### Matriz de Cumplimiento (DoD)

| Requerimiento | Estado | Detalle de Implementacion |
|---|---|---|
| Instalacion de Laravel Fortify 1.41 | Cumplido | Instalado via Composer; proveedor registrado en `bootstrap/providers.php` |
| Instalacion de Spatie Laravel-Permission 8.3 | Cumplido | Instalado via Composer; configuracion y migraciones publicadas |
| Adaptacion a tabla personalizada `usuarios` | Cumplido | Migraciones 2FA y passkeys enlazadas a `usuarios` en vez de `users` |
| Traits integrados en modelo User | Cumplido | `HasRoles` y `TwoFactorAuthenticatable` configurados en `app/Models/User.php` |
| Alias de middleware en Laravel 13 | Cumplido | `role`, `permission` y `role_or_permission` registrados en `bootstrap/app.php` |
| Definicion y poblado de 8 roles base | Cumplido | Generados en `DatabaseSeeder.php` y asignados a usuarios de muestra |
| Suite de pruebas de autenticacion y RBAC | Cumplido | 4 tests nuevos y 26 aserciones en `AuthenticationAndRbacTest.php` |
| Flujo de ramas y Git | Cumplido | Desarrollado en rama `Backend-Lead-Rafa`, integrado en `develop` via PR #4 |

---

##### Tarea 3: SCRUM-98 (S1-T06) — Flujo de Login/Logout con Fortify, Validacion de Estatus y Expiracion Diferenciada de Sesiones

- **Identificador Jira:** `SCRUM-98`
- **Codigo Interno:** `S1-T06`
- **Responsable:** Rafael Piedra (Dev 2 — Backend Lead)
- **Sprint:** Sprint 1
- **Rama de Trabajo:** `Backend-Lead-Rafa`
- **Estado:** Completada y validada

###### Descripcion del Alcance Tecnico
1. **Esquema de Usuarios y Estatus de Cuenta:**
   - Creacion y ejecucion de la migracion `database/migrations/2026_10_16_192041_add_estatus_to_usuarios_table.php`, agregando el campo `estatus` (`enum('activo', 'inactivo', 'suspendido')`, valor por defecto `'activo'`) a la tabla `usuarios`.
   - Incorporacion de la propiedad `estatus` dentro del arreglo `$fillable` en `app/Models/User.php`.
2. **Logica de Autenticacion Restringida por Estatus:**
   - Personalizacion del callback de autenticacion en `app/Providers/FortifyServiceProvider.php` mediante `Fortify::authenticateUsing()`.
   - Verificacion de correspondencia de credenciales hash (`Hash::check`).
   - Evaluacion obligatoria de cuenta activa (`$user->estatus === 'activo'`).
   - Emision de excepcion de validacion con mensaje claro en espanol ante cuentas no activas o suspendidas: *"Su cuenta no se encuentra activa en el sistema. Contacte al administrador."*.
3. **Controladores de Respuesta Fortify (Login / Logout):**
   - Creacion de `app/Http/Responses/LoginResponse.php` implementando `LoginResponseContract`: inicializa en la sesion el marcador `last_activity_time` con la estampa de tiempo actual (`now()->timestamp`) y redirige a la ruta destino configurada (`Fortify::redirects('login')`, default `/home`) o genera respuesta JSON 200 en peticiones asincronas.
   - Creacion de `app/Http/Responses/LogoutResponse.php` implementando `LogoutResponseContract`: remueve la marca de actividad, invalida la sesion de usuario, regenera el token CSRF y redirige al formulario de acceso con mensaje informativo en espanol.
   - Vinculacion de ambos contratos en el metodo `register()` de `FortifyServiceProvider`.
4. **Middleware de Expiracion Diferenciada de Sesiones (`CheckSessionTimeout`):**
   - Implementacion de `app/Http/Middleware/CheckSessionTimeout.php` con analisis en tiempo de ejecucion sobre los roles de Spatie asignados al usuario autenticado.
   - **Politica de 30 minutos (1,800 segundos):** Aplicada estrictamente a roles administrativos y de toma de decisiones criticas (`Administrador General`, `Presidente`, `Secretario`, `Tesorero`, `Comision Disciplinaria`).
   - **Politica de 8 horas (28,800 segundos):** Aplicada a roles operativos y de consulta (`Directivo de Club`, `Arbitro`, `Consulta Publica` u otros usuarios sin privilegios administrativos).
   - Accion ante expiracion: Cierre forzoso de sesion (`Auth::logout()`), invalidacion total de sesion, regeneracion de token CSRF y redireccion a `/login` con mensaje de alerta en espanol: *"Su sesion ha expirado por inactividad por motivos de seguridad."* (o emision de estado HTTP 401 Unauthorized en peticiones JSON).
   - Renovacion automatica de `last_activity_time` en cada interaccion valida dentro del periodo permitido.
5. **Registro de Middleware y Vistas en Laravel 13:**
   - Registro del alias `'session.timeout'` y anexion al grupo de middleware `'web'` en `bootstrap/app.php`.
   - Definicion de la vista de inicio de sesion en `FortifyServiceProvider` vinculada a `auth.login`.
6. **Interfaz de Usuario (Vista Blade de Autenticacion):**
   - Creacion de `resources/views/auth/login.blade.php` con maquetacion responsiva en Tailwind CSS.
   - Inyeccion obligatoria de directiva `@csrf`.
   - Despliegue de banners de alerta para estados de sesion (`session('status')`) y listado/resaltado de errores de validacion en espanol.
7. **Suite de Pruebas Automatizadas (PHPUnit):**
   - Creacion de `tests/Feature/SessionAndAuthFlowTest.php` con 9 casos de prueba exhaustivos bajo estrategia transaccional (`DatabaseTransactions`):
     - `test_usuario_activo_puede_iniciar_sesion_satisfactoriamente`
     - `test_usuario_inactivo_no_puede_iniciar_sesion_y_recibe_error_en_espanol`
     - `test_usuario_suspendido_no_puede_iniciar_sesion`
     - `test_credenciales_con_contrasena_invalida_son_rechazadas`
     - `test_cierre_de_sesion_destruye_la_sesion_y_regenera_tokens`
     - `test_sesion_de_administrador_expira_tras_30_minutos_de_inactividad`
     - `test_sesion_de_administrador_permanece_activa_dentro_de_los_30_minutos`
     - `test_sesion_de_usuario_operativo_continua_activa_a_los_45_minutos`
     - `test_sesion_de_usuario_operativo_expira_al_superar_las_8_horas`
   - Cobertura: 9 pruebas aprobadas, 37 aserciones exitosas, elevando la suite general del proyecto a 17 pruebas y 105 aserciones superadas sin errores.

###### Matriz de Cumplimiento (DoD)

| Requerimiento | Estado | Detalle de Implementacion |
|---|---|---|
| Campo de estatus en base de datos | Cumplido | Migracion ejecutada agregando columna `estatus` (`activo`, `inactivo`, `suspendido`) en `usuarios` |
| Modelo User actualizado | Cumplido | Campo `estatus` incluido en `$fillable` de `app/Models/User.php` |
| Autenticacion Fortify personalizada | Cumplido | `Fortify::authenticateUsing` valida credenciales y restringe acceso a usuarios no activos |
| Mensajeria de error en espanol | Cumplido | Mensajes descriptivos en espanol para cuentas inactivas, suspendidas y sesiones expiradas |
| Flujo de Login y registro de actividad | Cumplido | `LoginResponse.php` registra `last_activity_time` en la sesion y redirige a ruta destino |
| Flujo de Logout seguro | Cumplido | `LogoutResponse.php` limpia sesion, invalida credenciales y regenera token CSRF |
| Middleware de expiracion diferenciada | Cumplido | `CheckSessionTimeout.php` aplica regla de 30 minutos (roles administrativos) y 8 horas (roles operativos) |
| Registro de middleware en Laravel 13 | Cumplido | Alias `'session.timeout'` y grupo `'web'` configurados en `bootstrap/app.php` |
| Vista Blade de inicio de sesion | Cumplido | Implementada en `resources/views/auth/login.blade.php` con CSRF y estilos Tailwind CSS |
| Suite de pruebas automatizadas | Cumplido | 9 pruebas y 37 aserciones verificadas al 100% en `SessionAndAuthFlowTest.php` |
| Flujo de ramas y Git | Cumplido | Desarrollado en rama `Backend-Lead-Rafa` |

---

##### Tarea 4: SCRUM-99 (S1-T07) — Rol Administrador General, Permisos Base de Gobernanza y Respuesta 403

- **Identificador Jira:** `SCRUM-99`
- **Codigo Interno:** `S1-T07`
- **Responsable:** Rafael Piedra (Dev 2 — Backend Lead)
- **Sprint:** Sprint 1
- **Rama de Trabajo:** `Backend-Lead-Rafa`
- **Estado:** Completada y validada

###### Descripcion del Alcance Tecnico
1. **Seeder de Roles y Permisos Granulares de Gobernanza:**
   - Creacion de `database/seeders/RoleAndPermissionSeeder.php` e integracion dentro del flujo principal de `database/seeders/DatabaseSeeder.php`.
   - Registro de 27 permisos atomicos que cubren la totalidad de los modulos de administracion deportiva (panel de gobernanza, ligas, temporadas, convocatorias, categorias, clubes, campos, usuarios, roles y auditoria).
   - Asignacion consolidada del 100% de los permisos al rol `Administrador General`.
   - Asignacion de permisos base diferenciados a roles operativos (`Presidente`, `Secretario`, `Directivo de Club`, `Arbitro`, `Consulta Publica`).
2. **Politicas de Autorizacion (Policies):**
   - Creacion de `app/Policies/TemporadaPolicy.php` para la autorizacion de lectura, alta, edicion y eliminacion de temporadas, integrando la regla de negocio que bloquea cualquier modificacion o borrado sobre temporadas en estado `cerrada`.
   - Creacion de `app/Policies/UserPolicy.php` para la gestion de cuentas y perfiles de usuario, con proteccion estricta contra autoeliminacion de la cuenta en sesion.
   - Registro de politicas en `app/Providers/AppServiceProvider.php` mediante `Gate::policy()`.
3. **Proteccion de Rutas y Grupos de Gobernanza:**
   - Configuracion de rutas protegidas en `routes/web.php` bajo prefijo `/admin`:
     - `/admin/dashboard` y `/admin/gobernanza`: Protegidas con middleware `auth` y `role:Administrador General`.
     - `/admin/gestion/usuarios`: Protegida con middleware `auth` y `permission:usuarios.ver`.
4. **Manejo Estandarizado de Respuestas HTTP 403 Forbidden:**
   - Configuracion del interceptor de excepciones en `bootstrap/app.php` para capturar `UnauthorizedException` de Spatie y `AuthorizationException` de Laravel.
   - Peticiones Web: Renderizan la vista visual de error `resources/views/errors/403.blade.php`.
   - Peticiones AJAX / JSON / API: Devuelven una respuesta JSON estandarizada con codigo HTTP 403 (`error: Acceso denegado`, mensaje descriptivo y status 403), evitando la exposicion de trazas de depuracion, variables de entorno o estructura de base de datos.
5. **Vista Blade de Error 403:**
   - Creacion de `resources/views/errors/403.blade.php` con diseno limpio y responsivo en Tailwind CSS, informando al usuario que la accion requiere privilegios administrativos de RBAC, con opciones para volver atras o ir a la portada.
6. **Suite de Pruebas Automatizadas (PHPUnit):**
   - Creacion de `tests/Feature/RbacGovernanceAnd403Test.php` con 9 casos de prueba ejecutados bajo estrategia transaccional (`DatabaseTransactions`):
     - `test_rol_administrador_general_posee_todos_los_permisos_de_gobernanza`
     - `test_administrador_general_puede_acceder_a_rutas_de_gobernanza`
     - `test_usuario_sin_rol_administrativo_es_rechazado_con_codigo_403_en_rutas_de_gobernanza`
     - `test_solicitud_ajax_o_json_sin_permiso_retorna_403_estructurado`
     - `test_usuario_no_autenticado_es_redirigido_a_login`
     - `test_usuario_con_permiso_especifico_puede_acceder_a_su_modulo_correspondiente`
     - `test_politica_temporada_policy_permite_creacion_al_administrador`
     - `test_politica_temporada_policy_bloquea_modificacion_de_temporada_cerrada`
     - `test_politica_user_policy_impide_que_un_usuario_se_elimine_a_si_mismo`
   - Cobertura global: 26 pruebas y 147 aserciones superadas sin errores en todo el proyecto.

###### Matriz de Cumplimiento (DoD)

| Requerimiento | Estado | Detalle de Implementacion |
|---|---|---|
| Seeder de permisos atomicos | Cumplido | 27 permisos de gobernanza sembrados en `RoleAndPermissionSeeder.php` |
| Permisos asignados a Administrador General | Cumplido | Asignacion del 100% de permisos del sistema al rol maestro |
| Politica de autorizacion TemporadaPolicy | Cumplido | Implementada en `app/Policies/TemporadaPolicy.php` con bloqueo de estado cerrada |
| Politica de autorizacion UserPolicy | Cumplido | Implementada en `app/Policies/UserPolicy.php` con restriccion de autoeliminacion |
| Registro de politicas en AppServiceProvider | Cumplido | Mapeadas en el metodo `boot()` mediante `Gate::policy()` |
| Rutas administrativas protegidas | Cumplido | Prefijo `/admin` asegurado con middleware `role:Administrador General` y permisos |
| Manejador de error HTTP 403 Forbidden | Cumplido | Configurado en `bootstrap/app.php` para excepciones de Spatie y de autorizacion |
| Vista Blade para error 403 | Cumplido | Disenada en `resources/views/errors/403.blade.php` en espanol y sin exponer datos sensibles |
| Respuesta JSON para API / AJAX | Cumplido | Retorno de JSON estructurado con status HTTP 403 ante solicitudes asincronas |
| Suite de pruebas automatizadas | Cumplido | 9 pruebas y 41 aserciones verificadas al 100% en `RbacGovernanceAnd403Test.php` |
| Formato y estilo PSR-12 | Cumplido | Codigo validado y aprobado mediante Laravel Pint |
| Flujo de ramas y Git | Cumplido | Desarrollado en rama `Backend-Lead-Rafa` |

---

#### 2. Jose Gael Cruz Aviles (Dev 3 — Logica de Negocio y Algoritmos)

##### Tarea 1: SCRUM-106 (S1-T14) — Modelos y Controlador de Temporadas y Convocatorias con Bloqueo de Estado

- **Identificador Jira:** `SCRUM-106`
- **Codigo Interno:** `S1-T14`
- **Responsable:** Jose Gael Cruz Aviles (Dev 3 — Logica de Negocio y Algoritmos)
- **Sprint:** Sprint 1
- **Rama de Trabajo:** `feature/S1-T14-temporadas`
- **Pull Request de Integracion:** Merged en PR #5 hacia `develop` (Commit `b31db27`)
- **Estado:** Completada y validada en `develop`

###### Descripcion del Alcance Tecnico
1. **Modelos Eloquent de Dominio:**
   - Creacion del modelo `Temporada` en `app/Models/Temporada.php` enlazado a la tabla relacional `temporadas`, con asignacion masiva protegida (`liga_id`, `nombre`, `fecha_inicio`, `fecha_fin`, `estado`).
   - Creacion del modelo `Convocatoria` en `app/Models/Convocatoria.php` enlazado a la tabla relacional `convocatorias`, con soporte para almacenamiento de ruta del PDF y reglas versionadas.
2. **Controlador de Temporadas (`TemporadaController`):**
   - Implementacion de `app/Http/Controllers/TemporadaController.php` con logica RESTful completa:
     - Metodo `create()` para renderizar el formulario visual de alta.
     - Metodo `store()` con validacion de campos requeridos, validacion de coherencia temporal (`fecha_fin` posterior a `fecha_inicio`), recepcion y almacenamiento seguro de archivos PDF de convocatoria (hasta 5MB en disco local) e insercion atomica de registros en `temporadas` y `convocatorias`.
     - Metodos `edit()` y `destroy()` con regla de bloqueo de negocio: si la temporada se encuentra con `estado === 'cerrada'`, el sistema aborta la peticion con codigo HTTP 403 Forbidden impidiendo modificaciones o eliminaciones.
3. **Interfaz de Usuario (Vista Blade):**
   - Diseno e implementacion del formulario en `resources/views/temporadas/create.blade.php` para la captura de nombre de temporada, rango de fechas y subida del documento PDF de la convocatoria.
4. **Enrutamiento Web:**
   - Registro de las rutas de recurso en `routes/web.php` a traves de `Route::resource('temporadas', TemporadaController::class)`.

###### Matriz de Cumplimiento (DoD)

| Requerimiento | Estado | Detalle de Implementacion |
|---|---|---|
| Modelos Eloquent de Dominio | Cumplido | `Temporada.php` y `Convocatoria.php` estructurados en `app/Models/` |
| Validacion estricta en creacion | Cumplido | Validacion de tipo, tamano de PDF (5MB) y regla `after:fecha_inicio` |
| Almacenamiento seguro de PDF | Cumplido | Subida de archivos PDF a disco local enlazados a la convocatoria |
| Regla de negocio de temporada cerrada | Cumplido | Bloqueo con HTTP 403 Forbidden en edicion y borrado si el estado es `cerrada` |
| Vista Blade de creacion | Cumplido | Formulario responsivo implementado en `resources/views/temporadas/create.blade.php` |
| Rutas resource RESTful | Cumplido | Rutas de temporadas registradas y operativas en `routes/web.php` |
| Flujo de ramas y Git | Cumplido | Desarrollado en `feature/S1-T14-temporadas`, integrado en `develop` via PR #5 |

---

#### 3. Carlos Daniel Gaona Gomez (Dev 4 — Scrum Master / QA y Despliegue)

##### Tarea 1: SCRUM-92 (HAB-S1) y SCRUM-93 (S1-T01) — Habilitadoras Tecnicas, Estrategia de Ramas y Workflows de Calidad

- **Identificador Jira:** `SCRUM-92` / `SCRUM-93`
- **Codigo Interno:** `HAB-S1` / `S1-T01`
- **Responsable:** Carlos Daniel Gaona Gomez (Dev 4 — Scrum Master / QA y Despliegue)
- **Sprint:** Sprint 1
- **Rama de Trabajo:** `chore/SCRUM-93-sincronizar-main-develop` / workflows
- **Pull Requests de Integracion:** Merged en PR #6, PR #7 y PR #8 hacia `develop`
- **Estado:** Completada y validada en `develop`

###### Descripcion del Alcance Tecnico
1. **Estrategia de Ramas y Gobernanza Git (`SCRUM-93` / `S1-T01`):**
   - Definicion y unificacion de la estructura de ramas del repositorio: rama de produccion (`main`), rama de integracion continua (`develop`) y ramas tematicas por rol (`Backend-Lead-Rafa`, `feature/*`, `chore/*`).
   - Sincronizacion de historiales y reglas estrictas de integracion mediante revision obligatoria de Pull Requests (PR #6 y PR #7).
2. **Workflows de Calidad y Asistencia (`SCRUM-92` / `HAB-S1`):**
   - Configuracion de flujos automatizados de GitHub Actions en `.github/workflows/claude.yml` y `.github/workflows/claude-code-review.yml` para revision automatica de codigo en Pull Requests (PR #8).
3. **Pipeline de Integracion Continua CI (`SCRUM-96` / `S1-T04` - En Revision):**
   - En desarrollo activo y actualmente en revision en el **Pull Request #9**: pipeline con tres verificaciones automatizadas obligatorias (Laravel Pint con preset PSR-12, analisis estatico con Larastan nivel 5 y ejecucion de suite PHPUnit contra MariaDB 12.3).

###### Matriz de Cumplimiento (DoD)

| Requerimiento | Estado | Detalle de Implementacion |
|---|---|---|
| Estrategia de Ramas | Cumplido | Ramas `develop` y `main` estructuradas con reglas de merge obligatorio |
| Sincronizacion de Historial | Cumplido | Unificacion de commits y ramas base validada en PR #6 y PR #7 |
| Workflows de Calidad | Cumplido | Flujos automatizados configurados en `.github/workflows/` via PR #8 |
| Preparacion de Pipeline CI | En revision | PR #9 abierto con configuracion de Pint (PSR-12), Larastan nivel 5 y PHPUnit |

---

#### 4. Jose Luis Medina Ramirez (Dev 1 — Product Owner / Frontend Lead)

##### Estado de Tareas Asignadas (Sprint 1)

- **Identificador Jira:** `SCRUM-101` (S1-T09) — Layout base y navegacion administrativa con Blade y Tailwind CSS.
  - **Estado:** Pendiente / En cola de desarrollo.
- **Identificador Jira:** `SCRUM-102` (S1-T10) — Vistas Blade para gestion de roles y asignacion de permisos RBAC.
  - **Estado:** Pendiente / En cola de desarrollo.
- **Identificador Jira:** `SCRUM-109` (S1-T17) — Vistas Blade para gestion de temporadas y convocatorias.
  - **Estado:** Pendiente / En cola de desarrollo.


