<?php

namespace App\Http\Controllers;

use App\Models\Convocatoria;
use App\Models\Temporada;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TemporadaController extends Controller
{
    // Listado de temporadas en JSON mientras no exista la vista correspondiente
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Temporada::class);

        return response()->json([
            'modulo' => 'Temporadas',
            'temporadas' => Temporada::query()
                ->orderByDesc('fecha_inicio')
                ->get(['id', 'nombre', 'fecha_inicio', 'fecha_fin', 'estado']),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Temporada::class);

        return view('temporadas.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Temporada::class);

        $request->validate($this->reglasTemporada() + [
            'convocatoria_pdf' => 'required|mimes:pdf|max:5120',
        ]);

        $rutaPdf = null;

        // Temporada, archivo y convocatoria se registran juntos o no se registra nada (RNF-22)
        try {
            DB::transaction(function () use ($request, &$rutaPdf) {
                // 1. Crear la temporada con la liga_id 1 y estado 'activa' (respetando el ENUM)
                $temporada = Temporada::create([
                    'liga_id' => 1,
                    'nombre' => $request->nombre,
                    'fecha_inicio' => $request->fecha_inicio,
                    'fecha_fin' => $request->fecha_fin,
                    'estado' => 'activa',
                ]);

                // 2. Guardar el PDF en el disco privado, fuera de public/ (RNF-30)
                $rutaPdf = $request->file('convocatoria_pdf')->store('convocatorias', 'local');

                if ($rutaPdf === false) {
                    throw new RuntimeException('No fue posible guardar el documento de la convocatoria.');
                }

                // 3. Registrar la convocatoria vinculada a la temporada
                Convocatoria::create([
                    'temporada_id' => $temporada->id,
                    'archivo_pdf' => $rutaPdf,
                    'reglas_versionadas' => json_encode([]),
                ]);
            });
        } catch (Throwable $excepcion) {
            // Se elimina el PDF ya guardado para no dejar archivos huerfanos
            if (is_string($rutaPdf)) {
                Storage::disk('local')->delete($rutaPdf);
            }

            throw $excepcion;
        }

        return redirect()->back()->with('success', 'Temporada creada correctamente.');
    }

    // Detalle de la temporada en JSON; se permite consultar aunque este cerrada (RF-01.4)
    public function show(Temporada $temporada): JsonResponse
    {
        Gate::authorize('view', $temporada);

        $tieneConvocatoria = $temporada->convocatorias()->exists();

        return response()->json([
            'modulo' => 'Temporada',
            'temporada_id' => $temporada->id,
            'nombre' => $temporada->nombre,
            'fecha_inicio' => $temporada->fecha_inicio,
            'fecha_fin' => $temporada->fecha_fin,
            'estado' => $temporada->estado,
            'convocatoria_url' => $tieneConvocatoria ? route('temporadas.convocatoria', $temporada) : null,
            'acceso' => 'autorizado',
        ]);
    }

    public function edit(Temporada $temporada): View
    {
        // La politica rechaza la edicion de temporadas cerradas (RF-01.4)
        Gate::authorize('update', $temporada);

        return view('temporadas.edit', compact('temporada'));
    }

    // Actualizacion de nombre y fechas; la politica bloquea temporadas cerradas (RF-01.4)
    public function update(Request $request, Temporada $temporada): RedirectResponse
    {
        Gate::authorize('update', $temporada);

        $datos = $request->validate($this->reglasTemporada());

        $temporada->update($datos);

        return redirect()->back()->with('success', 'Temporada actualizada correctamente.');
    }

    public function destroy(Temporada $temporada): RedirectResponse
    {
        // La politica rechaza la eliminacion de temporadas cerradas (RF-01.4)
        Gate::authorize('delete', $temporada);

        $temporada->delete();

        return redirect()->back()->with('success', 'Temporada eliminada.');
    }

    // Entrega en linea del PDF de la convocatoria mas reciente solo a usuarios autorizados (RNF-30)
    public function convocatoria(Temporada $temporada): StreamedResponse
    {
        Gate::authorize('view', $temporada);

        $convocatoria = $temporada->convocatorias()->latest()->latest('id')->first();
        $disco = Storage::disk('local');

        if ($convocatoria === null || ! $convocatoria->archivo_pdf || ! $disco->exists($convocatoria->archivo_pdf)) {
            abort(404, 'La temporada no cuenta con un documento de convocatoria disponible.');
        }

        return $disco->response(
            $convocatoria->archivo_pdf,
            'convocatoria-'.Str::slug($temporada->nombre).'.pdf',
            [
                'Content-Type' => 'application/pdf',
                'X-Robots-Tag' => 'noindex, nofollow',
                'Cache-Control' => 'private, no-store',
            ],
            'inline'
        );
    }

    // Reglas compartidas por el alta y la actualizacion de temporadas
    private function reglasTemporada(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_inicio',
        ];
    }
}
