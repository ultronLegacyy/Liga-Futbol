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

El diseno e implementacion del esquema relacional comprende un total de 35 tablas organizadas en 6 modulos estructurales, de estricto apego a la especificacion funcional `BASE_DE_DATOS.md`:

### Modulo 1: Core, Gobernanza y Autenticacion
- `ligas`: Parametrizacion general de la liga deportiva, configuracion y estado.
- `clubes`: Entidades juridicas/deportivas afiliadas a la liga.
- `usuarios`: Cuentas de acceso con roles tipificados y credenciales.
- `password_reset_tokens`: Tokens de recuperacion de credenciales.
- `sessions`: Control de sesiones de usuario activas.
- `auditoria_logs`: Trazabilidad completa de acciones y cambios de datos.
- `asambleas`: Registro de convocatorias, quorum y actas de asambleas.
- `comunicados_noticias`: Boletines informativos oficiales de la liga.
- `registro_notificaciones`: Notificaciones enviadas por correo, SMS o internas.

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

Para ejecutar las migraciones desde cero:

```bash
php artisan migrate:fresh
```

---

## 6. Seeders y Datos de Prueba

El seeder integral se encuentra en `database/seeders/DatabaseSeeder.php`. Pobla un conjunto cohesionado de datos de prueba cubriendo las 35 tablas:

- Liga activa de muestra: Liga Municipal de Futbol Amateur de Morelia, A.C.
- Usuarios con roles de Administrador General, Directivo de Club y Arbitro.
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

Se crearon pruebas automatizadas con PHPUnit en `tests/Feature/DatabaseSchemaTest.php` para validar:

1. Existencia e integridad de las 35 tablas en el motor de base de datos.
2. Correcta ejecucion e insercion de datos de los seeders en entidades maestras y transaccionales.

### Comando para ejecutar las pruebas:

```bash
php artisan test
```

### Resultado de ejecucion:

```text
Tests:    4 passed (42 assertions)
Duration: 0.18s
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
| Restriccion de estilo | Cumplido | Cero emojis en codigo, mensajes y documentacion |
