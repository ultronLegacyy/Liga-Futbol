# Lote 02 de Jira: Sprint 1 (borrador)

- Estado: **borrador, no ejecutable todavía**. Requiere el lote 01 creado y estas confirmaciones: capacidad del equipo (V-02), Dev 3/Dev 4 (V-03), cuenta de Carlos Daniel (V-04), zona horaria (V-05) y la selección final del Sprint Planning.
- Los sprints no se pueden crear por MCP. Antes de este lote, alguien con permisos crea en Jira (Backlog → Crear sprint) los 9 sprints con las fechas de D-015, sin iniciarlos, y mueve una historia cualquiera al Sprint 1. Así el coordinador puede leer el ID del sprint y asignar el resto con `editJiraIssue` (campo `customfield_10020`).
- Story points: no se cargan hasta que el equipo estime en Planning Poker.
- No se inicia el sprint ni se cambia ningún estado sin autorización.

## Operaciones previstas

1. `editJiraIssue`: asignar al Sprint 1 las historias que el equipo seleccione (núcleo propuesto: HU-005, HU-007, HU-001, HU-004).
2. `createJiraIssue` tipo **Subtask** (12 subtareas del núcleo), con `parent` = clave de su historia:

| # | Historia | Subtarea | Responsable propuesto |
|---|---|---|---|
| 1 | HU-005 | Migración `temporadas` (nombre, fechas, estado, versión de reglas) | Dev 2 |
| 2 | HU-005 | Controlador + Form Request de temporada | Dev 2 |
| 3 | HU-005 | Vista CRUD Blade de temporadas | Dev 1 |
| 4 | HU-005 | Subida de convocatoria a Storage privado (ver habilitadora) | Dev 4 |
| 5 | HU-007 | Migraciones `categorias`, `grupos`, relación con `temporadas` | Dev 2 |
| 6 | HU-007 | Vista CRUD de categorías y grupos | Dev 1 |
| 7 | HU-001 | Configurar base del proyecto Laravel + conexión MariaDB | Dev 2 |
| 8 | HU-001 | Autenticación (Laravel Breeze/Fortify o equivalente, a confirmar — V-08) | Dev 2 |
| 9 | HU-001 | Middleware de roles/permisos (spatie/laravel-permission, propuesto en el PDF del equipo — a confirmar) | Dev 2 |
| 10 | HU-001 | Layout maestro Blade (sidebar, topbar) del panel administrativo | Dev 1 |
| 11 | HU-004 | `AuditService`: capturar usuario, fecha, hora, IP, valor anterior/nuevo, motivo, sin saturar los controladores | Dev 3 |
| 12 | HU-004 | Migración de `auditoria_logs` (inmutable: sin update/delete permitidos a nivel aplicación) | Dev 2 + Dev 3 |

3. `createJiraIssue` tipo **Task** (7 tareas habilitadoras de la DoD, en el Sprint 1, sin épica o en EP-01 según decida el equipo):

| # | Tarea | Responsable propuesto |
|---|---|---|
| 1 | Repositorio git con estrategia de ramas y revisión por otro integrante (RNF-38) | Todo el equipo, coordina Dev 2 (SM) |
| 2 | Configurar Laravel Pint (PSR-12) y Larastan nivel 5+ en CI/pre-commit | Dev 4 |
| 3 | Base de pruebas automatizadas (PHPUnit) para los módulos de este sprint | Dev 4 |
| 4 | Túnel (Ngrok o Cloudflare, con TLS — a confirmar en V-08) para exponer el entorno de desarrollo | Dev 4 |
| 5 | Storage privado (`storage/app/private` o equivalente) para convocatorias y futuros documentos confidenciales, sin indexar | Dev 4 |
| 6 | Variables de entorno para configuración (RNF-42): base de datos, túnel, futura SMTP | Dev 2 + Dev 4 |
| 7 | Actualizar `docs/trazabilidad.md` con el estado de cada historia cerrada | Quien cierre la historia |

4. `editJiraIssue`: asignar cada historia, subtarea y tarea a su responsable real:

| Rol | Persona (ERS 1.3 / D-017) | Cuenta Jira encontrada | Estado |
|---|---|---|---|
| Dev 1 · PO / Frontend Lead | Jose Luis Medina Ramirez | «Medina Ramírez José Luis» | Lista |
| Dev 2 · SM / Backend Lead | Carlos Daniel Gaona Gomez | «Daniel» (por confirmar, V-04) | Pendiente |
| Dev 3 · Lógica / algoritmos | Rafael Piedra Sanchez o Jose Gael Cruz Aviles | «Rafael Piedra» / «Jose Gael Cruz Aviles» | Pendiente (V-03) |
| Dev 4 · QA / documentación / despliegue | La otra persona | — | Pendiente (V-03) |

Si el equipo incluye historias opcionales, se agregan sus subtareas (5 propuestas en `scrum/sprints/sprint-01.md`).

## Verificación

- JQL `project = SCRUM AND sprint = <ID Sprint 1>` devuelve exactamente las historias, subtareas y tareas aprobadas.
- Ninguna incidencia cambia de estado: todas quedan «Por hacer».
- Las claves se anotan en `scrum/sprints/sprint-01.md` y en `docs/trazabilidad.md`.
