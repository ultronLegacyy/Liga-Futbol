---
name: estado-del-arte-blockchain-lwc-embebidos
description: "Ubicación y contenido del documento de estado del arte del proyecto, con las hipótesis de diseño ya fijadas"
metadata: 
  node_type: memory
  type: project
  originSessionId: b9d58994-e81c-4f32-afe8-9afc794dd724
  modified: 2026-09-14T20:33:35.532Z
---

El documento de memoria de investigación vive en `docs/01-estado-del-arte-blockchain-lwc-embebidos.md` (dentro del repo del proyecto). Cubre: blockchain (qué es/para qué/cómo/limitaciones), criptografía ligera, sistemas embebidos, convergencia en IoMT, marco regulatorio, brechas de investigación y bibliografía de 55 referencias.

Hipótesis de diseño ya fijadas en él (§8.2), que condicionan todo el trabajo posterior:
- H1: el dispositivo médico **nunca** será nodo de la blockchain; media un *gateway*.
- H2: blockchain **permissioned de consorcio con consenso BFT**, no pública.
- H3: **ningún dato personal on-chain** — solo hashes y punteros (patrón off-chain), por el conflicto con el Art. 17 GDPR.
- H4: **Ascon-AEAD128** (NIST SP 800-232) como primitiva por defecto del canal dispositivo↔gateway.
- H5: criptografía asimétrica solo en eventos raros (un ECDH secp256r1 cuesta ~1457 µJ frente a los ~108 µJ del ciclo completo de protocolo IMDfence).
- H6: se requiere raíz de confianza hardware (secure boot, TrustZone-M, PUF); sin ella lo demás es decorativo (problema del oráculo).
- H7: diseño cripto-ágil desde el inicio, por la vida útil de 8-10 años y la migración post-cuántica.
- H8: primer caso de uso a implementar = anclaje de integridad de firmware/SBOM en la cadena.

Siguiente paso bloqueante (§8.3): **definir la plataforma hardware objetivo** (candidatos anotados: STM32L5/U5, nRF5340, ESP32-C6). Sin eso ninguna medición de rendimiento es comparable.

**Why:** son decisiones ya razonadas con evidencia; re-derivarlas o contradecirlas sin motivo haría perder trabajo.

**How to apply:** antes de proponer arquitectura o código para este proyecto, leer el documento y partir de estas hipótesis; si una debe revisarse, actualizar §8.2 del .md y esta memoria. Ver [[proyecto-seguridad-dispositivos-medicos-embebidos]].
