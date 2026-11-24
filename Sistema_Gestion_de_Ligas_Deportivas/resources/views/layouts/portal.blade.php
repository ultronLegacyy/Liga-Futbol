<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="@yield('descripcion', 'Portal público de la Liga Municipal de Fútbol.')">
    <meta name="theme-color" content="#082a86">
    <title>@yield('title', 'Portal') | Liga Municipal de Fútbol</title>
    {{--
        Portal público (IU-01, RNF-04, RNF-10, RNF-13), con el diseño deportivo aprobado en HAB-S7 (SCRUM-236):
        CSS en línea y sin JavaScript ni recursos externos, para cargar rápido en un celular con 4G;
        contraste de al menos 4.5:1, objetivos táctiles de 44 px y animaciones solo si el sistema las permite.
    --}}
    <style>
        :root {
            --fondo: #f2f4f8;
            --tarjeta: #ffffff;
            --suave: #e8edf6;
            --borde: #d9e0eb;
            --texto: #0d1220;
            --texto-secundario: #4a5468;
            --marino: #082a86;
            --electrico: #1656e6;
            --acento: #1f5eff;
            --celeste: #7cc6ff;
            --oscuro: #071a4f;
            --foco: #ffb020;
            --display: "Barlow Condensed", "Roboto Condensed", "Arial Narrow", "Helvetica Neue Condensed", Impact, sans-serif;
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
            -webkit-font-smoothing: antialiased;
        }

        a { color: var(--acento); }

        a:focus-visible, button:focus-visible, summary:focus-visible {
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

        .contenedor {
            max-width: 72rem;
            margin: 0 auto;
            padding: 1rem;
        }

        /* Tipografía de exhibición: condensada, gruesa e inclinada */
        .display {
            font-family: var(--display);
            font-weight: 900;
            font-style: italic;
            font-stretch: condensed;
            text-transform: uppercase;
            letter-spacing: -.01em;
            line-height: .92;
        }

        /* Encabezado y portada (hero) en azul eléctrico */
        .portada {
            position: relative;
            overflow: hidden;
            color: #fff;
            background: linear-gradient(155deg, var(--marino), var(--electrico) 62%, var(--marino));
        }

        .portada::before {
            content: "";
            position: absolute;
            right: -14%;
            bottom: -45%;
            width: 75%;
            height: 80%;
            border-radius: 4rem 0 0 0;
            background: linear-gradient(170deg, rgba(255, 255, 255, .2), rgba(255, 255, 255, .03));
            transform: rotate(-16deg);
            pointer-events: none;
        }

        .portada a { color: #fff; }

        .barra {
            position: relative;
            z-index: 2;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: .5rem 1rem;
        }

        .marca {
            display: inline-flex;
            align-items: center;
            gap: .625rem;
            min-height: 44px;
            color: #fff;
            text-decoration: none;
        }

        .marca svg { width: 2.125rem; height: 2.125rem; flex: none; }
        .marca b { display: block; font-size: 1.625rem; }
        .marca small { display: block; font-size: .6875rem; font-weight: 700; letter-spacing: .18em; text-transform: uppercase; opacity: .85; }

        .navegacion { display: flex; flex-wrap: wrap; gap: .25rem; }

        .navegacion a {
            display: inline-flex;
            align-items: center;
            min-height: 44px;
            padding: 0 .875rem;
            border-radius: 999px;
            font-size: .9375rem;
            font-weight: 600;
            text-decoration: none;
        }

        .navegacion a:hover { background: rgba(255, 255, 255, .14); }

        .navegacion a[aria-current="page"] {
            background: rgba(255, 255, 255, .16);
            box-shadow: inset 0 -3px 0 var(--celeste);
        }

        .navegacion .cuenta {
            background: var(--celeste);
            color: var(--oscuro);
            font-weight: 800;
        }

        .navegacion .cuenta:hover { background: #fff; }

        .hero {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 1.5rem;
            align-items: center;
            padding-top: 1.5rem;
            padding-bottom: 2rem;
        }

        .etiqueta-hero {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            margin: 0 0 1rem;
            padding: .375rem .875rem .375rem .625rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, .14);
            font-size: .75rem;
            font-weight: 800;
            letter-spacing: .16em;
            text-transform: uppercase;
        }

        .punto {
            width: .5625rem;
            height: .5625rem;
            border-radius: 50%;
            background: #ff5a4f;
        }

        .hero h1 {
            margin: 0;
            font-size: clamp(2.25rem, 10vw, 6.5rem);
            overflow-wrap: anywhere;
            text-wrap: balance;
        }

        .hero h1 em { font-style: inherit; color: var(--celeste); }

        .linea {
            display: block;
            width: 12rem;
            height: 4px;
            margin: 1.25rem 0 1rem;
            background: #fff;
            clip-path: polygon(0 0, 100% 0, 96% 100%, 0 100%);
        }

        .hero-texto p { margin: 0; max-width: 34rem; font-size: 1.0625rem; opacity: .92; }

        .arte {
            position: relative;
            height: 9.5rem;
            pointer-events: none;
        }

        .estelas {
            position: absolute;
            inset: 25% -20% 20% 10%;
            transform: rotate(-9deg) skewX(-14deg);
            background:
                repeating-linear-gradient(180deg, transparent 0 9px, rgba(124, 198, 255, .75) 9px 14px, transparent 14px 22px, rgba(255, 255, 255, .55) 22px 25px, transparent 25px 34px, rgba(255, 90, 79, .6) 34px 38px, transparent 38px 47px);
            -webkit-mask-image: linear-gradient(90deg, transparent, #000 40%, #000 85%, transparent);
            mask-image: linear-gradient(90deg, transparent, #000 40%, #000 85%, transparent);
            filter: blur(1px);
        }

        .balon {
            position: absolute;
            left: 18%;
            top: 0;
            width: 9rem;
            height: 9rem;
            filter: drop-shadow(0 1.5rem 1.5rem rgba(0, 0, 0, .35));
        }

        .balon .gajos { transform-origin: 100px 100px; }

        @media (prefers-reduced-motion: no-preference) {
            .balon { animation: flotar 5s ease-in-out infinite; }
            .balon .gajos { animation: girar 16s linear infinite; }
            .estelas { animation: correr 2.2s linear infinite; background-size: 100% 47px; }
            .punto { animation: latido 1.6s infinite; }
        }

        @keyframes flotar { 50% { transform: translateY(-.875rem); } }
        @keyframes girar { to { transform: rotate(360deg); } }
        @keyframes correr { to { background-position: 0 47px; } }
        @keyframes latido { 50% { box-shadow: 0 0 0 .5rem rgba(255, 90, 79, 0); } 0% { box-shadow: 0 0 0 0 rgba(255, 90, 79, .7); } }

        /* Componentes compartidos del portal */
        .seccion-titulo {
            margin: 2rem 0 .875rem;
            font-size: 2rem;
        }

        .chips {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .chips a {
            display: inline-flex;
            align-items: center;
            gap: .375rem;
            min-height: 44px;
            padding: 0 1.125rem;
            border: 2px solid currentColor;
            border-radius: 999px;
            font-size: .875rem;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
            text-decoration: none;
        }

        .chips a:hover { background: #fff; color: var(--marino); border-color: #fff; }

        .tarjeta {
            border: 1px solid var(--borde);
            border-radius: 1.25rem;
            background: var(--tarjeta);
            overflow: hidden;
        }

        .aviso {
            margin: 1.5rem 0;
            padding: 1rem 1.25rem;
            border: 1px solid #f0c36a;
            border-left: 6px solid #d98b00;
            border-radius: 1rem;
            background: #fff8e8;
        }

        .aviso p { margin: 0 0 .5rem; }
        .aviso ul { margin: 0; padding-left: 1.25rem; }
        .aviso li + li { margin-top: .25rem; }

        .vacio {
            margin: 1.5rem 0;
            padding: 1.5rem;
            border: 2px dashed var(--borde);
            border-radius: 1.25rem;
            background: var(--tarjeta);
        }

        .vacio p { margin: 0 0 .5rem; }
        .vacio p:last-child { margin: 0; }

        .pie {
            margin-top: 3rem;
            background: var(--oscuro);
            color: #dbe4f5;
            font-size: .875rem;
        }

        .pie a { color: #fff; }

        .pie .contenedor {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding-top: 2rem;
            padding-bottom: 2rem;
        }

        .pie p { margin: 0; max-width: 36rem; }

        @media (min-width: 48rem) {
            .contenedor { padding: 1.25rem 2rem; }
            .hero { grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); padding-top: 2.5rem; padding-bottom: 3.5rem; }
            .arte { height: 18rem; }
            .balon { width: 15rem; height: 15rem; left: 22%; top: 1rem; }
            .seccion-titulo { font-size: 2.5rem; }
        }

        @yield('estilos')
    </style>
</head>
<body>
    <a class="saltar" href="#contenido">Saltar al contenido</a>

    <header class="portada">
        <div class="contenedor barra">
            <a class="marca" href="{{ route('portal.programacion') }}">
                <svg viewBox="0 0 40 40" aria-hidden="true" focusable="false"><path d="M4 28 22 6h14L18 28Z" fill="#fff"/><path d="M14 34 24 22h12L26 34Z" fill="#7cc6ff"/></svg>
                <span><b class="display">Liga Municipal</b><small>de Fútbol · Morelia</small></span>
            </a>
            <nav class="navegacion" aria-label="Portal público">
                <a href="{{ route('portal.programacion') }}" @if (request()->routeIs('portal.programacion')) aria-current="page" @endif>Programación</a>
                <a href="{{ route('portal.resultados') }}" @if (request()->routeIs('portal.resultados')) aria-current="page" @endif>Resultados</a>
                <a href="{{ route('portal.castigados') }}" @if (request()->routeIs('portal.castigados')) aria-current="page" @endif>Castigados</a>
                <a href="{{ route('portal.buscar') }}" @if (request()->routeIs('portal.buscar')) aria-current="page" @endif>Buscar</a>
                @guest
                    <a class="cuenta" href="{{ route('inicio') }}">Iniciar sesión</a>
                @else
                    <a class="cuenta" href="{{ auth()->user()->rutaInicio() }}">Ir al panel</a>
                @endguest
            </nav>
        </div>

        <div class="contenedor hero">
            <div class="hero-texto">
                @yield('hero')
            </div>
            <div class="arte" aria-hidden="true">
                <div class="estelas"></div>
                <svg class="balon" viewBox="0 0 200 200" focusable="false">
                    <defs>
                        <radialGradient id="luz" cx="34%" cy="28%" r="80%"><stop offset="0" stop-color="#fff"/><stop offset=".55" stop-color="#eef1f5"/><stop offset="1" stop-color="#a7afb9"/></radialGradient>
                        <radialGradient id="sombra" cx="34%" cy="28%" r="78%"><stop offset=".45" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity=".5"/></radialGradient>
                        <clipPath id="esfera"><circle cx="100" cy="100" r="96"/></clipPath>
                    </defs>
                    <circle cx="100" cy="100" r="96" fill="url(#luz)"/>
                    <g clip-path="url(#esfera)"><g class="gajos">
                        <path fill="#1b1f26" d="M100,70 129,91 118,124 82,124 71,91ZM100,34 73,15 84,-17 116,-17 127,15ZM163,80 173,48 206,48 216,80 189,99ZM139,153 172,153 182,185 155,204 129,185ZM61,153 71,185 45,204 18,185 28,153ZM37,80 11,99 -16,80 -6,48 27,48Z"/>
                        <path fill="none" stroke="#2a2f37" stroke-width="2.4" d="M100,70 L100,34M129,91 L163,80M118,124 L139,153M82,124 L61,153M71,91 L37,80M127,15 L141,43 L173,48M114,80 L141,43M189,99 L167,122 L172,153M123,108 L167,122M129,185 L100,170 L71,185M100,124 L100,170M28,153 L33,122 L11,99M77,108 L33,122M27,48 L59,43 L73,15M86,80 L59,43"/>
                    </g></g>
                    <circle cx="100" cy="100" r="96" fill="url(#sombra)"/>
                    <ellipse cx="70" cy="58" rx="30" ry="18" fill="#fff" opacity=".55" transform="rotate(-30 70 58)"/>
                </svg>
            </div>
        </div>
    </header>

    <main id="contenido" class="contenedor" tabindex="-1">
        @yield('content')
    </main>

    <footer class="pie">
        <div class="contenedor">
            <p>Información pública de la liga. Para cualquier aclaración, comunícate con la Secretaría de la liga.</p>
            <nav class="chips" aria-label="Pie del portal">
                <a href="{{ route('portal.programacion') }}">Programación</a>
                <a href="{{ route('portal.resultados') }}">Resultados y tablas</a>
                <a href="{{ route('portal.castigados') }}">Castigados</a>
            </nav>
        </div>
    </footer>
</body>
</html>
