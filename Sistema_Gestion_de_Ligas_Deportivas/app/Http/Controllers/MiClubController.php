<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Equipo;
use App\Models\Jornada;
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

        abort_unless(
            $usuario->hasAnyRole(['Presidente del Club', 'Delegado del Club', 'Jugador']),
            403,
            '«Mi club» es para el Presidente del Club, el Delegado y el Jugador.'
        );

        // Directivo es quien tiene el rol y un club asignado. Un Delegado sin club que también es Jugador entra como
        // jugador y ve solo su equipo (RNF-29; HU-080 permite varios roles por cuenta).
        $esDirectivo = $usuario->hasAnyRole(['Presidente del Club', 'Delegado del Club']) && $usuario->club_id !== null;
        // La inscripción vigente más reciente; una baja ya no da acceso a los datos del equipo
        $jugador = $usuario->persona_id !== null
            ? Jugador::query()
                ->where('persona_id', $usuario->persona_id)
                ->where('estado_elegibilidad', '!=', 'Baja')
                ->with('equipo:id,nombre,club_id')
                ->orderByDesc('id')
                ->first()
            : null;

        $club = null;
        if ($esDirectivo) {
            $club = Club::query()->find($usuario->club_id);
        } elseif ($usuario->hasRole('Jugador') && $jugador?->equipo !== null) {
            $club = Club::query()->find($jugador->equipo->club_id);
        }

        if ($club === null) {
            // Sin club: el Presidente o el Delegado que no es Jugador ve el aviso de ligar la cuenta a un club con la
            // Secretaría; el Jugador (aunque también tenga un rol de club sin club_id), el de vincular su registro
            $avisoDeClub = $esDirectivo || ($usuario->hasAnyRole(['Presidente del Club', 'Delegado del Club']) && ! $usuario->hasRole('Jugador'));

            return view('mi-club.show', ['club' => null, 'esDirectivo' => $avisoDeClub, 'usuario' => $usuario]);
        }

        // El jugador ve solo su equipo; los directivos, todos los equipos del club
        $equipos = Equipo::query()
            ->where('club_id', $club->id)
            ->when(! $esDirectivo && $jugador !== null, fn ($consulta) => $consulta->whereKey($jugador?->equipo_id))
            // Solo la temporada en curso: los equipos de temporadas cerradas no entran en el resumen
            ->whereHas('grupo.temporada', fn ($temporada) => $temporada->where('estado', 'activa'))
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
            // Igual que el portal: solo jornadas publicadas, porque la programación en revisión todavía puede cambiar
            ->whereHas('jornada', fn ($jornada) => $jornada->where('estado', Jornada::ESTADO_PUBLICADA))
            ->whereDate('fecha_partido', '>=', $hoy->toDateString())
            ->whereDate('fecha_partido', '<=', $hoy->copy()->addDays(14)->toDateString())
            ->with('local:id,nombre', 'visitante:id,nombre', 'cancha:id,nombre')
            ->orderBy('fecha_partido')
            ->orderBy('hora_partido')
            ->limit(20)
            ->get();

        // Sanciones vigentes publicadas de las personas del club: el mismo boletín del portal. Igual que el boletín del
        // Delegado (VigenciaSancionService), las inscripciones en «Baja» no cuentan como del club.
        $personas = Jugador::query()
            ->whereIn('equipo_id', $ids)
            ->where('estado_elegibilidad', '!=', 'Baja')
            ->pluck('persona_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->all();
        $castigados = $this->vigencia->obtenerBoletinCastigados()
            ->filter(fn (Sancion $sancion): bool => in_array($sancion->equipo_id, $ids->all(), true) || in_array((int) $sancion->persona_id, $personas, true))
            ->values();

        return view('mi-club.show', compact('club', 'equipos', 'partidos', 'castigados', 'esDirectivo', 'usuario', 'jugador'));
    }
}
