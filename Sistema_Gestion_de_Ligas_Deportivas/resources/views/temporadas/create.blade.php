<!DOCTYPE html>
<html>
<head><title>Crear Temporada</title></head>
<body>
    <h1>Crear Nueva Temporada</h1>
    @if(session('success')) 
        <p style="color: green;">{{ session('success') }}</p> 
    @endif

    <form action="{{ route('temporadas.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <label>Nombre:</label><br>
        <input type="text" name="nombre" required><br><br>

        <label>Fecha de Inicio:</label><br>
        <input type="date" name="fecha_inicio" required><br><br>

        <label>Fecha de Fin:</label><br>
        <input type="date" name="fecha_fin" required><br><br>

        <label>Convocatoria (PDF):</label><br>
        <input type="file" name="convocatoria_pdf" accept="application/pdf" required><br><br>

        <button type="submit">Guardar Temporada</button>
    </form>
</body>
</html>
