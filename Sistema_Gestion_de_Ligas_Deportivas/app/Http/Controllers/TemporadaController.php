<?php

namespace App\Http\Controllers;

use App\Models\Temporada;
use App\Models\Convocatoria;
use Illuminate\Http\Request;

class TemporadaController extends Controller
{
    public function create()
    {
        return view('temporadas.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_inicio',
            'convocatoria_pdf' => 'required|mimes:pdf|max:5120',
        ]);

        // 1. Crear la temporada con la liga_id 1 y estado 'activa' (respetando el ENUM)
        $temporada = Temporada::create([
    'liga_id' => 1,
    'nombre' => $request->nombre,
    'fecha_inicio' => $request->fecha_inicio,
    'fecha_fin' => $request->fecha_fin,
    'estado' => 'activa',
]);

        // 2. Subir el PDF y guardarlo en la tabla de convocatorias
        if ($request->hasFile('convocatoria_pdf')) {
            $rutaPdf = $request->file('convocatoria_pdf')->store('convocatorias', 'local');
            Convocatoria::create([
                'temporada_id' => $temporada->id,
                'archivo_pdf' => $rutaPdf,
                'reglas_versionadas' => json_encode([]),
            ]);
        }

        return redirect()->back()->with('success', 'Temporada creada correctamente.');
    }

    public function edit(Temporada $temporada)
    {
        if ($temporada->estado === 'cerrada') {
            abort(403, 'Error: Una temporada cerrada no puede modificarse.');
        }
        return view('temporadas.edit', compact('temporada'));
    }

    public function destroy(Temporada $temporada)
    {
        if ($temporada->estado === 'cerrada') {
            abort(403, 'Error: Una temporada cerrada no puede eliminarse.');
        }
        $temporada->delete();
        return redirect()->back()->with('success', 'Temporada eliminada.');
    }
}
