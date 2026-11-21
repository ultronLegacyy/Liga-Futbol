<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="@yield('descripcion', 'Portal público de la Liga Municipal de Fútbol.')">
    <title>@yield('title', 'Portal') | Liga Municipal de Fútbol</title>
    {{--
        Portal público (IU-01, RNF-04, RNF-10, RNF-13): CSS en línea y sin JavaScript para cargar rápido
        en un celular con 4G; contraste de al menos 4.5:1 y objetivos táctiles de 44 px.
    --}}
    <style>
        :root {
            --fondo: #101512;
            --tarjeta: #191f1b;
            --borde: #303a33;
            --texto: #f3f6f3;
            --texto-secundario: #b4beb6;
            --enlace: #8bd6a9;
            --foco: #f5c86b;
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            min-width: 320px;
            background: var(--fondo);
            color: var(--texto);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            font-size: 1rem;
            line-height: 1.5;
        }

        a { color: var(--enlace); }

        a:focus-visible, button:focus-visible {
            outline: 3px solid var(--foco);
            outline-offset: 2px;
        }

        .saltar {
            position: absolute;
            left: -9999px;
            top: 0;
            padding: .75rem 1rem;
            background: var(--foco);
            color: #101512;
            font-weight: 700;
            z-index: 10;
        }

        .saltar:focus { left: 1rem; top: 1rem; }

        .encabezado {
            border-bottom: 1px solid var(--borde);
            background: var(--tarjeta);
        }

        .encabezado .contenedor {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: .5rem 1rem;
        }

        .marca {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--texto);
            text-decoration: none;
        }

        .navegacion { display: flex; flex-wrap: wrap; gap: .25rem; }

        .navegacion a {
            display: inline-flex;
            align-items: center;
            min-height: 44px;
            padding: 0 .75rem;
            border-radius: .5rem;
            text-decoration: none;
        }

        .navegacion a[aria-current="page"] {
            background: #176b45;
            color: #fff;
        }

        .contenedor {
            max-width: 64rem;
            margin: 0 auto;
            padding: 1rem;
        }

        .pie {
            border-top: 1px solid var(--borde);
            color: var(--texto-secundario);
            font-size: .875rem;
        }

        @yield('estilos')
    </style>
</head>
<body>
    <a class="saltar" href="#contenido">Saltar al contenido</a>

    <header class="encabezado">
        <div class="contenedor">
            <a class="marca" href="{{ route('portal.programacion') }}">Liga Municipal de Fútbol</a>
            <nav class="navegacion" aria-label="Portal público">
                <a href="{{ route('portal.programacion') }}" @if (request()->routeIs('portal.programacion')) aria-current="page" @endif>Programación</a>
                @guest
                    <a href="{{ route('inicio') }}">Iniciar sesión</a>
                @else
                    <a href="{{ auth()->user()->rutaInicio() }}">Ir al panel</a>
                @endguest
            </nav>
        </div>
    </header>

    <main id="contenido" class="contenedor" tabindex="-1">
        @yield('content')
    </main>

    <footer class="pie">
        <div class="contenedor">
            <p>Información pública de la liga. Para cualquier aclaración, comunícate con la Secretaría de la liga.</p>
        </div>
    </footer>
</body>
</html>
