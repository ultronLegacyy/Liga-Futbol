<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJugadorRequest;
use App\Models\Equipo;
use App\Models\User;
use App\Services\RegistroJugadorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JugadorController extends Controller
{
    public function create(Request $request): View
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);
        abort_unless(
            $usuario->can('jugadores.crear')
                || ($usuario->hasRole('Delegado del Club') && $usuario->club_id !== null),
            403,
        );

        $equipos = Equipo::query()
            ->with(['temporada', 'grupo.categoria'])
            ->whereHas('temporada', fn (Builder $consulta): Builder => $consulta->where('estado', 'activa'))
            ->when(
                $usuario->hasRole('Delegado del Club') && $usuario->club_id !== null,
                fn (Builder $consulta): Builder => $consulta->where('club_id', $usuario->club_id)
            )
            ->orderBy('nombre')
            ->get();

        $temporadas = $equipos->pluck('temporada')->unique('id')->values();
        $categorias = $equipos->pluck('grupo.categoria')->unique('id')->values();

        return view('jugadores.create', compact('equipos', 'temporadas', 'categorias'));
    }

    public function store(
        StoreJugadorRequest $request,
        RegistroJugadorService $registro,
    ): JsonResponse|RedirectResponse {
        $equipoRuta = $request->route('equipo');

        $equipoId = $equipoRuta instanceof Equipo
            ? $equipoRuta->getKey()
            : (is_numeric($equipoRuta) ? (int) $equipoRuta : $request->validated('equipo_id'));
        $equipo = $equipoRuta instanceof Equipo ? $equipoRuta : Equipo::query()->findOrFail($equipoId);

        /** @var array{persona_id?: int|string|null, dorsal?: int|string|null, nombre?: string, apellidos?: string, fecha_nacimiento?: string, curp?: string, documentos?: array<string, \Illuminate\Http\UploadedFile>, fotografia_base64?: string} $datos */
        $datos = $request->validated();
        $jugador = $registro->registrar($equipo, $datos);

        $respuesta = [
            'mensaje' => 'Jugador inscrito exitosamente.',
            'inscripcion' => [
                'id' => $jugador->id,
                'persona_id' => $jugador->persona_id,
                'equipo_id' => $jugador->equipo_id,
                'dorsal' => $jugador->dorsal,
            ],
        ];

        if (! $request->expectsJson()) {
            return redirect()->route('jugadores.create')->with('success', $respuesta['mensaje']);
        }

        return response()->json($respuesta, 201);
    }
}
