<?php

namespace App\Http\Controllers;

use App\Models\Cancha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

// Catálogo de canchas y sedes (HU-023, RF-05.1). Las vistas Blade llegan con S2-T24
class CanchaController extends Controller
{
    /**
     * Listar el catálogo de canchas.
     */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Cancha::class);

        $canchas = Cancha::query()
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'modulo' => 'Canchas',
            'canchas' => $canchas->map(
                fn (Cancha $cancha): array => $this->datosCancha($cancha)
            )->values(),
        ]);
    }

    /**
     * Consultar una cancha con sus características y su capacidad.
     */
    public function show(Cancha $cancha): JsonResponse
    {
        Gate::authorize('view', $cancha);

        return response()->json(['modulo' => 'Cancha'] + $this->datosCancha($cancha));
    }

    /**
     * Registrar una cancha nueva.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Cancha::class);

        $datos = $request->validate($this->reglasCancha(), $this->mensajesCancha());

        // El alta y su auditoría se guardan juntas o no se guarda nada (RNF-22)
        DB::transaction(fn () => Cancha::create($datos));

        return redirect()->back()
            ->with('success', 'Cancha registrada correctamente.');
    }

    /**
     * Cambiar las características o la capacidad de una cancha.
     */
    public function update(Request $request, Cancha $cancha): RedirectResponse
    {
        Gate::authorize('update', $cancha);

        $datos = $request->validate($this->reglasCancha(), $this->mensajesCancha());

        DB::transaction(fn () => $cancha->update($datos));

        return redirect()->back()
            ->with('success', 'Cancha actualizada correctamente.');
    }

    /**
     * Datos públicos de una cancha.
     *
     * @return array<string, mixed>
     */
    private function datosCancha(Cancha $cancha): array
    {
        return [
            'id' => $cancha->id,
            'nombre' => $cancha->nombre,
            'direccion_ubicacion' => $cancha->direccion_ubicacion,
            'capacidad_partidos_consecutivos' => $cancha->capacidad_partidos_consecutivos,
            'estado' => $cancha->estado,
        ];
    }

    /**
     * Reglas de validación del alta y la edición.
     *
     * @return array<string, string>
     */
    private function reglasCancha(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'direccion_ubicacion' => 'nullable|string|max:255',
            // El máximo es el de la columna (entero sin signo)
            'capacidad_partidos_consecutivos' => 'required|integer|min:1|max:4294967295',
        ];
    }

    /**
     * Mensajes en español mientras no exista la traducción general de la validación (IU-03).
     *
     * @return array<string, string>
     */
    private function mensajesCancha(): array
    {
        return [
            'nombre.required' => 'El nombre de la cancha es obligatorio.',
            'nombre.string' => 'El nombre de la cancha debe ser texto.',
            'nombre.max' => 'El nombre de la cancha no debe tener más de :max caracteres.',
            'direccion_ubicacion.string' => 'La dirección o ubicación debe ser texto.',
            'direccion_ubicacion.max' => 'La dirección o ubicación no debe tener más de :max caracteres.',
            'capacidad_partidos_consecutivos.required' => 'La capacidad de partidos consecutivos es obligatoria.',
            'capacidad_partidos_consecutivos.integer' => 'La capacidad de partidos consecutivos debe ser un número entero.',
            'capacidad_partidos_consecutivos.min' => 'La capacidad de partidos consecutivos debe ser de al menos :min.',
            'capacidad_partidos_consecutivos.max' => 'La capacidad de partidos consecutivos no puede ser mayor que :max.',
        ];
    }
}
