# Registro de decisiones: SGLD

Memoria persistente del proyecto. Cada entrada se agrega al final y no se borra: si una decisión cambia, se registra una nueva y la anterior pasa a «Reemplazada por D-NNN».

Estados: **Propuesta** (pendiente de aprobación del equipo), **Aprobada**, **Reemplazada**, **Hecho** (dato técnico verificado, no requiere votación).

Formato:

```
### D-NNN · Título
- Fecha: AAAA-MM-DD
- Estado: Propuesta | Aprobada | Reemplazada por D-NNN | Hecho
- Decisión:
- Motivo:
- Impacto (documentos, IDs de la ERS, backlog):
- Decidió (rol):
```

## Decisiones de la fase ERS

Decisiones tomadas por el equipo durante la elaboración de la ERS (antes del 2026-10-07). Ya están reflejadas en `docs/ERS.docx`.

| ID | Decisión | Estado |
|---|---|---|
| D-001 | La única integración externa es el correo electrónico. | Aprobada |
| D-002 | El sistema es para una sola liga; el soporte para varias ligas queda para el futuro. | Aprobada |
| D-003 | Interfaz web con Laravel Blade (no API REST). | Aprobada |
| D-004 | Nombre oficial: «Sistema de Gestión para la Liga Municipal de Morelia». | Aprobada |
| D-005 | Reglamento de referencia: FMF Sector Amateur + IFAB (no se cuenta con el reglamento propio de la Liga). | Aprobada |
| D-006 | Sistema totalmente dependiente de Internet, gratuito por ahora y alojado en equipos propios. | Aprobada |
| D-007 | Modelo de calidad ISO/IEC 25010:2023; la sección 3.3 tiene RNF medibles. | Aprobada |
| D-008 | RF-11 con prioridad Baja/Opcional. | Aprobada |
| D-009 | La ERS no requiere aprobación formal de la Liga. | Aprobada |

## Decisiones de configuración (2026-10-12)

### D-010 · Coordinación con una sesión central y dos subagentes
- Fecha: 2026-10-12
- Estado: Aprobada
- Decisión: la sesión principal de Claude Code coordina. Invoca a dos subagentes, `product-owner` y `scrum-master` (`.claude/agents/`). No hay agentes desarrolladores.
- Motivo: un solo chat para el equipo, con contextos especializados por rol.
- Decidió: equipo (solicitud de configuración).

### D-011 · Modelo de los subagentes
- Fecha: 2026-10-12
- Estado: Propuesta
- Decisión: `model: sonnet` para ambos agentes.
- Motivo: buena relación entre calidad y costo. Opus cuesta más sin una ventaja clara para estas tareas. Haiku es más barato, pero con más riesgo de omitir trazabilidad o de suponer datos. El Scrum Master podría pasar a `haiku` si el consumo preocupa.
- Decidió: propuesta del asistente, pendiente de confirmar por el equipo.

### D-012 · Origen de las skills
- Fecha: 2026-10-12
- Estado: Hecho
- Decisión: las skills se cargan del plugin `role-based-skills`, ubicado en `~/.claude/skills/claude-role-skills` (CrashBytes/claude-role-skills, commit `cf4acdb`). Los agentes las precargan como `role-based-skills:product-owner` y `role-based-skills:scrum-master`.
- Impacto: la copia en `.claude/skills/claude-role-skills` es idéntica, pero la global la oculta porque las dos se llaman `role-based-skills`. Claude Code lo indica como aviso al iniciar. En una computadora sin el clon global, se carga la copia del proyecto y los agentes funcionan igual.

### D-013 · Jira en esta etapa
- Fecha: 2026-10-12
- Estado: Reemplazada por D-020
- Decisión: no se conecta ni se modifica Jira todavía. Los agentes no tienen herramientas MCP. Las escrituras del servidor Atlassian piden confirmación (`.claude/settings.json`).
- Decidió: equipo (solicitud de configuración).

### D-014 · Convención de IDs locales del backlog
- Fecha: 2026-10-12
- Estado: Aprobada (el equipo autorizó el lote 01 de Jira, que usa esta convención)
- Decisión: épicas `EP-NN` e historias `HU-NNN`, independientes de las claves que asigne Jira.
- Motivo: poder trazar el backlog antes de crear las incidencias en Jira.
- Decidió: propuesta del asistente, pendiente de aprobación.

## Decisiones de planificación (2026-10-12)

### D-015 · Calendario del proyecto
- Fecha: 2026-10-12
- Estado: Aprobada
- Decisión: 9 sprints de 1 semana laboral (lunes a viernes) entre el 12/10/2026 y el 11/12/2026, fecha de entrega final.

| Sprint | Fechas (2026) |
|---|---|
| 1 | 12–16 oct |
| 2 | 19–23 oct |
| 3 | 26–30 oct |
| 4 | 2–6 nov |
| 5 | 9–13 nov |
| 6 | 16–20 nov |
| 7 | 23–27 nov |
| 8 | 30 nov–4 dic |
| 9 | 7–11 dic |

- Impacto: la ERS (2.2 y RNF-03) habla de una primera versión de 10 semanas. No se modifica la ERS; el criterio de RNF-03 se evalúa al cierre del Sprint 9. Actualizar la ERS queda pendiente de instrucción del equipo.
- Decidió: equipo.

### D-016 · Eventos Scrum
- Fecha: 2026-10-12
- Estado: Aprobada
- Decisión: Sprint Planning los lunes; Daily Scrum de 15 minutos diarios; Sprint Review y Retrospectiva los viernes.
- Decidió: equipo.

### D-017 · Roles del equipo
- Fecha: 2026-10-12
- Estado: Reemplazada por D-021 en la asignación de personas (los roles técnicos se mantienen)
- Decisión: Dev 1 Product Owner y Frontend Lead; Dev 2 Scrum Master y Backend Lead; Dev 3 Lógica de negocio y algoritmos; Dev 4 QA, documentación y despliegue. Los cuatro son desarrolladores y pueden colaborar en otras áreas.
- Personas según la ERS (1.3): Product Owner = Jose Luis Medina Ramirez (Dev 1); Scrum Master = Carlos Daniel Gaona Gomez (Dev 2). Rafael Piedra Sanchez y Jose Gael Cruz Aviles son Dev 3 y Dev 4, en orden por confirmar.
- Decidió: equipo.

### D-018 · Jerarquía de documentos
- Fecha: 2026-10-12
- Estado: Aprobada
- Decisión: 1) ERS, fuente principal de requisitos y alcance; 2) `docs/PLANIFICACIÓN SCRUM.pdf`, base del calendario y responsabilidades, sujeta a refinamiento técnico; 3) `docs/SCRUM_ONLY.pdf`, referencia metodológica.
- Decidió: equipo.

### D-019 · Proyecto Jira
- Fecha: 2026-10-12
- Estado: Hecho
- Decisión: sitio `ligafutbol.atlassian.net`, proyecto `SCRUM` («LIGA FUTBOL»), administrado por el equipo (team-managed), con tipos Epic, Story, Task y Subtask.

### D-020 · Interacción con Jira
- Fecha: 2026-10-12
- Estado: Aprobada
- Decisión: la sesión principal es la única que escribe en Jira. Antes de cada lote muestra una vista previa y espera autorización explícita; después verifica cada resultado. No marca trabajo como terminado ni inicia sprints sin autorización. Los subagentes siguen sin acceso a Jira.
- Decidió: equipo.

### D-021 · Asignación de personas
- Fecha: 2026-10-12
- Estado: Aprobada
- Decisión:

| Rol técnico | Persona | Rol Scrum | Fortaleza declarada |
|---|---|---|---|
| Dev 1 · Frontend Lead | Jose Luis Medina Ramirez | Product Owner | Interfaces |
| Dev 2 · Backend Lead | Rafael Piedra Sanchez | — | Laravel |
| Dev 3 · Lógica de negocio y algoritmos | Jose Gael Cruz Aviles | — | Flexible: se le asigna lo necesario |
| Dev 4 · QA, documentación y despliegue | Carlos Daniel Gaona Gomez (cuenta Jira «Daniel») | Scrum Master | Flexible: se le asigna lo necesario |

- Motivo: indicación de Daniel. Medina sabe de interfaces y Rafa de Laravel; Gael y Daniel se asignan según la necesidad de cada sprint. El rol Scrum de Daniel sale de la ERS (1.3).
- Decidió: equipo (Daniel). La asignación de Gael como Dev 3 la propuso el coordinador.

### D-022 · Capacidad del equipo
- Fecha: 2026-10-12
- Estado: Aprobada
- Decisión: cada integrante dispone de unas 2–4 horas diarias, de lunes a viernes (10–20 h por sprint antes de descontar los eventos Scrum). El Sprint 1 tiene 4 días hábiles (martes 13 a viernes 16).
- Impacto: el roadmap y el reparto de carga se planifican con esta capacidad (`scrum/sprints/roadmap.md`).
- Decidió: equipo (Daniel).

### D-023 · Versiones de la plataforma
- Fecha: 2026-10-12
- Estado: Aprobada (criterio «versión estable más reciente de cada tecnología»); las herramientas marcadas «propuesta» esperan confirmación.
- Decisión (verificado en las fuentes oficiales el 12/10/2026):

| Componente | Versión | Nota |
|---|---|---|
| PHP | 8.5 (última 8.5.x) | Rama estable más reciente, con soporte activo hasta el 31/12/2027. PHP 8.6 sigue en RC y no se usa. |
| Laravel | 13.x (`laravel/framework ^13.0`; 13.35.0 publicado el 06/10/2026) | Requiere PHP 8.3–8.5. |
| MariaDB | 12.3 LTS | LTS más reciente (GA el 28/05/2026). La 13.x es de lanzamiento continuo (rolling). Laravel admite MariaDB 10.3+. |
| Composer | 2.10.3 | |
| Node.js (solo para compilar assets) | 24 LTS «Krypton» (24.21.0) | Node 26 sigue como «Current». |
| Vite / Tailwind CSS / laravel-vite-plugin | 8 / 4 / 3 | Los que trae el esqueleto de Laravel 13. |
| PHPUnit | 12.5 | El que trae el esqueleto de Laravel 13. |
| Laravel Pint | 1.27 o superior | RNF-38 (PSR-12). |
| Larastan | 3.13 | Nivel 5 o superior (RNF-38). |
| spatie/laravel-permission | 8.3 | RBAC (RF-12.1). |
| laravel/fortify | 1.41 | Propuesta: autenticación en el servidor con 2FA TOTP, recuperación de contraseña y verificación de correo, usando vistas Blade propias. Los starter kits de Laravel 13 usan Inertia (React/Vue/Svelte) o Livewire, y Breeze ya no recibe actualizaciones; Fortify con Blade respeta IC-02. |
| barryvdh/laravel-dompdf | 3.1.2 | PDF (Tarjetón, exportables). |
| Túnel de desarrollo | Agente ngrok v3 con dominio estático gratuito | Propuesta: URL fija con HTTPS para las Reviews (RNF-32). La alternativa es Cloudflare Tunnel. |
| Correo en desarrollo | Mailpit | Propuesta: captura los correos en local mientras se define el servicio SMTP real. |

- Decidió: equipo (criterio); coordinador (versiones concretas, verificadas en php.net, laravel.com, mariadb.org, nodejs.org, getcomposer.org y packagist.org).

## Integración con Jira Cloud

Situación verificada el 2026-10-12 (solo lectura):
- Servidor MCP `plugin:enterprise-search:atlassian` conectado al sitio `ligafutbol.atlassian.net` con la cuenta «Daniel» (permisos `read:jira-work` y `write:jira-work`).
- Proyecto `SCRUM` («LIGA FUTBOL»), team-managed. Tipos: Epic, Story, Task y Subtask. Campos disponibles en Story: Parent (épica), Story point estimate (`customfield_10016`), Sprint (`customfield_10020`), Start date, Due date, Labels, Priority (Highest…Lowest), Assignee y Flagged (impedimento).
- Incidencias existentes: SCRUM-3 «Task 3» (En curso) y SCRUM-4 «Subtask 2.1» (Por hacer), sin sprint. Parecen ejemplos de la plantilla.
- Cuentas encontradas: «Medina Ramírez José Luis», «Rafael Piedra», «Jose Gael Cruz Aviles» y «Daniel» (cuenta conectada). No aparece ninguna cuenta con «Gaona» ni «Carlos».
- Zona horaria: el MCP no expone la configuración. Las fechas que devuelve Jira vienen con desfase −06:00, compatible con America/Mexico_City; falta confirmarlo en la configuración del sitio y del perfil.
- El MCP no tiene herramientas para crear, iniciar ni cerrar sprints, ni para borrar incidencias. Los sprints se crean en la interfaz de Jira.

Pendientes:
1. ~~Confirmar que la cuenta «Daniel» es de Carlos Daniel Gaona Gomez.~~ Resuelto: sí lo es (D-021).
2. Confirmar la zona horaria del sitio y de los perfiles (America/Mexico_City).
3. Decidir qué hacer con SCRUM-3 y SCRUM-4.
4. Crear los sprints 1–9 en la interfaz de Jira con las fechas de D-015.
5. Ejecutar los lotes de épicas, historias y subtareas aprobados (`scrum/jira-lote-01.md`).

## Pendientes de decisión

- Aprobar `docs/BPMN_procesos.drawio` para la sección 4.1 de la ERS. Falta confirmar quién valida el resultado del partido, la aprobación del Comité Ejecutivo y el orden cotejo/pago del registro.
- Los 12 puntos del Anexo B de la ERS (validación con la Liga).
- Definition of Ready, Definition of Done y escala de estimación (la capacidad y la asignación de personas quedaron en D-021 y D-022). Lista completa en `scrum/pendientes-validacion.md`.
- D-011 (modelo de los agentes).
