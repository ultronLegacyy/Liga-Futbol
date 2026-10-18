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

        {{-- Motivo del alta: queda en la auditoria de la temporada y de la version 1 de la convocatoria (RF-01.3) --}}
        <label for="motivo">Motivo:</label><br>
        <textarea id="motivo" name="motivo" rows="3" maxlength="1000" required
            @error('motivo') aria-invalid="true" aria-describedby="motivo-error" @enderror>{{ old('motivo') }}</textarea><br>
        @error('motivo')
            <p id="motivo-error" style="color: red;">{{ $message }}</p>
        @enderror
        <br>

        <button type="submit">Guardar Temporada</button>
    </form>
</body>
</html>
