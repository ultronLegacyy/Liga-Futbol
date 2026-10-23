{{-- Rango de años de nacimiento de la categoría (Tabla 4 de la ERS); cualquiera de los dos extremos puede faltar --}}
@if ($categoria->anio_nacimiento_desde && $categoria->anio_nacimiento_hasta)
    {{ $categoria->anio_nacimiento_desde }} – {{ $categoria->anio_nacimiento_hasta }}
@elseif ($categoria->anio_nacimiento_desde)
    Desde {{ $categoria->anio_nacimiento_desde }}
@elseif ($categoria->anio_nacimiento_hasta)
    Hasta {{ $categoria->anio_nacimiento_hasta }}
@else
    Sin rango registrado
@endif
