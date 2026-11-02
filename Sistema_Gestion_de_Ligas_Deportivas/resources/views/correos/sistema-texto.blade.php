{{-- Texto plano: sin escapar, porque aquí no se interpreta HTML y & o < deben verse tal cual --}}
Liga Fútbol · SGLD

{!! $asunto !!}

{!! $mensaje !!}
@if ($enlaceUrl)

{!! $enlaceTexto ?? 'Enlace' !!}: {!! $enlaceUrl !!}
@endif

--
Correo automático del Sistema de Gestión de Ligas Deportivas.
