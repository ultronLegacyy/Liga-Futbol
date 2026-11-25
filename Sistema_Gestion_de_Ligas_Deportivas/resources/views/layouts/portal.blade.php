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
            --display: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
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

        /* Títulos: letra gruesa y redonda, sin cursiva, con interletrado cerrado */
        .display {
            font-family: var(--display);
            font-weight: 800;
            letter-spacing: -.03em;
            line-height: 1.05;
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

        .navegacion { display: flex; flex-wrap: wrap; gap: .25rem; }

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
            background: radial-gradient(circle at 85% 20%, rgba(47, 91, 255, .55), transparent 55%), var(--navy);
            color: var(--blanco);
            box-shadow: var(--sombra);
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

        .hero h1 {
            margin: 0;
            font-size: clamp(2.125rem, 9vw, 4.25rem);
            text-wrap: balance;
        }

        .hero h1 em { font-style: normal; color: var(--lima); }

        .linea {
            display: block;
            width: 3.5rem;
            height: .375rem;
            margin: 1.125rem 0 1rem;
            border-radius: 999px;
            background: var(--lima);
        }

        .hero-texto p { margin: 0; max-width: 34rem; font-size: 1.0625rem; color: #dfe5ff; }

        /* Ilustración plana: anillos concéntricos y un balón de líneas, sin imágenes */
        .arte {
            position: relative;
            display: none;
            place-items: center;
            height: 11rem;
            pointer-events: none;
        }

        .anillos { position: relative; width: 11rem; height: 11rem; }
        .anillos svg { position: absolute; inset: 0; width: 100%; height: 100%; }
        .orbita { position: absolute; inset: 0; }
        .orbita::before {
            content: "";
            position: absolute;
            top: 2%;
            left: 50%;
            width: .875rem;
            height: .875rem;
            margin-left: -.4375rem;
            border-radius: 50%;
            background: var(--lima);
            box-shadow: 0 0 0 .375rem rgba(212, 245, 60, .2);
        }
        .orbita.lenta::before { top: auto; bottom: 14%; left: 16%; width: .5rem; height: .5rem; background: #fff; box-shadow: none; }

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
            .anillos .balon-linea { animation: flotar 5s ease-in-out infinite; transform-box: fill-box; transform-origin: center; }
            .orbita { animation: girar 9s linear infinite; }
            .orbita.lenta { animation-duration: 16s; animation-direction: reverse; }
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

        @keyframes flotar { 50% { transform: translateY(-6px); } }
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
            .hero { grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); padding: 3rem 3rem 3.25rem; }
            .arte { display: grid; height: 15rem; }
            .anillos { width: 15rem; height: 15rem; }
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
                    <div class="anillos">
                        <div class="orbita"></div>
                        <div class="orbita lenta"></div>
                        <svg viewBox="0 0 200 200" focusable="false">
                            <circle cx="100" cy="100" r="96" fill="rgba(255,255,255,.06)" stroke="rgba(255,255,255,.18)" stroke-width="2"/>
                            <circle cx="100" cy="100" r="70" fill="none" stroke="rgba(255,255,255,.22)" stroke-width="2" stroke-dasharray="6 10"/>
                            <circle cx="100" cy="100" r="46" fill="#d4f53c"/>
                            <g class="balon-linea" fill="none" stroke="#0b1a5c" stroke-width="3.5" stroke-linejoin="round" stroke-linecap="round">
                                <circle cx="100" cy="100" r="30"/>
                                <path d="M100 86l13 9.5-5 15.5H92l-5-15.5Z" fill="#0b1a5c"/>
                                <path d="M100 86V70M113 95.5l15-5M108 111l9 13M92 111l-9 13M87 95.5l-15-5"/>
                            </g>
                        </svg>
                    </div>
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
