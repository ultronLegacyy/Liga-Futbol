# SGLD: gestión de requisitos y Scrum

## Proyecto

- Sistema: «Sistema de Gestión para la Liga Municipal de Morelia» (nombre oficial en la ERS); el equipo lo llama SGLD.
- Cliente: Liga Municipal de Fútbol Amateur de Morelia, A.C. Proyecto académico de Ingeniería de Software (TecNM Morelia).
- Esta carpeta guarda la ERS y la gestión Scrum del proyecto. No contiene código de la aplicación; el desarrollo lo hacen los cuatro integrantes humanos.
- Equipo (D-021): Dev 1 Jose Luis Medina Ramirez (Product Owner, Frontend/interfaces); Dev 2 Rafael Piedra Sanchez (Backend Laravel); Dev 3 Jose Gael Cruz Aviles (lógica y algoritmos, flexible); Dev 4 Carlos Daniel Gaona Gomez (Scrum Master; QA, documentación y despliegue, flexible). Capacidad: 2–4 h diarias por persona (D-022). Versiones de la plataforma: D-023.
- Calendario (D-015): 9 sprints de 1 semana, del 12/10/2026 al 11/12/2026. Planning los lunes, Daily de 15 min, Review y Retro los viernes.

## Documentos

| Ruta | Contenido |
|---|---|
| `docs/ERS.docx` | ERS editable (ISO/IEC/IEEE 29148). Solo se modifica con instrucción explícita del equipo. |
| `docs/ERS.pdf` | Versión de lectura de la ERS (31 págs.). Debe regenerarse cada vez que cambie el `.docx`. |
| `docs/BPMN_procesos.drawio` | 4 procesos BPMN «como será»; pendiente de aprobación para la sección 4.1 de la ERS. |
| `docs/PLANIFICACIÓN SCRUM.pdf` | Plan previo del equipo (13 págs.): 9 sprints de 1 semana hasta el 11/12, eventos, roles Dev 1–4 y épicas mapeadas a RF/AF. Es un insumo; lo que se adopte se registra en `docs/decisiones.md`. |
| `docs/SCRUM_ONLY.pdf` | Presentación del curso «Introducción a Scrum» (142 diapositivas escaneadas, sin texto). Leer solo por páginas. |
| `docs/decisiones.md` | Registro de decisiones del proyecto (memoria persistente). |
| `docs/trazabilidad.md` | Matriz requisito de la ERS → épica → historia → sprint → Jira. |
| `scrum/product-backlog.md` | Product Backlog. |
| `scrum/sprints/roadmap.md` | Roadmap provisional S1–S9 (solo el sprint en curso está comprometido). |
| `scrum/sprints/`, `scrum/ceremonias/` | Sprint Backlogs (`sprint-NN.md`) y notas de eventos Scrum. |
| `scrum/pendientes-validacion.md` | Lo que el equipo o la Liga deben confirmar (V-xx, PA-xx, Anexo B). |
| `scrum/jira/` | Lotes de cambios para Jira: vista previa (`.md`) y datos exactos (`.json`). |
| `historial/` | Conversaciones exportadas. |

Otros insumos en `docs/`: `respuestas.docx`, convocatorias (`.jpg`), Business Model Canvas y la presentación base del sistema.

Estado de la ERS: las secciones 4.2 y 4.3 no se tocan sin instrucción. El Anexo B lista 12 puntos por validar con la Liga.

## Coordinador principal (esta sesión)

- Atiende al equipo, decide qué delegar y a qué agente, y presenta los resultados.
- Pasa a cada agente solo lo necesario (tarea, rutas, IDs), no la conversación completa.
- Registra en `docs/decisiones.md` las decisiones que el equipo apruebe.
- Atiende directamente las preguntas rápidas, los cambios a la ERS (solo con instrucción) y la preparación de Jira.

## Cuándo invocar a cada agente

- `@agent-product-owner`: analizar la ERS; identificar RF/RNF/AF/OR; crear o mejorar épicas e historias; redactar criterios de aceptación; aplicar INVEST y MoSCoW; priorizar el Product Backlog; actualizar la trazabilidad; detectar dependencias, riesgos y ambigüedades.
- `@agent-scrum-master`: Sprint Planning, Sprint Goal y Sprint Backlog; Daily Scrum, Sprint Review y Retrospectiva; impedimentos; seguimiento del sprint; revisión de prácticas Scrum; comunicación entre los cuatro integrantes.
- Una tarea va a un solo agente. Si necesita a los dos (por ejemplo, el refinamiento antes de un Planning), primero el Product Owner y después el Scrum Master con el resultado.
- No hay agentes desarrolladores y no se crean más agentes sin aprobación del equipo.

## Trazabilidad

- IDs de la ERS: RF-01 a RF-16 (incisos RF-xx.y), RNF-xx, AF-01 a AF-14, OR-xx, IU/IH/IC. No se inventan, no se renumeran y no se reutilizan.
- Cada épica e historia cita al menos un ID de la ERS. Lo que no tenga respaldo en la ERS se marca `[POR DEFINIR]` y se pregunta.
- La ERS exige que el 100 % de los RF Alta/Esencial y al menos el 80 % de los Media/Deseado tengan prueba de aceptación aprobada al cierre de las 10 semanas; la matriz debe permitir comprobarlo.
- Los IDs locales de épicas e historias siguen la convención aprobada en `docs/decisiones.md`. Cuando existan claves de Jira, se anotan junto al ID local en `docs/trazabilidad.md`.

## Backlogs

- `scrum/product-backlog.md` lo mantiene el agente Product Owner; el Product Owner humano aprueba el contenido y el orden.
- `scrum/sprints/sprint-NN.md` lo mantiene el agente Scrum Master; el equipo decide qué entra al sprint.
- Prioridad MoSCoW con la equivalencia de la ERS: Alta/Esencial = Must, Media/Deseado = Should, Baja/Opcional = Could.
- No se registran historias ficticias, estimaciones inventadas ni avances que el equipo no haya reportado. Las estimaciones las da el equipo.

## Jira Cloud

- Sitio `ligafutbol.atlassian.net`, proyecto `SCRUM` («LIGA FUTBOL», team-managed): D-019.
- Solo la sesión principal escribe en Jira, por lotes. Antes de cada lote muestra la vista previa (`scrum/jira/lote-NN.md`) y espera autorización explícita; después verifica cada resultado (D-020). Busca duplicados por la etiqueta del ID local antes de crear.
- Nunca marca trabajo como terminado ni inicia o cierra sprints sin autorización. Los sprints se crean en la interfaz de Jira; el MCP no tiene herramientas para eso.
- Los agentes no tienen acceso a herramientas MCP (`disallowedTools: mcp__*`), y `.claude/settings.json` pide confirmación para toda escritura del servidor Atlassian.

## Aprobación humana

Requieren aprobación explícita del equipo:
- modificar la ERS o el BPMN;
- registrar o cambiar decisiones;
- cambiar prioridades o el orden del Product Backlog;
- comprometer elementos en un sprint;
- cualquier escritura en Jira;
- `git commit` o `git push`;
- borrar o mover archivos.

## Sesiones y persistencia

- Iniciar la sesión principal desde esta carpeta: `claude -n SGLD-Gestion-Scrum`.
- Continuar la más reciente: `claude -c`. Elegir por nombre: `claude -r SGLD-Gestion-Scrum` (selector filtrado) o, dentro de una sesión, `/resume SGLD-Gestion-Scrum`. Renombrar: `/rename SGLD-Gestion-Scrum`.
- Exportar una conversación importante: `/export historial/AAAA-MM-DD_tema.txt`.
- El contexto duradero vive en los archivos (`docs/decisiones.md`, `docs/trazabilidad.md`, `scrum/`) y en la memoria de cada agente (`.claude/agent-memory/`). No se recargan conversaciones completas; si la sesión crece, usar `/compact`.

## Idioma

Todo en español: respuestas, documentos y mensajes de los agentes.
