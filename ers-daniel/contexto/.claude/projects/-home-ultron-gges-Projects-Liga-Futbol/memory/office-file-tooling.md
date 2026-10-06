---
name: office-file-tooling
description: "En esta máquina no hay pandoc, markitdown, LibreOffice ni npm; cómo leer, generar, validar y renderizar los archivos Office del proyecto"
metadata:
  node_type: memory
  type: project
  originSessionId: 77f17848-87b2-415d-b0cf-25f2ea23444e
  modified: 2026-10-05T18:22:19.006Z
---

Verificado 2026-10-05: no hay pandoc, markitdown, soffice/LibreOffice, npm (ni el paquete `docx`), python-docx, python-pptx, openpyxl ni pandas. Sí hay python3 (stdlib), node, pdftotext/pdftoppm, ImageMagick (montage/magick), rsvg-convert y PIL. Shell por defecto: zsh (usar `bash -c` para arreglos y expansión de palabras).
- Leer .docx/.pptx/.xlsx: zipfile + xml.etree. `contexto/ERS simplificado.docx` es en realidad un .doc OLE2 (requiere parser CFB).
- Generar .docx: escribir OOXML a mano con stdlib (respetar el orden del esquema: p. ej., tblLayout va después de tblBorders; updateFields antes de compat).
- Validar: el validate.py de la skill docx necesita lxml → `python3 -m venv <scratchpad>/venv && pip install lxml defusedxml`.
- Renderizar a PDF: OnlyOffice `/opt/onlyoffice/desktopeditors/converter/x2t params.xml` (m_nFormatTo=513, m_sAllFontsPath=~/.local/share/onlyoffice/desktopeditors/data/fonts/AllFonts.js). x2t no respeta cantSplit en tablas; Word sí.
- Diagramas: SVG a mano → rsvg-convert a PNG.

**Why:** evita volver a descubrir las herramientas que faltan.
**How to apply:** usar estos caminos directamente. Ver [[liga-ers-project]].
