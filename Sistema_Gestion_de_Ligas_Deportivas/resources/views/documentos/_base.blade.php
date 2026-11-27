{{--
    Base de los documentos oficiales en PDF (HU-069, IH-03): carta vertical, márgenes de impresora, letra DejaVu
    (trae acentos y la ñ), tablas en lugar de flex o grid (dompdf) y sin imágenes remotas. El encabezado azul
    marino también se lee impreso en blanco y negro porque el texto va en negritas y con borde.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }}</title>
    <style>
        @page { margin: 18mm 16mm 20mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #111827; line-height: 1.4; }
        .membrete { width: 100%; border-collapse: collapse; border-bottom: 3px solid #082a86; margin-bottom: 14px; }
        .membrete td { padding: 0 0 8px; vertical-align: bottom; }
        .liga { font-size: 15px; font-weight: bold; color: #082a86; text-transform: uppercase; letter-spacing: .5px; }
        .liga small { display: block; font-size: 9px; color: #374151; font-weight: normal; text-transform: none; letter-spacing: 0; }
        .folio { text-align: right; font-size: 9px; color: #374151; }
        .folio b { display: block; font-size: 11px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 2px; text-transform: uppercase; }
        h2 { font-size: 12px; margin: 16px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #9ca3af; text-transform: uppercase; }
        .sub { margin: 0 0 12px; color: #374151; }
        table.datos { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .datos td { padding: 3px 8px 3px 0; vertical-align: top; }
        .datos td.et { width: 28%; font-weight: bold; color: #374151; }
        table.tabla { width: 100%; border-collapse: collapse; }
        .tabla th, .tabla td { border: 1px solid #9ca3af; padding: 5px 6px; text-align: left; vertical-align: top; }
        .tabla th { background: #e5e7eb; font-size: 9.5px; text-transform: uppercase; }
        .tabla tfoot td { font-weight: bold; background: #f3f4f6; }
        .num { text-align: right !important; white-space: nowrap; }
        .vacio { padding: 8px 10px; border: 1px dashed #9ca3af; color: #374151; }
        .marcador { width: 100%; border-collapse: collapse; margin: 6px 0 10px; }
        .marcador td { border: 2px solid #111827; padding: 8px; text-align: center; font-size: 13px; font-weight: bold; }
        .marcador td.goles { width: 16%; font-size: 26px; }
        .firmas { width: 100%; border-collapse: collapse; margin-top: 18px; }
        .firmas td { width: 50%; padding: 0 12px; text-align: center; vertical-align: bottom; }
        .firmas img { max-width: 180px; max-height: 70px; }
        .firmas .linea { border-top: 1px solid #111827; padding-top: 4px; margin-top: 4px; }
        .pie { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 8.5px; color: #4b5563; border-top: 1px solid #d1d5db; padding-top: 4px; }
        .sello { margin-top: 22px; padding: 10px; border: 2px solid #082a86; }
    </style>
</head>
<body>
    <div class="pie">Documento generado por el Sistema de Gestión de la Liga el {{ $generado }} h (hora de México) · Folio {{ $folio }}.</div>

    <table class="membrete">
        <tr>
            <td class="liga">Liga Municipal de Fútbol Amateur de Morelia<small>Sistema de Gestión de la Liga Deportiva (SGLD)</small></td>
            <td class="folio">Folio<b>{{ $folio }}</b>{{ $generado }} h</td>
        </tr>
    </table>

    @yield('documento')
</body>
</html>
