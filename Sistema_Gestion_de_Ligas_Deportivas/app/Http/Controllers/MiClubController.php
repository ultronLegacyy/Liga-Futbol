<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Sancion;
use App\Models\User;
use App\Services\VigenciaSancionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * HAB-S7 · S7-T05 (SCRUM-238): «Mi club» para el Presidente del Club, el Delegado y el Jugador.
 * Cada quien ve solo lo suyo (RNF-29): los directivos, su club; el jugador, su equipo. No se edita nada aquí:
 * las altas y cambios siguen en sus módulos. «Mi perfil» del jugador queda pendiente del Product Owner.
 */
class MiClubController extends Controller
{
    public function __construct(private readonly VigenciaSancionService $vigencia)
    {
    }

    public function show(Request $request): View
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);

        $esDirectivo = $usuario->hasAnyRole(['Presidente del Club', 'Delegado del Club']);
        $jugador = $usuario->persona_id !== null
            ? Jugador::query()->where('persona_id', $usuario->persona_id)->with('equipo:id,nombre,club_id')->orderByDesc('id')->first()
            : null;

        $club = null;
        if ($esDirectivo && $usuario->club_id !== null) {
            $club = Club::query()->find($usuario->club_id);
        } elseif ($usuario->hasRole('Jugador') && $jugador?->equipo !== null) {
            $club = Club::query()->find($jugador->equipo->club_id);
        }

        abort_if(
            ! $esDirectivo && ! $usuario->hasRole('Jugador'),
            403,
            '«Mi club» es para el Presidente del Club, el Delegado y el Jugador.'
        );

        if ($club === null) {
            return view('mi-club.show', ['club' => null, 'esDirectivo' => $esDirectivo, 'usuario' => $usuario]);
        }

        // El jugador ve solo su equipo; los directivos, todos los equipos del club
        $equipos = Equipo::query()
            ->where('club_id', $club->id)
            ->when(! $esDirectivo && $jugador !== null, fn ($consulta) => $consulta->whereKey($jugador?->equipo_id))
            ->with('grupo:id,categoria_id,nombre', 'grupo.categoria:id,nombre,rama')
            ->withCount([
                'jugadores as activos' => fn ($consulta) => $consulta->where('estado_elegibilidad', 'Activo'),
                'jugadores as suspendidos' => fn ($consulta) => $consulta->where('estado_elegibilidad', 'Suspendido'),
            ])
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'club_id', 'grupo_id']);
        $ids = $equipos->pluck('id');

        $hoy = Carbon::today('America/Mexico_City');
        $partidos = Partido::query()
            ->where(fn ($consulta) => $consulta->whereIn('equipo_local_id', $ids)->orWhereIn('equipo_visitante_id', $ids))
            ->where('estado', '!=', Partido::ESTADO_BORRADOR)
            ->whereDate('fecha_partido', '>=', $hoy->toDateString())
            ->whereDate('fecha_partido', '<=', $hoy->copy()->addDays(14)->toDateString())
            ->with('local:id,nombre', 'visitante:id,nombre', 'cancha:id,nombre')
            ->orderBy('fecha_partido')
            ->orderBy('hora_partido')
            ->limit(20)
            ->get();

        // Sanciones vigentes publicadas de las personas del club: el mismo boletín del portal
        $personas = Jugador::query()->whereIn('equipo_id', $ids)->pluck('persona_id')->filter()->all();
        $castigados = $this->vigencia->obtenerBoletinCastigados()
            ->filter(fn (Sancion $sancion): bool => in_array($sancion->equipo_id, $ids->all(), true) || in_array($sancion->persona_id, $personas, true))
            ->values();

        return view('mi-club.show', compact('club', 'equipos', 'partidos', 'castigados', 'esDirectivo', 'usuario', 'jugador'));
    }
}
