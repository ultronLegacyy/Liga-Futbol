---
name: liga-ers-project
description: "ERS del Sistema de Gestión de la Liga Municipal de Futbol Amateur Morelia — fuentes, decisiones del usuario, hallazgos y estado"
metadata:
  node_type: memory
  type: project
  originSessionId: 77f17848-87b2-415d-b0cf-25f2ea23444e
  modified: 2026-10-05T18:22:13.070Z
---

Proyecto académico de Ingeniería de Software (TecNM Morelia; materiales del Dr. Claudio Ernesto Florián Arenas), redactado como ERS de producto real. Rama `ers-daniel`; insumos en `ers-daniel/contexto/`; entregable en `ers-daniel/ers-daniel.docx`.

Fuentes primarias (desde 2026-10-05): `respuestas.docx` (respuestas P1–P23 del usuario), `convocatoria.jpg` y `convocatorio_infantil.jpg` (convocatorias 2026–2027, firmadas 03/08/2026), `calendario.jpg` (programación rama infantil 03/10/2026). La presentación `Sistema_Gestion_...pptx` es fuente secundaria.

Hallazgos:
- `reglamento_liga_municipal_infantil.pdf` NO es de la Liga: es de Merlo, Buenos Aires, Argentina (2022). El reglamento interno real no se encontró ni en línea.
- `1. Cuestionario…xlsx` y `2. Business_Model_Canvas…pptx` son de otro proyecto (Ally-Educ); SCRUM_ONLY.pdf es curso genérico.
- Prensa (Contramuro 2025): 232 clubes, 732 equipos, >25,000 jugadores, 25 categorías, 40 canchas en la U.D.C.
- El instituto municipal es IMCUFIDE (el usuario lo llamó «IMDE»).
- Edades de veteranos del usuario no coinciden con la convocatoria; se adoptó la convocatoria.

Decisiones del usuario: por ahora solo la sección 1 (1.1–1.5); el modelado del sistema va en 1.5; 1.6 Personal involucrado y los datos de portada/historial quedan en blanco para que él los llene; especificar el sistema completo y priorizar lo que cabe en 10 semanas; solo app web; online y offline; integración solo WhatsApp; migración histórica por definir; gestión económica dentro del alcance.

Estado: 2026-10-05 se entregó la sección 1 completa en `ers-daniel.docx` (23 páginas, 3 figuras). Siguiente: secciones 2+ cuando el usuario lo pida.

**Why:** el ERS se construye por secciones a lo largo de varias sesiones.
**How to apply:** el usuario editará el .docx a mano (portada, 1.6), así que los cambios futuros deben editar el .docx existente en lugar de regenerarlo. Reglas: [[ers-working-rules]]. Herramientas: [[office-file-tooling]].
