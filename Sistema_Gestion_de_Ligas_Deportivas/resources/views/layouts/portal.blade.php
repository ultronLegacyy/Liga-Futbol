<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="@yield('descripcion', 'Portal público de la Liga Municipal de Fútbol.')">
    <meta name="theme-color" content="#0b1a5c">
    <title>@yield('title', 'Portal') | Liga Municipal de Fútbol</title>
    {{--
        Portal público (IU-01, RNF-04, RNF-10, RNF-13) con el diseño limpio aprobado por el Product Owner
        (25/11/2026) y su paleta: Ink #0B0F14, Navy #0B1A5C, Royal #2F5BFF (principal), Lime #D4F53C (acento)
        y blanco. CSS en línea, sin JavaScript ni recursos externos; contraste ≥ 4.5:1, objetivos de 44 px y
        una sola animación con transform, solo si el sistema la permite.
    --}}
    <style>
        :root {
            --tinta: #0b0f14;
            --navy: #0b1a5c;
            --royal: #2f5bff;
            --royal-oscuro: #1f44d6;
            --lima: #d4f53c;
            --blanco: #ffffff;

            --fondo: #f3f5fb;
            --tarjeta: var(--blanco);
            --suave: #eef1fb;
            --borde: #e1e6f2;
            --texto: var(--tinta);
            --texto-secundario: #4b5568;
            --marino: var(--navy);
            --electrico: var(--royal);
            --acento: var(--royal-oscuro);
            --celeste: var(--lima);
            --oscuro: var(--navy);
            --foco: #ff9f1c;
            --sombra: 0 12px 32px -14px rgba(11, 26, 92, .22);
            --display: "Barlow Condensed", "Roboto Condensed", "sans-serif-condensed", "Arial Narrow", "Helvetica Neue Condensed", Impact, sans-serif;
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            min-width: 320px;
            background: var(--fondo);
            color: var(--texto);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            font-size: 1rem;
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
        }

        a { color: var(--acento); }

        a:focus-visible, button:focus-visible, summary:focus-visible, input:focus-visible, select:focus-visible {
            outline: 3px solid var(--foco);
            outline-offset: 2px;
        }

        .saltar {
            position: absolute;
            left: -9999px;
            top: 0;
            padding: .75rem 1rem;
            background: var(--lima);
            color: var(--tinta);
            font-weight: 700;
            z-index: 10;
        }

        .saltar:focus { left: 1rem; top: 1rem; }

        .contenedor {
            max-width: 72rem;
            margin: 0 auto;
            padding: 1rem;
        }

        /* Títulos deportivos: condensados, gruesos, inclinados y en mayúsculas (fuentes del sistema) */
        .display {
            font-family: var(--display);
            font-weight: 900;
            font-style: italic;
            font-stretch: condensed;
            text-transform: uppercase;
            letter-spacing: -.005em;
            line-height: .92;
        }

        /* Cabecera blanca fija, como en la referencia */
        .cabecera {
            position: sticky;
            top: 0;
            z-index: 5;
            background: rgba(255, 255, 255, .92);
            border-bottom: 1px solid var(--borde);
            backdrop-filter: blur(10px);
        }

        .barra {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: .5rem 1rem;
            padding-top: .625rem;
            padding-bottom: .625rem;
        }

        .marca {
            display: inline-flex;
            align-items: center;
            gap: .625rem;
            min-height: 44px;
            color: var(--tinta);
            text-decoration: none;
        }

        .marca svg { width: 2.25rem; height: 2.25rem; flex: none; }
        .marca b { display: block; font-size: 1.125rem; }
        .marca small { display: block; font-size: .75rem; font-weight: 600; color: var(--texto-secundario); }

        /* En el celular el menú es una sola fila que se desliza, para no amontonar la cabecera */
        .navegacion { display: flex; gap: .25rem; max-width: 100%; overflow-x: auto; scrollbar-width: none; }
        .navegacion::-webkit-scrollbar { display: none; }
        .navegacion a { flex: none; }

        .navegacion a {
            display: inline-flex;
            align-items: center;
            min-height: 44px;
            padding: 0 .875rem;
            border-radius: 999px;
            color: var(--tinta);
            font-size: .9375rem;
            font-weight: 600;
            text-decoration: none;
        }

        .navegacion a:hover { background: var(--suave); }
        .navegacion a[aria-current="page"] { background: var(--suave); color: var(--royal-oscuro); }

        .navegacion .cuenta {
            background: var(--royal);
            color: var(--blanco);
            font-weight: 700;
        }

        .navegacion .cuenta:hover { background: var(--royal-oscuro); }

        /* Portada: tarjeta Navy redondeada sobre el fondo claro */
        .portada {
            position: relative;
            overflow: hidden;
            margin-top: 1rem;
            border-radius: 2rem;
            background: linear-gradient(150deg, var(--navy) 10%, var(--royal) 75%, #2149e6);
            color: var(--blanco);
            box-shadow: var(--sombra);
        }

        /* Rampa clara al fondo, como un graderío */
        .portada::before {
            content: "";
            position: absolute;
            right: -12%;
            bottom: -40%;
            width: 70%;
            height: 75%;
            border-radius: 4rem 0 0 0;
            background: linear-gradient(170deg, rgba(255, 255, 255, .16), rgba(255, 255, 255, .02));
            transform: rotate(-14deg);
            pointer-events: none;
        }

        .portada a { color: var(--blanco); }

        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 1.5rem;
            align-items: center;
            padding: 1.75rem 1.25rem 2rem;
        }

        .etiqueta-hero {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            margin: 0 0 1rem;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .2em;
            text-transform: uppercase;
            color: #c9d3ff;
        }

        .punto {
            width: .625rem;
            height: .625rem;
            border-radius: 50%;
            background: var(--lima);
        }

        /* Primera línea en blanco y el resto en Lime, sin partir el texto del título */
        .hero h1 {
            margin: 0;
            color: var(--lima);
            font-size: clamp(2rem, 9vw, 4.5rem);
            overflow-wrap: normal;
            text-wrap: balance;
        }

        .hero h1::first-line { color: var(--blanco); }

        .linea {
            display: block;
            width: 11rem;
            height: 4px;
            margin: 1.25rem 0 1rem;
            background: linear-gradient(90deg, var(--blanco) 82%, transparent 82% 86%, var(--blanco) 86%);
            clip-path: polygon(0 0, 100% 0, 96% 100%, 0 100%);
        }

        .hero-texto { position: relative; z-index: 1; }
        .hero-texto p { margin: 0; max-width: 30rem; font-size: 1.0625rem; color: #e6ebff; }

        /* Ilustración: balón con camiseta sobre estelas de velocidad. SVG y CSS, sin imágenes ni filtros */
        .arte {
            position: relative;
            display: none;
            height: 11rem;
            pointer-events: none;
        }

        .estelas {
            position: absolute;
            inset: 28% -30% 18% -10%;
            transform: rotate(-9deg) skewX(-14deg);
            background: repeating-linear-gradient(180deg, transparent 0 10px, rgba(212, 245, 60, .8) 10px 14px, transparent 14px 24px, rgba(255, 255, 255, .5) 24px 26px, transparent 26px 36px, rgba(255, 255, 255, .25) 36px 42px, transparent 42px 52px);
            -webkit-mask-image: linear-gradient(90deg, transparent, #000 35%, #000 80%, transparent);
            mask-image: linear-gradient(90deg, transparent, #000 35%, #000 80%, transparent);
        }

        .balon { position: absolute; left: 22%; top: 6%; width: 62%; aspect-ratio: 1; }
        .camiseta { position: absolute; right: 4%; top: -4%; width: 34%; transform: rotate(14deg); }
        .sombra-balon { position: absolute; left: 30%; bottom: 0; width: 46%; height: 1.25rem; border-radius: 50%; background: radial-gradient(rgba(0, 0, 0, .3), transparent 70%); }

        /* Cinta de partidos que corre (se pausa al pasar el cursor o con el foco) */
        .cinta {
            overflow: hidden;
            margin-top: 1rem;
            border-radius: 999px;
            background: var(--lima);
            color: var(--tinta);
            font-weight: 800;
            white-space: nowrap;
        }

        .cinta ul { display: inline-flex; gap: 2.5rem; margin: 0; padding: .75rem 1.25rem; list-style: none; }
        .cinta li { display: inline-flex; align-items: center; gap: .5rem; }
        .cinta li::after { content: "●"; margin-left: 2.5rem; font-size: .5rem; color: var(--navy); }
        .cinta small { padding: .125rem .5rem; border-radius: 999px; background: var(--navy); color: var(--lima); font-size: .6875rem; letter-spacing: .08em; text-transform: uppercase; }

        /*
         * Movimiento e interacción solo con CSS (RNF-04: sin JavaScript) y solo con transform u opacity, que mueve
         * la tarjeta gráfica sin repintar. Todo se apaga con prefers-reduced-motion (RNF-13).
         */
        @media (prefers-reduced-motion: no-preference) {
            .balon { animation: flotar 5s ease-in-out infinite; }
            .camiseta { animation: flotar-camiseta 6s ease-in-out infinite; }
            .estelas { animation: correr-estelas 2.4s linear infinite; }
            .cinta ul { animation: correr 38s linear infinite; }
            .cinta:hover ul, .cinta:focus-within ul { animation-play-state: paused; }
            .punto { position: relative; }
            .punto::after { content: ""; position: absolute; inset: 0; border-radius: 50%; background: var(--lima); animation: latido 1.8s ease-out infinite; }

            /* La portada solo se desliza (sin opacidad) para no retrasar el LCP */
            .hero-texto > * { animation: deslizar .7s cubic-bezier(.2, .8, .2, 1) both; }
            .hero-texto > :nth-child(2) { animation-delay: .06s; }
            .hero-texto > :nth-child(3) { animation-delay: .12s; }
            .hero-texto > :nth-child(n+4) { animation-delay: .18s; }
            .arte { animation: aparecer .9s .15s cubic-bezier(.2, .8, .2, 1) both; }

            /* Al bajar, cada tarjeta sube con un desliz suave (navegadores con scroll-driven animations) */
            @supports (animation-timeline: view()) {
                .revela, .tarjeta { animation: subir linear both; animation-timeline: view(); animation-range: entry 0% entry 40%; }
            }
        }

        @keyframes flotar { 50% { transform: translateY(-14px); } }
        @keyframes flotar-camiseta { 50% { transform: rotate(9deg) translateY(-10px); } }
        @keyframes correr-estelas { from { translate: -6% 0; } to { translate: 4% 0; } }
        @keyframes girar { to { transform: rotate(360deg); } }
        @keyframes correr { to { transform: translateX(-50%); } }
        @keyframes latido { from { transform: scale(1); opacity: .7; } to { transform: scale(3); opacity: 0; } }
        /* Solo transform: el contenido nunca queda invisible si el navegador no anima */
        @keyframes subir { from { transform: translateY(28px) scale(.97); } to { transform: none; } }
        @keyframes deslizar { from { transform: translateY(14px); } to { transform: none; } }
        @keyframes entrar { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: none; } }
        @keyframes aparecer { from { opacity: 0; transform: scale(.85) rotate(-8deg); } to { opacity: 1; transform: none; } }

        /* Respuesta al usuario: tarjetas que se elevan, flechas que avanzan y botones que se hunden al tocar */
        .tarjeta, .chips a, .navegacion a, .pregunta summary { transition: translate .25s cubic-bezier(.2, .8, .2, 1), scale .15s, box-shadow .25s, background-color .2s, color .2s; }
        /* translate y scale son propiedades aparte de transform, así no chocan con la animación de entrada */
        .tarjeta:hover, .tarjeta:focus-within { translate: 0 -4px; box-shadow: 0 22px 40px -18px rgba(11, 26, 92, .35); }
        .chips a:active, .navegacion a:active { scale: .96; }
        .flecha { display: inline-block; transition: transform .25s; }
        a:hover .flecha, a:focus-visible .flecha { transform: translateX(4px); }
        a:hover .flecha-atras, a:focus-visible .flecha-atras { transform: translateX(-4px); }

        /* Preguntas frecuentes plegables (details/summary, accesibles con teclado) */
        .preguntas { display: grid; gap: .75rem; margin: 0; padding: 0; list-style: none; }
        .pregunta { border-radius: 1.25rem; background: var(--tarjeta); box-shadow: var(--sombra); }
        .pregunta summary { display: flex; align-items: center; justify-content: space-between; gap: 1rem; min-height: 56px; padding: 1rem 1.25rem; font-weight: 700; cursor: pointer; list-style: none; border-radius: 1.25rem; }
        .pregunta summary::-webkit-details-marker { display: none; }
        .pregunta summary::after { content: "+"; display: grid; place-items: center; flex: none; width: 2rem; height: 2rem; border-radius: 50%; background: var(--suave); color: var(--royal-oscuro); font-size: 1.25rem; transition: transform .25s, background-color .2s; }
        .pregunta[open] summary::after { transform: rotate(45deg); background: var(--lima); color: var(--tinta); }
        .pregunta summary:hover { background: var(--suave); }
        .pregunta p { margin: 0; padding: 0 1.25rem 1.25rem; color: var(--texto-secundario); }
        .centrado { text-align: center; }
        .etiqueta-seccion { margin: 3rem 0 0; color: var(--royal-oscuro); font-size: .75rem; font-weight: 800; letter-spacing: .2em; text-transform: uppercase; }
        .centrado .seccion-titulo { margin-top: .5rem; }

        /* La cabecera se compacta al bajar (scroll-driven, sin JavaScript) */
        @supports (animation-timeline: scroll()) {
            @media (prefers-reduced-motion: no-preference) {
                .cabecera { animation: compactar linear both; animation-timeline: scroll(); animation-range: 0 120px; }
                @keyframes compactar { to { box-shadow: 0 10px 30px -18px rgba(11, 26, 92, .35); } }
            }
        }

        /* Componentes compartidos del portal */
        .seccion-titulo {
            margin: 2.25rem 0 1rem;
            font-size: 1.75rem;
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
            border: 1.5px solid rgba(255, 255, 255, .55);
            border-radius: 999px;
            font-size: .9375rem;
            font-weight: 700;
            text-decoration: none;
        }

        .chips a:hover, .chips a[aria-current="page"] { background: var(--lima); color: var(--tinta); border-color: var(--lima); }

        .tarjeta {
            border: 1px solid var(--borde);
            border-radius: 1.5rem;
            background: var(--tarjeta);
            box-shadow: var(--sombra);
            overflow: hidden;
        }

        .aviso {
            margin: 1.5rem 0;
            padding: 1.125rem 1.25rem;
            border-radius: 1.25rem;
            background: #fbffe6;
            border: 1px solid #e3f29a;
        }

        .aviso p { margin: 0 0 .5rem; font-weight: 600; }
        .aviso ul { margin: 0; padding-left: 1.25rem; }
        .aviso li + li { margin-top: .25rem; }

        .vacio {
            margin: 1.5rem 0;
            padding: 1.5rem;
            border-radius: 1.5rem;
            background: var(--tarjeta);
            box-shadow: var(--sombra);
            text-align: center;
        }

        .vacio p { margin: 0 0 .5rem; }
        .vacio p:first-child { font-weight: 700; }
        .vacio p:last-child { margin: 0; color: var(--texto-secundario); }

        .pie {
            margin-top: 3rem;
            background: var(--navy);
            color: #dfe5ff;
            font-size: .9375rem;
        }

        .pie a { color: var(--blanco); }
        .pie .chips a { border-color: rgba(255, 255, 255, .4); }

        .pie .contenedor {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding-top: 2.25rem;
            padding-bottom: 2.25rem;
        }

        .pie p { margin: 0; max-width: 36rem; }

        @media (min-width: 48rem) {
            .contenedor { padding: 1.25rem 2rem; }
            .barra { padding-top: .75rem; padding-bottom: .75rem; }
            .portada { margin-top: 1.5rem; }
            .hero { grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 2.5rem; padding: 3.5rem 3.5rem 3.75rem; }
            .arte { display: block; height: 22rem; }
            .hero h1 { font-size: clamp(3rem, 5.2vw, 5.25rem); }
            .seccion-titulo { font-size: 2.125rem; }
        }

        @yield('estilos')
    </style>
</head>
<body>
    <a class="saltar" href="#contenido">Saltar al contenido</a>

    <header class="cabecera">
        <div class="contenedor barra">
            <a class="marca" href="{{ route('portal.programacion') }}">
                <svg viewBox="0 0 40 40" aria-hidden="true" focusable="false"><circle cx="20" cy="20" r="18" fill="none" stroke="#0b1a5c" stroke-width="3"/><circle cx="20" cy="20" r="11" fill="none" stroke="#2f5bff" stroke-width="3"/><circle cx="20" cy="20" r="4.5" fill="#d4f53c" stroke="#0b1a5c" stroke-width="2"/></svg>
                <span><b class="display">Liga Municipal</b><small>de Fútbol · Morelia</small></span>
            </a>
            <nav class="navegacion" aria-label="Portal público">
                <a href="{{ route('portal.programacion') }}" @if (request()->routeIs('portal.programacion')) aria-current="page" @endif>Programación</a>
                <a href="{{ route('portal.castigados') }}" @if (request()->routeIs('portal.castigados')) aria-current="page" @endif>Castigados</a>
                @guest
                    <a class="cuenta" href="{{ route('inicio') }}">Iniciar sesión</a>
                @else
                    <a class="cuenta" href="{{ auth()->user()->rutaInicio() }}">Ir al panel</a>
                @endguest
            </nav>
        </div>
    </header>

    <div class="contenedor">
        <section class="portada" aria-label="Portada">
            <div class="hero">
                <div class="hero-texto">
                    @yield('hero')
                </div>
                <div class="arte" aria-hidden="true">
                    <div class="estelas"></div>
                    <div class="sombra-balon"></div>
                    <svg class="balon" viewBox="0 0 200 200" focusable="false">
                        <defs>
                            <radialGradient id="luz" cx="34%" cy="28%" r="80%"><stop offset="0" stop-color="#fff"/><stop offset=".55" stop-color="#eef1f5"/><stop offset="1" stop-color="#a7afb9"/></radialGradient>
                            <radialGradient id="sombra" cx="34%" cy="28%" r="78%"><stop offset=".45" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity=".45"/></radialGradient>
                            <clipPath id="esfera"><circle cx="100" cy="100" r="96"/></clipPath>
                        </defs>
                        <circle cx="100" cy="100" r="96" fill="url(#luz)"/>
                        <g clip-path="url(#esfera)">
                            <path fill="#0b0f14" d="M100,70 129,91 118,124 82,124 71,91ZM100,34 73,15 84,-17 116,-17 127,15ZM163,80 173,48 206,48 216,80 189,99ZM139,153 172,153 182,185 155,204 129,185ZM61,153 71,185 45,204 18,185 28,153ZM37,80 11,99 -16,80 -6,48 27,48Z"/>
                            <path fill="none" stroke="#2a2f37" stroke-width="2.4" d="M100,70 L100,34M129,91 L163,80M118,124 L139,153M82,124 L61,153M71,91 L37,80M127,15 L141,43 L173,48M114,80 L141,43M189,99 L167,122 L172,153M123,108 L167,122M129,185 L100,170 L71,185M100,124 L100,170M28,153 L33,122 L11,99M77,108 L33,122M27,48 L59,43 L73,15M86,80 L59,43"/>
                        </g>
                        <circle cx="100" cy="100" r="96" fill="url(#sombra)"/>
                        <ellipse cx="70" cy="58" rx="30" ry="18" fill="#fff" opacity=".55" transform="rotate(-30 70 58)"/>
                    </svg>
                    <svg class="camiseta" viewBox="0 0 200 200" focusable="false">
                        <path d="M70 18 38 30 6 70l28 24 16-14v104h100V80l16 14 28-24-32-40-32-12q-30 28-60 0Z" fill="#d4f53c"/>
                        <path d="M70 18q30 28 60 0l-8-3q-22 19-44 0Z" fill="#0b1a5c"/>
                        <path d="M6 70l28 24 6-6-28-25ZM194 70l-28 24-6-6 28-25Z" fill="#0b1a5c"/>
                        <text x="100" y="150" text-anchor="middle" font-family="Impact, 'Arial Narrow', sans-serif" font-style="italic" font-weight="900" font-size="64" fill="#0b1a5c">10</text>
                    </svg>
                </div>
            </div>
        </section>
        @yield('cinta')
    </div>

    <main id="contenido" class="contenedor" tabindex="-1">
        @yield('content')
    </main>

    <section class="contenedor centrado" aria-labelledby="preguntas-titulo">
        <p class="etiqueta-seccion">Dudas comunes</p>
        <h2 class="display seccion-titulo" id="preguntas-titulo">Preguntas frecuentes</h2>
        <ul class="preguntas" style="text-align: left; max-width: 46rem; margin: 0 auto;">
            <li><details class="pregunta"><summary>¿Cuándo se publica la programación?</summary><p>En cuanto la liga publica la jornada. Si un partido cambia de fecha, hora o cancha, aquí lo verás marcado como reprogramado con su fecha anterior.</p></details></li>
            <li><details class="pregunta"><summary>¿Por qué todavía no aparece un resultado?</summary><p>Los resultados se muestran cuando el árbitro cierra la cédula y la liga la valida. Así la tabla solo cuenta resultados oficiales.</p></details></li>
            <li><details class="pregunta"><summary>¿Cómo sé si un jugador está castigado?</summary><p>Consulta el boletín de castigados: muestra solo las sanciones validadas por la Comisión Disciplinaria y cuántos partidos faltan por cumplir.</p></details></li>
            <li><details class="pregunta"><summary>¿Necesito una cuenta para ver el portal?</summary><p>No. Todo lo público se consulta sin iniciar sesión. Las cuentas son para la liga, los árbitros y los clubes.</p></details></li>
        </ul>
    </section>

    <footer class="pie">
        <div class="contenedor">
            <p>Información pública de la liga. Para cualquier aclaración, comunícate con la Secretaría de la liga.</p>
            <nav class="chips" aria-label="Pie del portal">
                <a href="{{ route('portal.programacion') }}">Programación</a>
                <a href="{{ route('portal.castigados') }}">Castigados</a>
            </nav>
        </div>
    </footer>
</body>
</html>
