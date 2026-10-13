---
name: ers-internal-inconsistencies
description: Contradicciones o vacíos detectados dentro de la propia ERS del SGLD (no entre ERS y planificación) que afectan la redacción de historias.
metadata:
  type: project
---

Al refinar el Product Backlog completo (2026-10-12) se detectaron inconsistencias dentro de `docs/ERS.pdf` que no se resuelven solas y que cualquier historia relacionada debe marcar `[POR DEFINIR]`:

1. **Rol "Comité Ejecutivo" sin definir en 2.3**: el Anexo A (matriz de trazabilidad de requisitos funcionales) asigna "Comité Ejecutivo" como actor principal de RF-11 (liguilla, ascensos/descensos, cierre de temporada), pero la tabla de usuarios de la sección 2.3 no lo incluye entre los 14 roles. El rol más cercano es "Presidente (Consejo Directivo)", pero no es exacto. Mismo problema con "Mesa Directiva" y "Asamblea de Presidentes" (mencionados en RF-10.5 como segunda instancia de recursos).
2. **Conflicto de prioridad AF-08 vs RF-10**: la Tabla 2 (áreas funcionales) marca "inconformidades y recursos" dentro de AF-08 como Could, pero RF-10 completo (que incluye RF-10.5, inconformidades/recursos) tiene prioridad Alta/Esencial en su ficha de requisito. No hay una regla en la ERS que diga cuál prevalece.
3. **RNF-03 con umbral matemáticamente imposible de cumplir de forma parcial**: solo hay 4 RF Media/Deseado (RF-07, RF-13, RF-15, RF-16). El umbral "≥80% de RF Media con prueba aprobada" exige, con un denominador de 4, completar los 4 (3/4=75%<80%). En la práctica esto vuelve ese umbral un Must encubierto.
4. **RF-03/AF-03 mencionan "credenciales" de jugador** pero ningún inciso RF-03.x o RF-04.x detalla su emisión; lo más cercano es RF-16.2 (cédulas oficiales/constancias en PDF). No está claro si la credencial es un documento distinto.
5. **RF-08.3/RF-08.4 (corregir y validar un resultado) no definen el rol autorizado**; el Anexo B (preguntas 4 y 5) solo da un supuesto de trabajo, no un rol formal de 2.3.
6. **9 vs 10 semanas**: RNF-03 y la sección 2.2 hablan de "10 semanas" para la primera versión, pero el calendario aprobado por el equipo (ver `docs/decisiones.md`, pendiente D "Duración del sprint...") es de 9 sprints de 1 semana.
7. **Comisión Arbitral**: el glosario (Tabla 3) dice textualmente que "su existencia formal en la Liga está por validar", pero 2.3 la lista como rol con permisos plenos de gestión del padrón arbitral. No hay un inciso RF explícito para administrar la disponibilidad de árbitros (RF-03.2 da identidad, RF-06.1 solo asigna árbitro a un partido ya existente).

Todas estas quedaron registradas como preguntas abiertas PA-01 a PA-14 en `scrum/product-backlog.md` (versión del 2026-10-12). Al retomar el backlog, revisar primero si el equipo o la Liga ya las resolvieron antes de volver a flagearlas.
