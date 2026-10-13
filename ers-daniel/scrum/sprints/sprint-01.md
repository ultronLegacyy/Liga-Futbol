# Sprint 1

- Fechas: 2026-10-12 a 2026-10-16 (D-015). Hoy es lunes 12/10 por la noche: el Sprint Planning formal aún no se ha hecho; quedan **martes 13 a viernes 16 (4 días hábiles)** de ejecución real.
- Sprint Goal (refinado a partir del objetivo preliminar del equipo): **Para el viernes 16/10, el equipo cuenta con una base Laravel + MariaDB operativa, con autenticación y control de acceso por roles (RBAC) funcionando para los roles imprescindibles, auditoría automática de operaciones críticas, y el registro de temporadas, categorías y grupos administrable sin tocar código — sentando las bases técnicas y de datos sobre las que se construye el resto del roadmap (S2–S9).**
- Capacidad declarada por el equipo: **[POR DEFINIR]** — pregunta para el equipo: ¿cuántas horas tiene disponibles cada integrante martes, miércoles, jueves y viernes de esta semana? (ver tabla de capacidad al final). Sin esta cifra, la selección de abajo es una propuesta de alcance, no un compromiso.
- Estado: Planeado (pendiente de Sprint Planning formal con el equipo completo).
- Fuente de las historias: `scrum/product-backlog.md`, HU-001 a HU-008 (únicas candidatas de EP-01 que no dependen de tener el sistema construido; HU-009 y HU-010 se agendan al cierre, S9, por esa misma razón).

## Sprint Goal: estabilidad durante el sprint

El Sprint Goal no cambia durante la semana salvo negociación entre el Product Owner y el equipo (material del curso). Lo que puede cambiar, sin tocar el objetivo, es cuáles historias opcionales entran o salen según la capacidad real.

## Historias seleccionables

### Núcleo recomendado

Mínimo propuesto para cumplir el Sprint Goal aunque la semana se quede corta. Orden de dependencia: HU-005 y HU-001/HU-004 primero (no dependen de nada), luego HU-007.

| ID | Historia | Épica | MoSCoW | Tamaño preliminar | Responsable propuesto | Depende de |
|---|---|---|---|---|---|---|
| HU-005 | Crear y administrar temporadas con convocatoria versionada | EP-01 | Must | S (preliminar) | Dev 2 (migración/backend) + Dev 1 (CRUD Blade) | Ninguna |
| HU-007 | Crear categorías y grupos por temporada | EP-01 | Must | S (preliminar) | Dev 2 + Dev 1 | HU-005 |
| HU-001 | Iniciar sesión segura con control de acceso por roles (RBAC) — **alcance reducido**: priorizar Administrador General y 2–3 roles más representativos; el resto de los 14 roles se completa en S2 si no alcanza | EP-01 | Must | L (preliminar; el backlog ya marca esta historia como no-Pequeña) | Dev 2 (auth + middleware) | Ninguna |
| HU-004 | Registrar auditoría inmutable de operaciones críticas | EP-01 | Must | M (preliminar) | Dev 3 (AuditService) | Ninguna |

### Opcionales si hay capacidad

Solo entran si el núcleo se completa antes del jueves y el equipo decide ampliar el alcance en la Daily correspondiente (no por decisión unilateral de una sola persona).

| ID | Historia | Épica | MoSCoW | Tamaño preliminar | Responsable propuesto | Depende de | Nota |
|---|---|---|---|---|---|---|---|
| HU-006 | Configurar las reglas de la temporada (cuotas, fechas, clasificación, desempates, ascenso/descenso) | EP-01 | Must | M (preliminar) | Dev 2 + Dev 1 | HU-005 | Sus parámetros se usan recién en S5–S8; no bloquea nada de S2 si se difiere |
| HU-008 | Configurar el formato de competencia y los criterios de clasificación | EP-01 | Must | S (preliminar) | Dev 2 + Dev 1 | HU-007 | Igual que HU-006, se usa recién en S7 |
| HU-002 | Proteger cuentas con segundo factor y bloqueo por fuerza bruta | EP-01 | Must | M (preliminar) | Dev 2 | HU-001 | El aviso por correo (RNF-28) queda bloqueado hasta que el equipo decida sobre SMTP (V-09); se puede implementar el bloqueo por intentos sin el aviso por correo |
| HU-003 | Recuperar el acceso mediante correo electrónico | EP-01 | Must | M (preliminar) | Dev 2 | HU-001 + SMTP configurado | **Bloqueada** hasta que el equipo decida configurar SMTP esta semana o la próxima (V-09); no se recomienda iniciarla sin esa decisión |

Si la capacidad resulta muy limitada (por ejemplo, si solo hay 2–3 días-persona reales entre los cuatro), se recomienda recortar primero HU-003, luego HU-002, luego HU-008, y finalmente HU-006 — en ese orden — antes de tocar el núcleo. Ver recomendación completa al final.

## Criterios de aceptación (resumen, referencia completa en el backlog)

- **HU-005**: temporada con nombre/fechas/convocatoria se crea; cambios a reglas se versionan con usuario/fecha/motivo; temporada cerrada no se puede modificar ni eliminar.
- **HU-007**: categoría nueva no requiere tocar código; un grupo se asocia a una categoría de una temporada; un equipo queda en una sola categoría/grupo.
- **HU-001**: usuario autenticado solo ve módulos/acciones de su rol; ruta fuera de su rol responde 403; sesión expira a los 30 min (roles con privilegios) u 8 h (resto); credenciales viajan por HTTPS/TLS; acceso cruzado (cambiar un ID en la URL) responde 403 en el 100% de los casos.
- **HU-004**: cambio a marcador/sanción/pago/permiso registra usuario, fecha, hora, IP, valor anterior, valor nuevo y motivo; un registro de auditoría no se puede modificar ni borrar; cierre de cédula/actualización de tabla es una sola transacción atómica.
- **HU-006** (opcional): cuotas/fechas/clasificación/desempates/ascenso-descenso se guardan como datos de la temporada, sin tocar código.
- **HU-008** (opcional): formato de competencia y criterios de clasificación quedan guardados y disponibles para HU-041/HU-042 (sprints posteriores).
- **HU-002** (opcional): segundo factor obligatorio para roles con privilegios; bloqueo de 15 min tras 5 intentos fallidos; límite también por IP; contraseñas con Argon2id/bcrypt ≥12, 15 caracteres mínimo (8 con 2FA).
- **HU-003** (opcional, bloqueada): enlace de recuperación de un solo uso con vigencia limitada; enlace vencido se rechaza; nueva contraseña invalida la sesión anterior.

## Subtareas técnicas

Tomadas de `docs/PLANIFICACIÓN SCRUM.pdf` (Sprint 1) más las habilitadoras que exige la Definition of Done del backlog.

### Por historia

| Historia | Subtarea | Responsable propuesto |
|---|---|---|
| HU-005 | Migración `temporadas` (nombre, fechas, estado, versión de reglas) | Dev 2 |
| HU-005 | Controlador + Form Request de temporada | Dev 2 |
| HU-005 | Vista CRUD Blade de temporadas | Dev 1 |
| HU-005 | Subida de convocatoria a Storage privado (ver habilitadora) | Dev 4 |
| HU-007 | Migraciones `categorias`, `grupos`, relación con `temporadas` | Dev 2 |
| HU-007 | Vista CRUD de categorías y grupos | Dev 1 |
| HU-001 | Configurar base del proyecto Laravel + conexión MariaDB | Dev 2 |
| HU-001 | Autenticación (Laravel Breeze/Fortify o equivalente, a confirmar — V-08) | Dev 2 |
| HU-001 | Middleware de roles/permisos (spatie/laravel-permission, propuesto en el PDF del equipo — a confirmar) | Dev 2 |
| HU-001 | Layout maestro Blade (sidebar, topbar) del panel administrativo | Dev 1 |
| HU-004 | `AuditService`: capturar usuario, fecha, hora, IP, valor anterior/nuevo, motivo, sin saturar los controladores | Dev 3 |
| HU-004 | Migración de `auditoria_logs` (inmutable: sin update/delete permitidos a nivel aplicación) | Dev 2 + Dev 3 |
| HU-006 (opcional) | Migración de reglas de temporada (cuotas, fechas límite, desempates, ascenso/descenso como datos, no código) | Dev 2 |
| HU-008 (opcional) | Migración de formato de competencia y criterios de clasificación por grupo | Dev 2 |
| HU-002 (opcional) | Lógica de bloqueo por intentos fallidos + límite por IP | Dev 2 |
| HU-002 (opcional) | Segundo factor (TOTP o código por correo) para roles con privilegios | Dev 2 |
| HU-003 (opcional, bloqueada) | Flujo de recuperación por correo (requiere SMTP) | Dev 2 |

### Habilitadoras (Definition of Done, no ligadas a una sola historia)

| Subtarea | Responsable propuesto |
|---|---|
| Repositorio git con estrategia de ramas y revisión por otro integrante (RNF-38) | Todo el equipo, coordina Dev 2 (SM) |
| Configurar Laravel Pint (PSR-12) y Larastan nivel 5+ en CI/pre-commit | Dev 4 |
| Base de pruebas automatizadas (PHPUnit) para los módulos de este sprint | Dev 4 |
| Túnel (Ngrok o Cloudflare, con TLS — a confirmar en V-08) para exponer el entorno de desarrollo | Dev 4 |
| Storage privado (`storage/app/private` o equivalente) para convocatorias y futuros documentos confidenciales, sin indexar | Dev 4 |
| Variables de entorno para configuración (RNF-42): base de datos, túnel, futura SMTP | Dev 2 + Dev 4 |
| Actualizar `docs/trazabilidad.md` con el estado de cada historia cerrada | Quien cierre la historia |

## Tamaño preliminar

S/M/L junto a cada historia en las tablas de arriba. **Preliminar: se confirma en Planning Poker durante el Sprint Planning formal**, con la escala que el equipo decida (el curso propone Fibonacci modificado: 1, 2, 3, 5, 8, 13, 20, 40, 100, ∞, ?).

## Dependencias

- Internas al sprint: HU-007 depende de HU-005; HU-008 (opcional) depende de HU-007; HU-006 (opcional) depende de HU-005; HU-002 (opcional) depende de HU-001.
- Externas, no resueltas: HU-003 depende de SMTP configurado (sin sprint asignado, V-09); HU-002 (aviso de bloqueo, RNF-28) depende de lo mismo para su parte de correo.
- De todo el sprint hacia el resto del roadmap: HU-005 y HU-007 son la base de datos de la que depende prácticamente toda la estructura deportiva de S2 en adelante (ver `scrum/sprints/roadmap.md`); HU-001/HU-004 son la base de seguridad/auditoría que usan HU-039, HU-045, HU-047 más adelante.

## Riesgos

- HU-001 cubre 14 roles distintos y el backlog ya la marca como no-Pequeña: alto riesgo de no caber en 4 días si se intenta completa. Mitigación propuesta: reducir el alcance a los roles imprescindibles para demostrar el patrón (ver «Núcleo recomendado»), y completar el resto en S2.
- Decisiones técnicas base sin tomar (V-08): versión de PHP/Laravel/MariaDB, túnel (Ngrok vs. Cloudflare), paquete de roles (spatie/laravel-permission u otro), estrategia de ramas. Sin esto, Dev 2 no puede empezar la base del proyecto con certeza el martes por la mañana.
- Credenciales expuestas en el repositorio público (V-01, `pendientes-validacion.md`): revisar y revocar antes de empujar código real de este sprint a un remoto compartido.
- RNF-32 (TLS/HSTS de grado producción, SSL Labs A) solo se puede verificar totalmente en el hospedaje definitivo, aún sin decidir (sección 1.2 de la ERS); en el entorno de desarrollo con túnel se verifica de forma parcial.
- SMTP sin sprint asignado (V-09): bloquea HU-003 completa y la parte de aviso por correo de HU-002.
- Disponibilidad y capacidad del equipo sin declarar (V-02): esta selección es una propuesta, no un compromiso, hasta que se llene la tabla de capacidad.
- Asignación de Dev 3/Dev 4 aún sin confirmar entre Rafael Piedra Sanchez y Jose Gael Cruz Aviles (V-03, D-017): los responsables propuestos abajo usan los roles «Dev 3» y «Dev 4», no nombres.

## Definition of Done del sprint

Basada en la Definition of Done propuesta en `scrum/product-backlog.md` (pendiente de aprobación formal del equipo) y en el ejemplo de DoD del curso (funcionalidad con criterios de aceptación, código en git, pruebas, documentación). Para que una historia de este sprint se considere Hecha:

1. Código en la rama principal, revisado por otro integrante, sin errores de Pint (PSR-12) ni hallazgos de Larastan nivel 5+.
2. Existen pruebas automatizadas del módulo afectado (autenticación, auditoría, temporadas/categorías/grupos).
3. Se verificaron todos los criterios de aceptación de la historia (ver sección de arriba y el detalle completo en el backlog).
4. No quedan defectos abiertos de severidad crítica o alta.
5. Las vistas Blade nuevas están en español, con validación de datos y mensajes de error claros, y se adaptan a escritorio (móvil/tableta se verifica donde aplique en este sprint).
6. Las operaciones sobre temporadas/categorías/grupos y los cambios de permisos quedan en la bitácora de auditoría (HU-004).
7. Las credenciales y la sesión viajan por HTTPS/TLS, incluso sobre el túnel de desarrollo.
8. Se actualizó la fila correspondiente en `docs/trazabilidad.md`.
9. Se demostró en el Sprint Review del viernes 16/10 ante el Product Owner.

## Agenda de eventos (sprint de 1 semana, time-boxes proporcionales a los del curso para sprints de 1 mes)

### Sprint Planning

- Aún pendiente de hacerse formalmente esta semana (idealmente el martes 13 por la mañana, ya que el lunes por la noche no hubo sesión del equipo completo).
- Time-box propuesto: ≤ 2 horas (proporcional a las ≤ 8 h del curso para un sprint de 4 semanas).
- Agenda: 1) confirmar el Sprint Goal refinado arriba; 2) que el equipo declare su capacidad (tabla de abajo); 3) confirmar núcleo/opcionales de esta propuesta o ajustarlos; 4) Planning Poker de las historias seleccionadas; 5) desglose de tareas si falta detalle; 6) compromiso del equipo como unidad.

### Daily Scrum

- Martes 13 a viernes 16, 15 minutos, misma hora y lugar — hora **[POR DEFINIR]** por el equipo.
- Tres preguntas: ¿qué completé desde la última Daily? ¿qué voy a hacer antes de la próxima? ¿tengo algún impedimento?
- Los impedimentos se registran en la tabla de abajo, no se resuelven en la Daily misma.

### Sprint Review

- Viernes 16/10, time-box propuesto ≤ 1 hora (proporcional a las ≤ 4 h del curso).
- Agenda: 1) Product Owner recuerda el Sprint Goal; 2) equipo demuestra el incremento funcionando (login, RBAC, auditoría, temporadas/categorías/grupos) — no diapositivas; 3) retroalimentación; 4) el Product Owner actualiza el backlog si corresponde; 5) vista previa de qué sigue en S2.

### Retrospectiva

- Viernes 16/10, después de la Review, time-box propuesto ≤ 45 minutos (proporcional a las ≤ 3 h del curso).
- Al ser el primer sprint del proyecto, se sugiere el formato Start/Stop/Continue (simple, bueno para equipos nuevos), pero el equipo puede elegir otro.
- Reglas: regla de Las Vegas, sin culpar a personas, todos participan, se sale con acciones concretas con responsable (rol) y fecha.

### Refinamiento

- Al menos una sesión durante el sprint (lo organiza el Scrum Master, el Product Owner siempre presente), por ejemplo para preparar las historias de EP-02 (S2), incluida HU-070.

## Tabla de capacidad (a llenar por el equipo)

| Persona (rol) | Martes | Miércoles | Jueves | Viernes | Total horas | Notas |
|---|---|---|---|---|---|---|
| Dev 1 (PO / Frontend Lead) | [POR DEFINIR] | [POR DEFINIR] | [POR DEFINIR] | [POR DEFINIR] | [POR DEFINIR] | |
| Dev 2 (SM / Backend Lead) | [POR DEFINIR] | [POR DEFINIR] | [POR DEFINIR] | [POR DEFINIR] | [POR DEFINIR] | |
| Dev 3 (Lógica/algoritmos) | [POR DEFINIR] | [POR DEFINIR] | [POR DEFINIR] | [POR DEFINIR] | [POR DEFINIR] | Persona por confirmar (V-03) |
| Dev 4 (QA/documentación/despliegue) | [POR DEFINIR] | [POR DEFINIR] | [POR DEFINIR] | [POR DEFINIR] | [POR DEFINIR] | Persona por confirmar (V-03) |

## Sprint Backlog

| ID | Historia | Responsable (rol) | Estimación | Estado | Notas |
|---|---|---|---|---|---|
| HU-005 | Crear y administrar temporadas con convocatoria versionada | Dev 2 + Dev 1 | [POR DEFINIR] | Sin reporte | Núcleo |
| HU-007 | Crear categorías y grupos por temporada | Dev 2 + Dev 1 | [POR DEFINIR] | Sin reporte | Núcleo; depende de HU-005 |
| HU-001 | Iniciar sesión segura con RBAC (alcance reducido) | Dev 2 | [POR DEFINIR] | Sin reporte | Núcleo; completar roles restantes en S2 si no alcanza |
| HU-004 | Registrar auditoría inmutable de operaciones críticas | Dev 3 | [POR DEFINIR] | Sin reporte | Núcleo |
| HU-006 | Configurar las reglas de la temporada | Dev 2 + Dev 1 | [POR DEFINIR] | Sin reporte | Opcional; depende de HU-005 |
| HU-008 | Configurar el formato de competencia y criterios de clasificación | Dev 2 + Dev 1 | [POR DEFINIR] | Sin reporte | Opcional; depende de HU-007 |
| HU-002 | Proteger cuentas con segundo factor y bloqueo por fuerza bruta | Dev 2 | [POR DEFINIR] | Sin reporte | Opcional; aviso por correo bloqueado por V-09 |
| HU-003 | Recuperar el acceso mediante correo electrónico | Dev 2 | [POR DEFINIR] | Sin reporte | Opcional, bloqueada por V-09 |

## Impedimentos

| Fecha | Impedimento | Afecta a | Responsable (rol) | Siguiente acción | Estado |
|---|---|---|---|---|---|
| 2026-10-12 | Decisiones técnicas base sin tomar: versiones PHP/Laravel/MariaDB, túnel, paquete de roles, estrategia de ramas (V-08) | HU-001, HU-005, HU-007, HU-004 (todo el sprint) | Dev 2, con el equipo | Decidir en el Sprint Planning formal del martes | Abierto |
| 2026-10-12 | Configuración de correo SMTP sin sprint asignado (V-09); la tarea correspondiente del Sprint 9 está tachada en `PLANIFICACIÓN SCRUM.pdf` | HU-002 (aviso de bloqueo), HU-003 (completa) | Equipo | Decidir si se adelanta SMTP a S1–S2 o se difiere HU-003 | Abierto |
| 2026-10-12 | Capacidad y disponibilidad del equipo sin declarar (V-02) | Todo el sprint (compromiso) | Los 4 integrantes | Llenar la tabla de capacidad en el Sprint Planning | Abierto |
| 2026-10-12 | Credenciales expuestas en el repositorio público `ultronLegacyy/Liga-Futbol` (V-01) | Seguridad del repositorio antes de subir código real | Dueño del repositorio | Revocar credenciales y limpiar el historial | Abierto |
| 2026-10-12 | Asignación de Dev 3/Dev 4 sin confirmar entre Rafael Piedra Sanchez y Jose Gael Cruz Aviles (V-03) | Responsables de HU-004 (Dev 3) y de las habilitadoras de QA (Dev 4) | Equipo | Confirmar el orden en el Sprint Planning | Abierto |

## Cierre

- Terminado (cumple la Definition of Done): sin reporte.
- No terminado y motivo: sin reporte.
- Enlace a la Review y a la Retrospectiva: `scrum/ceremonias/`

## Recomendación crítica: ¿cabe el Sprint 1 propuesto en martes a viernes?

**No tal como está planteado el conjunto completo (8 historias).** Con 4 días hábiles reales, capacidad aún sin declarar (V-02) y decisiones técnicas base sin tomar (V-08), completar las 8 historias de EP-01 en su alcance íntegro es poco realista, sobre todo porque HU-001 por sí sola ya está marcada en el backlog como demasiado grande para un sprint (cubre 14 roles).

Qué recortar primero y por qué, en este orden:

1. **HU-003** (recuperación por correo): se recorta primero porque está bloqueada de origen por una decisión externa (SMTP sin sprint asignado, V-09) que no se puede resolver solo con más horas de desarrollo esta semana.
2. **HU-002** (segundo factor y bloqueo por fuerza bruta): se recorta en segundo lugar por la misma razón parcial (el aviso por correo de RNF-28 depende de lo mismo que HU-003); si se mantiene, debe ser sin el aviso por correo.
3. **HU-008** y **HU-006**: se recortan en tercer y cuarto lugar porque, aunque son Must, sus datos (formato de competencia, cuotas, desempates) no los consume ninguna otra historia hasta S5–S8; diferirlas a S2 no bloquea nada del resto del roadmap.
4. El **núcleo verdaderamente indispensable** para no bloquear S2 en adelante es: HU-005 (temporadas) + HU-007 (categorías/grupos) + HU-001 reducida a los roles imprescindibles + HU-004 (auditoría, requisito transversal de la Definition of Done). Si ni siquiera esto cabe en 4 días, la siguiente candidata a recortar sería reducir aún más HU-001 (por ejemplo, solo Administrador General) y completar el resto de roles en S2, antes que sacrificar HU-005/HU-007/HU-004, porque son la base de datos y de seguridad de la que depende todo lo demás.

Esta recomendación es una propuesta del Scrum Master; la decisión final de qué entra y qué sale es del equipo, en el Sprint Planning formal.
