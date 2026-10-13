# Pendientes de validación: SGLD

Lista única de lo que el equipo o la Liga deben confirmar. Cuando un punto se resuelve, se marca «Resuelto», se indica la fecha y, si es una decisión, se registra en `docs/decisiones.md`.

Estados: Abierto · En curso · Resuelto.

## A. Antes de comprometer el Sprint 1 (12–16 oct)

| ID | Pendiente | Por qué importa | Quién | Estado |
|---|---|---|---|---|
| V-01 | El repositorio público `ultronLegacyy/Liga-Futbol` contiene credenciales de Claude Code en el commit `90111af` (`contexto/.claude/.credentials.json`, `daemon/control.key`, respaldos de `.claude.json`). **El equipo hizo privado el repositorio. Recomendación pendiente: rotar las credenciales expuestas cerrando y abriendo sesión en Claude Code, porque hacer privado el repositorio no las invalida.** | Credenciales expuestas. Hay que revocarlas y limpiar el historial antes de subir el código del sistema. | Dueño del repositorio | Resuelto (2026-10-12) |
| V-02 | Disponibilidad real de cada integrante (horas por día) en el Sprint 1 (martes a viernes) y en las semanas siguientes. **2–4 h diarias por persona (D-022).** | Sin capacidad no se puede comprometer trabajo (D-016, material del curso). | Los 4 integrantes | Resuelto (2026-10-12) |
| V-03 | Quién es Dev 3 (lógica/algoritmos) y quién Dev 4 (QA/documentación/despliegue): Rafael Piedra Sanchez o Jose Gael Cruz Aviles. **Dev 3 = Jose Gael Cruz Aviles; Dev 4 = Carlos Daniel Gaona Gomez; Rafael Piedra Sanchez es Dev 2 (D-021).** | Asignación de tareas y de incidencias en Jira. | Equipo | Resuelto (2026-10-12) |
| V-04 | ¿La cuenta de Jira «Daniel» (cuenta conectada al MCP) es de Carlos Daniel Gaona Gomez? No existe otra cuenta con «Gaona» ni «Carlos». **Sí, «Daniel» es Carlos Daniel Gaona Gomez.** | Asignar trabajo al Scrum Master. | Carlos Daniel Gaona Gomez | Resuelto (2026-10-12) |
| V-05 | Zona horaria del sitio Jira y de los perfiles: debe ser America/Mexico_City (RNF-18). El MCP no expone ese dato; las fechas llegan con −06:00. | Fechas de los sprints. | Administrador de Jira | Abierto |
| V-06 | SCRUM-3 «Task 3» (En curso) y SCRUM-4 «Subtask 2.1» (Por hacer) parecen ejemplos de la plantilla. ¿Se borran? El MCP no puede borrar incidencias. | Evitar ruido en el tablero. | Equipo | Abierto |
| V-07 | Aprobar la Definition of Ready y la Definition of Done propuestas en `scrum/product-backlog.md` y la escala de estimación (el curso propone Fibonacci modificado con Planning Poker). | Sin DoD no hay criterio de «Hecho». | Equipo | Abierto |
| V-08 | Decisiones técnicas base: versiones de PHP, Laravel y MariaDB (RNF-41 exige versiones definidas); túnel (Ngrok o Cloudflare, con TLS según RNF-32); paquete de roles (la planificación propone spatie/laravel-permission); repositorio del código y estrategia de ramas con revisión por otro integrante (RNF-38). **Versiones definidas en D-023; falta confirmar Fortify, ngrok, Mailpit y la estrategia de ramas.** | Son prerrequisitos de las tareas del Sprint 1. | Dev 2 con el equipo | En curso |
| V-09 | La tarea de Dev 4 del Sprint 9 en `PLANIFICACIÓN SCRUM.pdf` (SMTP, VPS, PostgreSQL, dominio) está tachada, así que la configuración de correo ya no tiene sprint. La recuperación de contraseña (Tabla 7), el aviso de bloqueo (RNF-28) y RF-15 la necesitan. | Definir en qué sprint se configura el correo (se propone S1–S2). | Equipo | Abierto |
| V-10 | Confirmar D-011 (modelo `sonnet` para los agentes) y D-014 (IDs `EP-NN` y `HU-NNN`). **D-014 aprobada con el lote 01; falta D-011.** | Las incidencias de Jira llevarán estos IDs. | Equipo | En curso |

## B. Preguntas abiertas del Product Backlog

Detalle en `scrum/product-backlog.md`, sección «Preguntas abiertas».

| ID | Tema | Quién | Estado |
|---|---|---|---|
| PA-01 | «Comité Ejecutivo» es actor de RF-11 (Anexo A) pero no aparece en los roles de 2.3. | Equipo | Abierto |
| PA-02 | Qué rol valida (RF-08.4) y corrige (RF-08.3) un resultado. | Equipo / Liga | Abierto |
| PA-03 | AF-08 marca inconformidades y recursos como Could; RF-10.5 es Alta. ¿Cuál prevalece? | Equipo | Abierto |
| PA-04 | 9 semanas aprobadas frente a las 10 de la ERS (2.2 y RNF-03). | Equipo | Abierto |
| PA-05 | RNF-03 («≥ 80 % de RF Media») obliga en la práctica a terminar los 4 RF Media. | Equipo | Abierto |
| PA-06 | «Endpoints tipo API» y «API interna» de la planificación frente a IC-02 (sin API REST separada). | Equipo | Abierto |
| PA-07 | VPS, dominio y PostgreSQL (S9): el texto está tachado en la planificación, así que el equipo lo descartó. Falta confirmarlo y resolver V-09. | Equipo | En curso |
| PA-08 | Tabla `ligas` en el esquema frente a «una sola liga» (D-002). | Equipo | Abierto |
| PA-09 | ¿Existe formalmente la Comisión Arbitral con padrón y disponibilidad de árbitros? | Liga | Abierto |
| PA-10 | Estilo visual de la Liga (IU-07). | Liga | Abierto |
| PA-11 | La «credencial» de jugador (AF-03) no tiene un inciso RF que la detalle. | Equipo / Liga | Abierto |
| PA-12 | La elegibilidad por «cuota cubierta» (RF-04.4, S3) depende de Tesorería (RF-13, S8). | Equipo | Abierto |
| PA-13 | El bloqueo por sanción (RF-10.4, S7) se necesita antes, en S4–S6. | Equipo | Abierto |
| PA-14 | ¿Cuándo se validan con la Liga los 12 puntos del Anexo B? | Liga | Abierto |

## C. Validación con la Liga

- Anexo B de la ERS (12 puntos): quién crea y aprueba la programación; datos obligatorios para programar; partidos programados en una cancha que se bloquea; quién modifica un resultado capturado; cuándo un resultado es oficial; quién resuelve sanciones; qué sanciones son públicas; forma y plazos de inconformidades y recursos; criterios de desempate; impedimentos para ascender o descender; alcance de una sanción; documentos para registrar a un jugador.
- Reglas de 2.4 marcadas «por confirmar con la Liga»: salto máximo de 1 categoría dentro del mismo club; baja automática al llegar a 5 defaults.
- PA-09 (Comisión Arbitral), PA-10 (estilo visual) y PA-11 (credencial).

## D. Inconsistencias de la ERS

No se corrigen sin instrucción del equipo.

| Punto | Detalle |
|---|---|
| Duración | 2.2 y RNF-03 dicen 10 semanas; el calendario aprobado es de 9 (D-015, PA-04). |
| API | 4.4 («API/servicios») y 4.5 («backend/API modular») frente a IC-02 («sin una API REST separada»). |
| Exportación | 4.4 menciona «Excel/CSV» en Reportes; 1.2 deja CSV/Excel fuera del alcance. |
| Roles | «Comité Ejecutivo» aparece en el glosario y en el Anexo A, pero no en 2.3 (PA-01). |
| Prioridad | AF-08 (Could para inconformidades y recursos) frente a RF-10 Alta (PA-03). |
| Secciones vacías | 4.1 BPMN, 4.2 Diseño de interfaz y 4.3 Diseño de base de datos. |

## E. BPMN

- Aprobar `docs/BPMN_procesos.drawio` para la sección 4.1 de la ERS.
- Confirmar en el diagrama: quién valida el resultado del partido (ver PA-02), la aprobación de la programación por el Comité Ejecutivo (Anexo B, punto 1) y el orden cotejo → pago en el registro de jugadores.
