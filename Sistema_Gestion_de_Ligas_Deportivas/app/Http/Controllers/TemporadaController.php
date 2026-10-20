<?php

namespace App\Http\Controllers;

use App\Models\AuditoriaLog;
use App\Models\Convocatoria;
use App\Models\Temporada;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TemporadaController extends Controller
{
    // Listado visual de temporadas utilizando la vista Blade del Layout Maestro
    public function index(): View
    {
        Gate::authorize('viewAny', Temporada::class);

        $temporadas = Temporada::query()
            ->orderByDesc('fecha_inicio')
            ->get();

        return view('temporadas.index', compact('temporadas'));
    }

    public function create(): View
    {
        Gate::authorize('create', Temporada::class);

        return view('temporadas.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Temporada::class);

        $datos = $request->validate($this->reglasTemporada() + [
            'convocatoria_pdf' => 'required|mimes:pdf|max:5120',
            'motivo' => 'required|string|max:1000',
        ]);

        $rutaPdf = null;

        // Temporada, archivo, version 1 y su auditoria con el motivo se registran juntos o nada (RNF-22, RF-01.3)
        try {
            app(AuditService::class)->conMotivo($datos['motivo'], function () use ($request, &$rutaPdf) {
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
                    'version' => 1,
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

    // Detalle visual de la temporada y sus convocatorias
    public function show(Temporada $temporada): View
    {
        Gate::authorize('view', $temporada);

        $convocatorias = $temporada->convocatorias()->orderBy('version')->get();

        $altas = AuditoriaLog::query()
            ->with('usuario:id,name')
            ->where('tabla_afectada', (new Convocatoria())->getTable())
            ->where('accion', 'crear')
            ->whereIn('registro_id', $convocatorias->modelKeys())
            ->orderBy('id')
            ->get()
            ->keyBy('registro_id');

        return view('temporadas.show', compact('temporada', 'convocatorias', 'altas'));
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

    // Nueva version de la convocatoria al cambiar las reglas; las anteriores se conservan (RF-01.3)
    public function versionarConvocatoria(Request $request, Temporada $temporada): RedirectResponse
    {
        // La politica rechaza la modificacion de temporadas cerradas (RF-01.4)
        Gate::authorize('update', $temporada);

        $datos = $request->validate([
            'convocatoria_pdf' => 'required|mimes:pdf|max:5120',
            'motivo' => 'required|string|max:1000',
        ]);

        $rutaPdf = null;

        // La version, su archivo y su auditoria (usuario, fecha, IP y motivo) se registran juntos o nada (RNF-22)
        try {
            $convocatoria = app(AuditService::class)->conMotivo($datos['motivo'], function () use ($request, $temporada, &$rutaPdf) {
                // 1. Bloquear la temporada para que dos peticiones simultaneas no tomen el mismo numero de version
                $bloqueada = Temporada::query()->whereKey($temporada->getKey())->lockForUpdate()->firstOrFail();

                // Se autoriza de nuevo con el estado vigente por si la temporada se cerro mientras tanto
                Gate::authorize('update', $bloqueada);

                // Lectura con bloqueo para tomar la ultima version confirmada
                $version = (int) $bloqueada->convocatorias()->lockForUpdate()->max('version') + 1;

                // 2. Guardar el PDF en el disco privado, fuera de public/ (RNF-30)
                $rutaPdf = $request->file('convocatoria_pdf')->store('convocatorias', 'local');

                if ($rutaPdf === false) {
                    throw new RuntimeException('No fue posible guardar el documento de la convocatoria.');
                }

                // 3. Registrar la version; el trait Auditable la audita con el motivo recibido
                return Convocatoria::create([
                    'temporada_id' => $bloqueada->id,
                    'version' => $version,
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

        return redirect()->back()->with('success', 'Se registró la versión '.$convocatoria->version.' de la convocatoria.');
    }

    // Entrega en linea del PDF de la convocatoria solo a usuarios autorizados (RNF-30);
    // sin parametro entrega la version mas alta y con ?version=N una version anterior (RF-01.3)
    public function convocatoria(Request $request, Temporada $temporada): StreamedResponse
    {
        Gate::authorize('view', $temporada);

        $version = null;

        if ($request->query->has('version')) {
            $version = filter_var($request->query('version'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            // Un valor que no es entero positivo se trata como una version inexistente
            if ($version === false) {
                abort(404, 'La version solicitada de la convocatoria no existe.');
            }
        }

        $convocatoria = $temporada->convocatorias()
            ->when($version !== null, fn ($consulta) => $consulta->where('version', $version))
            ->orderByDesc('version')
            ->first();
        $disco = Storage::disk('local');

        if ($convocatoria === null || ! $convocatoria->archivo_pdf || ! $disco->exists($convocatoria->archivo_pdf)) {
            abort(404, 'La temporada no cuenta con un documento de convocatoria disponible.');
        }

        return $disco->response(
            $convocatoria->archivo_pdf,
            'convocatoria-'.Str::slug($temporada->nombre).'-v'.$convocatoria->version.'.pdf',
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