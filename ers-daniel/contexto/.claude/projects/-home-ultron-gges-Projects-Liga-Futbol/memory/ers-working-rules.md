---
name: ers-working-rules
description: "Reglas obligatorias del usuario para redactar el ERS (no inventar, [POR DEFINIR] con impacto, ISO/IEC/IEEE 29148, atributos por requisito)"
metadata:
  node_type: memory
  type: feedback
  originSessionId: b0eb4969-ffa1-4028-947a-e60155236c87
  modified: 2026-10-03T19:04:04.381Z
---

- Actuar como Ingeniero de Requisitos senior y analista de negocio. Solo ERS: nada de arquitectura, código ni metodologías ágiles.
- No inventar ni suponer. Preguntar antes de escribir. Si falta un dato: [POR DEFINIR] + explicar el impacto. Si no conozco una norma o dato exacto, decirlo.
- Distinguir hechos, normas y opiniones.
- Estándar ISO/IEC/IEEE 29148 (IEEE 830 solo como referencia histórica); RNF según ISO/IEC 25010; idioma español; formato Markdown.
- IDs únicos RF-001, RNF-001… Cada requisito: ID, Nombre, Descripción, Fuente, Prioridad (Alta/Media/Baja o MoSCoW), Criterio de aceptación verificable, Método de verificación, Dependencias, Estado.
- Estructura pedida: 1 Introducción (1.1 Propósito, 1.2 Alcance, 1.3 Definiciones, 1.4 Referencias, 1.5 Visión general), 2 Descripción general (2.1–2.5), 3 Requisitos específicos (3.1–3.5), 4 Verificación y validación, 5 Matriz de trazabilidad, 6 Apéndices (glosario, historial, pendientes).
- Se trabaja por secciones; no generar el ERS hasta que el usuario responda las preguntas.

**Why:** instrucciones explícitas del usuario (2026-10-03).
**How to apply:** aplicarlas en cualquier sesión de este ERS sin que el usuario las repita. Proyecto: [[liga-ers-project]].
