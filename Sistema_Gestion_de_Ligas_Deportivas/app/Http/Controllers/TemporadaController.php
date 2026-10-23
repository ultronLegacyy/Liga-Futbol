<?php

namespace App\Http\Controllers;

use App\Models\AuditoriaLog;
use App\Models\Convocatoria;
use App\Models\Temporada;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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
    /**
     * Listar temporadas en JSON o mediante la vista Blade.
     */
    public function index(): View|JsonResponse
    {
        Gate::authorize('viewAny', Temporada::class);

        $temporadas = Temporada::query()
            ->orderByDesc('fecha_inicio')
            ->get();

        if (request()->expectsJson()) {
            return response()->json([
                'modulo' => 'Temporadas',
                'temporadas' => $temporadas->map(
                    fn (Temporada $temporada): array => [
                        'id' => $temporada->id,
                        'nombre' => $temporada->nombre,
                        'fecha_inicio' => $temporada->fecha_inicio,
                        'fecha_fin' => $temporada->fecha_fin,
                        'estado' => $temporada->estado,
                    ]
                )->values(),
            ]);
        }

        return view('temporadas.index', compact('temporadas'));
    }

    /**
     * Mostrar el formulario de creación.
     */
    public function create(): View
    {
        Gate::authorize('create', Temporada::class);

        return view('temporadas.create');
    }

    /**
     * Crear una temporada con su convocatoria inicial.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Temporada::class);

        $datos = $request->validate($this->reglasTemporada() + [
            'convocatoria_pdf' => 'required|mimes:pdf|max:5120',
            'motivo' => 'required|string|max:1000',
        ]);

        $rutaPdf = null;

        try {
            app(AuditService::class)->conMotivo(
                $datos['motivo'],
                function () use ($request, &$rutaPdf): void {
                    $temporada = Temporada::create([
                        'liga_id' => 1,
                        'nombre' => $request->nombre,
                        'fecha_inicio' => $request->fecha_inicio,
                        'fecha_fin' => $request->fecha_fin,
                        'estado' => 'activa',
                    ]);

                    $rutaPdf = $request->file('convocatoria_pdf')
                        ->store('convocatorias', 'local');

                    if ($rutaPdf === false) {
                        throw new RuntimeException(
                            'No fue posible guardar el documento de la convocatoria.'
                        );
                    }

                    Convocatoria::create([
                        'temporada_id' => $temporada->id,
                        'version' => 1,
                        'archivo_pdf' => $rutaPdf,
                        'reglas_versionadas' => json_encode([]),
                    ]);
                }
            );
        } catch (Throwable $excepcion) {
            if (is_string($rutaPdf)) {
                Storage::disk('local')->delete($rutaPdf);
            }

            throw $excepcion;
        }

        return redirect()->back()
            ->with('success', 'Temporada creada correctamente.');
    }

    /**
     * Mostrar una temporada en HTML o devolver sus datos en JSON.
     */
    public function show(
        Request $request,
        Temporada $temporada
    ): View|JsonResponse {
        Gate::authorize('view', $temporada);

        $convocatorias = $temporada->convocatorias()
            ->orderBy('version')
            ->get();

        $altas = AuditoriaLog::query()
            ->with('usuario:id,name')
            ->where(
                'tabla_afectada',
                (new Convocatoria())->getTable()
            )
            ->where('accion', 'crear')
            ->whereIn('registro_id', $convocatorias->modelKeys())
            ->orderBy('id')
            ->get()
            ->keyBy('registro_id');

        if ($request->expectsJson()) {
            return response()->json([
                'modulo' => 'Temporada',
                'temporada_id' => $temporada->id,
                'nombre' => $temporada->nombre,
                'fecha_inicio' => $temporada->fecha_inicio,
                'fecha_fin' => $temporada->fecha_fin,
                'estado' => $temporada->estado,
                'convocatoria_url' => $convocatorias->isNotEmpty()
                    ? route('temporadas.convocatoria', $temporada)
                    : null,
                'convocatorias' => $convocatorias->map(
                    function (Convocatoria $convocatoria) use (
                        $altas,
                        $temporada
                    ): array {
                        $auditoria = $altas->get($convocatoria->id);

                        return [
                            'id' => $convocatoria->id,
                            'version' => $convocatoria->version,
                            'fecha' => $auditoria?->created_at?->toISOString(),
                            'url' => route('temporadas.convocatoria', [
                                'temporada' => $temporada,
                                'version' => $convocatoria->version,
                            ]),
                            'motivo' => $auditoria?->motivo,
                            'usuario' => $auditoria?->usuario?->name,
                        ];
                    }
                )->values(),
                'acceso' => 'autorizado',
            ]);
        }

        return view(
            'temporadas.show',
            compact('temporada', 'convocatorias', 'altas')
        );
    }

    /**
     * Mostrar el formulario de edición.
     */
    public function edit(Temporada $temporada): View
    {
        Gate::authorize('update', $temporada);

        return view('temporadas.edit', compact('temporada'));
    }

    /**
     * Actualizar una temporada.
     */
    public function update(
        Request $request,
        Temporada $temporada
    ): RedirectResponse {
        Gate::authorize('update', $temporada);

        $datos = $request->validate(
            $this->reglasTemporada() + [
                'motivo' => 'required|string|max:1000',
                'estado' => 'sometimes|in:borrador,activa,cerrada',
            ]
        );

        $motivo = $datos['motivo'];
        unset($datos['motivo']);

        app(AuditService::class)->conMotivo(
            $motivo,
            function () use ($temporada, $datos) {
                // Capturamos exactamente las llaves que vienen en los datos a modificar
                $keys = array_keys($datos);
                $anteriores = $temporada->only($keys);

                $temporada->update($datos);

                app(AuditService::class)->registrar(
                    'actualizar',
                    $temporada,
                    $anteriores,
                    $temporada->only($keys)
                );
            }
        );

        return redirect()->back()
            ->with('success', 'Temporada actualizada correctamente.');
    }

    /**
     * Eliminar una temporada.
     */
    public function destroy(Temporada $temporada): RedirectResponse
    {
        Gate::authorize('delete', $temporada);

        $temporada->delete();

        return redirect()->back()
            ->with('success', 'Temporada eliminada.');
    }

    /**
     * Crear una nueva versión sin eliminar las anteriores.
     */
    public function versionarConvocatoria(
        Request $request,
        Temporada $temporada
    ): RedirectResponse {
        Gate::authorize('update', $temporada);

        $datos = $request->validate([
            'convocatoria_pdf' => 'required|mimes:pdf|max:5120',
            'motivo' => 'required|string|max:1000',
        ]);

        $rutaPdf = null;

        try {
            $convocatoria = app(AuditService::class)->conMotivo(
                $datos['motivo'],
                function () use (
                    $request,
                    $temporada,
                    &$rutaPdf
                ): Convocatoria {
                    $bloqueada = Temporada::query()
                        ->whereKey($temporada->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    Gate::authorize('update', $bloqueada);

                    $version = (int) $bloqueada->convocatorias()
                        ->lockForUpdate()
                        ->max('version') + 1;

                    $rutaPdf = $request->file('convocatoria_pdf')
                        ->store('convocatorias', 'local');

                    if ($rutaPdf === false) {
                        throw new RuntimeException(
                            'No fue posible guardar el documento de la convocatoria.'
                        );
                    }

                    return Convocatoria::create([
                        'temporada_id' => $bloqueada->id,
                        'version' => $version,
                        'archivo_pdf' => $rutaPdf,
                        'reglas_versionadas' => json_encode([]),
                    ]);
                }
            );
        } catch (Throwable $excepcion) {
            if (is_string($rutaPdf)) {
                Storage::disk('local')->delete($rutaPdf);
            }

            throw $excepcion;
        }

        return redirect()->back()->with(
            'success',
            'Se registró la versión '.$convocatoria->version.' de la convocatoria.'
        );
    }

    /**
     * Descargar una versión específica o la más reciente.
     */
    public function convocatoria(
        Request $request,
        Temporada $temporada
    ): StreamedResponse {
        Gate::authorize('view', $temporada);

        $version = null;

        if ($request->query->has('version')) {
            $version = filter_var(
                $request->query('version'),
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($version === false) {
                abort(
                    404,
                    'La version solicitada de la convocatoria no existe.'
                );
            }
        }

        $convocatoria = $temporada->convocatorias()
            ->when(
                $version !== null,
                fn ($consulta) => $consulta->where('version', $version)
            )
            ->orderByDesc('version')
            ->first();

        $disco = Storage::disk('local');

        if (
            $convocatoria === null
            || ! $convocatoria->archivo_pdf
            || ! $disco->exists($convocatoria->archivo_pdf)
        ) {
            abort(
                404,
                'La temporada no cuenta con un documento de convocatoria disponible.'
            );
        }

        return $disco->response(
            $convocatoria->archivo_pdf,
            'convocatoria-'.Str::slug($temporada->nombre)
                .'-v'.$convocatoria->version.'.pdf',
            [
                'Content-Type' => 'application/pdf',
                'X-Robots-Tag' => 'noindex, nofollow',
                'Cache-Control' => 'private, no-store',
            ],
            'inline'
        );
    }

    /**
     * Reglas de validación compartidas.
     */
    private function reglasTemporada(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_inicio',
        ];
    }
}