<?php

namespace App\Services;

use App\Models\Equipo;
use App\Models\User;
use App\Policies\EquipoPolicy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

// Historial de equipos a través de las temporadas (HU-015, RF-03.4); el de personas es Persona::rolesPorTemporada.
// Un equipo se registra una vez por temporada (club + temporada + nombre), así que su historial
// junta los registros del mismo club con el mismo nombre (regla pendiente de confirmar con el equipo)
class HistorialTemporadasService
{
    /**
     * Equipos distintos (club y nombre) con cuántas temporadas jugaron; los roles de club solo ven los de su club.
     *
     * @return LengthAwarePaginator<int, Equipo>
     */
    public function equipos(User $usuario): LengthAwarePaginator
    {
        // Misma consulta base que el detalle, para que el conteo y las filas del historial coincidan
        return $this->participaciones()
            ->selectRaw('equipos.club_id, equipos.nombre, MAX(equipos.id) as ultimo_id, COUNT(*) as total_temporadas')
            ->with('club:id,nombre')
            ->when($this->soloSuClub($usuario), fn (Builder $consulta) => $consulta->where('equipos.club_id', $usuario->club_id))
            ->groupBy('equipos.club_id', 'equipos.nombre')
            ->orderBy('equipos.nombre')
            ->orderBy('equipos.club_id')
            ->paginate(25);
    }

    /**
     * CA1: temporadas, categorías y grupos en los que participó el equipo, de la temporada más reciente a la más antigua.
     *
     * @return Collection<int, Equipo>
     */
    public function historialEquipo(Equipo $equipo): Collection
    {
        return $this->participaciones()
            ->select('equipos.*')
            ->with(['grupo:id,categoria_id,temporada_id,nombre', 'grupo.categoria:id,nombre,rama', 'grupo.temporada:id,nombre,fecha_inicio,fecha_fin,estado'])
            ->where('equipos.club_id', $equipo->club_id)
            ->where('equipos.nombre', $equipo->nombre)
            ->orderByDesc('temporadas.fecha_inicio')
            ->orderByDesc('equipos.id')
            ->get();
    }

    /**
     * Equipos con la temporada de su grupo: equipos.temporada_id puede venir vacío en registros anteriores
     * a esa columna, pero el grupo siempre la tiene (como en EquipoController).
     *
     * @return Builder<Equipo>
     */
    private function participaciones(): Builder
    {
        return Equipo::query()
            ->join('grupos', 'grupos.id', '=', 'equipos.grupo_id')
            ->join('temporadas', 'temporadas.id', '=', 'grupos.temporada_id');
    }

    // Los roles de club (directivos y jugadores) solo consultan los equipos de su club (RNF-29); sin club no ven ninguno
    private function soloSuClub(User $usuario): bool
    {
        return ! $usuario->hasRole('Administrador General')
            && $usuario->hasAnyRole(EquipoPolicy::ROLES_SOLO_SU_CLUB);
    }
}
