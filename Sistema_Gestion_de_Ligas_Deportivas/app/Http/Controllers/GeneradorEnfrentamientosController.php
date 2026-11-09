<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmarRolRequest;
use App\Http\Requests\GenerarRolRequest;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Services\GeneradorEnfrentamientosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

// Controlador para la generación y confirmación de enfrentamientos automáticos (HU-032, RF-07.1)
class GeneradorEnfrentamientosController extends Controller
{
    public function __construct(
        private readonly GeneradorEnfrentamientosService $generadorService
    ) {
    }

    /**
     * Vista de configuración de parámetros para generación del rol regular.
     */
    public function configurar(Grupo $grupo): View|RedirectResponse
    {
        Gate::authorize('create', [Jornada::class, $grupo]);

        try {
            $this->generadorService->validarGrupoParaGeneracion($grupo);
        } catch (ValidationException $e) {
            return redirect()->route('grupos.jornadas.index', $grupo)
                ->with('error', $e->validator->errors()->first() ?: $e->getMessage());
        }

        $grupo->load(['categoria.temporada', 'equipos']);
        $jornadasExistentesCount = $grupo->jornadas()->count();

        return view('jornadas.generar-rol', compact('grupo', 'jornadasExistentesCount'));
    }

    /**
     * Previsualización de la propuesta de enfrentamientos y descansos calculados.
     */
    public function previsualizar(GenerarRolRequest $request, Grupo $grupo): View|JsonResponse|RedirectResponse
    {
        Gate::authorize('create', [Jornada::class, $grupo]);

        try {
            $propuesta = $this->generadorService->generarPropuestaRol($grupo, $request->validated());
        } catch (ValidationException $e) {
            return redirect()->route('grupos.generar-rol', $grupo)
                ->with('error', $e->validator->errors()->first() ?: $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json($propuesta);
        }

        $grupo->load(['categoria.temporada', 'equipos']);
        $sobrescribir = (bool) $request->boolean('sobrescribir');

        return view('jornadas.previsualizar-rol', compact('grupo', 'propuesta', 'sobrescribir'));
    }

    /**
     * Confirmar y persistir transaccionalmente el rol regular generado.
     */
    public function confirmar(ConfirmarRolRequest $request, Grupo $grupo): RedirectResponse
    {
        Gate::authorize('create', [Jornada::class, $grupo]);

        /** @var string $payloadString */
        $payloadString = $request->validated('fixture_payload');
        $propuesta = json_decode($payloadString, true);

        if (! is_array($propuesta) || ! isset($propuesta['jornadas'])) {
            return redirect()->route('grupos.generar-rol', $grupo)
                ->with('error', 'El formato de la propuesta de enfrentamientos no es válido para confirmación.');
        }

        try {
            /** @var array{jornadas: array<int, array{numero_jornada: int, fecha_inicio: string, fecha_fin: string, equipo_descanso_id: int|null, partidos: array<int, array{equipo_local_id: int, equipo_visitante_id: int}>}>} $propuestaTipada */
            $propuestaTipada = $propuesta;
            $resultado = $this->generadorService->guardarRolGenerado(
                $grupo,
                $propuestaTipada,
                (bool) $request->boolean('sobrescribir')
            );
        } catch (ValidationException $e) {
            return redirect()->route('grupos.generar-rol', $grupo)
                ->with('error', $e->validator->errors()->first() ?: $e->getMessage());
        }

        return redirect()->route('grupos.jornadas.index', $grupo)
            ->with('success', "Rol regular generado exitosamente: {$resultado['jornadas_creadas']} jornadas y {$resultado['partidos_creados']} partidos programados.");
    }

    /**
     * Generación automática de partidos para una jornada existente específica.
     */
    public function generarJornada(Request $request, Jornada $jornada): RedirectResponse
    {
        Gate::authorize('update', $jornada);

        try {
            $this->generadorService->aplicarEnfrentamientosAJornada($jornada);
        } catch (ValidationException $e) {
            return redirect()->route('jornadas.show', $jornada)
                ->with('error', $e->validator->errors()->first() ?: $e->getMessage());
        }

        return redirect()->route('jornadas.show', $jornada)
            ->with('success', "Enfrentamientos generados exitosamente para la Jornada {$jornada->numero_jornada}.");
    }
}
