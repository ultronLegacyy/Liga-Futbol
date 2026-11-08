<?php

namespace App\Services;

use App\Models\Equipo;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

// Historial de equipos a través de las temporadas (HU-015, RF-03.4); el de personas es Persona::rolesPorTemporada.
// Un equipo se registra una vez por temporada (club + temporada + nombre), así que su historial
// junta los registros del mismo club con el mismo nombre (acuerdo con el equipo, 06/11/2026)
class HistorialTemporadasService
{
    /**
     * Equipos distintos (club y nombre) con cuántas temporadas jugaron; el Presidente y el Delegado del Club solo ven los de su club.
     *
     * @return LengthAwarePaginator<int, Equipo>
     */
    public function equipos(User $usuario): LengthAwarePaginator
    {
        return Equipo::query()
            ->selectRaw('club_id, nombre, MAX(id) as ultimo_id, COUNT(*) as total_temporadas')
            ->with('club:id,nombre')
            ->when($this->soloSuClub($usuario), fn (Builder $consulta) => $consulta->where('club_id', $usuario->club_id))
            ->groupBy('club_id', 'nombre')
            ->orderBy('nombre')
            ->orderBy('club_id')
            ->paginate(25);
    }

    /**
     * CA1: temporadas, categorías y grupos en los que participó el equipo, de la temporada más reciente a la más antigua.
     *
     * @return Collection<int, Equipo>
     */
    public function historialEquipo(Equipo $equipo): Collection
    {
        return Equipo::query()
            ->select('equipos.*')
            ->join('temporadas', 'temporadas.id', '=', 'equipos.temporada_id')
            ->with(['grupo:id,categoria_id,nombre', 'grupo.categoria:id,nombre,rama', 'temporada:id,nombre,fecha_inicio,fecha_fin,estado'])
            ->where('equipos.club_id', $equipo->club_id)
            ->where('equipos.nombre', $equipo->nombre)
            ->orderByDesc('temporadas.fecha_inicio')
            ->orderByDesc('equipos.id')
            ->get();
    }

    // Los directivos de club solo consultan los equipos de su club (RNF-29), como en EquipoPolicy::view
    private function soloSuClub(User $usuario): bool
    {
        return ! $usuario->hasRole('Administrador General')
            && $usuario->hasAnyRole(['Presidente del Club', 'Delegado del Club']);
    }
}
